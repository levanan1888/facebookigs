<?php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FacebookAdsService
{
    private string $baseUrl;
    private string $token;
    private int $paceMs = 200; // chờ giữa các request để tránh rate limit

    public function __construct()
    {
        $this->token = config('services.facebook.ads_token');
        $this->baseUrl = 'https://graph.facebook.com/v19.0/';
        // Pace mặc định 25ms giúp hạn chế bị rate-limit khi chạy dài
        $this->paceMs = 25;
    }

    /**
     * Gọi API theo URL đầy đủ (dùng cho paging.next)
     */
    private function getByUrl(string $url): array
    {
        $maxAttempts = 5;
        $attempt = 0;
        $sleepSeconds = 2;

        while (true) {
            $response = Http::timeout(30)->get($url);
            if ($response->ok()) {
                if ($this->paceMs > 0) {
                    usleep($this->paceMs * 1000);
                }
                return $response->json() ?? [];
            }

            $body = $response->json();
            $code = is_array($body) ? ($body['error']['code'] ?? null) : null;
            $isRateLimit = in_array($response->status(), [429], true) || (int) $code === 17;

            if ($isRateLimit && $attempt < ($maxAttempts - 1)) {
                $retryAfter = (int) ($response->header('Retry-After') ?? 0);
                $wait = max($retryAfter, $sleepSeconds * (2 ** $attempt));
                sleep($wait);
                $attempt++;
                continue;
            }

            return [
                'error' => [
                    'status' => $response->status(),
                    'body' => $body ?? $response->body(),
                    'url' => $url,
                    'attempt' => $attempt + 1,
                ],
            ];
        }
    }

    /**
     * Lặp qua tất cả các trang (paging) để lấy đủ dữ liệu.
     * Trả về mảng theo định dạng ['data' => [...]] để tương thích call-site hiện tại.
     */
    private function getAll(string $endpoint, array $params = [], int $limit = 100): array
    {
        $allData = [];
        $params['limit'] = min($limit, 100);

        do {
            $response = $this->get($endpoint, $params);

            if (isset($response['error'])) {
                return $response;
            }

            $allData = array_merge($allData, $response['data'] ?? []);

            // Check for next page
            if (isset($response['paging']['next'])) {
                $endpoint = $response['paging']['next'];
                $params = []; // Reset params for next page
            } else {
                break;
            }

            usleep($this->paceMs * 1000);
        } while (count($allData) < $limit);

        return ['data' => array_slice($allData, 0, $limit)];
    }

    public function isConfigured(): bool
    {
        return filled($this->token);
    }

    private function get(string $path, array $query = []): array
    {
        $query['access_token'] = $this->token;

        $maxAttempts = 5;
        $attempt = 0;
        $sleepSeconds = 2;
        $url = $this->baseUrl . '/' . ltrim($path, '/');

        while (true) {
            $response = Http::timeout(30)->get($url, $query);
            if ($response->ok()) {
                // pace giữa các request
                if ($this->paceMs > 0) {
                    usleep($this->paceMs * 1000);
                }
                return $response->json() ?? [];
            }

            $body = $response->json();
            $code = is_array($body) ? ($body['error']['code'] ?? null) : null;
            $isRateLimit = in_array($response->status(), [429], true) || (int) $code === 17;

            if ($isRateLimit && $attempt < ($maxAttempts - 1)) {
                $retryAfter = (int) ($response->header('Retry-After') ?? 0);
                $wait = max($retryAfter, $sleepSeconds * (2 ** $attempt));
                sleep($wait);
                $attempt++;
                continue;
            }

            return [
                'error' => [
                    'status' => $response->status(),
                    'body' => $body ?? $response->body(),
                    'path' => $path,
                    'attempt' => $attempt + 1,
                ],
            ];
        }
    }

    public function getBusinessManagers(): array
    {
        return $this->getAll('me/businesses', [
            'fields' => 'id,name,verification_status,created_time',
        ]);
    }

    public function getClientAdAccounts(string $bmId): array
    {
        return $this->getAll($bmId . '/client_ad_accounts', [
            'fields' => 'id,account_id,name,account_status',
        ]);
    }

    /**
     * Lấy các ad accounts thuộc sở hữu của Business Manager (owned)
     */
    public function getOwnedAdAccounts(string $bmId): array
    {
        return $this->getAll($bmId . '/owned_ad_accounts', [
            'fields' => 'id,account_id,name,account_status',
        ]);
    }

    public function getCampaigns(string $adAccountId): array
    {
        $id = str_starts_with($adAccountId, 'act_') ? $adAccountId : ('act_' . $adAccountId);
        return $this->getAll($id . '/campaigns', [
            'fields' => 'id,name,status,objective,start_time,stop_time,effective_status,configured_status,updated_time',
        ]);
    }

    public function getAdSetsByCampaign(string $campaignId): array
    {
        return $this->getAll($campaignId . '/adsets', [
            'fields' => 'id,name,status,optimization_goal,campaign_id',
        ]);
    }

    public function getAdsByAdSet(string $adSetId): array
    {
        return $this->getAll($adSetId . '/ads', [
            'fields' => 'id,name,status,effective_status,adset_id,campaign_id,account_id,object_story_id,effective_object_story_id,page_id,created_time,updated_time,creative{id,title,body,object_story_spec{page_id,link_data{link,message,name,image_hash,call_to_action{type},page_welcome_message}},object_story_id,effective_object_story_id,call_to_action{type},image_hash,page_id}',
        ]);
    }

    /**
     * Lấy thông tin chi tiết của một post
     */
    public function getPostDetails(string $postId): array
    {
        return $this->getAll($postId, [
            'fields' => 'id,message,type,status_type,attachments,permalink_url,created_time,updated_time,likes.summary(true),shares,comments.summary(true),reactions.summary(true),from,ad_info',
        ]);
    }

    /**
     * Lấy insights mở rộng của một post (từ 5 năm trước)
     */
    public function getPostInsightsExtended(string $postId): array
    {
        // Lấy 1 bản ghi tổng hợp cho toàn thời gian để giảm số lượng request/paging
        return $this->getAll($postId . '/insights', [
            'date_preset' => 'maximum',
            'time_increment' => 'all_days',
            'fields' => 'impressions,reach,clicks,unique_clicks,likes,shares,comments,reactions,saves,hides,hide_all_clicks,unlikes,negative_feedback,video_views,video_view_time,video_avg_time_watched,video_p25_watched_actions,video_p50_watched_actions,video_p75_watched_actions,video_p95_watched_actions,video_p100_watched_actions,engagement_rate,ctr,cpm,cpc,actions,action_values,cost_per_action_type,cost_per_unique_action_type,breakdowns,spend,frequency',
        ], 1);
    }

    /**
     * Lấy insights mở rộng của một ad (từ 5 năm trước)
     */
    public function getInsightsForAdExtended(string $adId): array
    {
        // Lấy 1 bản ghi tổng hợp để giảm thời gian
        return $this->getAll($adId . '/insights', [
            'date_preset' => 'maximum',
            'time_increment' => 'all_days',
            'fields' => 'spend,reach,impressions,clicks,ctr,cpc,cpm,actions,action_values,purchase_roas,frequency,unique_clicks',
        ], 1);
    }

    /**
     * Lấy insights cho nhiều ads đồng thời bằng Http::pool. Tự điều chỉnh concurrency khi gặp rate-limit.
     */
    public function getInsightsForAdsBatch(array $adIds, int $concurrency = 8): array
    {
        $results = [];
        $adIds = array_values(array_unique(array_filter($adIds)));
        $i = 0;
        while ($i < count($adIds)) {
            $chunk = array_slice($adIds, $i, max(1, $concurrency));

            $responses = Http::pool(function ($pool) use ($chunk) {
                foreach ($chunk as $adId) {
                    $pool->as((string) $adId)
                        ->timeout(30)
                        ->get($this->baseUrl . '/' . ltrim((string) $adId, '/') . '/insights', [
                            'access_token' => $this->token,
                            'date_preset' => 'maximum',
                            'time_increment' => 'all_days',
                            'fields' => 'spend,reach,impressions,clicks,ctr,cpc,cpm,actions,action_values,purchase_roas,frequency,unique_clicks',
                        ]);
                }
            });

            $rateLimited = false;
            foreach ($responses as $key => $resp) {
                if ($resp->ok()) {
                    $results[$key] = $resp->json() ?? [];
                } else {
                    $body = $resp->json();
                    $code = is_array($body) ? ($body['error']['code'] ?? null) : null;
                    if (in_array($resp->status(), [429], true) || in_array((int) $code, [17, 80004], true)) {
                        $rateLimited = true;
                    }
                    $results[$key] = [
                        'error' => [
                            'status' => $resp->status(),
                            'body' => $body ?? $resp->body(),
                            'ad_id' => $key,
                        ],
                    ];
                }
            }

            // Adaptive throttle nếu gặp rate-limit
            if ($rateLimited) {
                $concurrency = max(2, (int) floor($concurrency / 2));
                sleep(10); // backoff
            } else {
                $i += count($chunk);
                if ($this->paceMs > 0) {
                    usleep($this->paceMs * 1000);
                }
            }
        }

        return $results;
    }

    
}

