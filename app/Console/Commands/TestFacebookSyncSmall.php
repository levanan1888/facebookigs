<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\FacebookAdsSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestFacebookSyncSmall extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'facebook:test-sync-small {--limit=1 : Số lượng business manager tối đa để test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test đồng bộ Facebook Ads với delay tăng cao';

    /**
     * Execute the console command.
     */
    public function handle(FacebookAdsSyncService $syncService): int
    {
        $this->info('🚀 Bắt đầu test đồng bộ Facebook Ads với delay cao...');
        
        // Clear cache stop request
        \Illuminate\Support\Facades\Cache::forget('facebook_sync_stop_requested');
        
        $limit = (int) $this->option('limit');
        $this->info("📊 Giới hạn test: {$limit} business managers");
        $this->info("⏱️  Delay giữa các API calls: 3 giây");
        $this->info("🔄 Retry delay: 10s, 20s, 40s");
        
        try {
            $result = $syncService->syncYesterday(function (array $progress) {
                $this->info("📈 Tiến độ: {$progress['stage']}");
                $this->table(
                    ['Loại', 'Số lượng'],
                    [
                        ['Businesses', $progress['counts']['businesses']],
                        ['Accounts', $progress['counts']['accounts']],
                        ['Campaigns', $progress['counts']['campaigns']],
                        ['Ad Sets', $progress['counts']['adsets']],
                        ['Ads', $progress['counts']['ads']],
                        ['Pages', $progress['counts']['pages']],
                        ['Posts', $progress['counts']['posts']],
                        ['Insights', $progress['counts']['insights']],
                    ]
                );
                
                if (!empty($progress['errors'])) {
                    $this->error('❌ Có lỗi xảy ra:');
                    foreach ($progress['errors'] as $error) {
                        $this->error("- {$error['stage']}: " . json_encode($error['error']));
                    }
                }
            });
            
            $this->info('✅ Đồng bộ hoàn thành!');
            $this->table(
                ['Loại', 'Số lượng'],
                [
                    ['Businesses', $result['businesses']],
                    ['Accounts', $result['accounts']],
                    ['Campaigns', $result['campaigns']],
                    ['Ad Sets', $result['adsets']],
                    ['Ads', $result['ads']],
                    ['Pages', $result['pages']],
                    ['Posts', $result['posts']],
                    ['Insights', $result['insights']],
                ]
            );
            
            if (!empty($result['errors'])) {
                $this->error('❌ Có lỗi xảy ra trong quá trình đồng bộ:');
                foreach ($result['errors'] as $error) {
                    $this->error("- {$error['stage']}: " . json_encode($error['error']));
                }
            }
            
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ Lỗi khi đồng bộ: ' . $e->getMessage());
            Log::error('Lỗi khi test đồng bộ Facebook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return self::FAILURE;
        }
    }
}
