<?php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FacebookAdsService
{
    private string $baseUrl;
    private string $token;
    private int $paceMs = 200; // chờ giữa các request để tránh rate limit

    public function __construct(?string $token = null, string $baseUrl = 'https://graph.facebook.com/v23.0')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = $token ?? (string) (config('services.facebook_ads.token') ?? '');
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
        return $this->get('me/businesses', [
            'fields' => 'id,name,verification_status,created_time',
        ]);
    }

    public function getClientAdAccounts(string $bmId): array
    {
        return $this->get($bmId . '/client_ad_accounts', [
            'fields' => 'id,account_id,name,account_status',
        ]);
    }

    public function getCampaigns(string $adAccountId): array
    {
        $id = str_starts_with($adAccountId, 'act_') ? $adAccountId : ('act_' . $adAccountId);
        return $this->get($id . '/campaigns', [
            'fields' => 'id,name,status,objective,start_time,stop_time,effective_status,configured_status,updated_time',
        ]);
    }

    public function getAdSetsByCampaign(string $campaignId): array
    {
        return $this->get($campaignId . '/adsets', [
            'fields' => 'id,name,status,optimization_goal,campaign_id',
        ]);
    }

    public function getAdsByAdSet(string $adSetId): array
    {
        return $this->get($adSetId . '/ads', [
            'fields' => 'id,name,status,effective_status,adset_id,campaign_id,account_id,creative{id,title,body,object_story_spec},created_time,updated_time',
        ]);
    }

    public function getInsightsForAccount(string $adAccountId, string $datePreset = 'yesterday'): array
    {
        $id = str_starts_with($adAccountId, 'act_') ? $adAccountId : ('act_' . $adAccountId);
        return $this->get($id . '/insights', [
            'date_preset' => $datePreset,
            'level' => 'account',
            'fields' => 'spend,reach,impressions,clicks,ctr,cpc,cpm,frequency,unique_clicks,actions,action_values,purchase_roas',
            'breakdowns' => 'region',
        ]);
    }

    public function getInsightsForAdSet(string $adSetId, string $datePreset = 'yesterday'): array
    {
        return $this->get($adSetId . '/insights', [
            'date_preset' => $datePreset,
            'fields' => 'spend,reach,impressions,clicks,ctr,cpc,cpm,actions,action_values,purchase_roas,frequency,unique_clicks',
            'breakdowns' => 'region',
        ]);
    }

    public function getInsightsForAd(string $adId, string $datePreset = 'yesterday'): array
    {
        return $this->get($adId . '/insights', [
            'date_preset' => $datePreset,
            'fields' => 'spend,reach,impressions,clicks,ctr,cpc,cpm,actions,action_values,purchase_roas,frequency,unique_clicks',
        ]);
    }
}

