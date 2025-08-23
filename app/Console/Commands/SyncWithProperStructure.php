<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\FacebookAdsSyncService;
use Illuminate\Console\Command;

class SyncWithProperStructure extends Command
{
    protected $signature = 'facebook:sync-proper-structure';
    protected $description = 'Sync Facebook data với cấu trúc đúng và lưu vào từng bảng riêng biệt';

    private FacebookAdsSyncService $syncService;

    public function __construct(FacebookAdsSyncService $syncService)
    {
        parent::__construct();
        $this->syncService = $syncService;
    }

    public function handle(): int
    {
        $this->info('🚀 Bắt đầu sync Facebook data với cấu trúc đúng...');
        
        try {
            $result = $this->syncService->syncWithProperDataStructure();
            
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
                    ['Ad Insights', $result['ad_insights']],
                    ['Post Insights', $result['post_insights']],
                ]
            );
            
            if (!empty($result['errors'])) {
                $this->warn('⚠️ Có một số lỗi:');
                foreach ($result['errors'] as $error) {
                    $this->line("  - {$error}");
                }
            }
            
            $this->info('📊 Data đã được lưu đúng vào từng bảng:');
            $this->line('  - facebook_businesses');
            $this->line('  - facebook_ad_accounts');
            $this->line('  - facebook_campaigns');
            $this->line('  - facebook_ad_sets');
            $this->line('  - facebook_ads');
            $this->line('  - facebook_pages');
            $this->line('  - facebook_posts');
            $this->line('  - facebook_ad_insights (với video metrics)');
            $this->line('  - facebook_post_insights');
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ Lỗi trong quá trình sync: ' . $e->getMessage());
            return 1;
        }
    }
}
