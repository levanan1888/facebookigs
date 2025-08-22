<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookAdsService
{
    private string $accessToken;
    private string $apiVersion;

    public function __construct()
    {
        $this->accessToken = config('services.facebook.ads_token') ?? '';
        $this->apiVersion = config('services.facebook.api_version', 'v23.0');
        
        if (empty($this->accessToken)) {
            throw new \Exception('Facebook ads token không được cấu hình. Vui lòng kiểm tra FACEBOOK_ADS_TOKEN trong .env');
        }
    }

    /**
     * Lấy danh sách Business Managers
     */
    public function getBusinessManagers(): array
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/me/businesses";
        $params = [
            'access_token' => $this->accessToken,
            'fields' => 'id,name,verification_status,created_time'
        ];

        $response = Http::get($url, $params);
        
        if ($response->successful()) {
            return $response->json();
        }

        return ['error' => $response->json()];
    }

    /**
     * Lấy danh sách Ad Accounts được client quản lý
     */
    public function getClientAdAccounts(string $businessId): array
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$businessId}/client_ad_accounts";
        $params = [
            'access_token' => $this->accessToken,
            'fields' => 'id,account_id,name,account_status,created_time,updated_time'
        ];

        $response = Http::get($url, $params);
        
        if ($response->successful()) {
            return $response->json();
        }

        return ['error' => $response->json()];
    }

    /**
     * Lấy danh sách Ad Accounts sở hữu
     */
    public function getOwnedAdAccounts(string $businessId): array
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$businessId}/owned_ad_accounts";
        $params = [
            'access_token' => $this->accessToken,
            'fields' => 'id,account_id,name,account_status,created_time,updated_time'
        ];

        $response = Http::get($url, $params);
        
        if ($response->successful()) {
            return $response->json();
        }

        return ['error' => $response->json()];
    }

    /**
     * Lấy danh sách Campaigns
     */
    public function getCampaigns(string $accountId): array
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$accountId}/campaigns";
        $params = [
            'access_token' => $this->accessToken,
            'fields' => 'id,name,status,objective,start_time,stop_time,effective_status,configured_status,created_time,updated_time'
        ];

        $response = Http::get($url, $params);
        
        if ($response->successful()) {
            return $response->json();
        }

        return ['error' => $response->json()];
    }

    /**
     * Lấy danh sách Ad Sets theo Campaign
     */
    public function getAdSetsByCampaign(string $campaignId): array
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$campaignId}/adsets";
        $params = [
            'access_token' => $this->accessToken,
            'fields' => 'id,name,status,optimization_goal,created_time,updated_time'
        ];

        $response = Http::get($url, $params);
        
        if ($response->successful()) {
            return $response->json();
        }

        return ['error' => $response->json()];
    }

    /**
     * Lấy danh sách Ads theo Ad Set
     */
    public function getAdsByAdSet(string $adSetId): array
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$adSetId}/ads";
        $params = [
            'access_token' => $this->accessToken,
            'fields' => 'id,name,status,effective_status,creative{id,title,body,object_story_spec,object_story_id,effective_object_story_id},created_time,updated_time,object_story_id,effective_object_story_id'
        ];

        $response = Http::get($url, $params);
        
        if ($response->successful()) {
            return $response->json();
        }

        return ['error' => $response->json()];
    }

    /**
     * Lấy chi tiết Post
     */
    public function getPostDetails(string $postId): array
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$postId}";
        $params = [
            'access_token' => $this->accessToken,
            'fields' => 'id,message,type,status_type,attachments,permalink_url,created_time,updated_time'
        ];

        $response = Http::get($url, $params);
        
        if ($response->successful()) {
            return $response->json();
        }

        // Log chi tiết lỗi để debug
        $errorData = $response->json();
        Log::error("Facebook API error khi lấy post details", [
            'post_id' => $postId,
            'url' => $url,
            'status' => $response->status(),
            'response' => $errorData
        ]);

        return ['error' => $errorData];
    }

    /**
     * Lấy Post Insights mở rộng
     */
    public function getPostInsightsExtended(string $postId): array
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$postId}/insights";
        $params = [
            'access_token' => $this->accessToken,
            // Lưu ý: Likes/Comments/Shares không có trong insights; dùng edges riêng để đếm
            'fields' => 'impressions,reach,clicks,unique_clicks,reactions,saves,hides,hide_all_clicks,unlikes,negative_feedback,video_views,video_view_time,video_avg_time_watched,engagement_rate,ctr,cpm,cpc,spend,frequency,actions,action_values,cost_per_action_type,cost_per_unique_action_type,breakdowns',
            'date_preset' => 'last_5y',
            'period' => 'day'
        ];

        $response = Http::get($url, $params);
        
        if ($response->successful()) {
            return $response->json();
        }

        return ['error' => $response->json()];
    }

    /**
     * Lấy số reactions, comments, shares của một post.
     * Cần Page Access Token hoặc quyền phù hợp (pages_read_engagement hoặc Page Public Content Access).
     */
    public function getPostEngagementCounts(string $postId): array
    {
        // Reactions count
        $reactionsUrl = "https://graph.facebook.com/{$this->apiVersion}/{$postId}/reactions";
        $reactionsResp = Http::get($reactionsUrl, [
            'access_token' => $this->accessToken,
            'summary' => 'true',
            'limit' => 0,
        ]);

        // Comments count
        $commentsUrl = "https://graph.facebook.com/{$this->apiVersion}/{$postId}/comments";
        $commentsResp = Http::get($commentsUrl, [
            'access_token' => $this->accessToken,
            'summary' => 'true',
            'filter' => 'toplevel',
            'limit' => 0,
        ]);

        // Shares count (lấy qua field shares.summary)
        $sharesUrl = "https://graph.facebook.com/{$this->apiVersion}/{$postId}";
        $sharesResp = Http::get($sharesUrl, [
            'access_token' => $this->accessToken,
            'fields' => 'shares',
        ]);

        $reactions = $reactionsResp->successful() ? ($reactionsResp->json()['summary']['total_count'] ?? 0) : 0;
        $comments = $commentsResp->successful() ? ($commentsResp->json()['summary']['total_count'] ?? 0) : 0;
        $shares = 0;
        if ($sharesResp->successful()) {
            $sharesData = $sharesResp->json();
            $shares = isset($sharesData['shares']['count']) ? (int) $sharesData['shares']['count'] : 0;
        }

        return [
            'reactions' => (int) $reactions,
            'comments' => (int) $comments,
            'shares' => (int) $shares,
            'raw' => [
                'reactions' => $reactionsResp->json(),
                'comments' => $commentsResp->json(),
                'shares' => $sharesResp->json(),
            ],
        ];
    }

    /**
     * Lấy Insights cho nhiều Ads cùng lúc
     */
    public function getInsightsForAdsBatch(array $adIds, int $concurrency = 5): array
    {
        $results = [];
        $chunks = array_chunk($adIds, $concurrency);
        
        foreach ($chunks as $chunk) {
            $promises = [];
            
            foreach ($chunk as $adId) {
                $url = "https://graph.facebook.com/{$this->apiVersion}/{$adId}/insights";
                $params = [
                    'access_token' => $this->accessToken,
                    'fields' => 'spend,reach,impressions,clicks,ctr,cpc,cpm,frequency,unique_clicks,actions,action_values,purchase_roas',
                    'time_range' => json_encode([
                        'since' => date('Y-m-d', strtotime('-36 months')),
                        'until' => date('Y-m-d')
                    ])
                ];
                
                $promises[$adId] = Http::async()->get($url, $params);
            }
            
            // Wait for all requests to complete
            foreach ($promises as $adId => $promise) {
                $response = $promise->wait();
                
                if ($response->successful()) {
                    $results[$adId] = $response->json();
                } else {
                    $results[$adId] = ['error' => $response->json()];
                }
            }
            
            // Rate limiting - pause between chunks
            if (count($chunks) > 1) {
                usleep(1000000); // 1 second
            }
        }
        
        return $results;
    }

    /**
     * Lấy Insights cho một Ad đơn lẻ
     */
    public function getInsightsForAd(string $adId): array
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$adId}/insights";
        $params = [
            'access_token' => $this->accessToken,
            'fields' => 'spend,reach,impressions,clicks,ctr,cpc,cpm,frequency,unique_clicks,actions,action_values,purchase_roas,ad_name,ad_id',
            'time_range' => json_encode([
                'since' => date('Y-m-d', strtotime('-36 months')),
                'until' => date('Y-m-d')
            ])
        ];

        $response = Http::get($url, $params);
        
        if ($response->successful()) {
            return $response->json();
        }

        return ['error' => $response->json()];
    }
}
