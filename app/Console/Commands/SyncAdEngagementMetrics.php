<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FacebookAd;
use App\Services\FacebookAdsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncAdEngagementMetrics extends Command
{
    /**
     * The name and signature of theconsole command.
     *
     * @var string
     */
    protected $signature = 'facebook:sync-engagement 
                            {--limit=0 : Giới hạn số lượng ads để sync (0 = tất cả)}
                            {--ad-id= : ID của ad cụ thể để sync}
                            {--force : Bắt buộc sync lại ngay cả khi đã sync gần đây}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Đồng bộ engagement metrics (likes, comments, shares) từ Ad Insights';

    private FacebookAdsService $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = new FacebookAdsService();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Bắt đầu đồng bộ Ad Engagement Metrics...');
        
        try {
            $limit = (int) $this->option('limit');
            $specificAdId = $this->option('ad-id');
            $force = $this->option('force');
            
            if ($specificAdId) {
                return $this->syncSpecificAd($specificAdId, $force);
            }
            
            return $this->syncAllAds($limit, $force);
            
        } catch (\Exception $e) {
            $this->error('❌ Lỗi: ' . $e->getMessage());
            Log::error('Lỗi trong SyncAdEngagementMetrics: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
    
    /**
     * Đồng bộ một ad cụ thể
     */
    private function syncSpecificAd(string $adId, bool $force = false): int
    {
        $this->info("📝 Đang đồng bộ ad: {$adId}");
        
        try {
            $ad = FacebookAd::find($adId);
            
            if (!$ad) {
                $this->warn("⚠️  Ad ID {$adId} không tìm thấy trong database");
                return Command::FAILURE;
            }
            
            $result = $this->syncAdEngagement($ad, $force);
            
            if ($result) {
                $this->info("✅ Đã đồng bộ thành công ad: {$adId}");
                return Command::SUCCESS;
            } else {
                $this->error("❌ Không thể đồng bộ ad: {$adId}");
                return Command::FAILURE;
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Lỗi khi đồng bộ ad {$adId}: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    /**
     * Đồng bộ tất cả ads
     */
    private function syncAllAds(int $limit, bool $force = false): int
    {
        $this->info('📊 Đang lấy danh sách ads cần đồng bộ...');
        
        // Lấy ads có insights nhưng chưa có engagement metrics hoặc cần sync lại
        $query = FacebookAd::whereNotNull('last_insights_sync')
            ->where(function($q) use ($force) {
                if ($force) {
                    // Nếu force, lấy tất cả
                    return $q;
                } else {
                    // Nếu không force, chỉ lấy những ad chưa có engagement hoặc sync lâu rồi
                    return $q->where(function($subQ) {
                        $subQ->whereNull('post_likes')
                            ->orWhereNull('post_comments')
                            ->orWhereNull('post_share')
                            ->orWhere('last_insights_sync', '<', now()->subDays(1));
                    });
                }
            });
        
        if ($limit > 0) {
            $query->limit($limit);
        }
        
        $ads = $query->get();
        $totalAds = $ads->count();
        
        if ($totalAds === 0) {
            $this->info('✅ Không có ads nào cần đồng bộ');
            return Command::SUCCESS;
        }
        
        $this->info("📝 Tìm thấy {$totalAds} ads cần đồng bộ");
        
        $successCount = 0;
        $errorCount = 0;
        
        $bar = $this->output->createProgressBar($totalAds);
        $bar->start();
        
        foreach ($ads as $ad) {
            try {
                $result = $this->syncAdEngagement($ad, $force);
                if ($result) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
                
                $bar->advance();
                
                // Rate limiting
                usleep(500000); // 0.5 giây giữa các requests
                
            } catch (\Exception $e) {
                $errorCount++;
                Log::error("Lỗi khi sync ad {$ad->id}: " . $e->getMessage());
                $bar->advance();
            }
        }
        
        $bar->finish();
        $this->newLine();
        
        // Hiển thị kết quả
        $this->info('✅ Hoàn thành đồng bộ engagement metrics!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Tổng ads', $totalAds],
                ['Thành công', $successCount],
                ['Lỗi', $errorCount],
                ['Tỷ lệ thành công', round(($successCount / $totalAds) * 100, 2) . '%'],
            ]
        );
        
        return Command::SUCCESS;
    }
    
    /**
     * Đồng bộ engagement metrics cho một ad
     */
    private function syncAdEngagement(FacebookAd $ad, bool $force = false): bool
    {
        try {
            // Kiểm tra xem có cần sync không
            if (!$force && $ad->last_insights_sync && $ad->last_insights_sync->isAfter(now()->subHours(6))) {
                $this->line("⏭️  Ad {$ad->id} đã được sync gần đây, bỏ qua");
                return true;
            }
            
            $this->line("🔄 Đang sync engagement cho ad: {$ad->id} - {$ad->name}");
            
            // Lấy ad insights
            $adInsights = $this->api->getInsightsForAd($ad->id);
          
            if (isset($adInsights['error'])) {
                $this->warn("⚠️  Không thể lấy ad insights cho {$ad->id}: " . ($adInsights['error']['message'] ?? 'Unknown error'));
                return false;
            }
            
            // Trích xuất engagement metrics từ actions
            $engagementData = $this->extractEngagementFromActions($adInsights);
            
            if (!empty($engagementData)) {
                // Cập nhật database
                $ad->update($engagementData);
                $this->line("✅ Đã cập nhật engagement cho ad: {$ad->id}");
                $this->line("   👍 Likes: {$engagementData['post_likes']}");
                $this->line("   💬 Comments: {$engagementData['post_comments']}");
                $this->line("   🔄 Shares: {$engagementData['post_share']}");
                return true;
            } else {
                $this->warn("⚠️  Không tìm thấy engagement data cho ad: {$ad->id}");
                return false;
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Lỗi khi sync engagement cho ad {$ad->id}: " . $e->getMessage());
            Log::error("Lỗi sync ad engagement", [
                'ad_id' => $ad->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    /**
     * Trích xuất engagement metrics từ ad insights actions
     */
    private function extractEngagementFromActions(array $adInsights): array
    {
        $engagementData = [
            'post_likes' => 0,
            'post_comments' => 0,
            'post_share' => 0,
            'last_insights_sync' => now(),
        ];
        
        if (!isset($adInsights['data']) || empty($adInsights['data'])) {
            return $engagementData;
        }
        
        // Tính tổng từ tất cả các ngày
        $totalLikes = 0;
        $totalComments = 0;
        $totalShares = 0;
        
        foreach ($adInsights['data'] as $dailyInsight) {
            if (isset($dailyInsight['actions']) && is_array($dailyInsight['actions'])) {
                foreach ($dailyInsight['actions'] as $action) {
                    $actionType = $action['action_type'] ?? '';
                    $value = (int) ($action['value'] ?? 0);
                    
                    switch ($actionType) {
                        case 'like':
                        case 'post_reaction':
                            $totalLikes += $value;
                            break;
                        case 'comment':
                            $totalComments += $value;
                            break;
                        case 'post_share':
                        case 'share':
                            $totalShares += $value;
                            break;
                    }
                }
            }
        }
        
        $engagementData['post_likes'] = $totalLikes;
        $engagementData['post_comments'] = $totalComments;
        $engagementData['post_share'] = $totalShares;
        
        return $engagementData;
    }
}
