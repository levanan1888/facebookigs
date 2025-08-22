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
    private const API_VERSION = 'v23.0';
    private const BATCH_SIZE = 100;
    private const RATE_LIMIT_DELAY = 1; // 1 giây giữa các API calls
    
    public function __construct(private FacebookAdsService $api)
    {
    }

    /**
     * Đồng bộ dữ liệu Facebook Ads theo cấu trúc mới
     * Campaign → Ad Set → Ad → Ad Creative (Post) + Insights
     */
    public function syncFacebookData(?callable $onProgress = null): array
    {
        $result = [
            'businesses' => 0,
            'accounts' => 0,
            'campaigns' => 0,
            'adsets' => 0,
            'ads' => 0,
            'errors' => [],
            'start_time' => now(),
        ];

        try {
            $this->reportProgress($onProgress, 'Bắt đầu đồng bộ dữ liệu Facebook', $result);
            
            // 1. Lấy Business Managers
            $businesses = $this->syncBusinesses($result, $onProgress);
            
            // 2. Lấy Ad Accounts cho mỗi Business
            foreach ($businesses as $business) {
                $this->syncAdAccounts($business, $result, $onProgress);
            }
            
            // 3. Lấy Campaigns cho mỗi Ad Account
            $adAccounts = FacebookAdAccount::all();
            foreach ($adAccounts as $adAccount) {
                $this->syncCampaigns($adAccount, $result, $onProgress);
            }
            
            // 4. Lấy Ad Sets cho mỗi Campaign
            $campaigns = FacebookCampaign::all();
            foreach ($campaigns as $campaign) {
                $this->syncAdSets($campaign, $result, $onProgress);
            }
            
            // 5. Lấy Ads và Insights cho mỗi Ad Set
            $adSets = FacebookAdSet::all();
            foreach ($adSets as $adSet) {
                $this->syncAdsAndInsights($adSet, $result, $onProgress);
            }
            
            $result['end_time'] = now();
            $result['duration'] = $result['start_time']->diffInSeconds($result['end_time']);
            
            $this->reportProgress($onProgress, 'Hoàn thành đồng bộ dữ liệu', $result);
            
        } catch (\Exception $e) {
            Log::error('Lỗi trong quá trình đồng bộ: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            $result['errors'][] = [
                'stage' => 'main_sync',
                'error' => $e->getMessage(),
                'time' => now()->toISOString()
            ];
        }
        
        return $result;
    }

    /**
     * Đồng bộ Business Managers
     */
    private function syncBusinesses(array &$result, ?callable $onProgress): array
    {
        $this->reportProgress($onProgress, 'Đang lấy Business Managers', $result);
        
        $businesses = $this->api->getBusinessManagers();
        
        if (isset($businesses['error'])) {
            $result['errors'][] = [
                'stage' => 'getBusinessManagers',
                'error' => $businesses['error']
            ];
            return [];
        }
        
        $syncedBusinesses = [];
        foreach ($businesses['data'] ?? [] as $business) {
            try {
                $syncedBusiness = FacebookBusiness::updateOrCreate(
                    ['id' => $business['id']],
                    [
                        'name' => $business['name'] ?? null,
                        'verification_status' => $business['verification_status'] ?? null,
                        'created_time' => isset($business['created_time']) ? Carbon::parse($business['created_time']) : null,
                    ]
                );
                
                $syncedBusinesses[] = $syncedBusiness;
                $result['businesses']++;
                
            } catch (\Exception $e) {
                $result['errors'][] = [
                    'stage' => 'upsertBusiness',
                    'business_id' => $business['id'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        $this->reportProgress($onProgress, "Đã đồng bộ {$result['businesses']} Business Managers", $result);
        return $syncedBusinesses;
    }

    /**
     * Đồng bộ Ad Accounts cho một Business
     */
    private function syncAdAccounts(FacebookBusiness $business, array &$result, ?callable $onProgress): void
    {
        $this->reportProgress($onProgress, "Đang lấy Ad Accounts cho Business: {$business->name}", $result);
        
        // Lấy cả client và owned ad accounts
        $clientAccounts = $this->api->getClientAdAccounts($business->id);
        $ownedAccounts = $this->api->getOwnedAdAccounts($business->id);
        
        $allAccounts = [];
        if (!isset($clientAccounts['error'])) {
            $allAccounts = array_merge($allAccounts, $clientAccounts['data'] ?? []);
        }
        if (!isset($ownedAccounts['error'])) {
            $allAccounts = array_merge($allAccounts, $ownedAccounts['data'] ?? []);
        }
        
        // Loại bỏ duplicates
        $uniqueAccounts = [];
        foreach ($allAccounts as $account) {
            $uniqueAccounts[$account['id']] = $account;
        }
        
        foreach ($uniqueAccounts as $account) {
            try {
                FacebookAdAccount::updateOrCreate(
                    ['id' => $account['id']],
                    [
                        'account_id' => $account['account_id'] ?? null,
                        'name' => $account['name'] ?? null,
                        'account_status' => $account['account_status'] ?? null,
                        'business_id' => $business->id,
                        'created_time' => isset($account['created_time']) ? Carbon::parse($account['created_time']) : null,
                        'updated_time' => isset($account['updated_time']) ? Carbon::parse($account['updated_time']) : null,
                    ]
                );
                
                $result['accounts']++;
                
            } catch (\Exception $e) {
                $result['errors'][] = [
                    'stage' => 'upsertAdAccount',
                    'account_id' => $account['id'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        $this->reportProgress($onProgress, "Đã đồng bộ {$result['accounts']} Ad Accounts", $result);
    }

    /**
     * Đồng bộ Campaigns cho một Ad Account
     */
    private function syncCampaigns(FacebookAdAccount $adAccount, array &$result, ?callable $onProgress): void
    {
        $this->reportProgress($onProgress, "Đang lấy Campaigns cho Ad Account: {$adAccount->name}", $result);
        
        $campaigns = $this->api->getCampaigns($adAccount->id);
        
        if (isset($campaigns['error'])) {
                                    $result['errors'][] = [
                'stage' => 'getCampaigns',
                'account_id' => $adAccount->id,
                'error' => $campaigns['error']
            ];
            return;
        }
        
        foreach ($campaigns['data'] ?? [] as $campaign) {
            try {
                FacebookCampaign::updateOrCreate(
                    ['id' => $campaign['id']],
                    [
                        'name' => $campaign['name'] ?? null,
                        'status' => $campaign['status'] ?? null,
                        'objective' => $campaign['objective'] ?? null,
                        'start_time' => isset($campaign['start_time']) ? Carbon::parse($campaign['start_time']) : null,
                        'stop_time' => isset($campaign['stop_time']) ? Carbon::parse($campaign['stop_time']) : null,
                        'effective_status' => $campaign['effective_status'] ?? null,
                        'configured_status' => $campaign['configured_status'] ?? null,
                        'updated_time' => isset($campaign['updated_time']) ? Carbon::parse($campaign['updated_time']) : null,
                        'ad_account_id' => $adAccount->id,
                        'created_time' => isset($campaign['created_time']) ? Carbon::parse($campaign['created_time']) : null,
                    ]
                );
                
                $result['campaigns']++;
                
            } catch (\Exception $e) {
                                    $result['errors'][] = [
                    'stage' => 'upsertCampaign',
                    'campaign_id' => $campaign['id'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        $this->reportProgress($onProgress, "Đã đồng bộ {$result['campaigns']} Campaigns", $result);
    }

    /**
     * Đồng bộ Ad Sets cho một Campaign
     */
    private function syncAdSets(FacebookCampaign $campaign, array &$result, ?callable $onProgress): void
    {
        $this->reportProgress($onProgress, "Đang lấy Ad Sets cho Campaign: {$campaign->name}", $result);
        
        $adSets = $this->api->getAdSetsByCampaign($campaign->id);
        
        if (isset($adSets['error'])) {
                                    $result['errors'][] = [
                'stage' => 'getAdSetsByCampaign',
                'campaign_id' => $campaign->id,
                'error' => $adSets['error']
            ];
            return;
        }
        
        foreach ($adSets['data'] ?? [] as $adSet) {
            try {
                FacebookAdSet::updateOrCreate(
                    ['id' => $adSet['id']],
                    [
                        'name' => $adSet['name'] ?? null,
                        'status' => $adSet['status'] ?? null,
                        'optimization_goal' => $adSet['optimization_goal'] ?? null,
                        'campaign_id' => $campaign->id,
                        'created_time' => isset($adSet['created_time']) ? Carbon::parse($adSet['created_time']) : null,
                        'updated_time' => isset($adSet['updated_time']) ? Carbon::parse($adSet['updated_time']) : null,
                    ]
                );
                
                $result['adsets']++;
                
            } catch (\Exception $e) {
                                    $result['errors'][] = [
                    'stage' => 'upsertAdSet',
                    'adset_id' => $adSet['id'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        $this->reportProgress($onProgress, "Đã đồng bộ {$result['adsets']} Ad Sets", $result);
    }

    /**
     * Đồng bộ Ads và Insights cho một Ad Set
     */
    private function syncAdsAndInsights(FacebookAdSet $adSet, array &$result, ?callable $onProgress): void
    {
        $this->reportProgress($onProgress, "Đang lấy Ads cho Ad Set: {$adSet->name}", $result);
        
        $ads = $this->api->getAdsByAdSet($adSet->id);
        
        if (isset($ads['error'])) {
            $result['errors'][] = [
                'stage' => 'getAdsByAdSet',
                'adset_id' => $adSet->id,
                'error' => $ads['error']
            ];
            return;
        }
        
        $adsData = $ads['data'] ?? [];
        $totalAds = count($adsData);
        
        // Tiếp tục xử lý ads
        $this->reportProgress($onProgress, "📊 Đang xử lý {$totalAds} ads cho Ad Set: {$adSet->name}", $result);
        
        $this->reportProgress($onProgress, "Tìm thấy {$totalAds} Ads trong Ad Set: {$adSet->name}", $result);
        
        // Xử lý từng ad
        foreach ($adsData as $index => $ad) {
            try {
                $this->processSingleAd($ad, $adSet, $result, $onProgress, $index + 1, $totalAds);
                
                // Rate limiting
                if ($index < $totalAds - 1) {
                    sleep(self::RATE_LIMIT_DELAY);
                }
                
            } catch (\Exception $e) {
                $result['errors'][] = [
                    'stage' => 'processAd',
                    'ad_id' => $ad['id'] ?? 'unknown',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        $this->reportProgress($onProgress, "Đã xử lý {$totalAds} Ads cho Ad Set: {$adSet->name}", $result);
    }

    /**
     * Xử lý một Ad đơn lẻ
     */
    private function processSingleAd(array $ad, FacebookAdSet $adSet, array &$result, ?callable $onProgress, int $current, int $total): void
    {
        $this->reportProgress($onProgress, "Đang xử lý Ad {$current}/{$total}: {$ad['name']}", $result);
        
        // 1. Xác định loại Ad và lấy thông tin tương ứng
        $adType = $this->determineAdType($ad);
        $postData = null;
        $postInsights = null;
        
        if ($adType === 'post_ad') {
            // Post Ads - lấy post details và insights
            $postData = $this->extractPostData($ad);
            if ($postData && isset($postData['id'])) {
                $postInsights = $this->api->getPostInsightsExtended($postData['id']);
                $this->reportProgress($onProgress, "📱 Post Ad: Lấy được post insights cho post: {$postData['id']}", $result);
            }
        } else {
            // Link Ads - chỉ lấy creative data
            $this->reportProgress($onProgress, "🔗 Link Ad: Không có post, chỉ lấy creative data", $result);
        }
        
        // 3. Lấy Ad Insights
        $adInsights = $this->api->getInsightsForAdsBatch([$ad['id']]);
        $adInsightsData = $adInsights[$ad['id']] ?? null;
        
        // Log ad insights info
        $this->reportProgress($onProgress, "📊 Lấy được ad insights cho ad: {$ad['name']}", $result);
        
        // 4. Lưu vào database
        $this->upsertAdWithData($ad, $adSet, $postData, $postInsights, $adInsightsData);
        
        $result['ads']++;
        
        $this->reportProgress($onProgress, "Đã xử lý Ad {$current}/{$total}: {$ad['name']}", $result);
    }

    /**
     * Xác định loại Ad (Post Ad hay Link Ad)
     */
    private function determineAdType(array $ad): string
    {
        // Kiểm tra xem có phải là Post Ad không
        if (isset($ad['object_story_id'])) {
            return 'post_ad';
        }
        
        // Kiểm tra creative có chứa post_id không
        if (isset($ad['creative']['object_story_id']) || 
            isset($ad['creative']['effective_object_story_id']) ||
            isset($ad['creative']['object_story_spec'])) {
            return 'post_ad';
        }
        
        // Mặc định là Link Ad
        return 'link_ad';
    }

    /**
     * Trích xuất thông tin Post từ Ad Creative
     */
    private function extractPostData(array $ad): ?array
    {
        $creative = $ad['creative'] ?? [];
        
        // Log creative info để debug
        Log::info("Extract post data từ creative", [
            'ad_id' => $ad['id'] ?? 'N/A',
            'ad_name' => $ad['name'] ?? 'N/A',
            'creative_keys' => array_keys($creative),
            'has_object_story_id' => isset($creative['object_story_id']),
            'has_effective_object_story_id' => isset($creative['effective_object_story_id']),
            'has_object_story_spec' => isset($creative['object_story_spec'])
        ]);
        
        // Cách 1: Từ object_story_id (chuẩn cho post ads)
        if (isset($creative['object_story_id'])) {
            $storyId = $creative['object_story_id'];
            return $this->getPostDetails($storyId);
        }
        
        // Cách 2: Từ effective_object_story_id
        if (isset($creative['effective_object_story_id'])) {
            $storyId = $creative['effective_object_story_id'];
            return $this->getPostDetails($storyId);
        }
        
        // Cách 3: Từ object_story_spec
        if (isset($creative['object_story_spec'])) {
            $spec = $creative['object_story_spec'];
            
            if (isset($spec['link_data']['post_id'])) {
                $storyId = $spec['link_data']['post_id'];
                return $this->getPostDetails($storyId);
            }
            
            if (isset($spec['video_data']['post_id'])) {
                $storyId = $spec['video_data']['post_id'];
                return $this->getPostDetails($storyId);
            }
            
            if (isset($spec['photo_data']['post_id'])) {
                $storyId = $spec['photo_data']['post_id'];
                return $this->getPostDetails($storyId);
            }
        }
        
        return null;
    }

    /**
     * Lấy chi tiết Post từ Facebook API
     */
    private function getPostDetails(string $postId): ?array
    {
        try {
            $postData = $this->api->getPostDetails($postId);
            
            // Log post details info
            Log::info("Lấy được post details", [
                'post_id' => $postId,
                'post_type' => $postData['type'] ?? 'N/A',
                'has_message' => isset($postData['message'])
            ]);
            
            if (isset($postData['error'])) {
                Log::warning("Không lấy được post data", [
                    'post_id' => $postId,
                    'error' => $postData['error']
                ]);
                return null;
            }
            
            return $postData;
            
        } catch (\Exception $e) {
            Log::error("Lỗi khi lấy post details", [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Lưu Ad với tất cả dữ liệu liên quan
     */
    private function upsertAdWithData(array $ad, FacebookAdSet $adSet, ?array $postData, ?array $postInsights, ?array $adInsights): void
    {
        try {
            // Chuẩn bị dữ liệu cơ bản
            $basicData = [
            'name' => $ad['name'] ?? null,
            'status' => $ad['status'] ?? null,
            'effective_status' => $ad['effective_status'] ?? null,
                'adset_id' => $adSet->id,
                'campaign_id' => $adSet->campaign_id,
                'account_id' => $adSet->campaign->ad_account_id,
                'creative' => isset($ad['creative']) ? json_encode($ad['creative']) : null,
                'created_time' => isset($ad['created_time']) ? Carbon::parse($ad['created_time']) : null,
                'updated_time' => isset($ad['updated_time']) ? Carbon::parse($ad['updated_time']) : null,
                'last_insights_sync' => now(),
            ];
            
            // Thêm dữ liệu Post nếu có
            if ($postData) {
                $pageId = $this->extractPageId($ad, $postData);
                
                // Debug: Log page_id để kiểm tra
                Log::info("Extract page_id", [
                    'ad_id' => $ad['id'],
                    'page_id' => $pageId,
                    'page_id_type' => gettype($pageId)
                ]);
                
                $basicData = array_merge($basicData, [
                    'post_id' => $postData['id'],
                    'page_id' => $pageId, // page_id đã được xử lý trong extractPageId()
                    'post_message' => $postData['message'] ?? null,
                    'post_type' => $postData['type'] ?? null,
                    'post_status_type' => $postData['status_type'] ?? null,
                    'post_attachments' => isset($postData['attachments']) ? json_encode($postData['attachments']) : null,
                    'post_permalink_url' => $postData['permalink_url'] ?? null,
                    'post_created_time' => isset($postData['created_time']) ? Carbon::parse($postData['created_time']) : null,
                    'post_updated_time' => isset($postData['updated_time']) ? Carbon::parse($postData['updated_time']) : null,
                ]);
            }
            
            // Thêm dữ liệu Creative cho link ads
            $creativeData = $this->extractCreativeData($ad);
            if ($creativeData) {
                $basicData = array_merge($basicData, $creativeData);
            }
            
            // Thêm dữ liệu Insights
            $insightsData = $this->extractInsightsData($postInsights, $adInsights);
            if ($insightsData) {
                $basicData = array_merge($basicData, $insightsData);
            }

            // Metadata
            $basicData['post_metadata'] = json_encode([
                'has_post_data' => !empty($postData),
                'has_post_insights' => !empty($postInsights),
                'has_ad_insights' => !empty($adInsights),
                'last_sync' => now()->toISOString(),
            ]);
            
            $basicData['insights_metadata'] = json_encode([
                'post_insights_count' => isset($postInsights['data']) ? count($postInsights['data']) : 0,
                'ad_insights_count' => isset($adInsights['data']) ? count($adInsights['data']) : 0,
                'last_sync' => now()->toISOString(),
            ]);
            
            // Upsert vào database
            // Đảm bảo tất cả dữ liệu đều là string, number hoặc null trước khi lưu
            Log::info("Data trước khi validate", [
                'ad_id' => $ad['id'] ?? 'unknown',
                'data_keys' => array_keys($basicData),
                'page_id_type' => isset($basicData['page_id']) ? gettype($basicData['page_id']) : 'not_set',
                'page_id_value' => $basicData['page_id'] ?? 'not_set'
            ]);
            
            $this->validateDataBeforeSave($basicData);
            
            Log::info("Data sau khi validate", [
                'ad_id' => $ad['id'] ?? 'unknown',
                'data_keys' => array_keys($basicData),
                'page_id_type' => isset($basicData['page_id']) ? gettype($basicData['page_id']) : 'not_set',
                'page_id_value' => $basicData['page_id'] ?? 'not_set'
            ]);
            
            FacebookAd::updateOrCreate(
                ['id' => $ad['id']],
                $basicData
            );
            
        } catch (\Exception $e) {
            Log::error("Lỗi khi upsert ad", [
                'ad_id' => $ad['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Trích xuất Page ID từ Ad hoặc Post
     */
    private function extractPageId(array $ad, ?array $postData): ?string
    {
        try {
            // Từ object_story_spec
            if (isset($ad['creative']['object_story_spec']['page_id'])) {
                $pageId = $ad['creative']['object_story_spec']['page_id'];
                // Đảm bảo luôn trả về string hoặc null
                if (is_array($pageId)) {
                    return json_encode($pageId);
                }
                return is_string($pageId) ? $pageId : (string) $pageId;
            }
            
            // Từ object_story_id (format: pageId_postId)
            if (isset($ad['creative']['object_story_id'])) {
                $storyId = $ad['creative']['object_story_id'];
                if (is_string($storyId)) {
                    $parts = explode('_', $storyId);
                    return $parts[0] ?? null;
                }
            }
            
            // Từ ad object trực tiếp
            if (isset($ad['page_id'])) {
                $pageId = $ad['page_id'];
                // Đảm bảo luôn trả về string hoặc null
                if (is_array($pageId)) {
                    return json_encode($pageId);
                }
                return is_string($pageId) ? $pageId : (string) $pageId;
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error("Lỗi khi extract page_id", [
                'ad_id' => $ad['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Trích xuất dữ liệu Creative cho link ads
     */
    private function extractCreativeData(array $ad): array
    {
        $creative = $ad['creative'] ?? [];
        
        // Log creative data info
        Log::info("Extract creative data", [
                'ad_id' => $ad['id'] ?? 'N/A',
            'ad_name' => $ad['name'] ?? 'N/A',
            'creative_keys' => array_keys($creative),
            'has_object_story_spec' => isset($creative['object_story_spec']),
            'has_link_data' => isset($creative['object_story_spec']['link_data'])
        ]);
        
        $data = [];
        
        if (isset($creative['object_story_spec']['link_data'])) {
            $linkData = $creative['object_story_spec']['link_data'];
            $data = [
                'creative_link_url' => $linkData['link'] ?? null,
                'creative_link_message' => $linkData['message'] ?? null,
                'creative_link_name' => $linkData['name'] ?? null,
                'creative_image_hash' => $linkData['image_hash'] ?? null,
                'creative_call_to_action_type' => $linkData['call_to_action']['type'] ?? null,
                'creative_page_welcome_message' => $linkData['page_welcome_message'] ?? null,
            ];
        }
        
        return $data;
    }

    /**
     * Trích xuất và chuẩn hóa dữ liệu Insights
     */
    private function extractInsightsData(?array $postInsights, ?array $adInsights): array
    {
        $data = [];
        
        // Post Insights
        if ($postInsights && !isset($postInsights['error']) && isset($postInsights['data'][0])) {
            $post = $postInsights['data'][0];
            $data = array_merge($data, [
                'post_impressions' => (int) ($post['impressions'] ?? 0),
                'post_reach' => (int) ($post['reach'] ?? 0),
                'post_clicks' => (int) ($post['clicks'] ?? 0),
                'post_unique_clicks' => (int) ($post['unique_clicks'] ?? 0),
                'post_likes' => (int) ($post['likes'] ?? 0),
                'post_shares' => (int) ($post['shares'] ?? 0),
                'post_comments' => (int) ($post['comments'] ?? 0),
                'post_reactions' => (int) ($post['reactions'] ?? 0),
                'post_saves' => (int) ($post['saves'] ?? 0),
                'post_hides' => (int) ($post['hides'] ?? 0),
                'post_hide_all_clicks' => (int) ($post['hide_all_clicks'] ?? 0),
                'post_unlikes' => (int) ($post['unlikes'] ?? 0),
                'post_negative_feedback' => (int) ($post['negative_feedback'] ?? 0),
                'post_video_views' => (int) ($post['video_views'] ?? 0),
                'post_video_view_time' => (int) ($post['video_view_time'] ?? 0),
                'post_video_avg_time_watched' => (float) ($post['video_avg_time_watched'] ?? 0),
                'post_video_p25_watched_actions' => (int) ($post['video_p25_watched_actions'] ?? 0),
                'post_video_p50_watched_actions' => (int) ($post['video_p50_watched_actions'] ?? 0),
                'post_video_p75_watched_actions' => (int) ($post['video_p75_watched_actions'] ?? 0),
                'post_video_p95_watched_actions' => (int) ($post['video_p95_watched_actions'] ?? 0),
                'post_video_p100_watched_actions' => (int) ($post['video_p100_watched_actions'] ?? 0),
                'post_engagement_rate' => (float) ($post['engagement_rate'] ?? 0),
                'post_ctr' => (float) ($post['ctr'] ?? 0),
                'post_cpm' => (float) ($post['cpm'] ?? 0),
                'post_cpc' => (float) ($post['cpc'] ?? 0),
                'post_spend' => (float) ($post['spend'] ?? 0),
                'post_frequency' => (float) ($post['frequency'] ?? 0),
                'post_actions' => isset($post['actions']) ? json_encode($post['actions']) : null,
                'post_action_values' => isset($post['action_values']) ? json_encode($post['action_values']) : null,
                'post_cost_per_action_type' => isset($post['cost_per_action_type']) ? json_encode($post['cost_per_action_type']) : null,
                'post_cost_per_unique_action_type' => isset($post['cost_per_unique_action_type']) ? json_encode($post['cost_per_unique_action_type']) : null,
                'post_breakdowns' => isset($post['breakdowns']) ? json_encode($post['breakdowns']) : null,
            ]);
        }
        
        // Ad Insights - Lưu tất cả metrics từ lifetime data
        if ($adInsights && !isset($adInsights['error']) && isset($adInsights['data'])) {
            // Tính tổng tất cả metrics từ tất cả các ngày
            $totalMetrics = [
                'spend' => 0,
                'reach' => 0,
                'impressions' => 0,
                'clicks' => 0,
                'unique_clicks' => 0,
                'conversions' => 0,
                'conversion_values' => 0,
                'cost_per_conversion' => 0,
                'outbound_clicks' => 0,
                'unique_outbound_clicks' => 0,
                'inline_link_clicks' => 0,
                'unique_inline_link_clicks' => 0,
                'website_clicks' => 0,
            ];
            
            $allActions = [];
            $allActionValues = [];
            
            foreach ($adInsights['data'] as $dailyInsight) {
                $totalMetrics['spend'] += (float) ($dailyInsight['spend'] ?? 0);
                $totalMetrics['impressions'] += (int) ($dailyInsight['impressions'] ?? 0);
                $totalMetrics['clicks'] += (int) ($dailyInsight['clicks'] ?? 0);
                $totalMetrics['unique_clicks'] += (int) ($dailyInsight['unique_clicks'] ?? 0);
                $totalMetrics['conversions'] += (int) ($dailyInsight['conversions'] ?? 0);
                $totalMetrics['conversion_values'] += (float) ($dailyInsight['conversion_values'] ?? 0);
                $totalMetrics['outbound_clicks'] += (int) ($dailyInsight['outbound_clicks'] ?? 0);
                $totalMetrics['unique_outbound_clicks'] += (int) ($dailyInsight['unique_outbound_clicks'] ?? 0);
                $totalMetrics['inline_link_clicks'] += (int) ($dailyInsight['inline_link_clicks'] ?? 0);
                $totalMetrics['unique_inline_link_clicks'] += (int) ($dailyInsight['unique_inline_link_clicks'] ?? 0);
                $totalMetrics['website_clicks'] += (int) ($dailyInsight['website_clicks'] ?? 0);
                
                // Tính reach max (không cộng dồn)
                $totalMetrics['reach'] = max($totalMetrics['reach'], (int) ($dailyInsight['reach'] ?? 0));
                
                // Merge actions
                if (isset($dailyInsight['actions']) && is_array($dailyInsight['actions'])) {
                    foreach ($dailyInsight['actions'] as $action) {
                        $actionType = $action['action_type'] ?? 'unknown';
                        $allActions[$actionType] = ($allActions[$actionType] ?? 0) + (int) ($action['value'] ?? 0);
                    }
                }
                
                // Merge action values
                if (isset($dailyInsight['action_values']) && is_array($dailyInsight['action_values'])) {
                    foreach ($dailyInsight['action_values'] as $actionValue) {
                        $actionType = $actionValue['action_type'] ?? 'unknown';
                        $allActionValues[$actionType] = ($allActionValues[$actionType] ?? 0) + (float) ($actionValue['value'] ?? 0);
                    }
                }
            }
            
            // Tính các metrics trung bình
            $totalImpressions = $totalMetrics['impressions'];
            $totalClicks = $totalMetrics['clicks'];
            $totalSpend = $totalMetrics['spend'];
            
            $ctr = $totalImpressions > 0 ? ($totalClicks / $totalImpressions) * 100 : 0;
            $cpc = $totalClicks > 0 ? $totalSpend / $totalClicks : 0;
            $cpm = $totalImpressions > 0 ? ($totalSpend / $totalImpressions) * 1000 : 0;
            $frequency = $totalMetrics['reach'] > 0 ? $totalImpressions / $totalMetrics['reach'] : 0;
            $costPerConversion = $totalMetrics['conversions'] > 0 ? $totalSpend / $totalMetrics['conversions'] : 0;
            
            // ROAS calculation
            $purchaseRoas = 0;
            if (isset($allActionValues['purchase']) && $totalSpend > 0) {
                $purchaseRoas = $allActionValues['purchase'] / $totalSpend;
            }
            
            $data = array_merge($data, [
                'ad_spend' => $totalSpend,
                'ad_reach' => $totalMetrics['reach'],
                'ad_impressions' => $totalMetrics['impressions'],
                'ad_clicks' => $totalMetrics['clicks'],
                'ad_ctr' => $ctr,
                'ad_cpc' => $cpc,
                'ad_cpm' => $cpm,
                'ad_frequency' => $frequency,
                'ad_unique_clicks' => $totalMetrics['unique_clicks'],
                'ad_actions' => !empty($allActions) ? json_encode($allActions) : null,
                'ad_action_values' => !empty($allActionValues) ? json_encode($allActionValues) : null,
                'ad_purchase_roas' => $purchaseRoas,
                
                // Thêm các metrics mới
                'ad_conversions' => $totalMetrics['conversions'],
                'ad_conversion_values' => $totalMetrics['conversion_values'],
                'ad_cost_per_conversion' => $costPerConversion,
                'ad_outbound_clicks' => $totalMetrics['outbound_clicks'],
                'ad_unique_outbound_clicks' => $totalMetrics['unique_outbound_clicks'],
                'ad_inline_link_clicks' => $totalMetrics['inline_link_clicks'],
                'ad_unique_inline_link_clicks' => $totalMetrics['unique_inline_link_clicks'],
                'ad_website_clicks' => $totalMetrics['website_clicks'],
            ]);
        }
        
        return $data;
    }

    /**
     * Trích xuất Purchase ROAS từ Ad Insights
     */
    private function extractPurchaseRoas(array $adInsight): float
    {
        if (isset($adInsight['purchase_roas'])) {
            $roas = $adInsight['purchase_roas'];
            if (is_array($roas) && isset($roas[0]['value'])) {
                return (float) $roas[0]['value'];
            }
            return (float) $roas;
        }
        return 0.0;
    }

    /**
     * Báo cáo tiến độ
     */
    private function reportProgress(?callable $onProgress, string $message, array $result): void
    {
        Log::info($message, $result);
        
        if ($onProgress) {
            $onProgress([
                'message' => $message,
                'counts' => [
                    'businesses' => $result['businesses'],
                    'accounts' => $result['accounts'],
                    'campaigns' => $result['campaigns'],
                    'adsets' => $result['adsets'],
                    'ads' => $result['ads'],
                ],
                'errors' => $result['errors'],
                'timestamp' => now()->toISOString(),
            ]);
        }
    }

    /**
     * Đồng bộ dữ liệu ngày hôm qua (tương thích với code cũ)
     */
    public function syncYesterday(?callable $onProgress = null): array
    {
        return $this->syncFacebookData($onProgress);
    }

    /**
     * Validate data before saving to database.
     * Ensures all values are string, number, or null.
     */
    private function validateDataBeforeSave(array &$data): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Nếu là array, convert thành JSON string
                $data[$key] = json_encode($value);
            } elseif (is_bool($value)) {
                // Convert boolean thành string
                $data[$key] = $value ? '1' : '0';
            } elseif (is_object($value)) {
                // Convert object thành string
                $data[$key] = (string) $value;
            } elseif (!is_string($value) && !is_numeric($value) && $value !== null) {
                // Convert các kiểu dữ liệu khác thành string
                $data[$key] = (string) $value;
            }
        }
    }
}



