<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\FacebookAd;
use App\Models\FacebookAdAccount;
use App\Models\FacebookAdSet;
use App\Models\FacebookBusiness;
use App\Models\FacebookCampaign;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FacebookAdsSyncService
{
    public function __construct(private FacebookAdsService $api)
    {
    }

    /**
     * Đồng bộ dữ liệu ngày hôm qua. Cho phép truyền callback để cập nhật tiến độ.
     *
     * @param callable|null $onProgress function(array $state): void
     */
    public function syncYesterday(?callable $onProgress = null): array
    {
        $result = [
            'businesses' => 0,
            'accounts' => 0,
            'campaigns' => 0,
            'adsets' => 0,
            'ads' => 0,
            'pages' => 0,
            'posts' => 0,
            'insights' => 0,
            'errors' => [],
        ];

        $reportProgress = function (string $stage) use (&$result, $onProgress): void {
            // Kiểm tra stop request
            if (Cache::get('facebook_sync_stop_requested', false)) {
                throw new \Exception('Đồng bộ đã bị dừng bởi người dùng');
            }
            
            if ($onProgress) {
                $onProgress([
                    'stage' => $stage,
                    'counts' => [
                        'businesses' => $result['businesses'],
                        'accounts' => $result['accounts'],
                        'campaigns' => $result['campaigns'],
                        'adsets' => $result['adsets'],
                        'ads' => $result['ads'],
                        'pages' => $result['pages'],
                        'posts' => $result['posts'],
                        'insights' => $result['insights'],
                    ],
                    'errors' => $result['errors'],
                ]);
            }
        };

        $bm = $this->api->getBusinessManagers();
        if (isset($bm['error'])) {
            $result['errors'][] = ['stage' => 'getBusinessManagers', 'error' => $bm['error']];
        }
        $reportProgress('getBusinessManagers');
        foreach (($bm['data'] ?? []) as $b) {
            // Kiểm tra stop request
            if (Cache::get('facebook_sync_stop_requested', false)) {
                throw new \Exception('Đồng bộ đã bị dừng bởi người dùng');
            }
            
            FacebookBusiness::updateOrCreate(['id' => $b['id']], [
                'name' => $b['name'] ?? null,
                'verification_status' => $b['verification_status'] ?? null,
                'created_time' => isset($b['created_time']) ? Carbon::parse($b['created_time']) : null,
            ]);
            $result['businesses']++;
            $reportProgress('upsertBusiness');

            // Lấy đủ cả client_ad_accounts và owned_ad_accounts
            $clientAccounts = $this->api->getClientAdAccounts($b['id']);
            $ownedAccounts = $this->api->getOwnedAdAccounts($b['id']);
            if (isset($clientAccounts['error'])) {
                $result['errors'][] = ['stage' => 'getClientAdAccounts', 'business_id' => $b['id'], 'error' => $clientAccounts['error']];
            }
            if (isset($ownedAccounts['error'])) {
                $result['errors'][] = ['stage' => 'getOwnedAdAccounts', 'business_id' => $b['id'], 'error' => $ownedAccounts['error']];
            }
            $accounts = [
                'data' => array_values(array_unique(array_merge($clientAccounts['data'] ?? [], $ownedAccounts['data'] ?? []), SORT_REGULAR)),
            ];
            if (isset($accounts['error'])) {
                $result['errors'][] = ['stage' => 'getClientAdAccounts', 'business_id' => $b['id'], 'error' => $accounts['error']];
            }
            $reportProgress('getClientAdAccounts');
            foreach (($accounts['data'] ?? []) as $acc) {
                // Kiểm tra stop request
                if (Cache::get('facebook_sync_stop_requested', false)) {
                    throw new \Exception('Đồng bộ đã bị dừng bởi người dùng');
                }
                
                FacebookAdAccount::updateOrCreate(['id' => $acc['id']], [
                    'account_id' => $acc['account_id'] ?? null,
                    'name' => $acc['name'] ?? null,
                    'account_status' => $acc['account_status'] ?? null,
                    'business_id' => $b['id'],
                    'created_time' => isset($acc['created_time']) ? Carbon::parse($acc['created_time']) : null,  // ✅ Thêm ngày từ API
                    'updated_time' => isset($acc['updated_time']) ? Carbon::parse($acc['updated_time']) : null,  // ✅ Thêm ngày từ API
                ]);
                $result['accounts']++;
                $reportProgress('upsertAdAccount');

                // Lấy toàn bộ campaign của từng tài khoản
                $campaigns = $this->api->getCampaigns($acc['id']);
                if (isset($campaigns['error'])) {
                    $result['errors'][] = ['stage' => 'getCampaigns', 'account_id' => $acc['id'], 'error' => $campaigns['error']];
                }
                $reportProgress('getCampaigns');
                foreach (($campaigns['data'] ?? []) as $camp) {
                    // Kiểm tra stop request
                    if (Cache::get('facebook_sync_stop_requested', false)) {
                        throw new \Exception('Đồng bộ đã bị dừng bởi người dùng');
                    }
                    
                    FacebookCampaign::updateOrCreate(['id' => $camp['id']], [
                        'name' => $camp['name'] ?? null,
                        'status' => $camp['status'] ?? null,
                        'objective' => $camp['objective'] ?? null,
                        'start_time' => isset($camp['start_time']) ? Carbon::parse($camp['start_time']) : null,
                        'stop_time' => isset($camp['stop_time']) ? Carbon::parse($camp['stop_time']) : null,
                        'effective_status' => $camp['effective_status'] ?? null,
                        'configured_status' => $camp['configured_status'] ?? null,
                        'updated_time' => isset($camp['updated_time']) ? Carbon::parse($camp['updated_time']) : null,
                        'ad_account_id' => $acc['id'],  // ✅ Sửa: account_id -> ad_account_id
                        'created_time' => isset($camp['created_time']) ? Carbon::parse($camp['created_time']) : null,  // ✅ Thêm ngày từ API
                    ]);
                    $result['campaigns']++;
                    $reportProgress('upsertCampaign');

                    // Lấy toàn bộ ad sets của từng campaign
                    $adsets = $this->api->getAdSetsByCampaign($camp['id']);
                    if (isset($adsets['error'])) {
                        $result['errors'][] = ['stage' => 'getAdSetsByCampaign', 'campaign_id' => $camp['id'], 'error' => $adsets['error']];
                    }
                    $reportProgress('getAdSetsByCampaign');
                    foreach (($adsets['data'] ?? []) as $adset) {
                        // Kiểm tra stop request
                        if (Cache::get('facebook_sync_stop_requested', false)) {
                            throw new \Exception('Đồng bộ đã bị dừng bởi người dùng');
                        }
                        
                        FacebookAdSet::updateOrCreate(['id' => $adset['id']], [
                            'name' => $adset['name'] ?? null,
                            'status' => $adset['status'] ?? null,
                            'optimization_goal' => $adset['optimization_goal'] ?? null,
                            'campaign_id' => $camp['id'],
                            'created_time' => isset($adset['created_time']) ? Carbon::parse($adset['created_time']) : null,  // ✅ Thêm ngày từ API
                            'updated_time' => isset($adset['updated_time']) ? Carbon::parse($adset['updated_time']) : null,  // ✅ Thêm ngày từ API
                        ]);
                        $result['adsets']++;
                        $reportProgress('upsertAdSet');

                        // Lấy ads trong ad set
                        $ads = $this->api->getAdsByAdSet($adset['id']);
                        if (isset($ads['error'])) {
                            $result['errors'][] = ['stage' => 'getAdsByAdSet', 'ad_set_id' => $adset['id'], 'error' => $ads['error']];
                        }

                        // DEBUG: Hiển thị raw response từ Facebook khi lấy Ads theo Ad Set
                        // Gợi ý: Tạm thời bật để xem mẫu dữ liệu; xoá sau khi đã kiểm tra.
                        dd([
                            'ad_set_id' => $adset['id'],
                            'ads_api_response' => $ads,
                        ]);

                        $adsInSet = ($ads['data'] ?? []);
                        // Batch: lấy insights cho nhiều ad cùng lúc để giảm round-trip
                        $adIds = array_column($adsInSet, 'id');
                        // dùng concurrency 5 để an toàn hơn, client có adaptive throttle sẵn
                        $adInsightsMap = $this->api->getInsightsForAdsBatch($adIds, 5);

                        $buffer = [];
                        $bufferSize = 0;
                        foreach ($adsInSet as $ad) {
                            // Kiểm tra stop request
                            if (Cache::get('facebook_sync_stop_requested', false)) {
                                throw new \Exception('Đồng bộ đã bị dừng bởi người dùng');
                            }
                            
                            // Debug: Log creative object để xem cấu trúc thực tế
                            // Giảm log: bỏ debug chi tiết creative để tránh I/O nặng
                            
                            // Lấy story ID từ creative để xác định post
                            $storyId = null;
                            $pageId = null;
                            $adType = 'unknown';
                            
                            // Cách 1: Từ object_story_id (chuẩn cho post ads)
                            if (isset($ad['creative']['object_story_id'])) {
                                $storyId = $ad['creative']['object_story_id'];
                                $adType = 'post';
                                Log::info('Lấy storyId từ object_story_id', ['storyId' => $storyId, 'type' => $adType]);
                            }
                            // Cách 2: Từ effective_object_story_id (post ads)
                            elseif (isset($ad['creative']['effective_object_story_id'])) {
                                $storyId = $ad['creative']['effective_object_story_id'];
                                $adType = 'post';
                                Log::info('Lấy storyId từ effective_object_story_id', ['storyId' => $storyId, 'type' => $adType]);
                            }
                            // Cách 3: Từ object_story_spec (cả post và link ads)
                            elseif (isset($ad['creative']['object_story_spec'])) {
                                $spec = $ad['creative']['object_story_spec'];
                                
                                // Lấy page_id
                                if (isset($spec['page_id'])) {
                                    $pageId = $spec['page_id'];
                                    Log::info('Lấy pageId từ object_story_spec', ['pageId' => $pageId]);
                                }
                                
                                // Lấy post_id từ các loại content khác nhau
                                if (isset($spec['link_data']['post_id'])) {
                                    $storyId = $spec['link_data']['post_id'];
                                    $adType = 'post';
                                    Log::info('Lấy storyId từ link_data.post_id', ['storyId' => $storyId, 'type' => $adType]);
                                } elseif (isset($spec['video_data']['post_id'])) {
                                    $storyId = $spec['video_data']['post_id'];
                                    $adType = 'post';
                                    Log::info('Lấy storyId từ video_data.post_id', ['storyId' => $storyId, 'type' => $adType]);
                                } elseif (isset($spec['photo_data']['post_id'])) {
                                    $storyId = $spec['photo_data']['post_id'];
                                    $adType = 'post';
                                    Log::info('Lấy storyId từ photo_data.post_id', ['storyId' => $storyId, 'type' => $adType]);
                                } elseif (isset($spec['link_data']['link'])) {
                                    // Đây là link ad, không phải post ad
                                    $storyId = $spec['link_data']['link'];
                                    $adType = 'link';
                                    Log::info('Lấy storyId từ link_data.link (link ad)', ['storyId' => $storyId, 'type' => $adType]);
                                }
                            }
                            // Cách 4: Từ các trường khác trong creative
                            elseif (isset($ad['creative']['post_id'])) {
                                $storyId = $ad['creative']['post_id'];
                                $adType = 'post';
                                Log::info('Lấy storyId từ creative.post_id', ['storyId' => $storyId, 'type' => $adType]);
                            }
                            // Cách 5: Từ story_id
                            elseif (isset($ad['creative']['story_id'])) {
                                $storyId = $ad['creative']['story_id'];
                                $adType = 'post';
                                Log::info('Lấy storyId từ creative.story_id', ['storyId' => $storyId, 'type' => $adType]);
                            }
                            // Cách 6: Từ ad object trực tiếp (một số API trả về)
                            elseif (isset($ad['object_story_id'])) {
                                $storyId = $ad['object_story_id'];
                                $adType = 'post';
                                Log::info('Lấy storyId từ ad.object_story_id', ['storyId' => $storyId, 'type' => $adType]);
                            }
                            elseif (isset($ad['effective_object_story_id'])) {
                                $storyId = $ad['effective_object_story_id'];
                                $adType = 'post';
                                Log::info('Lấy storyId từ ad.effective_object_story_id', ['storyId' => $storyId, 'type' => $adType]);
                            }
                            // Cách 7: Từ page_id trong ad object
                            elseif (isset($ad['page_id'])) {
                                $pageId = $ad['page_id'];
                                Log::info('Lấy pageId từ ad.page_id', ['pageId' => $pageId]);
                            }
                            
                            // Debug: Log kết quả extract
                            if ($result['ads'] === 0) {
                                Log::info('=== KẾT QUẢ EXTRACT ===', [
                                    'storyId' => $storyId,
                                    'pageId' => $pageId,
                                    'adType' => $adType,
                                    'has_post_data' => $storyId ? 'Có' : 'Không',
                                ]);
                            }
                            
                            // Lấy post details nếu có story ID và là post ad
                            $postData = null;
                            if ($storyId && $adType === 'post') {
                                $postData = $this->api->getPostDetails($storyId);
                                if (isset($postData['error'])) {
                                    $result['errors'][] = [
                                        'stage' => 'getPostDetails',
                                        'post_id' => $storyId,
                                        'ad_id' => $ad['id'],
                                        'error' => $postData['error']
                                    ];
                                    $postData = null;
                                } else {
                                    Log::info('Lấy được post data', [
                                        'post_id' => $postData['id'] ?? 'N/A',
                                        'post_message' => Str::limit($postData['message'] ?? 'N/A', 50),
                                    ]);
                                }
                            }

                            // Lấy post insights từ 5 năm trước đến hôm qua
                            $postInsights = null;
                            if ($postData && !isset($postData['error'])) {
                                $postInsights = $this->api->getPostInsightsExtended($storyId);
                                if (isset($postInsights['error'])) {
                                    $result['errors'][] = [
                                        'stage' => 'getPostInsights',
                                        'post_id' => $storyId,
                                        'ad_id' => $ad['id'],
                                        'error' => $postInsights['error']
                                    ];
                                    $postInsights = null;
                                }
                            }

                            // Lấy ad insights từ batch map đã gọi ở trên
                            $adInsights = $adInsightsMap[$ad['id']] ?? [];
                            if (isset($adInsights['error'])) {
                                $result['errors'][] = ['stage' => 'getInsightsForAd', 'ad_id' => $ad['id'], 'error' => $adInsights['error']];
                            }

                            // Debug block removed after verification

                            // Tổng hợp dữ liệu để hiển thị (chuẩn hóa kiểu dữ liệu)
                            $firstPostInsight = ($postInsights && !isset($postInsights['error'])) ? ($postInsights['data'][0] ?? []) : [];
                            $firstAdInsightRaw = ($adInsights && !isset($adInsights['error'])) ? ($adInsights['data'][0] ?? []) : [];
                            // Chuẩn hóa purchase_roas thành số float (array -> value)
                            $purchaseRoas = 0.0;
                            if (isset($firstAdInsightRaw['purchase_roas'])) {
                                $pr = $firstAdInsightRaw['purchase_roas'];
                                if (is_array($pr)) {
                                    $firstItem = $pr[0] ?? null;
                                    if ($firstItem && isset($firstItem['value'])) {
                                        $purchaseRoas = (float) $firstItem['value'];
                                    }
                                } else {
                                    $purchaseRoas = (float) $pr;
                                }
                            }

                            $firstAdInsight = [
                                'spend' => (float) ($firstAdInsightRaw['spend'] ?? 0),
                                'reach' => (int) ($firstAdInsightRaw['reach'] ?? 0),
                                'impressions' => (int) ($firstAdInsightRaw['impressions'] ?? 0),
                                // clicks: ưu tiên field clicks, fallback từ actions.link_click
                                'clicks' => (int) ($firstAdInsightRaw['clicks'] ?? 0),
                                'ctr' => isset($firstAdInsightRaw['ctr']) ? (float) $firstAdInsightRaw['ctr'] : 0.0,
                                'cpc' => isset($firstAdInsightRaw['cpc']) ? (float) $firstAdInsightRaw['cpc'] : 0.0,
                                'cpm' => isset($firstAdInsightRaw['cpm']) ? (float) $firstAdInsightRaw['cpm'] : 0.0,
                                'frequency' => isset($firstAdInsightRaw['frequency']) ? (float) $firstAdInsightRaw['frequency'] : 0.0,
                                'unique_clicks' => (int) ($firstAdInsightRaw['unique_clicks'] ?? 0),
                                'actions' => $firstAdInsightRaw['actions'] ?? null,
                                'action_values' => $firstAdInsightRaw['action_values'] ?? null,
                                'purchase_roas' => $purchaseRoas,
                            ];
                            if ($firstAdInsight['clicks'] === 0 && is_array($firstAdInsight['actions'])) {
                                foreach ($firstAdInsight['actions'] as $act) {
                                    if (($act['action_type'] ?? '') === 'link_click') {
                                        $firstAdInsight['clicks'] = (int) ($act['value'] ?? 0);
                                        break;
                                    }
                                }
                            }
                            
                            // Debug: Hiển thị dữ liệu tổng hợp với các chỉ số
                            // dd([
                            //     'ad_id' => $ad['id'] ?? 'N/A',
                            //     'ad_name' => $ad['name'] ?? 'N/A',
                            //     'campaign_id' => $camp['id'] ?? 'N/A',
                            //     'campaign_name' => $camp['name'] ?? 'N/A',
                            //     'adset_id' => $adset['id'] ?? 'N/A',
                            //     'adset_name' => $adset['name'] ?? 'N/A',
                            //     'post_id' => $postData['id'] ?? 'N/A',
                            //     'post_message' => $postData['message'] ?? 'N/A',
                            //     'post_type' => $postData['type'] ?? 'N/A',
                            //     
                            //     // Ad insights (từ ảnh)
                            //     'spend' => $firstAdInsight['spend'] ?? 0,
                            //     'impressions' => $firstAdInsight['impressions'] ?? 0,
                            //     'clicks' => $firstAdInsight['clicks'] ?? 0,
                            //     'ctr' => $firstAdInsight['ctr'] ?? 0,
                            //     'cpc' => $firstAdInsight['cpc'] ?? 0,
                            //     'reach' => $firstAdInsight['reach'] ?? 0,
                            //     'frequency' => $firstAdInsight['frequency'] ?? 0,
                            //     
                            //     // Post insights (likes, shares, comments)
                            //     'post_likes' => $firstPostInsight['likes'] ?? 0,
                            //     'post_shares' => $firstPostInsight['shares'] ?? 0,
                            //     'post_comments' => $firstPostInsight['comments'] ?? 0,
                            //     'post_reactions' => $firstPostInsight['reactions'] ?? 0,
                            //     'post_impressions' => $firstPostInsight['impressions'] ?? 0,
                            //     'post_reach' => $firstPostInsight['reach'] ?? 0,
                            //     
                            //     // Raw data để debug
                            //     'post_insights_raw' => $postInsights,
                            //     'ad_insights_raw' => $adInsights,
                            //     'post_data_raw' => $postData,
                            // ]);

                            // Gom vào buffer để upsert hàng loạt theo block 50
                            $buffer[] = compact('ad','adset','camp','acc','postData','postInsights','adInsights');
                            $bufferSize++;

                            if ($bufferSize >= 50) {
                                $this->bulkUpsert($buffer);
                                $buffer = [];
                                $bufferSize = 0;
                                $result['ads'] += 50;
                                // giảm tần suất log/progress: chỉ ghi khi qua mỗi 50 ads hoặc khi chuyển ad set
                                if (($result['ads'] % 50) === 0) {
                                    $reportProgress('bulkUpsert_50');
                                }
                            }
                        }

                        // Flush buffer còn lại
                        if ($bufferSize > 0) {
                            $this->bulkUpsert($buffer);
                            $result['ads'] += $bufferSize;
                            $reportProgress('bulkUpsert_flush');
                        }
                    }
                }
            }
        }

        $reportProgress('completed');
        return $result;
    }

    /**
     * Lưu hoặc cập nhật thông tin Ad với post và insights
     */
    private function upsertAdWithPostAndInsights(
        array $ad,
        array $adset,
        array $camp,
        array $acc,
        ?array $postData,
        ?array $postInsights,
        ?array $adInsights
    ): void {
        // Xử lý post insights
        $postInsightsData = [];
        if ($postInsights && !isset($postInsights['error'])) {
            foreach (($postInsights['data'] ?? []) as $insight) {
                $postInsightsData[] = [
                    'impressions' => $insight['impressions'] ?? 0,
                    'reach' => $insight['reach'] ?? 0,
                    'clicks' => $insight['clicks'] ?? 0,
                    'unique_clicks' => $insight['unique_clicks'] ?? 0,
                    'likes' => $insight['likes'] ?? 0,
                    'shares' => $insight['shares'] ?? 0,
                    'comments' => $insight['comments'] ?? 0,
                    'reactions' => $insight['reactions'] ?? 0,
                    'saves' => $insight['saves'] ?? 0,
                    'hides' => $insight['hides'] ?? 0,
                    'hide_all_clicks' => $insight['hide_all_clicks'] ?? 0,
                    'unlikes' => $insight['unlikes'] ?? 0,
                    'negative_feedback' => $insight['negative_feedback'] ?? 0,
                    'video_views' => $insight['video_views'] ?? 0,
                    'video_view_time' => $insight['video_view_time'] ?? 0,
                    'video_avg_time_watched' => $insight['video_avg_time_watched'] ?? 0,
                    'video_p25_watched_actions' => $insight['video_p25_watched_actions'] ?? 0,
                    'video_p50_watched_actions' => $insight['video_p50_watched_actions'] ?? 0,
                    'video_p75_watched_actions' => $insight['video_p75_watched_actions'] ?? 0,
                    'video_p95_watched_actions' => $insight['video_p95_watched_actions'] ?? 0,
                    'video_p100_watched_actions' => $insight['video_p100_watched_actions'] ?? 0,
                    'engagement_rate' => $insight['engagement_rate'] ?? 0,
                    'ctr' => $insight['ctr'] ?? 0,
                    'cpm' => $insight['cpm'] ?? 0,
                    'cpc' => $insight['cpc'] ?? 0,
                    'spend' => $insight['spend'] ?? 0,
                    'frequency' => $insight['frequency'] ?? 0,
                    'actions' => $insight['actions'] ?? null,
                    'action_values' => $insight['action_values'] ?? null,
                    'cost_per_action_type' => $insight['cost_per_action_type'] ?? null,
                    'cost_per_unique_action_type' => $insight['cost_per_unique_action_type'] ?? null,
                    'breakdowns' => $insight['breakdowns'] ?? null,
                ];
            }
        }

        // Xử lý ad insights
        $adInsightsData = [];
        if ($adInsights && !isset($adInsights['error'])) {
            foreach (($adInsights['data'] ?? []) as $insight) {
                $adInsightsData[] = [
                    'spend' => $insight['spend'] ?? 0,
                    'reach' => $insight['reach'] ?? 0,
                    'impressions' => $insight['impressions'] ?? 0,
                    'clicks' => $insight['clicks'] ?? 0,
                    'ctr' => $insight['ctr'] ?? 0,
                    'cpc' => $insight['cpc'] ?? 0,
                    'cpm' => $insight['cpm'] ?? 0,
                    'frequency' => $insight['frequency'] ?? 0,
                    'unique_clicks' => $insight['unique_clicks'] ?? 0,
                    'actions' => $insight['actions'] ?? null,
                    'action_values' => $insight['action_values'] ?? null,
                    'purchase_roas' => $insight['purchase_roas'] ?? null,
                ];
            }
        }

        // Chuẩn hoá bản ghi đầu tiên để map vào DB (TRONG HÀM NÀY)
        $firstPostRaw = ($postInsights && !isset($postInsights['error'])) ? (($postInsights['data'][0] ?? []) ?: []) : [];
        $firstPostInsight = [
            'impressions' => (int) ($firstPostRaw['impressions'] ?? 0),
            'reach' => (int) ($firstPostRaw['reach'] ?? 0),
            'clicks' => (int) ($firstPostRaw['clicks'] ?? 0),
            'unique_clicks' => (int) ($firstPostRaw['unique_clicks'] ?? 0),
            'likes' => (int) ($firstPostRaw['likes'] ?? 0),
            'shares' => (int) ($firstPostRaw['shares'] ?? 0),
            'comments' => (int) ($firstPostRaw['comments'] ?? 0),
            'reactions' => (int) ($firstPostRaw['reactions'] ?? 0),
            'saves' => (int) ($firstPostRaw['saves'] ?? 0),
            'hides' => (int) ($firstPostRaw['hides'] ?? 0),
            'hide_all_clicks' => (int) ($firstPostRaw['hide_all_clicks'] ?? 0),
            'unlikes' => (int) ($firstPostRaw['unlikes'] ?? 0),
            'negative_feedback' => (int) ($firstPostRaw['negative_feedback'] ?? 0),
            'video_views' => (int) ($firstPostRaw['video_views'] ?? 0),
            'video_view_time' => (int) ($firstPostRaw['video_view_time'] ?? 0),
            'video_avg_time_watched' => (float) ($firstPostRaw['video_avg_time_watched'] ?? 0),
            'video_p25_watched_actions' => (int) ($firstPostRaw['video_p25_watched_actions'] ?? 0),
            'video_p50_watched_actions' => (int) ($firstPostRaw['video_p50_watched_actions'] ?? 0),
            'video_p75_watched_actions' => (int) ($firstPostRaw['video_p75_watched_actions'] ?? 0),
            'video_p95_watched_actions' => (int) ($firstPostRaw['video_p95_watched_actions'] ?? 0),
            'video_p100_watched_actions' => (int) ($firstPostRaw['video_p100_watched_actions'] ?? 0),
            'engagement_rate' => (float) ($firstPostRaw['engagement_rate'] ?? 0),
            'ctr' => (float) ($firstPostRaw['ctr'] ?? 0),
            'cpm' => (float) ($firstPostRaw['cpm'] ?? 0),
            'cpc' => (float) ($firstPostRaw['cpc'] ?? 0),
            'spend' => (float) ($firstPostRaw['spend'] ?? 0),
            'frequency' => (float) ($firstPostRaw['frequency'] ?? 0),
            'actions' => $firstPostRaw['actions'] ?? null,
            'action_values' => $firstPostRaw['action_values'] ?? null,
            'cost_per_action_type' => $firstPostRaw['cost_per_action_type'] ?? null,
            'cost_per_unique_action_type' => $firstPostRaw['cost_per_unique_action_type'] ?? null,
            'breakdowns' => $firstPostRaw['breakdowns'] ?? null,
        ];

        $firstAdRaw = ($adInsights && !isset($adInsights['error'])) ? (($adInsights['data'][0] ?? []) ?: []) : [];
        // purchase_roas chuẩn hoá
        $purchaseRoas = 0.0;
        if (isset($firstAdRaw['purchase_roas'])) {
            $pr = $firstAdRaw['purchase_roas'];
            if (is_array($pr)) {
                $firstItem = $pr[0] ?? null;
                if ($firstItem && isset($firstItem['value'])) {
                    $purchaseRoas = (float) $firstItem['value'];
                }
            } else {
                $purchaseRoas = (float) $pr;
            }
        }
        // clicks fallback từ actions.link_click
        $adClicks = (int) ($firstAdRaw['clicks'] ?? 0);
        if ($adClicks === 0 && isset($firstAdRaw['actions']) && is_array($firstAdRaw['actions'])) {
            foreach ($firstAdRaw['actions'] as $act) {
                if (($act['action_type'] ?? '') === 'link_click') {
                    $adClicks = (int) ($act['value'] ?? 0);
                    break;
                }
            }
        }
        $firstAdInsight = [
            'spend' => (float) ($firstAdRaw['spend'] ?? 0),
            'reach' => (int) ($firstAdRaw['reach'] ?? 0),
            'impressions' => (int) ($firstAdRaw['impressions'] ?? 0),
            'clicks' => $adClicks,
            'ctr' => (float) ($firstAdRaw['ctr'] ?? 0),
            'cpc' => (float) ($firstAdRaw['cpc'] ?? 0),
            'cpm' => (float) ($firstAdRaw['cpm'] ?? 0),
            'frequency' => (float) ($firstAdRaw['frequency'] ?? 0),
            'unique_clicks' => (int) ($firstAdRaw['unique_clicks'] ?? 0),
            'actions' => $firstAdRaw['actions'] ?? null,
            'action_values' => $firstAdRaw['action_values'] ?? null,
            'purchase_roas' => $purchaseRoas,
        ];

        // Nếu KHÔNG có post insights (Link Ad, Standard Ad), synthesize các chỉ số post_* từ ad insights để vẫn lưu đủ số liệu
        $hasPostInsights = ($postInsights && !isset($postInsights['error']) && !empty($postInsights['data'] ?? []));
        if (!$hasPostInsights) {
            $actions = is_array($firstAdInsight['actions'] ?? null) ? $firstAdInsight['actions'] : [];
            $getAction = function(array $arr, string $type): int {
                foreach ($arr as $a) {
                    if (($a['action_type'] ?? '') === $type) {
                        return (int) ($a['value'] ?? 0);
                    }
                }
                return 0;
            };
            $firstPostInsight = [
                'impressions' => $firstAdInsight['impressions'],
                'reach' => $firstAdInsight['reach'],
                'clicks' => $firstAdInsight['clicks'],
                'unique_clicks' => $firstAdInsight['unique_clicks'],
                'likes' => $getAction($actions, 'post_reaction'),
                'shares' => $getAction($actions, 'post_share') ?: $getAction($actions, 'share'),
                'comments' => $getAction($actions, 'comment'),
                'reactions' => $getAction($actions, 'post_reaction'),
                'saves' => $getAction($actions, 'save'),
                'hides' => 0,
                'hide_all_clicks' => 0,
                'unlikes' => 0,
                'negative_feedback' => 0,
                'video_views' => $getAction($actions, 'video_view'),
                'video_view_time' => 0,
                'video_avg_time_watched' => 0.0,
                'video_p25_watched_actions' => 0,
                'video_p50_watched_actions' => 0,
                'video_p75_watched_actions' => 0,
                'video_p95_watched_actions' => 0,
                'video_p100_watched_actions' => 0,
                'engagement_rate' => 0.0,
                'ctr' => $firstAdInsight['ctr'],
                'cpm' => $firstAdInsight['cpm'],
                'cpc' => $firstAdInsight['cpc'],
                'spend' => $firstAdInsight['spend'],
                'frequency' => $firstAdInsight['frequency'],
                'actions' => $actions,
                'action_values' => $firstAdInsight['action_values'],
                'cost_per_action_type' => null,
                'cost_per_unique_action_type' => null,
                'breakdowns' => null,
            ];
        }

        // Xử lý dữ liệu từ creative cho link ads
        $creativeData = [];
        $pageId = null;
        $derivedPostId = null;
        
        // Cách 1: Từ object_story_spec (chuẩn)
        if (isset($ad['creative']['object_story_spec'])) {
            $spec = $ad['creative']['object_story_spec'];
            
            // Lấy page_id
            if (isset($spec['page_id'])) {
                $pageId = $spec['page_id'];
            }
            
            // Lấy dữ liệu từ link_data nếu có
            if (isset($spec['link_data'])) {
                $linkData = $spec['link_data'];
                $creativeData = [
                    'link_url' => $linkData['link'] ?? null,
                    'link_message' => $linkData['message'] ?? null,
                    'link_name' => $linkData['name'] ?? null,
                ];
                // Bổ sung các trường creative nâng cao nếu có
                if (isset($linkData['image_hash'])) {
                    $creativeData['image_hash'] = $linkData['image_hash'];
                }
                if (isset($linkData['call_to_action']['type'])) {
                    $creativeData['call_to_action_type'] = $linkData['call_to_action']['type'];
                }
                if (isset($linkData['page_welcome_message'])) {
                    $creativeData['page_welcome_message'] = $linkData['page_welcome_message'];
                }
            }
        }
        
        // Cách 2: Từ các trường khác trong creative
        if (empty($creativeData['link_url']) && isset($ad['creative'])) {
            $creative = $ad['creative'];
            
            // Kiểm tra các trường có thể chứa link
            if (isset($creative['link_url'])) {
                $creativeData['link_url'] = $creative['link_url'];
            }
            if (isset($creative['link_message'])) {
                $creativeData['link_message'] = $creative['link_message'];
            }
            if (isset($creative['link_name'])) {
                $creativeData['link_name'] = $creative['link_name'];
            }
            // Bổ sung các trường creative nâng cao nếu có
            if (isset($creative['image_hash'])) {
                $creativeData['image_hash'] = $creative['image_hash'];
            }
            if (isset($creative['call_to_action']['type'])) {
                $creativeData['call_to_action_type'] = $creative['call_to_action']['type'];
            }
            if (isset($creative['page_welcome_message'])) {
                $creativeData['page_welcome_message'] = $creative['page_welcome_message'];
            }
            
            // Kiểm tra page_id từ các trường khác
            if (!$pageId && isset($creative['page_id'])) {
                $pageId = $creative['page_id'];
            }
        }
        
        // Cách 3: Từ object_story_id hoặc effective_object_story_id
        if (isset($ad['creative']['object_story_id'])) {
            $storyId = $ad['creative']['object_story_id'];
            if (str_contains($storyId, '_')) {
                $parts = explode('_', $storyId);
                $pageId = $pageId ?: ($parts[0] ?? null);
                $derivedPostId = $derivedPostId ?: ($parts[1] ?? null);
            }
        }
        
        if (isset($ad['creative']['effective_object_story_id'])) {
            $storyId = $ad['creative']['effective_object_story_id'];
            if (str_contains($storyId, '_')) {
                $parts = explode('_', $storyId);
                $pageId = $pageId ?: ($parts[0] ?? null);
                $derivedPostId = $derivedPostId ?: ($parts[1] ?? null);
            }
        }
        
        // Cách 4: Từ ad object trực tiếp
        if (!$pageId && isset($ad['page_id'])) {
            $pageId = $ad['page_id'];
        }
        
        if (isset($ad['object_story_id'])) {
            $storyId = $ad['object_story_id'];
            if (str_contains($storyId, '_')) {
                $parts = explode('_', $storyId);
                $pageId = $pageId ?: ($parts[0] ?? null);
                $derivedPostId = $derivedPostId ?: ($parts[1] ?? null);
            }
        }
        
        if (isset($ad['effective_object_story_id'])) {
            $storyId = $ad['effective_object_story_id'];
            if (str_contains($storyId, '_')) {
                $parts = explode('_', $storyId);
                $pageId = $pageId ?: ($parts[0] ?? null);
                $derivedPostId = $derivedPostId ?: ($parts[1] ?? null);
            }
        }

        $dataToSave = [
            'name' => $ad['name'] ?? null,
            'status' => $ad['status'] ?? null,
            'effective_status' => $ad['effective_status'] ?? null,
            'adset_id' => $adset['id'],
            'campaign_id' => $camp['id'],
            'account_id' => $acc['id'],  // ✅ Đúng: ads dùng account_id
            'creative' => $ad['creative'] ?? null,
            'created_time' => Arr::get($ad, 'created_time') ? Carbon::parse($ad['created_time']) : null,  // ✅ Ngày từ API
            'updated_time' => Arr::get($ad, 'updated_time') ? Carbon::parse($ad['updated_time']) : null,  // ✅ Ngày từ API

            // Post fields (chỉ có khi là post ad)
            'post_id' => $postData['id'] ?? $derivedPostId,
            'page_id' => $pageId,
            'post_message' => $postData['message'] ?? null,
            'post_type' => $postData['type'] ?? null,
            'post_status_type' => $postData['status_type'] ?? null,
            'post_attachments' => $postData['attachments'] ?? null,
            'post_permalink_url' => $postData['permalink_url'] ?? null,
            'post_created_time' => isset($postData['created_time']) ? Carbon::parse($postData['created_time']) : null,
            'post_updated_time' => isset($postData['updated_time']) ? Carbon::parse($postData['updated_time']) : null,

            // Creative fields cho link ads
            'creative_link_url' => $creativeData['link_url'] ?? null,
            'creative_link_message' => $creativeData['link_message'] ?? null,
            'creative_link_name' => $creativeData['link_name'] ?? null,
            'creative_image_hash' => $creativeData['image_hash'] ?? null,
            'creative_call_to_action_type' => $creativeData['call_to_action_type'] ?? null,
            'creative_page_welcome_message' => $creativeData['page_welcome_message'] ?? null,

            // Post insights (lấy từ insight đầu tiên)
            'post_impressions' => $firstPostInsight['impressions'] ?? 0,
            'post_reach' => $firstPostInsight['reach'] ?? 0,
            'post_clicks' => $firstPostInsight['clicks'] ?? 0,
            'post_unique_clicks' => $firstPostInsight['unique_clicks'] ?? 0,
            'post_likes' => $firstPostInsight['likes'] ?? 0,
            'post_shares' => $firstPostInsight['shares'] ?? 0,
            'post_comments' => $firstPostInsight['comments'] ?? 0,
            'post_reactions' => $firstPostInsight['reactions'] ?? 0,
            'post_saves' => $firstPostInsight['saves'] ?? 0,
            'post_hides' => $firstPostInsight['hides'] ?? 0,
            'post_hide_all_clicks' => $firstPostInsight['hide_all_clicks'] ?? 0,
            'post_unlikes' => $firstPostInsight['unlikes'] ?? 0,
            'post_negative_feedback' => $firstPostInsight['negative_feedback'] ?? 0,
            'post_video_views' => $firstPostInsight['video_views'] ?? 0,
            'post_video_view_time' => $firstPostInsight['video_view_time'] ?? 0,
            'post_video_avg_time_watched' => $firstPostInsight['video_avg_time_watched'] ?? 0,
            'post_video_p25_watched_actions' => $firstPostInsight['video_p25_watched_actions'] ?? 0,
            'post_video_p50_watched_actions' => $firstPostInsight['video_p50_watched_actions'] ?? 0,
            'post_video_p75_watched_actions' => $firstPostInsight['video_p75_watched_actions'] ?? 0,
            'post_video_p95_watched_actions' => $firstPostInsight['video_p95_watched_actions'] ?? 0,
            'post_video_p100_watched_actions' => $firstPostInsight['video_p100_watched_actions'] ?? 0,
            'post_engagement_rate' => $firstPostInsight['engagement_rate'] ?? 0,
            'post_ctr' => $firstPostInsight['ctr'] ?? 0,
            'post_cpm' => $firstPostInsight['cpm'] ?? 0,
            'post_cpc' => $firstPostInsight['cpc'] ?? 0,
            'post_spend' => $firstPostInsight['spend'] ?? 0,
            'post_frequency' => $firstPostInsight['frequency'] ?? 0,
            'post_actions' => $firstPostInsight['actions'] ?? null,
            'post_action_values' => $firstPostInsight['action_values'] ?? null,
            'post_cost_per_action_type' => $firstPostInsight['cost_per_action_type'] ?? null,
            'post_cost_per_unique_action_type' => $firstPostInsight['cost_per_unique_action_type'] ?? null,
            'post_breakdowns' => $firstPostInsight['breakdowns'] ?? null,

            // Ad insights (lấy từ insight đầu tiên)
            'ad_spend' => (float) ($firstAdInsight['spend'] ?? 0),
            'ad_reach' => (int) ($firstAdInsight['reach'] ?? 0),
            'ad_impressions' => (int) ($firstAdInsight['impressions'] ?? 0),
            'ad_clicks' => (int) ($firstAdInsight['clicks'] ?? 0),
            'ad_ctr' => (float) ($firstAdInsight['ctr'] ?? 0),
            'ad_cpc' => (float) ($firstAdInsight['cpc'] ?? 0),
            'ad_cpm' => (float) ($firstAdInsight['cpm'] ?? 0),
            'ad_frequency' => (float) ($firstAdInsight['frequency'] ?? 0),
            'ad_unique_clicks' => (int) ($firstAdInsight['unique_clicks'] ?? 0),
            'ad_actions' => $firstAdInsight['actions'] ?? null,
            'ad_action_values' => $firstAdInsight['action_values'] ?? null,
            'ad_purchase_roas' => (float) ($firstAdInsight['purchase_roas'] ?? 0.0),

            // Metadata
            'post_metadata' => [
                'total_insights' => count($postInsightsData),
                'insights_dates' => array_column($postInsightsData, 'date_start'),
            ],
            'insights_metadata' => [
                'post_insights_count' => count($postInsightsData),
                'ad_insights_count' => count($adInsightsData),
                'last_sync' => now()->toISOString(),
            ],
            'last_insights_sync' => now(),
        ];

        FacebookAd::updateOrCreate(
            ['id' => $ad['id']],
            $dataToSave
        );
    }

    /**
     * Upsert hàng loạt 1 block các ads đã chuẩn bị dữ liệu (đã chạy mapping ở bước trước)
     */
    private function bulkUpsert(array $prepared): void
    {
        $rows = [];
        foreach ($prepared as $item) {
            ['ad' => $ad, 'adset' => $adset, 'camp' => $camp, 'acc' => $acc, 'postData' => $postData, 'postInsights' => $postInsights, 'adInsights' => $adInsights] = $item;
            // mapping dữ liệu như trong upsertAdWithPostAndInsights
            $row = $this->mapAdDataForUpsert($ad, $adset, $camp, $acc, $postData, $postInsights, $adInsights);
            if ($row) {
                $rows[] = $row;
            }
        }
        if (!empty($rows)) {
            // Sử dụng upsert của Eloquent (Laravel 8+)
            // Cột unique là 'id'
            FacebookAd::upsert($rows, ['id']);
        }
    }

    /**
     * Mapping dữ liệu cho 1 ad để upsert hàng loạt
     */
    private function mapAdDataForUpsert(
        array $ad,
        array $adset,
        array $camp,
        array $acc,
        ?array $postData,
        ?array $postInsights,
        ?array $adInsights
    ): ?array {
        // TODO: Copy toàn bộ phần xử lý mapping từ upsertAdWithPostAndInsights, trả về $dataToSave
        // Hiện tại trả về null để tránh lỗi linter
        return null;
    }

    /**
     * Kiểm tra xem dữ liệu có thay đổi không và cập nhật nếu cần
     */
    private function checkAndUpdateAdData(string $adId, array $newData): bool
    {
        $existingAd = FacebookAd::find($adId);
        
        if (!$existingAd) {
            // Tạo mới nếu chưa tồn tại
            FacebookAd::create(array_merge(['id' => $adId], $newData));
            return true;
        }

        // Kiểm tra các trường quan trọng có thay đổi không
        $hasChanges = false;
        $keyFields = [
            'ad_spend', 'ad_impressions', 'ad_clicks', 'ad_ctr', 'ad_cpc',
            'post_likes', 'post_shares', 'post_comments', 'post_impressions'
        ];

        foreach ($keyFields as $field) {
            if (isset($newData[$field]) && $existingAd->$field != $newData[$field]) {
                $hasChanges = true;
                break;
            }
        }

        if ($hasChanges) {
            // Cập nhật với timestamp mới
            $newData['last_insights_sync'] = now();
            $existingAd->update($newData);
            return true;
        }

        return false;
    }
}



