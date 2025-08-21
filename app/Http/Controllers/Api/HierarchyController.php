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

            // Nếu yêu cầu kèm month, tính KPI theo tháng từ bảng FacebookAd
            if ($month) {
                [$y, $m] = explode('-', $month) + [null, null];
                if ($y && $m) {
                    $start = "$y-$m-01";
                    $end = date('Y-m-t', strtotime($start));
                    $campaignIds = $campaigns->pluck('id');
                    // Lấy insights từ bảng FacebookAd; ưu tiên theo tháng, fallback nếu chưa có mốc sync
                    $insights = FacebookAd::whereIn('campaign_id', $campaignIds)
                        ->whereBetween('last_insights_sync', [$start, $end])
                        ->get(['campaign_id', 'ad_spend', 'ad_impressions', 'ad_clicks', 'ad_reach', 'ad_ctr', 'ad_cpc', 'ad_cpm']);

                    if ($insights->isEmpty()) {
                        // Fallback: không lọc theo tháng để tránh case dữ liệu cũ chưa có last_insights_sync
                        $insights = FacebookAd::whereIn('campaign_id', $campaignIds)
                            ->get(['campaign_id', 'ad_spend', 'ad_impressions', 'ad_clicks', 'ad_reach', 'ad_ctr', 'ad_cpc', 'ad_cpm']);
                    }
                    
                    $byCampaign = [];
                    foreach ($insights as $in) {
                        $campaignId = $in->campaign_id;
                        if (!$campaignId) continue;
                        if (!isset($byCampaign[$campaignId])) {
                            $byCampaign[$campaignId] = ['spend'=>0,'impressions'=>0,'clicks'=>0,'reach'=>0,'ctr'=>null,'cpc'=>null,'cpm'=>null,'rows'=>0];
                        }
                        $byCampaign[$campaignId]['spend'] += (float) ($in->ad_spend ?? 0);
                        $byCampaign[$campaignId]['impressions'] += (int) ($in->ad_impressions ?? 0);
                        $byCampaign[$campaignId]['clicks'] += (int) ($in->ad_clicks ?? 0);
                        $byCampaign[$campaignId]['reach'] += (int) ($in->ad_reach ?? 0);
                        $byCampaign[$campaignId]['rows']++;
                        // Tính trung bình CTR/CPC/CPM nếu có
                        foreach (['ctr','cpc','cpm'] as $k) {
                            if (isset($in->$k)) {
                                $prev = $byCampaign[$campaignId][$k];
                                $byCampaign[$campaignId][$k] = ($prev === null) ? (float)$in->$k : (($prev + (float)$in->$k) / 2);
                            }
                        }
                    }

                    // Đếm ad sets theo campaign bằng 1 query
                    $adsetsCounts = 
                        \App\Models\FacebookAdSet::whereIn('campaign_id', $campaignIds)
                        ->selectRaw('campaign_id, COUNT(*) as cnt')
                        ->groupBy('campaign_id')
                        ->pluck('cnt', 'campaign_id');

                    // Gắn KPI + ad_sets_count vào list
                    $campaigns = $campaigns->map(function ($c) use ($byCampaign, $adsetsCounts) {
                        $m = $byCampaign[$c->id] ?? ['spend'=>0,'impressions'=>0,'clicks'=>0,'reach'=>0,'ctr'=>0,'cpc'=>0,'cpm'=>0];
                        $c->kpi = $m;
                        $c->ad_sets_count = (int) ($adsetsCounts[$c->id] ?? 0);
                        return $c;
                    });
                }
            }

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

            $adSets = FacebookAdSet::where('campaign_id', $campaignId)
                ->orderBy('created_at', 'desc')
                ->get([
                    'id', 'name', 'status', 'optimization_goal', 'created_at'
                ]);

            // Nếu yêu cầu kèm month, tính KPI theo tháng từ bảng FacebookAd
            if ($month) {
                [$y, $m] = explode('-', $month) + [null, null];
                if ($y && $m) {
                    $start = "$y-$m-01";
                    $end = date('Y-m-t', strtotime($start));
                    $adsetIds = $adSets->pluck('id');
                    
                    $insights = FacebookAd::whereIn('adset_id', $adsetIds)
                        ->whereBetween('last_insights_sync', [$start, $end])
                        ->whereNotNull('ad_spend')
                        ->get(['adset_id', 'ad_spend', 'ad_impressions', 'ad_clicks', 'ad_reach', 'ad_ctr', 'ad_cpc', 'ad_cpm']);
                    
                    $byAdset = [];
                    foreach ($insights as $in) {
                        $adsetId = $in->adset_id;
                        if (!$adsetId) continue;
                        if (!isset($byAdset[$adsetId])) {
                            $byAdset[$adsetId] = ['spend'=>0,'impressions'=>0,'clicks'=>0,'reach'=>0,'ctr'=>null,'cpc'=>null,'cpm'=>null,'ads_count'=>0];
                        }
                        $byAdset[$adsetId]['spend'] += (float) ($in->ad_spend ?? 0);
                        $byAdset[$adsetId]['impressions'] += (int) ($in->ad_impressions ?? 0);
                        $byAdset[$adsetId]['clicks'] += (int) ($in->ad_clicks ?? 0);
                        $byAdset[$adsetId]['reach'] += (int) ($in->ad_reach ?? 0);
                        $byAdset[$adsetId]['ads_count']++;
                        
                        // Tính trung bình CTR/CPC/CPM
                        foreach (['ctr','cpc','cpm'] as $k) {
                            if (isset($in->$k)) {
                                $prev = $byAdset[$adsetId][$k];
                                $byAdset[$adsetId][$k] = ($prev === null) ? (float)$in->$k : (($prev + (float)$in->$k) / 2);
                            }
                        }
                    }

                    // Gắn KPI vào list
                    $adSets = $adSets->map(function ($a) use ($byAdset) {
                        $m = $byAdset[$a->id] ?? ['spend'=>0,'impressions'=>0,'clicks'=>0,'reach'=>0,'ctr'=>0,'cpc'=>0,'cpm'=>0,'ads_count'=>0];
                        $a->kpi = $m;
                        return $a;
                    });
                }
            }

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
                $query->where('adset_id', $adsetId);
            } elseif ($campaignId) {
                // Lấy ads theo Campaign
                $query->where('campaign_id', $campaignId);
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
}
