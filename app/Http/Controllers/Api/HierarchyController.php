<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FacebookBusiness;
use App\Models\FacebookAdAccount;
use App\Models\FacebookCampaign;
use App\Models\FacebookAdSet;
use App\Models\FacebookAd;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class HierarchyController extends Controller
{
    /**
     * Lấy danh sách Business Managers
     */
    public function getBusinesses(): JsonResponse
    {
        try {
            // Hiển thị ngày đồng bộ (created_at) và đếm số tài khoản quảng cáo
            $businesses = FacebookBusiness::withCount('adAccounts')
                ->orderBy('created_at', 'desc')
                ->get(['id', 'name', 'verification_status', 'created_at']);

            return response()->json($businesses);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Lỗi khi tải Business Managers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy danh sách Ad Accounts theo Business Manager
     */
    public function getAccounts(Request $request): JsonResponse
    {
        try {
            $businessId = $request->get('businessId');
            $month = $request->get('month'); // YYYY-MM to aggregate monthly KPIs
            
            if (!$businessId) {
                return response()->json([
                    'error' => 'Thiếu businessId parameter'
                ], 400);
            }

            $accounts = FacebookAdAccount::where('business_id', $businessId)
                ->orderBy('created_at', 'desc')
                ->get(['id', 'name', 'account_id', 'account_status', 'created_at']);

            // Gắn campaigns_count (kể cả dữ liệu legacy chưa có ad_account_id)
            $accounts->transform(function ($a) {
                $idsFromLink = FacebookCampaign::where('ad_account_id', $a->id)->pluck('id')->all();
                $idsFromAds = FacebookAd::where('account_id', $a->id)->distinct()->pluck('campaign_id')->filter()->all();
                $a->campaigns_count = count(array_unique(array_merge($idsFromLink, $idsFromAds)));
                return $a;
            });

            if ($month) {
                [$y, $m] = explode('-', $month) + [null, null];
                if ($y && $m) {
                    $start = "$y-$m-01";
                    $end = date('Y-m-t', strtotime($start));
                    // Lấy insights từ bảng FacebookAd thay vì FacebookInsight
                    $insights = FacebookAd::whereIn('account_id', $accounts->pluck('id'))
                        ->whereBetween('last_insights_sync', [$start, $end])
                        ->whereNotNull('ad_spend')
                        ->get(['account_id', 'ad_spend', 'ad_impressions', 'ad_clicks', 'ad_reach']);
                    
                    $byAcc = [];
                    foreach ($insights as $in) {
                        $accId = $in->account_id;
                        if (!$accId) continue;
                        if (!isset($byAcc[$accId])) {
                            $byAcc[$accId] = ['spend'=>0,'impressions'=>0,'clicks'=>0,'reach'=>0];
                        }
                        $byAcc[$accId]['spend'] += (float) ($in->ad_spend ?? 0);
                        $byAcc[$accId]['impressions'] += (int) ($in->ad_impressions ?? 0);
                        $byAcc[$accId]['clicks'] += (int) ($in->ad_clicks ?? 0);
                        $byAcc[$accId]['reach'] += (int) ($in->ad_reach ?? 0);
                    }
                    // attach KPI
                    $accounts->transform(function ($a) use ($byAcc) {
                        $a->kpi = $byAcc[$a->id] ?? ['spend'=>0,'impressions'=>0,'clicks'=>0,'reach'=>0];
                        return $a;
                    });
                }
            }

            return response()->json($accounts);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Lỗi khi tải Ad Accounts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy danh sách Campaigns theo Ad Account
     */
    public function getCampaigns(Request $request): JsonResponse
    {
        try {
            $accountId = $request->get('accountId');
            $month = $request->get('month'); // YYYY-MM
            
            if (!$accountId) {
                return response()->json([
                    'error' => 'Thiếu accountId parameter'
                ], 400);
            }

            // Lấy Ad Account để kiểm tra
            $adAccount = FacebookAdAccount::find($accountId);
            if (!$adAccount) {
                return response()->json([
                    'error' => 'Không tìm thấy Ad Account'
                ], 404);
            }

            // Lấy campaigns theo ad_account_id (Facebook's account ID)
            $campaigns = FacebookCampaign::where('ad_account_id', $adAccount->id)
                ->orderBy('created_at', 'desc')
                ->get([
                    'id', 'name', 'status', 'objective', 'effective_status',
                    'start_time', 'stop_time', 'created_at'
                ]);

            // Fallback: nếu chưa có liên kết ad_account_id do dữ liệu cũ, lấy theo Ads.account_id
            if ($campaigns->isEmpty()) {
                $campaignIds = FacebookAd::where('account_id', $adAccount->id)
                    ->distinct()
                    ->pluck('campaign_id')
                    ->filter();
                if ($campaignIds->isNotEmpty()) {
                    $campaigns = FacebookCampaign::whereIn('id', $campaignIds)
                        ->orderBy('created_at', 'desc')
                        ->get([
                            'id', 'name', 'status', 'objective', 'effective_status',
                            'start_time', 'stop_time', 'created_at'
                        ]);
                }
            }

            // Tính KPI từ bảng facebook_ads (không phụ thuộc tham số tháng). Nếu truyền month sẽ lọc theo tháng.
            $campaignIds = $campaigns->pluck('id')->all();
            // campaign_id trong bảng facebook_ads có thể lưu kèm dấu '"..."', cần normalize khi so khớp
            $insightsQuery = FacebookAd::whereIn(DB::raw('TRIM(BOTH "\"" FROM campaign_id)'), $campaignIds)
                ->select(['campaign_id', 'ad_spend', 'ad_impressions', 'ad_clicks', 'ad_reach', 'ad_ctr', 'ad_cpc', 'ad_cpm']);
            // Nếu có month hợp lệ, lọc theo khoảng ngày trong tháng
            $start = $end = null;
            if ($month) {
                [$y, $m] = explode('-', $month) + [null, null];
                if ($y && $m) {
                    $start = "$y-$m-01";
                    $end = date('Y-m-t', strtotime($start));
                    $insightsQuery->whereBetween('last_insights_sync', [$start, $end]);
                }
            }

            $insights = $insightsQuery->get();

            // Nếu lọc theo tháng mà không có dữ liệu, fallback bỏ lọc để vẫn có số
            if ($insights->isEmpty() && $start && $end) {
                $insights = FacebookAd::whereIn(DB::raw('TRIM(BOTH "\"" FROM campaign_id)'), $campaignIds)
                    ->get(['campaign_id', 'ad_spend', 'ad_impressions', 'ad_clicks', 'ad_reach', 'ad_ctr', 'ad_cpc', 'ad_cpm']);
            }

            $byCampaign = [];
            foreach ($insights as $in) {
                // Chuẩn hoá campaign_id: loại bỏ dấu " nếu có để khớp với $campaigns->pluck('id')
                $cid = is_string($in->campaign_id) ? trim($in->campaign_id, '"') : $in->campaign_id;
                if (!$cid) continue;
                if (!isset($byCampaign[$cid])) {
                    $byCampaign[$cid] = ['spend'=>0,'impressions'=>0,'clicks'=>0,'reach'=>0,'ctr'=>null,'cpc'=>null,'cpm'=>null,'rows'=>0];
                }
                $byCampaign[$cid]['spend'] += (float) ($in->ad_spend ?? 0);
                $byCampaign[$cid]['impressions'] += (int) ($in->ad_impressions ?? 0);
                $byCampaign[$cid]['clicks'] += (int) ($in->ad_clicks ?? 0);
                $byCampaign[$cid]['reach'] += (int) ($in->ad_reach ?? 0);
                $byCampaign[$cid]['rows']++;
                foreach (['ctr','cpc','cpm'] as $k) {
                    if (isset($in->$k)) {
                        $prev = $byCampaign[$cid][$k];
                        $byCampaign[$cid][$k] = ($prev === null) ? (float)$in->$k : (($prev + (float)$in->$k) / 2);
                    }
                }
            }

            // Đếm số ad sets cho mỗi campaign
            $adsetsCounts = \App\Models\FacebookAdSet::whereIn('campaign_id', $campaignIds)
                ->selectRaw('campaign_id, COUNT(*) as cnt')
                ->groupBy('campaign_id')
                ->pluck('cnt', 'campaign_id');

            // Gắn số liệu phẳng từ DB + ad_sets_count vào danh sách campaigns
            $campaigns = $campaigns->map(function ($c) use ($byCampaign, $adsetsCounts) {
                $m = $byCampaign[$c->id] ?? ['spend'=>0,'impressions'=>0,'clicks'=>0,'reach'=>0,'ctr'=>0,'cpc'=>0,'cpm'=>0,'rows'=>0];
                $c->total_spend = (float) $m['spend'];
                $c->total_impressions = (int) $m['impressions'];
                $c->total_clicks = (int) $m['clicks'];
                $c->total_reach = (int) $m['reach'];
                $c->avg_ctr = (float) ($m['ctr'] ?? 0);
                $c->avg_cpc = (float) ($m['cpc'] ?? 0);
                $c->avg_cpm = (float) ($m['cpm'] ?? 0);
                if (($c->avg_ctr ?? 0) == 0 && $c->total_impressions > 0 && $c->total_clicks >= 0) {
                    $c->avg_ctr = round(($c->total_clicks / max(1, $c->total_impressions)) * 100, 4);
                }
                if (($c->avg_cpc ?? 0) == 0 && $c->total_clicks > 0) {
                    $c->avg_cpc = round($c->total_spend / max(1, $c->total_clicks), 4);
                }
                if (($c->avg_cpm ?? 0) == 0 && $c->total_impressions > 0) {
                    $c->avg_cpm = round(($c->total_spend / max(1, $c->total_impressions)) * 1000, 4);
                }
                $c->ctr = $c->avg_ctr;
                $c->cpc = $c->avg_cpc;
                $c->cpm = $c->avg_cpm;
                $c->ads_count = (int) ($m['rows'] ?? 0);
                $c->ad_sets_count = (int) ($adsetsCounts[$c->id] ?? 0);
                return $c;
            });

            return response()->json([
                'success' => true,
                'data' => $campaigns,
                'account_name' => $adAccount->name,
                'total_campaigns' => $campaigns->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Lỗi khi tải Campaigns: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy danh sách Ad Sets theo Campaign
     */
    public function getAdSets(Request $request): JsonResponse
    {
        try {
            $campaignId = $request->get('campaignId');
            $month = $request->get('month'); // YYYY-MM
            
            if (!$campaignId) {
                return response()->json([
                    'error' => 'Thiếu campaignId parameter'
                ], 400);
            }

            // Lấy ad sets theo campaign_id
            $adSets = FacebookAdSet::where('campaign_id', $campaignId)
                ->orderBy('created_at', 'desc')
                ->get(['id', 'name', 'status', 'optimization_goal', 'created_at']);

            // Fallback: nếu dữ liệu cũ không có liên kết, suy ra từ bảng ads theo adset_id đã chuẩn hoá
            if ($adSets->isEmpty()) {
                $adsetIds = FacebookAd::where(DB::raw('TRIM(BOTH "\"" FROM campaign_id)'), $campaignId)
                    ->distinct()
                    ->pluck('adset_id')
                    ->map(function ($v) { return is_string($v) ? trim($v, '"') : $v; })
                    ->filter();
                if ($adsetIds->isNotEmpty()) {
                    $adSets = FacebookAdSet::whereIn('id', $adsetIds)
                        ->orderBy('created_at', 'desc')
                        ->get(['id', 'name', 'status', 'optimization_goal', 'created_at']);
                }
            }

            // Tính KPI tương tự campaigns (tuỳ chọn theo month)
            $adsetIds = $adSets->pluck('id')->all();
            $insightsQuery = FacebookAd::whereIn(DB::raw('TRIM(BOTH "\"" FROM adset_id)'), $adsetIds)
                ->select(['adset_id', 'ad_spend', 'ad_impressions', 'ad_clicks', 'ad_reach', 'ad_ctr', 'ad_cpc', 'ad_cpm']);

            $start = $end = null;
            if ($month) {
                [$y, $m] = explode('-', $month) + [null, null];
                if ($y && $m) {
                    $start = "$y-$m-01";
                    $end = date('Y-m-t', strtotime($start));
                    $insightsQuery->whereBetween('last_insights_sync', [$start, $end]);
                }
            }

            $insights = $insightsQuery->get();
            if ($insights->isEmpty() && $start && $end) {
                $insights = FacebookAd::whereIn(DB::raw('TRIM(BOTH "\"" FROM adset_id)'), $adsetIds)
                    ->get(['adset_id', 'ad_spend', 'ad_impressions', 'ad_clicks', 'ad_reach', 'ad_ctr', 'ad_cpc', 'ad_cpm']);
            }

            $byAdset = [];
            foreach ($insights as $in) {
                $aid = is_string($in->adset_id) ? trim($in->adset_id, '"') : $in->adset_id;
                if (!$aid) continue;
                if (!isset($byAdset[$aid])) {
                    $byAdset[$aid] = ['spend'=>0,'impressions'=>0,'clicks'=>0,'reach'=>0,'ctr'=>null,'cpc'=>null,'cpm'=>null,'rows'=>0];
                }
                $byAdset[$aid]['spend'] += (float) ($in->ad_spend ?? 0);
                $byAdset[$aid]['impressions'] += (int) ($in->ad_impressions ?? 0);
                $byAdset[$aid]['clicks'] += (int) ($in->ad_clicks ?? 0);
                $byAdset[$aid]['reach'] += (int) ($in->ad_reach ?? 0);
                $byAdset[$aid]['rows']++;
                foreach (['ctr','cpc','cpm'] as $k) {
                    if (isset($in->$k)) {
                        $prev = $byAdset[$aid][$k];
                        $byAdset[$aid][$k] = ($prev === null) ? (float)$in->$k : (($prev + (float)$in->$k) / 2);
                    }
                }
            }

            // Gắn KPI phẳng vào từng ad set
            $adSets = $adSets->map(function ($a) use ($byAdset) {
                $m = $byAdset[$a->id] ?? ['spend'=>0,'impressions'=>0,'clicks'=>0,'reach'=>0,'ctr'=>0,'cpc'=>0,'cpm'=>0,'rows'=>0];
                $a->total_spend = (float) $m['spend'];
                $a->total_impressions = (int) $m['impressions'];
                $a->total_clicks = (int) $m['clicks'];
                $a->total_reach = (int) $m['reach'];
                $a->avg_ctr = (float) ($m['ctr'] ?? 0);
                $a->avg_cpc = (float) ($m['cpc'] ?? 0);
                $a->avg_cpm = (float) ($m['cpm'] ?? 0);
                // Derive metrics if not provided
                if (($a->avg_ctr ?? 0) == 0 && $a->total_impressions > 0 && $a->total_clicks >= 0) {
                    $a->avg_ctr = round(($a->total_clicks / max(1, $a->total_impressions)) * 100, 4);
                }
                if (($a->avg_cpc ?? 0) == 0 && $a->total_clicks > 0) {
                    $a->avg_cpc = round($a->total_spend / max(1, $a->total_clicks), 4);
                }
                if (($a->avg_cpm ?? 0) == 0 && $a->total_impressions > 0) {
                    $a->avg_cpm = round(($a->total_spend / max(1, $a->total_impressions)) * 1000, 4);
                }
                $a->ctr = $a->avg_ctr;
                $a->cpc = $a->avg_cpc;
                $a->cpm = $a->avg_cpm;
                $a->ads_count = (int) ($m['rows'] ?? 0);
                return $a;
            });

            return response()->json([
                'success' => true,
                'data' => $adSets,
                'total_adsets' => $adSets->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Lỗi khi tải Ad Sets: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy danh sách Ads theo context
     */
    public function getAds(Request $request): JsonResponse
    {
        try {
            $adsetId = $request->get('adsetId');
            $campaignId = $request->get('campaignId');
            $accountId = $request->get('accountId');

            $query = FacebookAd::with(['campaign:id,name', 'adSet:id,name']);

            if ($adsetId) {
                // Lấy ads theo Ad Set
                $query->where(DB::raw('TRIM(BOTH "\"" FROM adset_id)'), $adsetId);
            } elseif ($campaignId) {
                // Lấy ads theo Campaign
                $query->where(DB::raw('TRIM(BOTH "\"" FROM campaign_id)'), $campaignId);
            } elseif ($accountId) {
                // Lấy ads theo Ad Account
                $query->where('account_id', $accountId);
            } else {
                return response()->json([
                    'error' => 'Thiếu parameter để xác định context'
                ], 400);
            }

            // Lấy tất cả ads (không chỉ post ads) - sắp theo ngày đồng bộ để nhất quán
            $ads = $query->orderBy('created_at', 'desc')
                ->limit(100)
                ->get([
                    'id', 'name', 'status', 'effective_status', 'post_id', 'post_message', 'post_type',
                    'post_created_time', 'ad_spend', 'ad_impressions', 'ad_clicks', 'ad_reach',
                    'post_likes', 'post_shares', 'post_comments', 'post_reactions',
                    'creative_link_url', 'creative_link_message', 'creative_link_name',
                    'page_id', 'created_at', 'created_time'
                ]);

            // Thêm thông tin bổ sung
            $ads->each(function ($ad) {
                $ad->ad_type = $ad->post_id ? 'Post Ad' : ($ad->creative_link_url ? 'Link Ad' : 'Standard Ad');
                $ad->content_preview = $ad->post_id ? 
                    Str::limit($ad->post_message ?? 'No message', 50) : 
                    ($ad->creative_link_url ? Str::limit($ad->creative_link_name ?? 'No name', 50) : 'No content');
                $ad->campaign_name = $ad->campaign->name ?? 'N/A';
                $ad->adset_name = $ad->adSet->name ?? 'N/A';
            });

            return response()->json([
                'success' => true,
                'data' => $ads,
                'total_ads' => $ads->count(),
                'post_ads' => $ads->where('post_id')->count(),
                'link_ads' => $ads->where('creative_link_url')->count(),
                'standard_ads' => $ads->whereNull('post_id')->whereNull('creative_link_url')->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Lỗi khi tải Ads: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy danh sách Posts theo context
     */
    public function getPosts(Request $request): JsonResponse
    {
        try {
            $adsetId = $request->get('adsetId');
            $campaignId = $request->get('campaignId');
            $accountId = $request->get('accountId');

            $query = FacebookAd::with(['campaign:id,name', 'adSet:id,name'])
                ->where(function($q) {
                    $q->whereNotNull('post_id')
                      ->orWhereNotNull('creative_link_url');
                });

            if ($adsetId) {
                // Lấy posts theo Ad Set
                $query->where(DB::raw('TRIM(BOTH "\"" FROM adset_id)'), $adsetId);
            } elseif ($campaignId) {
                // Lấy posts theo Campaign
                $query->where(DB::raw('TRIM(BOTH "\"" FROM campaign_id)'), $campaignId);
            } elseif ($accountId) {
                // Lấy posts theo Ad Account
                $query->where('account_id', $accountId);
            } else {
                return response()->json([
                    'error' => 'Thiếu parameter để xác định context'
                ], 400);
            }

            $posts = $query->orderBy('last_insights_sync', 'desc')
                ->limit(100)
                ->get([
                    'id', 'name', 'status', 'effective_status', 'post_id', 'post_message', 'post_type',
                    'post_created_time', 'post_permalink_url', 'page_id',
                    'post_likes', 'post_shares', 'post_comments', 'post_reactions',
                    'creative_link_url', 'creative_link_message', 'creative_link_name',
                    'ad_spend', 'ad_impressions', 'ad_clicks', 'ad_reach',
                    'created_at', 'last_insights_sync'
                ]);

            // Nếu theo campaign, gộp các ads trùng cùng page_id + post_id thành 1 post duy nhất
            if ($campaignId) {
                $posts = $posts
                    ->filter(function ($p) {
                        return !empty($p->page_id) && !empty($p->post_id);
                    })
                    ->groupBy(function ($p) {
                        return ($p->page_id ?? '') . '|' . ($p->post_id ?? '');
                    })
                    ->map(function ($group) {
                        // Chọn bản ghi đại diện: mới nhất theo last_insights_sync, fallback created_at
                        $rep = $group->sortByDesc(function ($p) {
                            return $p->last_insights_sync ?? $p->created_at;
                        })->first();

                        // Clone để tránh thay đổi collection gốc
                        $post = clone $rep;

                        // Cộng gộp các chỉ số
                        // post_likes được tính gồm cả post_reactions để phản ánh đầy đủ lượt thích + cảm xúc
                        $post->post_likes = (int) $group->sum(function ($p) {
                            return (int) ($p->post_likes ?? 0) + (int) ($p->post_reactions ?? 0);
                        });
                        $post->post_shares = (int) $group->sum(function ($p) { return (int) ($p->post_shares ?? 0); });
                        $post->post_comments = (int) $group->sum(function ($p) { return (int) ($p->post_comments ?? 0); });

                        $post->ad_spend = (float) $group->sum(function ($p) { return (float) ($p->ad_spend ?? 0); });
                        $post->ad_impressions = (int) $group->sum(function ($p) { return (int) ($p->ad_impressions ?? 0); });
                        $post->ad_clicks = (int) $group->sum(function ($p) { return (int) ($p->ad_clicks ?? 0); });
                        $post->ad_reach = (int) $group->max(function ($p) { return (int) ($p->ad_reach ?? 0); }); // reach dùng max

                        return $post;
                    })
                    ->values();
            }

            // Thêm thông tin bổ sung
            $posts->each(function ($post) {
                // Chuẩn hoá: Like = Like gốc + toàn bộ Reactions
                $post->post_likes = (int) ($post->post_likes ?? 0) + (int) ($post->post_reactions ?? 0);
                $post->post_type_display = $post->post_id ? 'Post Ad' : 'Link Ad';
                $post->content_preview = $post->post_id ? 
                    Str::limit($post->post_message ?? 'No message', 100) : 
                    Str::limit($post->creative_link_message ?? $post->creative_link_name ?? 'No content', 100);
                $post->campaign_name = $post->campaign->name ?? 'N/A';
                $post->adset_name = $post->adSet->name ?? 'N/A';
                // Tổng tương tác bao gồm: (Likes + Reactions) + Shares + Comments
                $post->total_engagement = ($post->post_likes ?? 0) + ($post->post_shares ?? 0) + ($post->post_comments ?? 0);
                $post->engagement_rate = $post->ad_impressions > 0 ? 
                    round(($post->total_engagement / $post->ad_impressions) * 100, 2) : 0;
            });

            return response()->json([
                'success' => true,
                'data' => $posts,
                'total_posts' => $posts->count(),
                'post_ads' => $posts->where('post_id')->count(),
                'link_ads' => $posts->where('creative_link_url')->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Lỗi khi tải Posts: ' . $e->getMessage()
            ], 500);
        }
    }
}
