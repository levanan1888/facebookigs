<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\FacebookSyncController;

class ClearFacebookSyncCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'facebook:clear-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear tất cả cache liên quan đến Facebook sync';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Đang clear cache Facebook sync...');
        
        try {
            FacebookSyncController::clearAllSyncCache();
            $this->info('✅ Đã clear sạch tất cả cache Facebook sync!');
            
        } catch (\Exception $e) {
            $this->error('❌ Lỗi khi clear cache: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
