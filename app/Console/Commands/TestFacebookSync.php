<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\FacebookAdsSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestFacebookSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'facebook:test-sync {--since= : Start date (Y-m-d)} {--until= : End date (Y-m-d)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Facebook Ads sync với progress callback';

    /**
     * Execute the console command.
     */
    public function handle(FacebookAdsSyncService $syncService): int
    {
        $this->info('🚀 Bắt đầu test Facebook Ads sync...');
        
        $since = $this->option('since') ?: now()->subDays(7)->format('Y-m-d');
        $until = $this->option('until') ?: now()->format('Y-m-d');
        
        $this->info("📅 Time range: {$since} đến {$until}");
        
        // Progress callback
        $onProgress = function (array $progress) {
            $this->info("📊 {$progress['message']}");
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Businesses', $progress['counts']['businesses']],
                    ['Ad Accounts', $progress['counts']['accounts']],
                    ['Campaigns', $progress['counts']['campaigns']],
                    ['Ad Sets', $progress['counts']['adsets']],
                    ['Ads', $progress['counts']['ads']],
                    ['Posts', $progress['counts']['posts']],
                    ['Pages', $progress['counts']['pages']],
                    ['Post Insights', $progress['counts']['post_insights']],
                    ['Ad Insights', $progress['counts']['ad_insights']],
                ]
            );
            
            if (!empty($progress['errors'])) {
                $this->error('❌ Errors:');
                foreach ($progress['errors'] as $error) {
                    $this->error("- {$error['stage']}: {$error['error']}");
                }
            }
            
            $this->newLine();
        };
        
        try {
            $result = $syncService->syncFacebookData($onProgress, $since, $until);
            
            $this->info('✅ Sync completed!');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Businesses', $result['businesses']],
                    ['Ad Accounts', $result['accounts']],
                    ['Campaigns', $result['campaigns']],
                    ['Ad Sets', $result['adsets']],
                    ['Ads', $result['ads']],
                    ['Posts', $result['posts']],
                    ['Pages', $result['pages']],
                    ['Post Insights', $result['post_insights']],
                    ['Ad Insights', $result['ad_insights']],
                ]
            );
            
            if (!empty($result['errors'])) {
                $this->error('❌ Errors occurred:');
                foreach ($result['errors'] as $error) {
                    $this->error("- {$error['stage']}: {$error['error']}");
                }
            }
            
            $this->info("⏱️ Duration: {$result['duration']} seconds");
            
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("❌ Sync failed: {$e->getMessage()}");
            Log::error('Facebook sync test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return self::FAILURE;
        }
    }
}
