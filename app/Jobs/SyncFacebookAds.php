<?php
declare(strict_types=1);

namespace App\Jobs;

use App\Services\FacebookAdsService;
use App\Services\FacebookAdsSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class SyncFacebookAds implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $syncId;

    public int $timeout = 1200; // 20 minutes

    public function __construct(string $syncId)
    {
        $this->syncId = $syncId;
    }

    public function handle(): void
    {
        $cacheKey = self::cacheKey($this->syncId);

        $api = new FacebookAdsService();
        $syncService = new FacebookAdsSyncService($api);

        $stagePercent = [
            'getBusinessManagers' => 5,
            'upsertBusiness' => 10,
            'getClientAdAccounts' => 15,
            'upsertAdAccount' => 25,
            'getCampaigns' => 35,
            'upsertCampaign' => 45,
            'getAdSetsByCampaign' => 55,
            'upsertAdSet' => 65,
            'getAdsByAdSet' => 75,
            'upsertAd' => 85,
            'upsertAdInsights' => 90,
            'upsertAdSetInsights' => 95,
            'upsertAccountInsights' => 96,
            'completed' => 100,
        ];

        $onProgress = function (array $state) use ($cacheKey, $stagePercent): void {
            $current = Cache::get($cacheKey, []);
            $stage = $state['stage'] ?? 'running';
            $percent = (int) ($stagePercent[$stage] ?? ($current['percent'] ?? 0));
            $payload = [
                'id' => $current['id'] ?? null,
                'startedAt' => $current['startedAt'] ?? now()->toISOString(),
                'stage' => $stage,
                'counts' => $state['counts'] ?? $current['counts'] ?? [
                    'businesses' => 0,
                    'accounts' => 0,
                    'campaigns' => 0,
                    'adsets' => 0,
                    'ads' => 0,
                    'insights' => 0,
                ],
                'errors' => $state['errors'] ?? $current['errors'] ?? [],
                'percent' => max((int) ($current['percent'] ?? 0), $percent),
                'done' => false,
                'result' => null,
                'updatedAt' => now()->toISOString(),
            ];
            Cache::put($cacheKey, $payload, now()->addHours(2));
        };

        try {
            $result = $syncService->syncYesterday($onProgress);
            $final = Cache::get($cacheKey, []);
            $final['stage'] = 'completed';
            $final['percent'] = 100;
            $final['done'] = true;
            $final['result'] = $result;
            $final['updatedAt'] = now()->toISOString();
            Cache::put($cacheKey, $final, now()->addHours(2));
        } catch (\Throwable $e) {
            $current = Cache::get($cacheKey, []);
            $current['stage'] = 'failed';
            $current['done'] = true;
            $current['percent'] = $current['percent'] ?? 0;
            $current['errors'] = array_values(array_merge($current['errors'] ?? [], [[
                'stage' => 'exception',
                'message' => $e->getMessage(),
            ]]));
            $current['updatedAt'] = now()->toISOString();
            Cache::put($cacheKey, $current, now()->addHours(2));
        }
    }

    public static function cacheKey(string $syncId): string
    {
        return 'facebook_sync:' . $syncId;
    }
}


