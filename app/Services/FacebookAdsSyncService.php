<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\FacebookAd;
use App\Models\FacebookAdAccount;
use App\Models\FacebookAdSet;
use App\Models\FacebookBusiness;
use App\Models\FacebookCampaign;
use App\Models\FacebookInsight;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

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
            'insights' => 0,
            'errors' => [],
        ];

        $reportProgress = function (string $stage) use (&$result, $onProgress): void {
            if ($onProgress) {
                $onProgress([
                    'stage' => $stage,
                    'counts' => [
                        'businesses' => $result['businesses'],
                        'accounts' => $result['accounts'],
                        'campaigns' => $result['campaigns'],
                        'adsets' => $result['adsets'],
                        'ads' => $result['ads'],
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
            FacebookBusiness::updateOrCreate(['id' => $b['id']], [
                'name' => $b['name'] ?? null,
                'verification_status' => $b['verification_status'] ?? null,
                'created_time' => isset($b['created_time']) ? Carbon::parse($b['created_time']) : null,
            ]);
            $result['businesses']++;
            $reportProgress('upsertBusiness');

            $accounts = $this->api->getClientAdAccounts($b['id']);
            if (isset($accounts['error'])) {
                $result['errors'][] = ['stage' => 'getClientAdAccounts', 'business_id' => $b['id'], 'error' => $accounts['error']];
            }
            $reportProgress('getClientAdAccounts');
            foreach (($accounts['data'] ?? []) as $acc) {
                FacebookAdAccount::updateOrCreate(['id' => $acc['id']], [
                    'account_id' => $acc['account_id'] ?? null,
                    'name' => $acc['name'] ?? null,
                    'account_status' => $acc['account_status'] ?? null,
                    'business_id' => $b['id'],
                ]);
                $result['accounts']++;
                $reportProgress('upsertAdAccount');

                $campaigns = $this->api->getCampaigns($acc['id']);
                if (isset($campaigns['error'])) {
                    $result['errors'][] = ['stage' => 'getCampaigns', 'ad_account_id' => $acc['id'], 'error' => $campaigns['error']];
                }
                $reportProgress('getCampaigns');
                foreach (($campaigns['data'] ?? []) as $camp) {
                    FacebookCampaign::updateOrCreate(['id' => $camp['id']], [
                        'name' => $camp['name'] ?? null,
                        'status' => $camp['status'] ?? null,
                        'objective' => $camp['objective'] ?? null,
                        'start_time' => Arr::get($camp, 'start_time') ? Carbon::parse($camp['start_time']) : null,
                        'stop_time' => Arr::get($camp, 'stop_time') ? Carbon::parse($camp['stop_time']) : null,
                        'effective_status' => $camp['effective_status'] ?? null,
                        'configured_status' => $camp['configured_status'] ?? null,
                        'updated_time' => Arr::get($camp, 'updated_time') ? Carbon::parse($camp['updated_time']) : null,
                        'ad_account_id' => $acc['id'],
                    ]);
                    $result['campaigns']++;
                    $reportProgress('upsertCampaign');

                    $adsets = $this->api->getAdSetsByCampaign($camp['id']);
                    if (isset($adsets['error'])) {
                        $result['errors'][] = ['stage' => 'getAdSetsByCampaign', 'campaign_id' => $camp['id'], 'error' => $adsets['error']];
                    }
                    $reportProgress('getAdSetsByCampaign');
                    foreach (($adsets['data'] ?? []) as $adset) {
                        FacebookAdSet::updateOrCreate(['id' => $adset['id']], [
                            'name' => $adset['name'] ?? null,
                            'status' => $adset['status'] ?? null,
                            'optimization_goal' => $adset['optimization_goal'] ?? null,
                            'campaign_id' => $camp['id'],
                        ]);
                        $result['adsets']++;
                        $reportProgress('upsertAdSet');

                        $ads = $this->api->getAdsByAdSet($adset['id']);
                        if (isset($ads['error'])) {
                            $result['errors'][] = ['stage' => 'getAdsByAdSet', 'ad_set_id' => $adset['id'], 'error' => $ads['error']];
                        }
                        $reportProgress('getAdsByAdSet');
                        foreach (($ads['data'] ?? []) as $ad) {
                            FacebookAd::updateOrCreate(['id' => $ad['id']], [
                                'name' => $ad['name'] ?? null,
                                'status' => $ad['status'] ?? null,
                                'effective_status' => $ad['effective_status'] ?? null,
                                'adset_id' => $adset['id'],
                                'campaign_id' => $camp['id'],
                                'account_id' => $acc['id'],
                                'creative' => $ad['creative'] ?? null,
                                'created_time' => Arr::get($ad, 'created_time') ? Carbon::parse($ad['created_time']) : null,
                                'updated_time' => Arr::get($ad, 'updated_time') ? Carbon::parse($ad['updated_time']) : null,
                            ]);
                            $result['ads']++;
                            $reportProgress('upsertAd');

                            $adInsights = $this->api->getInsightsForAd($ad['id']);
                            if (isset($adInsights['error'])) {
                                $result['errors'][] = ['stage' => 'getInsightsForAd', 'ad_id' => $ad['id'], 'error' => $adInsights['error']];
                            }
                            foreach (($adInsights['data'] ?? []) as $in) {
                                FacebookInsight::updateOrCreate([
                                    'level' => 'ad',
                                    'ref_id' => $ad['id'],
                                    'date' => now()->subDay()->toDateString(),
                                ], [
                                    'spend' => $in['spend'] ?? null,
                                    'reach' => $in['reach'] ?? null,
                                    'impressions' => $in['impressions'] ?? null,
                                    'clicks' => $in['clicks'] ?? null,
                                    'ctr' => $in['ctr'] ?? null,
                                    'cpc' => $in['cpc'] ?? null,
                                    'cpm' => $in['cpm'] ?? null,
                                    'frequency' => $in['frequency'] ?? null,
                                    'unique_clicks' => $in['unique_clicks'] ?? null,
                                    'actions' => $in['actions'] ?? null,
                                    'action_values' => $in['action_values'] ?? null,
                                    'purchase_roas' => $in['purchase_roas'] ?? null,
                                ]);
                                $result['insights']++;
                                $reportProgress('upsertAdInsights');
                            }
                        }

                        $adsetInsights = $this->api->getInsightsForAdSet($adset['id']);
                        if (isset($adsetInsights['error'])) {
                            $result['errors'][] = ['stage' => 'getInsightsForAdSet', 'ad_set_id' => $adset['id'], 'error' => $adsetInsights['error']];
                        }
                        foreach (($adsetInsights['data'] ?? []) as $in) {
                            FacebookInsight::updateOrCreate([
                                'level' => 'adset',
                                'ref_id' => $adset['id'],
                                'date' => now()->subDay()->toDateString(),
                            ], [
                                'spend' => $in['spend'] ?? null,
                                'reach' => $in['reach'] ?? null,
                                'impressions' => $in['impressions'] ?? null,
                                'clicks' => $in['clicks'] ?? null,
                                'ctr' => $in['ctr'] ?? null,
                                'cpc' => $in['cpc'] ?? null,
                                'cpm' => $in['cpm'] ?? null,
                                'frequency' => $in['frequency'] ?? null,
                                'unique_clicks' => $in['unique_clicks'] ?? null,
                                'actions' => $in['actions'] ?? null,
                                'action_values' => $in['action_values'] ?? null,
                                'purchase_roas' => $in['purchase_roas'] ?? null,
                            ]);
                            $result['insights']++;
                            $reportProgress('upsertAdSetInsights');
                        }
                    }
                }

                $accountInsights = $this->api->getInsightsForAccount($acc['id']);
                if (isset($accountInsights['error'])) {
                    $result['errors'][] = ['stage' => 'getInsightsForAccount', 'ad_account_id' => $acc['id'], 'error' => $accountInsights['error']];
                }
                foreach (($accountInsights['data'] ?? []) as $in) {
                    FacebookInsight::updateOrCreate([
                        'level' => 'account',
                        'ref_id' => $acc['id'],
                        'date' => now()->subDay()->toDateString(),
                    ], [
                        'spend' => $in['spend'] ?? null,
                        'reach' => $in['reach'] ?? null,
                        'impressions' => $in['impressions'] ?? null,
                        'clicks' => $in['clicks'] ?? null,
                        'ctr' => $in['ctr'] ?? null,
                        'cpc' => $in['cpc'] ?? null,
                        'cpm' => $in['cpm'] ?? null,
                        'frequency' => $in['frequency'] ?? null,
                        'unique_clicks' => $in['unique_clicks'] ?? null,
                        'actions' => $in['actions'] ?? null,
                        'action_values' => $in['action_values'] ?? null,
                        'purchase_roas' => $in['purchase_roas'] ?? null,
                    ]);
                    $result['insights']++;
                    $reportProgress('upsertAccountInsights');
                }
            }
        }

        $reportProgress('completed');
        return $result;
    }
}



