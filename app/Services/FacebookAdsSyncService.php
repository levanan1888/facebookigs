<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\FacebookAd;
use App\Models\FacebookAdAccount;
use App\Models\FacebookAdSet;
use App\Models\FacebookBusiness;
use App\Models\FacebookCampaign;
use App\Models\FacebookCreative;
use App\Models\FacebookPost;
use App\Models\FacebookPage;
use App\Models\FacebookPostInsight;
use App\Models\FacebookAdInsight;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FacebookAdsSyncService
{
    private const API_VERSION = 'v19.0';
    private const BATCH_SIZE = 100;
    private const RATE_LIMIT_DELAY = 1; // 1 giây giữa các API calls
    private ?int $lastProcessedAdInsightId = null;
    
    public function __construct(private FacebookAdsService $api)
    {
    }

    /**
     * Đồng bộ dữ liệu Facebook Ads theo cấu trúc mới đã normalize
     * Campaign → Ad Set → Ad → Ad Creative (Post) + Insights
     */
    public function syncFacebookData(?callable $onProgress = null, ?string $since = null, ?string $until = null): array
    {
        $since = $since ?: now()->subYear()->format('Y-m-d');
        $until = $until ?: now()->format('Y-m-d');
        
        $result = [
            'businesses' => 0,
            'accounts' => 0,
            'campaigns' => 0,
            'adsets' => 0,
            'ads' => 0,
            'posts' => 0,
            'pages' => 0,
            'post_insights' => 0,
            'ad_insights' => 0,
            'breakdowns' => 0,
            'errors' => [],
            'start_time' => now(),
            'time_range' => [
                'since' => $since,
                'until' => $until
            ],
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
                'stage' => 'businesses',
                'error' => $businesses['error']['message'] ?? 'Unknown error',
                'time' => now()->toISOString()
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
                        'updated_time' => isset($business['updated_time']) ? Carbon::parse($business['updated_time']) : null,
                    ]
                );
                $syncedBusinesses[] = $syncedBusiness;
                $result['businesses']++;
            } catch (\Exception $e) {
                Log::error("Lỗi khi sync business", [
                    'business_id' => $business['id'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
                $result['errors'][] = [
                    'stage' => 'business',
                    'business_id' => $business['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                    'time' => now()->toISOString()
                ];
            }
        }

        return $syncedBusinesses;
    }

    /**
     * Đồng bộ Ad Accounts cho Business
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
        
        $accounts = ['data' => array_values($uniqueAccounts)];
        
        if (isset($accounts['error'])) {
            $result['errors'][] = [
                'stage' => 'ad_accounts',
                'business_id' => $business->id,
                'error' => $accounts['error']['message'] ?? 'Unknown error',
                'time' => now()->toISOString()
            ];
            return;
        }

        foreach ($accounts['data'] ?? [] as $account) {
            try {
                FacebookAdAccount::updateOrCreate(
                    ['id' => $account['id']],
                    [
                        'business_id' => $business->id,
                        'name' => $account['name'] ?? null,
                        'account_id' => $account['account_id'] ?? null,
                        'account_status' => $account['account_status'] ?? null,
                        'currency' => $account['currency'] ?? null,
                        'timezone_name' => $account['timezone_name'] ?? null,
                        'created_time' => isset($account['created_time']) ? Carbon::parse($account['created_time']) : null,
                        'updated_time' => isset($account['updated_time']) ? Carbon::parse($account['updated_time']) : null,
                    ]
                );
                $result['accounts']++;
            } catch (\Exception $e) {
                Log::error("Lỗi khi sync ad account", [
                    'account_id' => $account['id'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
                $result['errors'][] = [
                    'stage' => 'ad_account',
                    'account_id' => $account['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                    'time' => now()->toISOString()
                ];
            }
        }
    }

    /**
     * Đồng bộ Campaigns cho Ad Account
     */
    private function syncCampaigns(FacebookAdAccount $adAccount, array &$result, ?callable $onProgress): void
    {
        $this->reportProgress($onProgress, "Đang lấy Campaigns cho Account: {$adAccount->name}", $result);
        
        $campaigns = $this->api->getCampaigns($adAccount->id);
        
        if (isset($campaigns['error'])) {
                                    $result['errors'][] = [
                'stage' => 'campaigns',
                'account_id' => $adAccount->id,
                'error' => $campaigns['error']['message'] ?? 'Unknown error',
                'time' => now()->toISOString()
            ];
            return;
        }
        
        foreach ($campaigns['data'] ?? [] as $campaign) {
            try {
                FacebookCampaign::updateOrCreate(
                    ['id' => $campaign['id']],
                    [
                        'ad_account_id' => $adAccount->id,
                        'name' => $campaign['name'] ?? null,
                        'status' => $campaign['status'] ?? null,
                        'objective' => $campaign['objective'] ?? null,
                        'special_ad_categories' => isset($campaign['special_ad_categories']) ? json_encode($campaign['special_ad_categories']) : null,
                        'created_time' => isset($campaign['created_time']) ? Carbon::parse($campaign['created_time']) : null,
                        'updated_time' => isset($campaign['updated_time']) ? Carbon::parse($campaign['updated_time']) : null,
                    ]
                );
                $result['campaigns']++;
            } catch (\Exception $e) {
                Log::error("Lỗi khi sync campaign", [
                    'campaign_id' => $campaign['id'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
                $result['errors'][] = [
                    'stage' => 'campaign',
                    'campaign_id' => $campaign['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                    'time' => now()->toISOString()
                ];
            }
        }
    }

    /**
     * Đồng bộ Ad Sets cho Campaign
     */
    private function syncAdSets(FacebookCampaign $campaign, array &$result, ?callable $onProgress): void
    {
        $this->reportProgress($onProgress, "Đang lấy Ad Sets cho Campaign: {$campaign->name}", $result);
        
        $adSets = $this->api->getAdSetsByCampaign($campaign->id);
        
        if (isset($adSets['error'])) {
            $errorMessage = 'Unknown error';
            if (is_array($adSets['error'])) {
                $errorMessage = $adSets['error']['message'] ?? $adSets['error']['error_user_msg'] ?? 'Unknown error';
            } elseif (is_string($adSets['error'])) {
                $errorMessage = $adSets['error'];
            }
            
            Log::error("Lỗi khi lấy Ad Sets", [
                'campaign_id' => $campaign->id,
                'campaign_name' => $campaign->name,
                'error' => $adSets['error']
            ]);
            
            $result['errors'][] = [
                'stage' => 'adsets',
                'campaign_id' => $campaign->id,
                'error' => $errorMessage,
                'time' => now()->toISOString()
            ];
            return;
        }
        
        foreach ($adSets['data'] ?? [] as $adSet) {
            try {
                FacebookAdSet::updateOrCreate(
                    ['id' => $adSet['id']],
                    [
                        'campaign_id' => $campaign->id,
                        'name' => $adSet['name'] ?? null,
                        'status' => $adSet['status'] ?? null,
                        'daily_budget' => $adSet['daily_budget'] ?? null,
                        'lifetime_budget' => $adSet['lifetime_budget'] ?? null,
                        'billing_event' => $adSet['billing_event'] ?? null,
                        'optimization_goal' => $adSet['optimization_goal'] ?? null,
                        'bid_amount' => $adSet['bid_amount'] ?? null,
                        'bid_strategy' => $adSet['bid_strategy'] ?? null,
                        'created_time' => isset($adSet['created_time']) ? Carbon::parse($adSet['created_time']) : null,
                        'updated_time' => isset($adSet['updated_time']) ? Carbon::parse($adSet['updated_time']) : null,
                    ]
                );
                $result['adsets']++;
            } catch (\Exception $e) {
                Log::error("Lỗi khi sync adset", [
                    'adset_id' => $adSet['id'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
                $result['errors'][] = [
                    'stage' => 'adset',
                    'adset_id' => $adSet['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                    'time' => now()->toISOString()
                ];
            }
        }
    }

    /**
     * Đồng bộ Ads và Insights cho Ad Set
     */
    private function syncAdsAndInsights(FacebookAdSet $adSet, array &$result, ?callable $onProgress): void
    {
        $this->reportProgress($onProgress, "Đang lấy Ads cho Ad Set: {$adSet->name}", $result);
        
        $ads = $this->api->getAdsByAdSet($adSet->id);
        
        if (isset($ads['error'])) {
            $errorMessage = 'Unknown error';
            if (is_array($ads['error'])) {
                $errorMessage = $ads['error']['message'] ?? $ads['error']['error_user_msg'] ?? 'Unknown error';
            } elseif (is_string($ads['error'])) {
                $errorMessage = $ads['error'];
            }
            
            Log::error("Lỗi khi lấy Ads", [
                'adset_id' => $adSet->id,
                'adset_name' => $adSet->name,
                'error' => $ads['error']
            ]);
            
            $result['errors'][] = [
                'stage' => 'ads',
                'adset_id' => $adSet->id,
                'error' => $errorMessage,
                'time' => now()->toISOString()
            ];
            return;
        }
        
        foreach ($ads['data'] ?? [] as $ad) {
            try {
                $this->processAdWithNormalizedStructure($ad, $adSet, $result);
            } catch (\Exception $e) {
                Log::error("Lỗi khi process ad", [
                    'ad_id' => $ad['id'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
                $result['errors'][] = [
                    'stage' => 'ad_processing',
                    'ad_id' => $ad['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                    'time' => now()->toISOString()
                ];
            }
        }
    }

    /**
     * Xử lý Ad với cấu trúc database đã normalize
     */
    private function processAdWithNormalizedStructure(array $ad, FacebookAdSet $adSet, array &$result): void
    {
        // 1. Lưu Ad cơ bản
        $facebookAd = FacebookAd::updateOrCreate(
            ['id' => $ad['id']],
            [
                'name' => $ad['name'] ?? null,
                'status' => $ad['status'] ?? null,
                'effective_status' => $ad['effective_status'] ?? null,
                'adset_id' => $adSet->id,
                'campaign_id' => $adSet->campaign_id,
                'account_id' => $adSet->campaign->ad_account_id,
                'created_time' => isset($ad['created_time']) ? Carbon::parse($ad['created_time']) : null,
                'updated_time' => isset($ad['updated_time']) ? Carbon::parse($ad['updated_time']) : null,
                'last_insights_sync' => now(),
            ]
        );
        $result['ads']++;

        // 2. Lưu Creative data vào bảng facebook_creatives
        if (isset($ad['creative'])) {
            $this->saveCreativeData($ad['creative'], $facebookAd);
        }

        // 2. Xử lý Post data nếu có (chỉ lưu thông tin cơ bản, không lấy insights do hạn chế quyền)
        $postData = $this->extractPostData($ad);
        if ($postData) {
            $this->processPostData($postData, $facebookAd, $result);
        }

        // 4. Lấy và lưu Ad Insights đầy đủ với video metrics và breakdowns
        $this->processCompleteAdInsights($facebookAd, $result);
    }

    /**
     * Xử lý Post data và lưu vào bảng facebook_posts
     */
    private function processPostData(array $postData, FacebookAd $facebookAd, array &$result): void
    {
        try {
            // Lưu Page trước nếu chưa có
            $pageId = $this->extractPageId($facebookAd, $postData);
            if ($pageId) {
                $page = FacebookPage::firstOrCreate(
                    ['id' => $pageId],
                    [
                        'name' => $postData['from']['name'] ?? null,
                        'category' => $postData['from']['category'] ?? null,
                        'verification_status' => $postData['from']['verification_status'] ?? null,
                    ]
                );
                $result['pages']++;
            }

            // Lưu Post
            $post = FacebookPost::updateOrCreate(
                ['id' => $postData['id']],
                [
                    'page_id' => $pageId,
                    'message' => $postData['message'] ?? null,
                    'type' => $postData['type'] ?? null,
                    'status_type' => $postData['status_type'] ?? null,
                    'attachments' => isset($postData['attachments']) ? json_encode($postData['attachments']) : null,
                    'permalink_url' => $postData['permalink_url'] ?? null,
                    'created_time' => isset($postData['created_time']) ? Carbon::parse($postData['created_time']) : null,
                    'updated_time' => isset($postData['updated_time']) ? Carbon::parse($postData['updated_time']) : null,
                ]
            );
            $result['posts']++;

            // Cập nhật foreign key trong FacebookAd
            $facebookAd->update(['post_id' => $post->id]);

                } catch (\Exception $e) {
            Log::error("Lỗi khi process post data", [
                'post_id' => $postData['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
            throw $e;
        }
    }

    /**
     * Xử lý Post Insights và lưu vào bảng facebook_post_insights
     */
    private function processPostInsights(array $postInsights, FacebookAd $facebookAd, array &$result): void
    {
        try {
            if (!isset($postInsights['data']) || empty($postInsights['data'])) {
                return;
            }

            foreach ($postInsights['data'] as $insight) {
                FacebookPostInsight::updateOrCreate(
                    [
                        'post_id' => $facebookAd->post_id,
                        'date' => $insight['date'] ?? now()->toDateString(),
                    ],
                    [
                        'impressions' => (int) ($insight['impressions'] ?? 0),
                        'reach' => (int) ($insight['reach'] ?? 0),
                        'clicks' => (int) ($insight['clicks'] ?? 0),
                        'unique_clicks' => (int) ($insight['unique_clicks'] ?? 0),
                        'likes' => (int) ($insight['likes'] ?? 0),
                        'shares' => (int) ($insight['shares'] ?? 0),
                        'comments' => (int) ($insight['comments'] ?? 0),
                        'reactions' => (int) ($insight['reactions'] ?? 0),
                        'saves' => (int) ($insight['saves'] ?? 0),
                        'hides' => (int) ($insight['hides'] ?? 0),
                        'hide_all_clicks' => (int) ($insight['hide_all_clicks'] ?? 0),
                        'unlikes' => (int) ($insight['unlikes'] ?? 0),
                        'negative_feedback' => (int) ($insight['negative_feedback'] ?? 0),
                        'video_views' => (int) ($insight['post_video_views'] ?? 0),
                        'video_view_time' => (int) ($insight['post_video_views_unique'] ?? 0), // Sử dụng unique views thay vì view_time
                        'video_avg_time_watched' => (float) ($insight['post_video_avg_time_watched'] ?? 0),
                        'video_plays' => (int) ($insight['post_video_views'] ?? 0), // Sử dụng total views
                        'video_plays_at_25' => (int) ($insight['post_video_complete_views_30s'] ?? 0), // Sử dụng complete views 30s
                        'video_plays_at_50' => (int) ($insight['post_video_views_10s'] ?? 0), // Sử dụng views 10s
                        'video_plays_at_75' => (int) ($insight['post_video_views_paid'] ?? 0), // Sử dụng paid views
                        'video_plays_at_100' => (int) ($insight['post_video_views_organic'] ?? 0), // Sử dụng organic views
                        'video_p25_watched_actions' => (int) ($insight['post_video_views_10s'] ?? 0), // Sử dụng views 10s
                        'video_p50_watched_actions' => (int) ($insight['post_video_complete_views_30s'] ?? 0), // Sử dụng complete views
                        'video_p75_watched_actions' => (int) ($insight['post_video_views_paid'] ?? 0), // Sử dụng paid views
                        'video_p95_watched_actions' => (int) ($insight['post_video_views_organic'] ?? 0), // Sử dụng organic views
                        'video_p100_watched_actions' => (int) ($insight['post_video_views_unique'] ?? 0), // Sử dụng unique views
                        'thruplays' => (int) ($insight['post_video_complete_views_30s'] ?? 0), // Sử dụng complete views 30s
                        'engagement_rate' => (float) ($insight['engagement_rate'] ?? 0),
                        'ctr' => (float) ($insight['ctr'] ?? 0),
                        'cpm' => (float) ($insight['cpm'] ?? 0),
                        'cpc' => (float) ($insight['cpc'] ?? 0),
                        'spend' => (float) ($insight['spend'] ?? 0),
                        'frequency' => (float) ($insight['frequency'] ?? 0),
                        'actions' => isset($insight['actions']) ? json_encode($insight['actions']) : null,
                        'action_values' => isset($insight['action_values']) ? json_encode($insight['action_values']) : null,
                        'cost_per_action_type' => isset($insight['cost_per_action_type']) ? json_encode($insight['cost_per_action_type']) : null,
                        'cost_per_unique_action_type' => isset($insight['cost_per_unique_action_type']) ? json_encode($insight['cost_per_unique_action_type']) : null,
                        'breakdowns' => isset($insight['breakdowns']) ? json_encode($insight['breakdowns']) : null,
                    ]
                );
                $result['post_insights']++;
            }

        } catch (\Exception $e) {
            Log::error("Lỗi khi process post insights", [
                'post_id' => $facebookAd->post_id ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Xử lý Ad Insights đầy đủ với video metrics và breakdowns theo Facebook Marketing API v23.0
     */
    private function processCompleteAdInsights(FacebookAd $facebookAd, array &$result): void
    {
        try {
            // Lấy insights đầy đủ với tất cả breakdowns
            $completeInsights = $this->api->getCompleteAdInsights($facebookAd->id);
            
            // Xử lý insights cơ bản trước để có ad_insight_id
            if (isset($completeInsights['basic']['data']) && !empty($completeInsights['basic']['data'])) {
                $this->processBasicAdInsights($completeInsights['basic'], $facebookAd, $result);
            }
            
            // Xử lý breakdowns sau khi đã có ad_insight_id
            foreach ($completeInsights as $breakdownType => $insightsData) {
                if ($breakdownType === 'basic') continue;
                
                if (isset($insightsData['data']) && !empty($insightsData['data'])) {
                    $this->processAdInsightsBreakdowns($insightsData, $facebookAd, $breakdownType, $result);
                }
            }
            
            // Reset ad_insight_id sau khi xử lý xong
            $this->lastProcessedAdInsightId = null;

        } catch (\Exception $e) {
            Log::error("Lỗi khi xử lý complete ad insights", [
                'ad_id' => $facebookAd->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Xử lý Ad Insights cơ bản và lưu vào bảng facebook_ad_insights
     */
    private function processBasicAdInsights(array $adInsights, FacebookAd $facebookAd, array &$result): void
    {
        try {
            if (!isset($adInsights['data']) || empty($adInsights['data'])) {
                return;
            }

            foreach ($adInsights['data'] as $insight) {
                // Extract video metrics từ actions và video fields
                $videoMetrics = $this->extractCompleteVideoMetrics($insight);
                
                // Xác định date từ insight
                $date = $insight['date_start'] ?? $insight['date_stop'] ?? now()->toDateString();
                
                $adInsight = FacebookAdInsight::updateOrCreate(
                    [
                        'ad_id' => $facebookAd->id,
                        'date' => $date,
                    ],
                    [
                        // Basic metrics
                        'spend' => (float) ($insight['spend'] ?? 0),
                        'reach' => (int) ($insight['reach'] ?? 0),
                        'impressions' => (int) ($insight['impressions'] ?? 0),
                        'clicks' => (int) ($insight['clicks'] ?? 0),
                        'unique_clicks' => (int) ($insight['unique_clicks'] ?? 0),
                        'ctr' => (float) ($insight['ctr'] ?? 0),
                        'unique_ctr' => (float) ($insight['unique_ctr'] ?? 0),
                        'cpc' => (float) ($insight['cpc'] ?? 0),
                        'cpm' => (float) ($insight['cpm'] ?? 0),
                        'frequency' => (float) ($insight['frequency'] ?? 0),
                        
                        // Conversion metrics
                        'conversions' => (int) ($insight['conversions'] ?? 0),
                        'conversion_values' => (float) ($insight['conversion_values'] ?? 0),
                        'cost_per_conversion' => (float) ($insight['cost_per_conversion'] ?? 0),
                        'purchase_roas' => (float) ($insight['purchase_roas'] ?? 0),
                        
                        // Click metrics
                        'outbound_clicks' => (int) ($insight['outbound_clicks'] ?? 0),
                        'unique_outbound_clicks' => (int) ($insight['unique_outbound_clicks'] ?? 0),
                        'inline_link_clicks' => (int) ($insight['inline_link_clicks'] ?? 0),
                        'unique_inline_link_clicks' => (int) ($insight['unique_inline_link_clicks'] ?? 0),
                        
                        // JSON fields - Laravel tự động handle JSON casting
                        'actions' => $insight['actions'] ?? null,
                        'action_values' => $insight['action_values'] ?? null,
                        'cost_per_action_type' => $insight['cost_per_action_type'] ?? null,
                        'cost_per_unique_action_type' => $insight['cost_per_unique_action_type'] ?? null,
                        
                        // Video metrics từ actions và video fields
                        'video_views' => $videoMetrics['video_views'],
                        'video_plays' => $videoMetrics['video_plays'],
                        'video_avg_time_watched' => $videoMetrics['video_avg_time_watched'],
                        'video_p25_watched_actions' => $videoMetrics['video_p25_watched_actions'],
                        'video_p50_watched_actions' => $videoMetrics['video_p50_watched_actions'],
                        'video_p75_watched_actions' => $videoMetrics['video_p75_watched_actions'],
                        'video_p95_watched_actions' => $videoMetrics['video_p95_watched_actions'],
                        'video_p100_watched_actions' => $videoMetrics['video_p100_watched_actions'],
                        'thruplays' => $videoMetrics['thruplays'],
                        'video_30_sec_watched' => $videoMetrics['video_30_sec_watched'],
                    ]
                );
                $result['ad_insights']++;
                
                // Lưu ad_insight_id để sử dụng cho breakdowns
                $this->lastProcessedAdInsightId = $adInsight->id;
            }

        } catch (\Exception $e) {
            Log::error("Lỗi khi process basic ad insights", [
                'ad_id' => $facebookAd->id ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Extract video metrics đầy đủ từ insight data
     */
    private function extractCompleteVideoMetrics(array $insight): array
    {
        $videoMetrics = [
            'video_views' => 0,
            'video_plays' => 0,
            'video_avg_time_watched' => 0.0,
            'video_p25_watched_actions' => 0,
            'video_p50_watched_actions' => 0,
            'video_p75_watched_actions' => 0,
            'video_p95_watched_actions' => 0,
            'video_p100_watched_actions' => 0,
            'thruplays' => 0,
            'video_30_sec_watched' => 0,
        ];
        
        // Extract từ video fields trực tiếp
        if (isset($insight['video_30_sec_watched_actions'])) {
            foreach ($insight['video_30_sec_watched_actions'] as $action) {
                if ($action['action_type'] === 'video_view') {
                    $videoMetrics['video_30_sec_watched'] = (int) $action['value'];
                }
            }
        }
        
        // Extract từ video_30_sec_watched trực tiếp nếu có
        if (isset($insight['video_30_sec_watched'])) {
            $videoMetrics['video_30_sec_watched'] = (int) $insight['video_30_sec_watched'];
        }
        
        if (isset($insight['video_avg_time_watched_actions'])) {
            foreach ($insight['video_avg_time_watched_actions'] as $action) {
                if ($action['action_type'] === 'video_view') {
                    $videoMetrics['video_avg_time_watched'] = (float) $action['value'];
                }
            }
        }
        
        // Extract từ video percentage watched actions
        $percentageFields = [
            'video_p25_watched_actions' => 'video_p25_watched_actions',
            'video_p50_watched_actions' => 'video_p50_watched_actions', 
            'video_p75_watched_actions' => 'video_p75_watched_actions',
            'video_p95_watched_actions' => 'video_p95_watched_actions',
            'video_p100_watched_actions' => 'video_p100_watched_actions'
        ];
        
        foreach ($percentageFields as $field => $metricKey) {
            if (isset($insight[$field])) {
                foreach ($insight[$field] as $action) {
                    if ($action['action_type'] === 'video_view') {
                        $videoMetrics[$metricKey] = (int) $action['value'];
                    }
                }
            }
        }
        
        // Extract từ actions array (fallback)
        if (isset($insight['actions'])) {
            foreach ($insight['actions'] as $action) {
                switch ($action['action_type']) {
                    case 'video_view':
                        $videoMetrics['video_views'] = (int) $action['value'];
                        break;
                    case 'video_play':
                        $videoMetrics['video_plays'] = (int) $action['value'];
                        break;
                    case 'thruplay':
                        $videoMetrics['thruplays'] = (int) $action['value'];
                        break;
                }
            }
        }
        
        return $videoMetrics;
    }
    
    /**
     * Xử lý Ad Insights breakdowns và lưu vào bảng facebook_breakdowns
     */
    private function processAdInsightsBreakdowns(array $insightsData, FacebookAd $facebookAd, string $breakdownType, array &$result): void
    {
        try {
            if (!isset($insightsData['data']) || empty($insightsData['data'])) {
                return;
            }

            foreach ($insightsData['data'] as $insight) {
                $videoMetrics = $this->extractCompleteVideoMetrics($insight);
                $date = $insight['date_start'] ?? $insight['date_stop'] ?? now()->toDateString();
                
                // Tạo breakdown data
                $breakdownValues = [];
                $dimensionFields = ['age', 'gender', 'country', 'region', 'publisher_platform', 'platform_position', 'device_platform', 'impression_device'];
                
                foreach ($dimensionFields as $field) {
                    if (isset($insight[$field])) {
                        $breakdownValues[$field] = $insight[$field];
                    }
                }
                
                // Tạo breakdown cho từng giá trị breakdown
                foreach ($breakdownValues as $breakdownField => $breakdownValue) {
                    // Tạo breakdown_type dạng "age:25-34" hoặc "gender:male"
                    $breakdownTypeValue = $breakdownField . ':' . $breakdownValue;
                    $metrics = [
                        // Basic metrics
                        'spend' => (float) ($insight['spend'] ?? 0),
                        'reach' => (int) ($insight['reach'] ?? 0),
                        'impressions' => (int) ($insight['impressions'] ?? 0),
                        'clicks' => (int) ($insight['clicks'] ?? 0),
                        'unique_clicks' => (int) ($insight['unique_clicks'] ?? 0),
                        'ctr' => (float) ($insight['ctr'] ?? 0),
                        'cpc' => (float) ($insight['cpc'] ?? 0),
                        'cpm' => (float) ($insight['cpm'] ?? 0),
                        'frequency' => (float) ($insight['frequency'] ?? 0),
                        
                        // Video metrics
                        'video_views' => $videoMetrics['video_views'],
                        'video_plays' => $videoMetrics['video_plays'],
                        'video_avg_time_watched' => $videoMetrics['video_avg_time_watched'],
                        'video_p25_watched_actions' => $videoMetrics['video_p25_watched_actions'],
                        'video_p50_watched_actions' => $videoMetrics['video_p50_watched_actions'],
                        'video_p75_watched_actions' => $videoMetrics['video_p75_watched_actions'],
                        'video_p95_watched_actions' => $videoMetrics['video_p95_watched_actions'],
                        'video_p100_watched_actions' => $videoMetrics['video_p100_watched_actions'],
                        'thruplays' => $videoMetrics['thruplays'],
                        'video_30_sec_watched' => $videoMetrics['video_30_sec_watched'],
                        
                        // JSON fields
                        'actions' => isset($insight['actions']) ? $insight['actions'] : null,
                        'action_values' => isset($insight['action_values']) ? $insight['action_values'] : null,
                    ];
                    
                    // Sử dụng ad_insight_id đã lưu từ processBasicAdInsights
                    if ($this->lastProcessedAdInsightId) {
                        \App\Models\FacebookBreakdown::create([
                            'ad_insight_id' => $this->lastProcessedAdInsightId,
                            'breakdown_type' => $breakdownType,
                            'breakdown_value' => $breakdownTypeValue,
                            'metrics' => $metrics
                        ]);
                        $result['breakdowns']++;
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error("Lỗi khi xử lý ad insights breakdowns", [
                'ad_id' => $facebookAd->id,
                'breakdown_type' => $breakdownType,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Extract breakdown data từ API response
     */
    private function extractBreakdownsFromData(array $data): array
    {
        $breakdowns = [];
        
        foreach ($data as $insight) {
            if (isset($insight['age'])) {
                $breakdowns[] = [
                    'dimension' => 'age',
                    'value' => $insight['age'],
                    'impressions' => (int) ($insight['impressions'] ?? 0),
                    'reach' => (int) ($insight['reach'] ?? 0),
                    'clicks' => (int) ($insight['clicks'] ?? 0),
                    'spend' => (float) ($insight['spend'] ?? 0),
                ];
            }
            
            if (isset($insight['gender'])) {
                $breakdowns[] = [
                    'dimension' => 'gender',
                    'value' => $insight['gender'],
                    'impressions' => (int) ($insight['impressions'] ?? 0),
                    'reach' => (int) ($insight['reach'] ?? 0),
                    'clicks' => (int) ($insight['clicks'] ?? 0),
                    'spend' => (float) ($insight['spend'] ?? 0),
                ];
            }
            
            if (isset($insight['region'])) {
                $breakdowns[] = [
                    'dimension' => 'region',
                    'value' => $insight['region'],
                    'impressions' => (int) ($insight['impressions'] ?? 0),
                    'reach' => (int) ($insight['reach'] ?? 0),
                    'clicks' => (int) ($insight['clicks'] ?? 0),
                    'spend' => (float) ($insight['spend'] ?? 0),
                ];
            }
            
            if (isset($insight['platform_position'])) {
                $breakdowns[] = [
                    'dimension' => 'platform_position',
                    'value' => $insight['platform_position'],
                    'impressions' => (int) ($insight['impressions'] ?? 0),
                    'reach' => (int) ($insight['reach'] ?? 0),
                    'clicks' => (int) ($insight['clicks'] ?? 0),
                    'spend' => (float) ($insight['spend'] ?? 0),
                ];
            }
            
            if (isset($insight['action_type'])) {
                $breakdowns[] = [
                    'dimension' => 'action_type',
                    'value' => $insight['action_type'],
                    'impressions' => (int) ($insight['impressions'] ?? 0),
                    'reach' => (int) ($insight['reach'] ?? 0),
                    'clicks' => (int) ($insight['clicks'] ?? 0),
                    'spend' => (float) ($insight['spend'] ?? 0),
                ];
            }
        }
        
        return $breakdowns;
    }

    /**
     * Extract video metrics từ actions array của Ad Insights
     */
    private function extractVideoMetricsFromActions(array $actions): array
    {
        $videoMetrics = [
            'video_views' => 0,
            'video_view_time' => 0,
            'video_avg_time_watched' => 0,
            'video_plays' => 0,
            'video_plays_at_25' => 0,
            'video_plays_at_50' => 0,
            'video_plays_at_75' => 0,
            'video_plays_at_100' => 0,
            'video_p25_watched_actions' => 0,
            'video_p50_watched_actions' => 0,
            'video_p75_watched_actions' => 0,
            'video_p95_watched_actions' => 0,
            'video_p100_watched_actions' => 0,
            'thruplays' => 0,
        ];

        foreach ($actions as $action) {
            $actionType = $action['action_type'] ?? '';
            $value = (int) ($action['value'] ?? 0);

            switch ($actionType) {
                case 'video_view':
                    $videoMetrics['video_views'] = $value;
                    $videoMetrics['video_plays'] = $value; // Sử dụng video_view làm video_plays
                    break;
                case 'video_play':
                    $videoMetrics['video_plays'] = $value;
                    break;
                case 'video_p25_watched_actions':
                    $videoMetrics['video_p25_watched_actions'] = $value;
                    $videoMetrics['video_plays_at_25'] = $value;
                    break;
                case 'video_p50_watched_actions':
                    $videoMetrics['video_p50_watched_actions'] = $value;
                    $videoMetrics['video_plays_at_50'] = $value;
                    break;
                case 'video_p75_watched_actions':
                    $videoMetrics['video_p75_watched_actions'] = $value;
                    $videoMetrics['video_plays_at_75'] = $value;
                    break;
                case 'video_p95_watched_actions':
                    $videoMetrics['video_p95_watched_actions'] = $value;
                    break;
                case 'video_p100_watched_actions':
                    $videoMetrics['video_p100_watched_actions'] = $value;
                    $videoMetrics['video_plays_at_100'] = $value;
                    break;
                case 'video_thruplay_watched_actions':
                case 'thruplay':
                    $videoMetrics['thruplays'] = $value;
                    break;
                case 'video_avg_time_watched_actions':
                    $videoMetrics['video_avg_time_watched'] = (float) $value;
                    break;
                case 'video_view_time':
                    $videoMetrics['video_view_time'] = $value;
                    break;
            }
        }

        return $videoMetrics;
    }

    /**
     * Xác định loại Ad
     */
    private function determineAdType(array $ad): string
    {
        $creative = $ad['creative'] ?? [];
        
        // Kiểm tra creative có chứa post_id không
        if (isset($creative['object_story_id']) || 
            isset($creative['effective_object_story_id']) ||
            isset($creative['object_story_spec'])) {
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
            // Tách post_id từ story_id (format: pageId_postId)
            $parts = explode('_', $storyId);
            if (count($parts) >= 2) {
                $postId = $parts[1]; // Lấy phần thứ 2 (post ID)
                return $this->getPostDetails($postId);
            }
        }
        
        // Cách 2: Từ effective_object_story_id
        if (isset($creative['effective_object_story_id'])) {
            $storyId = $creative['effective_object_story_id'];
            // Tách post_id từ story_id (format: pageId_postId)
            $parts = explode('_', $storyId);
            if (count($parts) >= 2) {
                $postId = $parts[1]; // Lấy phần thứ 2 (post ID)
                return $this->getPostDetails($postId);
            }
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
     * Trích xuất Post ID từ Ad
     */
    private function extractPostId(FacebookAd $facebookAd): ?string
    {
        try {
                    if ($facebookAd->creative) {
            $creative = $facebookAd->creative->creative_data;
            
            // Ưu tiên từ object_story_id (format: pageId_postId)
            if (isset($creative['object_story_id'])) {
                    $storyId = $creative['object_story_id'];
                if (is_string($storyId)) {
                    $parts = explode('_', $storyId);
                        return $parts[1] ?? null; // Lấy phần thứ 2 (post ID)
                }
            }
            
            // Từ effective_object_story_id (format: pageId_postId)
                if (isset($creative['effective_object_story_id'])) {
                    $storyId = $creative['effective_object_story_id'];
                if (is_string($storyId)) {
                    $parts = explode('_', $storyId);
                        return $parts[1] ?? null; // Lấy phần thứ 2 (post ID)
                    }
                }
            }
            
            return null;
            
            } catch (\Exception $e) {
            Log::error("Lỗi khi extract post_id", [
                'ad_id' => $facebookAd->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Trích xuất Page ID từ Ad hoặc Post
     */
    private function extractPageId(FacebookAd $facebookAd, ?array $postData): ?string
    {
        try {
            // Ưu tiên từ object_story_id (format: pageId_postId)
            if ($facebookAd->creative) {
                $creative = $facebookAd->creative->creative_data;
                if (isset($creative['object_story_id'])) {
                    $storyId = $creative['object_story_id'];
                if (is_string($storyId)) {
                    $parts = explode('_', $storyId);
                        return $parts[0] ?? null;
                }
            }
            
            // Từ effective_object_story_id (format: pageId_postId)
                if (isset($creative['effective_object_story_id'])) {
                    $storyId = $creative['effective_object_story_id'];
                if (is_string($storyId)) {
                    $parts = explode('_', $storyId);
                        return $parts[0] ?? null;
                    }
                }
            }
            
            // Từ post data
            if ($postData && isset($postData['from']['id'])) {
                return $postData['from']['id'];
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error("Lỗi khi extract page_id", [
                'ad_id' => $facebookAd->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
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
                    'posts' => $result['posts'],
                    'pages' => $result['pages'],
                    'post_insights' => $result['post_insights'],
                    'ad_insights' => $result['ad_insights'],
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
     * Đồng bộ dữ liệu theo khoảng thời gian tùy chỉnh
     */
    public function syncFacebookDataInRange(?callable $onProgress = null, string $since, string $until): array
    {
        return $this->syncFacebookData($onProgress, $since, $until);
    }

    /**
     * Xử lý Post Insights với breakdown data
     */
    private function processPostInsightsWithBreakdown(array $postInsightsWithBreakdown, FacebookAd $facebookAd, array &$result): void
    {
        try {
            if (!isset($postInsightsWithBreakdown['data']) || empty($postInsightsWithBreakdown['data'])) {
                return;
            }

            foreach ($postInsightsWithBreakdown['data'] as $insight) {
                // Lưu breakdown data vào trường breakdowns
                $breakdowns = [];
                if (isset($insight['breakdowns'])) {
                    foreach ($insight['breakdowns'] as $breakdown) {
                        $breakdowns[] = [
                            'dimension' => $breakdown['dimension'] ?? '',
                            'value' => $breakdown['value'] ?? '',
                            'impressions' => (int) ($breakdown['impressions'] ?? 0),
                            'reach' => (int) ($breakdown['reach'] ?? 0),
                            'clicks' => (int) ($breakdown['clicks'] ?? 0),
                            'spend' => (float) ($breakdown['spend'] ?? 0),
                        ];
                    }
                }

                FacebookPostInsight::updateOrCreate(
                    [
                        'post_id' => $facebookAd->post_id,
                        'date' => $insight['date'] ?? now()->toDateString(),
                    ],
                    [
                        'breakdowns' => json_encode($breakdowns),
                    ]
                );
                $result['post_insights']++;
            }

        } catch (\Exception $e) {
            Log::error("Lỗi khi process post insights với breakdown", [
                'post_id' => $facebookAd->post_id ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Xử lý Ad Insights với breakdown data
     */
    private function processAdInsightsWithBreakdown(array $adInsightsWithBreakdown, FacebookAd $facebookAd, array &$result): void
    {
        try {
            if (!isset($adInsightsWithBreakdown['data']) || empty($adInsightsWithBreakdown['data'])) {
                return;
            }

            foreach ($adInsightsWithBreakdown['data'] as $insight) {
                // Lưu breakdown data vào trường breakdowns
                $breakdowns = [];
                if (isset($insight['breakdowns'])) {
                    foreach ($insight['breakdowns'] as $breakdown) {
                        $breakdowns[] = [
                            'dimension' => $breakdown['dimension'] ?? '',
                            'value' => $breakdown['value'] ?? '',
                            'impressions' => (int) ($breakdown['impressions'] ?? 0),
                            'reach' => (int) ($breakdown['reach'] ?? 0),
                            'clicks' => (int) ($breakdown['clicks'] ?? 0),
                            'spend' => (float) ($breakdown['spend'] ?? 0),
                        ];
                    }
                }

                FacebookAdInsight::updateOrCreate(
                    [
                        'ad_id' => $facebookAd->id,
                        'date' => $insight['date'] ?? now()->toDateString(),
                    ],
                    [
                        'breakdowns' => json_encode($breakdowns),
                    ]
                );
                $result['ad_insights']++;
            }

        } catch (\Exception $e) {
            Log::error("Lỗi khi process ad insights với breakdown", [
                'ad_id' => $facebookAd->id ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Cập nhật logic sync để lưu data đúng vào từng bảng
     */
    public function syncWithProperDataStructure(): array
    {
        $result = [
            'businesses' => 0,
            'accounts' => 0,
            'campaigns' => 0,
            'adsets' => 0,
            'ads' => 0,
            'posts' => 0,
            'pages' => 0,
            'post_insights' => 0,
            'ad_insights' => 0,
            'errors' => [],
            'start_time' => now()->toDateTimeString(),
            'time_range' => [
                'since' => date('Y-m-d', strtotime('-7 days')),
                'until' => date('Y-m-d')
            ]
        ];

        try {
            // 1. Lấy Business Managers
            $businesses = $this->api->getBusinessManagers();
            if ($businesses && !isset($businesses['error'])) {
                foreach ($businesses['data'] ?? [] as $business) {
                    $this->processBusiness($business, $result);
                }
            }

            Log::info("Sync completed", $result);
            return $result;

        } catch (\Exception $e) {
            Log::error("Lỗi trong sync", ['error' => $e->getMessage()]);
            $result['errors'][] = $e->getMessage();
            return $result;
        }
    }

    /**
     * Xử lý Business và lưu data đúng cấu trúc
     */
    private function processBusiness(array $business, array &$result): void
    {
        try {
            // Lưu Business
            $facebookBusiness = FacebookBusiness::updateOrCreate(
                ['id' => $business['id']],
                [
                    'name' => $business['name'] ?? null,
                    'verification_status' => $business['verification_status'] ?? null,
                    'created_time' => isset($business['created_time']) ? Carbon::parse($business['created_time']) : null,
                ]
            );
            $result['businesses']++;

            // Lấy Ad Accounts
            $clientAccounts = $this->api->getClientAdAccounts($business['id']);
            $ownedAccounts = $this->api->getOwnedAdAccounts($business['id']);

            $allAccounts = array_merge(
                $clientAccounts['data'] ?? [],
                $ownedAccounts['data'] ?? []
            );

            foreach ($allAccounts as $account) {
                $this->processAdAccount($account, $facebookBusiness, $result);
            }

        } catch (\Exception $e) {
            Log::error("Lỗi khi process business", [
                'business_id' => $business['id'],
                'error' => $e->getMessage()
            ]);
            $result['errors'][] = "Business {$business['id']}: " . $e->getMessage();
        }
    }

    /**
     * Xử lý Ad Account và lưu data đúng cấu trúc
     */
    private function processAdAccount(array $account, FacebookBusiness $business, array &$result): void
    {
        try {
            // Lưu Ad Account
            $facebookAccount = FacebookAdAccount::updateOrCreate(
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

            // Lấy Campaigns
            $campaigns = $this->api->getCampaigns($account['id']);
            if ($campaigns && !isset($campaigns['error'])) {
                foreach ($campaigns['data'] ?? [] as $campaign) {
                    $this->processCampaign($campaign, $facebookAccount, $result);
                }
            }

        } catch (\Exception $e) {
            Log::error("Lỗi khi process ad account", [
                'account_id' => $account['id'],
                'error' => $e->getMessage()
            ]);
            $result['errors'][] = "Account {$account['id']}: " . $e->getMessage();
        }
    }

    /**
     * Xử lý Campaign và lưu data đúng cấu trúc
     */
    private function processCampaign(array $campaign, FacebookAdAccount $account, array &$result): void
    {
        try {
            // Lưu Campaign
            $facebookCampaign = FacebookCampaign::updateOrCreate(
                ['id' => $campaign['id']],
                [
                    'name' => $campaign['name'] ?? null,
                    'status' => $campaign['status'] ?? null,
                    'objective' => $campaign['objective'] ?? null,
                    'account_id' => $account->id,
                    'created_time' => isset($campaign['created_time']) ? Carbon::parse($campaign['created_time']) : null,
                    'updated_time' => isset($campaign['updated_time']) ? Carbon::parse($campaign['updated_time']) : null,
                ]
            );
            $result['campaigns']++;

            // Lấy Ad Sets
            $adSets = $this->api->getAdSetsByCampaign($campaign['id']);
            if ($adSets && !isset($adSets['error'])) {
                foreach ($adSets['data'] ?? [] as $adSet) {
                    $this->processAdSet($adSet, $facebookCampaign, $result);
                }
            }

        } catch (\Exception $e) {
            Log::error("Lỗi khi process campaign", [
                'campaign_id' => $campaign['id'],
                'error' => $e->getMessage()
            ]);
            $result['errors'][] = "Campaign {$campaign['id']}: " . $e->getMessage();
        }
    }

    /**
     * Xử lý Ad Set và lưu data đúng cấu trúc
     */
    private function processAdSet(array $adSet, FacebookCampaign $campaign, array &$result): void
    {
        try {
            // Lưu Ad Set
            $facebookAdSet = FacebookAdSet::updateOrCreate(
                ['id' => $adSet['id']],
                [
                    'name' => $adSet['name'] ?? null,
                    'status' => $adSet['status'] ?? null,
                    'campaign_id' => $campaign->id,
                    'created_time' => isset($adSet['created_time']) ? Carbon::parse($adSet['created_time']) : null,
                    'updated_time' => isset($adSet['updated_time']) ? Carbon::parse($adSet['updated_time']) : null,
                ]
            );
            $result['adsets']++;

            // Lấy Ads
            $ads = $this->api->getAdsByAdSet($adSet['id']);
            if ($ads && !isset($ads['error'])) {
                foreach ($ads['data'] ?? [] as $ad) {
                    $this->processAdWithCompleteData($ad, $facebookAdSet, $result);
                }
            }

        } catch (\Exception $e) {
            Log::error("Lỗi khi process ad set", [
                'adset_id' => $adSet['id'],
                'error' => $e->getMessage()
            ]);
            $result['errors'][] = "AdSet {$adSet['id']}: " . $e->getMessage();
        }
    }

    /**
     * Xử lý Ad với đầy đủ data (Post, Insights, Breakdowns)
     */
    private function processAdWithCompleteData(array $ad, FacebookAdSet $adSet, array &$result): void
    {
        try {
            // 1. Lưu Ad
            $facebookAd = FacebookAd::updateOrCreate(
                ['ad_id' => $ad['id']],
                [
                    'name' => $ad['name'] ?? null,
                    'status' => $ad['status'] ?? null,
                    'effective_status' => $ad['effective_status'] ?? null,
                    'adset_id' => $adSet->id,
                    'creative' => $ad['creative'] ?? null,
                    'created_time' => isset($ad['created_time']) ? Carbon::parse($ad['created_time']) : null,
                    'updated_time' => isset($ad['updated_time']) ? Carbon::parse($ad['updated_time']) : null,
                ]
            );
            $result['ads']++;

            // 2. Extract và lưu Post data từ Creative
            $postData = $this->extractAndSavePostFromCreative($ad, $facebookAd);
            if ($postData) {
                $result['posts']++;
                $result['pages']++; // Page cũng được tạo
            }

            // 3. Lấy và lưu Ad Insights với video metrics
            $this->processAdInsightsWithVideoMetrics($ad, $facebookAd, $result);

            // 4. Lấy và lưu Breakdown data
            $this->processAdBreakdowns($ad, $facebookAd, $result);

        } catch (\Exception $e) {
            Log::error("Lỗi khi process ad", [
                'ad_id' => $ad['id'],
                'error' => $e->getMessage()
            ]);
            $result['errors'][] = "Ad {$ad['id']}: " . $e->getMessage();
        }
    }

    /**
     * Extract và lưu Post từ Creative với đầy đủ thông tin
     */
    private function extractAndSavePostFromCreative(array $ad, FacebookAd $facebookAd): ?array
    {
        $creative = $ad['creative'] ?? [];
        
        Log::info("Extract post từ creative", [
            'ad_id' => $ad['id'],
            'creative_keys' => array_keys($creative)
        ]);
        
        // Tìm story_id từ creative
        $storyId = null;
        $pageId = null;
        $postId = null;

        // Kiểm tra object_story_id
        if (isset($creative['object_story_id'])) {
            $storyId = $creative['object_story_id'];
            Log::info("Found object_story_id", ['story_id' => $storyId]);
        } elseif (isset($creative['effective_object_story_id'])) {
            $storyId = $creative['effective_object_story_id'];
            Log::info("Found effective_object_story_id", ['story_id' => $storyId]);
        }

        // Parse story_id để lấy page_id và post_id
        if ($storyId && strpos($storyId, '_') !== false) {
            $parts = explode('_', $storyId);
            if (count($parts) >= 2) {
                $pageId = $parts[0];
                $postId = $parts[1];
                Log::info("Parsed story_id", ['page_id' => $pageId, 'post_id' => $postId]);
            }
        }

        // Nếu không tìm thấy từ story_id, kiểm tra object_story_spec
        if (!$pageId && isset($creative['object_story_spec'])) {
            $spec = $creative['object_story_spec'];
            Log::info("Found object_story_spec", ['spec' => $spec]);
            
            if (isset($spec['page_id'])) {
                $pageId = $spec['page_id'];
                if (isset($spec['video_data']['post_id'])) {
                    $postId = $spec['video_data']['post_id'];
                } elseif (isset($spec['link_data']['post_id'])) {
                    $postId = $spec['link_data']['post_id'];
                }
            }
        }

        if ($pageId && $postId) {
            // Lưu Page
            $page = FacebookPage::firstOrCreate(
                ['id' => $pageId],
                [
                    'name' => 'Page ' . $pageId,
                    'category' => null,
                    'verification_status' => null,
                ]
            );

            // Lưu Post với thông tin từ creative
            $post = FacebookPost::updateOrCreate(
                ['id' => $postId],
                [
                    'page_id' => $pageId,
                    'message' => $creative['title'] ?? $creative['body'] ?? null,
                    'type' => 'post',
                    'status_type' => null,
                    'attachments' => isset($creative['object_story_spec']) ? json_encode($creative['object_story_spec']) : null,
                    'permalink_url' => null,
                    'created_time' => null,
                    'updated_time' => null,
                ]
            );

            // Cập nhật foreign key trong FacebookAd
            $facebookAd->update(['post_id' => $post->id]);

            Log::info("Đã lưu post data từ creative", [
                'ad_id' => $ad['id'],
                'page_id' => $pageId,
                'post_id' => $postId,
                'facebook_post_id' => $post->id,
                'creative_title' => $creative['title'] ?? null,
                'creative_body' => $creative['body'] ?? null
            ]);

            return [
                'page_id' => $pageId,
                'post_id' => $postId,
                'facebook_post_id' => $post->id
            ];
        }

        Log::warning("Không tìm thấy post data trong creative", [
            'ad_id' => $ad['id'],
            'creative' => $creative
        ]);

        return null;
    }

    /**
     * Xử lý Ad Insights với video metrics đầy đủ
     */
    private function processAdInsightsWithVideoMetrics(array $ad, FacebookAd $facebookAd, array &$result): void
    {
        try {
            // Lấy Ad Insights với video metrics đầy đủ
            $adInsights = $this->api->getInsightsForAd($ad['id']);
            
            if ($adInsights && !isset($adInsights['error']) && isset($adInsights['data'])) {
                foreach ($adInsights['data'] as $insight) {
                    // Lưu Ad Insight với đầy đủ video metrics từ API
                    FacebookAdInsight::updateOrCreate(
                        [
                            'ad_id' => $facebookAd->id,
                            'date' => $insight['date_start'] ?? date('Y-m-d')
                        ],
                        [
                            'spend' => (float) ($insight['spend'] ?? 0),
                            'reach' => (int) ($insight['reach'] ?? 0),
                            'impressions' => (int) ($insight['impressions'] ?? 0),
                            'clicks' => (int) ($insight['clicks'] ?? 0),
                            'ctr' => (float) ($insight['ctr'] ?? 0),
                            'cpc' => (float) ($insight['cpc'] ?? 0),
                            'cpm' => (float) ($insight['cpm'] ?? 0),
                            'frequency' => (float) ($insight['frequency'] ?? 0),
                            'unique_clicks' => (int) ($insight['unique_clicks'] ?? 0),
                            'unique_ctr' => (float) ($insight['unique_ctr'] ?? 0),
                            'unique_link_clicks_ctr' => (float) ($insight['unique_link_clicks_ctr'] ?? 0),
                            'unique_impressions' => (int) ($insight['unique_impressions'] ?? 0),
                            'conversions' => (int) ($insight['conversions'] ?? 0),
                            'conversion_values' => (float) ($insight['conversion_values'] ?? 0),
                            'cost_per_conversion' => (float) ($insight['cost_per_conversion'] ?? 0),
                            'purchase_roas' => (float) ($insight['purchase_roas'] ?? 0),
                            'outbound_clicks' => (int) ($insight['outbound_clicks'] ?? 0),
                            'unique_outbound_clicks' => (int) ($insight['unique_outbound_clicks'] ?? 0),
                            'inline_link_clicks' => (int) ($insight['inline_link_clicks'] ?? 0),
                            'unique_inline_link_clicks' => (int) ($insight['unique_inline_link_clicks'] ?? 0),
                            'website_clicks' => (int) ($insight['website_clicks'] ?? 0),
                            'actions' => isset($insight['actions']) ? json_encode($insight['actions']) : null,
                            'action_values' => isset($insight['action_values']) ? json_encode($insight['action_values']) : null,
                            
                            // Video metrics trực tiếp từ API
                            'video_plays' => (int) ($insight['video_plays'] ?? 0),
                            'video_plays_at_25_percent' => (int) ($insight['video_plays_at_25_percent'] ?? 0),
                            'video_plays_at_50_percent' => (int) ($insight['video_plays_at_50_percent'] ?? 0),
                            'video_plays_at_75_percent' => (int) ($insight['video_plays_at_75_percent'] ?? 0),
                            'video_plays_at_100_percent' => (int) ($insight['video_plays_at_100_percent'] ?? 0),
                            'video_avg_time_watched_actions' => (int) ($insight['video_avg_time_watched_actions'] ?? 0),
                            'video_p25_watched_actions' => (int) ($insight['video_p25_watched_actions'] ?? 0),
                            'video_p50_watched_actions' => (int) ($insight['video_p50_watched_actions'] ?? 0),
                            'video_p75_watched_actions' => (int) ($insight['video_p75_watched_actions'] ?? 0),
                            'video_p95_watched_actions' => (int) ($insight['video_p95_watched_actions'] ?? 0),
                            'video_p100_watched_actions' => (int) ($insight['video_p100_watched_actions'] ?? 0),
                            'thruplays' => (int) ($insight['thruplays'] ?? 0),
                            'video_avg_time_watched' => (float) ($insight['video_avg_time_watched'] ?? 0),
                            'video_view_time' => (int) ($insight['video_view_time'] ?? 0),
                            
                            // Post video metrics
                            'post_video_views' => (int) ($insight['post_video_views'] ?? 0),
                            'post_video_views_unique' => (int) ($insight['post_video_views_unique'] ?? 0),
                            'post_video_avg_time_watched' => (float) ($insight['post_video_avg_time_watched'] ?? 0),
                            'post_video_complete_views_30s' => (int) ($insight['post_video_complete_views_30s'] ?? 0),
                            'post_video_views_10s' => (int) ($insight['post_video_views_10s'] ?? 0),
                            'post_video_retention_graph' => isset($insight['post_video_retention_graph']) ? json_encode($insight['post_video_retention_graph']) : null,
                            'post_video_views_paid' => (int) ($insight['post_video_views_paid'] ?? 0),
                            'post_video_views_organic' => (int) ($insight['post_video_views_organic'] ?? 0),
                        ]
                    );
                }
                $result['ad_insights']++;
                
                Log::info("Đã lưu ad insights với video metrics", [
                    'ad_id' => $ad['id'],
                    'insights_count' => count($adInsights['data'])
                ]);
            } else {
                Log::warning("Không lấy được ad insights", [
                    'ad_id' => $ad['id'],
                    'error' => $adInsights['error'] ?? 'Unknown error'
                ]);
            }

        } catch (\Exception $e) {
            Log::error("Lỗi khi process ad insights", [
                'ad_id' => $ad['id'],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Xử lý Ad Breakdowns và lưu vào bảng riêng
     */
    private function processAdBreakdowns(array $ad, FacebookAd $facebookAd, array &$result): void
    {
        try {
            // Tìm insight record
            $existingInsight = FacebookAdInsight::where('ad_id', $facebookAd->id)->first();
            if (!$existingInsight) {
                Log::warning("Không tìm thấy insight record để lưu breakdown", ['ad_id' => $facebookAd->id]);
                return;
            }

            // Age/Gender breakdown
            $ageGenderBreakdown = $this->api->getInsightsWithAgeGenderBreakdown($ad['id']);
            if ($ageGenderBreakdown && !isset($ageGenderBreakdown['error'])) {
                $this->saveBreakdownsToTable($ageGenderBreakdown['data'] ?? [], $existingInsight->id, 'age_gender');
            }

            // Region breakdown
            $regionBreakdown = $this->api->getInsightsWithRegionBreakdown($ad['id']);
            if ($regionBreakdown && !isset($regionBreakdown['error'])) {
                $this->saveBreakdownsToTable($regionBreakdown['data'] ?? [], $existingInsight->id, 'region');
            }

            // Platform position breakdown
            $platformPositionBreakdown = $this->api->getInsightsWithPlatformPositionBreakdown($ad['id']);
            if ($platformPositionBreakdown && !isset($platformPositionBreakdown['error'])) {
                $this->saveBreakdownsToTable($platformPositionBreakdown['data'] ?? [], $existingInsight->id, 'platform_position');
            }

            // Publisher platform breakdown
            $publisherPlatformBreakdown = $this->api->getInsightsWithPublisherPlatformBreakdown($ad['id']);
            if ($publisherPlatformBreakdown && !isset($publisherPlatformBreakdown['error'])) {
                $this->saveBreakdownsToTable($publisherPlatformBreakdown['data'] ?? [], $existingInsight->id, 'publisher_platform');
            }

            // Device platform breakdown
            $devicePlatformBreakdown = $this->api->getInsightsWithDevicePlatformBreakdown($ad['id']);
            if ($devicePlatformBreakdown && !isset($devicePlatformBreakdown['error'])) {
                $this->saveBreakdownsToTable($devicePlatformBreakdown['data'] ?? [], $existingInsight->id, 'device_platform');
            }

            // Country breakdown
            $countryBreakdown = $this->api->getInsightsWithCountryBreakdown($ad['id']);
            if ($countryBreakdown && !isset($countryBreakdown['error'])) {
                $this->saveBreakdownsToTable($countryBreakdown['data'] ?? [], $existingInsight->id, 'country');
            }

            // Impression device breakdown
            $impressionDeviceBreakdown = $this->api->getInsightsWithImpressionDeviceBreakdown($ad['id']);
            if ($impressionDeviceBreakdown && !isset($impressionDeviceBreakdown['error'])) {
                $this->saveBreakdownsToTable($impressionDeviceBreakdown['data'] ?? [], $existingInsight->id, 'impression_device');
            }

            // Action type breakdown
            $actionTypeBreakdown = $this->api->getInsightsWithActionTypeBreakdown($ad['id']);
            if ($actionTypeBreakdown && !isset($actionTypeBreakdown['error'])) {
                $this->saveBreakdownsToTable($actionTypeBreakdown['data'] ?? [], $existingInsight->id, 'action_type');
            }

            // Action device breakdown
            $actionDeviceBreakdown = $this->api->getInsightsWithActionDeviceBreakdown($ad['id']);
            if ($actionDeviceBreakdown && !isset($actionDeviceBreakdown['error'])) {
                $this->saveBreakdownsToTable($actionDeviceBreakdown['data'] ?? [], $existingInsight->id, 'action_device');
            }

            // Action destination breakdown
            $actionDestinationBreakdown = $this->api->getInsightsWithActionDestinationBreakdown($ad['id']);
            if ($actionDestinationBreakdown && !isset($actionDestinationBreakdown['error'])) {
                $this->saveBreakdownsToTable($actionDestinationBreakdown['data'] ?? [], $existingInsight->id, 'action_destination');
            }

            // Action target ID breakdown
            $actionTargetIdBreakdown = $this->api->getInsightsWithActionTargetIdBreakdown($ad['id']);
            if ($actionTargetIdBreakdown && !isset($actionTargetIdBreakdown['error'])) {
                $this->saveBreakdownsToTable($actionTargetIdBreakdown['data'] ?? [], $existingInsight->id, 'action_target_id');
            }

            // Action reaction breakdown
            $actionReactionBreakdown = $this->api->getInsightsWithActionReactionBreakdown($ad['id']);
            if ($actionReactionBreakdown && !isset($actionReactionBreakdown['error'])) {
                $this->saveBreakdownsToTable($actionReactionBreakdown['data'] ?? [], $existingInsight->id, 'action_reaction');
            }

            // Action video sound breakdown
            $actionVideoSoundBreakdown = $this->api->getInsightsWithActionVideoSoundBreakdown($ad['id']);
            if ($actionVideoSoundBreakdown && !isset($actionVideoSoundBreakdown['error'])) {
                $this->saveBreakdownsToTable($actionVideoSoundBreakdown['data'] ?? [], $existingInsight->id, 'action_video_sound');
            }

            // Action video type breakdown
            $actionVideoTypeBreakdown = $this->api->getInsightsWithActionVideoTypeBreakdown($ad['id']);
            if ($actionVideoTypeBreakdown && !isset($actionVideoTypeBreakdown['error'])) {
                $this->saveBreakdownsToTable($actionVideoTypeBreakdown['data'] ?? [], $existingInsight->id, 'action_video_type');
            }

            // Action carousel card ID breakdown
            $actionCarouselCardIdBreakdown = $this->api->getInsightsWithActionCarouselCardIdBreakdown($ad['id']);
            if ($actionCarouselCardIdBreakdown && !isset($actionCarouselCardIdBreakdown['error'])) {
                $this->saveBreakdownsToTable($actionCarouselCardIdBreakdown['data'] ?? [], $existingInsight->id, 'action_carousel_card_id');
            }

            // Action carousel card name breakdown
            $actionCarouselCardNameBreakdown = $this->api->getInsightsWithActionCarouselCardNameBreakdown($ad['id']);
            if ($actionCarouselCardNameBreakdown && !isset($actionCarouselCardNameBreakdown['error'])) {
                $this->saveBreakdownsToTable($actionCarouselCardNameBreakdown['data'] ?? [], $existingInsight->id, 'action_carousel_card_name');
            }

            // Action canvas component name breakdown
            $actionCanvasComponentNameBreakdown = $this->api->getInsightsWithActionCanvasComponentNameBreakdown($ad['id']);
            if ($actionCanvasComponentNameBreakdown && !isset($actionCanvasComponentNameBreakdown['error'])) {
                $this->saveBreakdownsToTable($actionCanvasComponentNameBreakdown['data'] ?? [], $existingInsight->id, 'action_canvas_component_name');
            }

            Log::info("Đã lưu breakdown data", [
                'ad_id' => $ad['id'],
                'insight_id' => $existingInsight->id
            ]);

        } catch (\Exception $e) {
            Log::error("Lỗi khi process ad breakdowns", [
                'ad_id' => $ad['id'],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Lưu breakdown data vào bảng riêng
     */
    private function saveBreakdownsToTable(array $breakdownData, int $insightId, string $breakdownType): void
    {
        foreach ($breakdownData as $data) {
            // Xác định breakdown value dựa trên type
            $breakdownValue = $this->extractBreakdownValue($data, $breakdownType);
            
            if ($breakdownValue) {
                // Lưu vào bảng facebook_breakdowns
                \App\Models\FacebookBreakdown::updateOrCreate(
                    [
                        'ad_insight_id' => $insightId,
                        'breakdown_type' => $breakdownType,
                        'breakdown_value' => $breakdownValue
                    ],
                    [
                        'metrics' => [
                            'impressions' => (int) ($data['impressions'] ?? 0),
                            'reach' => (int) ($data['reach'] ?? 0),
                            'clicks' => (int) ($data['clicks'] ?? 0),
                            'spend' => (float) ($data['spend'] ?? 0),
                            'ctr' => (float) ($data['ctr'] ?? 0),
                            'cpc' => (float) ($data['cpc'] ?? 0),
                            'cpm' => (float) ($data['cpm'] ?? 0),
                            'frequency' => (float) ($data['frequency'] ?? 0),
                            // Video metrics nếu có
                            'video_plays' => (int) ($data['video_plays'] ?? 0),
                            'video_plays_at_25_percent' => (int) ($data['video_plays_at_25_percent'] ?? 0),
                            'video_plays_at_50_percent' => (int) ($data['video_plays_at_50_percent'] ?? 0),
                            'video_plays_at_75_percent' => (int) ($data['video_plays_at_75_percent'] ?? 0),
                            'video_plays_at_100_percent' => (int) ($data['video_plays_at_100_percent'] ?? 0),
                            'video_avg_time_watched_actions' => (int) ($data['video_avg_time_watched_actions'] ?? 0),
                            'video_p25_watched_actions' => (int) ($data['video_p25_watched_actions'] ?? 0),
                            'video_p50_watched_actions' => (int) ($data['video_p50_watched_actions'] ?? 0),
                            'video_p75_watched_actions' => (int) ($data['video_p75_watched_actions'] ?? 0),
                            'video_p100_watched_actions' => (int) ($data['video_p100_watched_actions'] ?? 0),
                            'thruplays' => (int) ($data['thruplays'] ?? 0),
                            'video_avg_time_watched' => (float) ($data['video_avg_time_watched'] ?? 0),
                            'video_view_time' => (int) ($data['video_view_time'] ?? 0),
                        ]
                    ]
                );
            }
        }
    }

    /**
     * Extract breakdown value từ data
     */
    private function extractBreakdownValue(array $data, string $breakdownType): ?string
    {
        switch ($breakdownType) {
            case 'age_gender':
                $age = $data['age'] ?? '';
                $gender = $data['gender'] ?? '';
                return $age && $gender ? "{$age}_{$gender}" : ($age ?: $gender);
            
            case 'region':
                return $data['region'] ?? null;
            
            case 'platform_position':
                return $data['platform_position'] ?? null;
            
            case 'publisher_platform':
                return $data['publisher_platform'] ?? null;
            
            case 'device_platform':
                return $data['device_platform'] ?? null;
            
            case 'country':
                return $data['country'] ?? null;
            
            case 'impression_device':
                return $data['impression_device'] ?? null;
            
            case 'action_type':
                return $data['action_type'] ?? null;
            
            case 'action_device':
                return $data['action_device'] ?? null;
            
            case 'action_destination':
                return $data['action_destination'] ?? null;
            
            case 'action_target_id':
                return $data['action_target_id'] ?? null;
            
            case 'action_reaction':
                return $data['action_reaction'] ?? null;
            
            case 'action_video_sound':
                return $data['action_video_sound'] ?? null;
            
            case 'action_video_type':
                return $data['action_video_type'] ?? null;
            
            case 'action_carousel_card_id':
                return $data['action_carousel_card_id'] ?? null;
            
            case 'action_carousel_card_name':
                return $data['action_carousel_card_name'] ?? null;
            
            case 'action_canvas_component_name':
                return $data['action_canvas_component_name'] ?? null;
            
            default:
                return null;
        }
    }

    /**
     * Lưu Creative data vào bảng facebook_creatives
     */
    private function saveCreativeData(array $creativeData, FacebookAd $facebookAd): void
    {
        try {
            // Extract các thông tin từ creative data
            $creativeFields = [
                'id' => $creativeData['id'] ?? null,
                'ad_id' => $facebookAd->id,
                'creative_data' => $creativeData,
                'link_url' => $creativeData['link_data']['link'] ?? null,
                'link_message' => $creativeData['link_data']['message'] ?? null,
                'link_name' => $creativeData['link_data']['name'] ?? null,
                'image_hash' => $creativeData['image_hash'] ?? null,
                'call_to_action_type' => $creativeData['call_to_action_type'] ?? null,
                'page_welcome_message' => $creativeData['page_welcome_message'] ?? null,
                'created_time' => isset($creativeData['created_time']) ? Carbon::parse($creativeData['created_time']) : null,
                'updated_time' => isset($creativeData['updated_time']) ? Carbon::parse($creativeData['updated_time']) : null,
            ];

            // Lưu hoặc cập nhật creative
            FacebookCreative::updateOrCreate(
                ['id' => $creativeFields['id'] ?? $facebookAd->id],
                $creativeFields
            );

            Log::info("Đã lưu creative data", [
                'ad_id' => $facebookAd->id,
                'creative_id' => $creativeFields['id']
            ]);

        } catch (\Exception $e) {
            Log::error("Lỗi khi lưu creative data", [
                'ad_id' => $facebookAd->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}




