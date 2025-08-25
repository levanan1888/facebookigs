<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FacebookAd;
use App\Models\FacebookPost;
use App\Services\FacebookAdsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestEngagementData extends Command
{
    protected $signature = 'facebook:test-engagement 
                            {--ad-id= : ID của ad cụ thể để test}
                            {--post-id= : ID của post cụ thể để test}
                            {--limit=5 : Số lượng ads để test}';

    protected $description = 'Test việc lấy engagement data từ Ad Insights API';

    private FacebookAdsService $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = new FacebookAdsService();
    }

    public function handle(): int
    {
        $this->info('🧪 Bắt đầu test engagement data...');
        
        try {
            $adId = $this->option('ad-id');
            $postId = $this->option('post-id');
            $limit = (int) $this->option('limit');
            
            if ($adId) {
                return $this->testSpecificAd($adId);
            }
            
            if ($postId) {
                return $this->testSpecificPost($postId);
            }
            
            return $this->testMultipleAds($limit);
            
        } catch (\Exception $e) {
            $this->error('❌ Lỗi: ' . $e->getMessage());
            Log::error('Lỗi trong TestEngagementData: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
    
    private function testSpecificAd(string $adId): int
    {
        $this->info("🔍 Test engagement data cho ad: {$adId}");
        
        try {
            $engagementData = $this->api->getAdEngagementData($adId);
            
            if (isset($engagementData['error'])) {
                $this->error("❌ Lỗi khi lấy engagement data: " . json_encode($engagementData['error']));
                return Command::FAILURE;
            }
            
            $this->info("✅ Engagement data:");
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Likes', $engagementData['likes'] ?? 0],
                    ['Shares', $engagementData['shares'] ?? 0],
                    ['Comments', $engagementData['comments'] ?? 0],
                    ['Reactions', $engagementData['reactions'] ?? 0],
                ]
            );
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("❌ Lỗi: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    private function testSpecificPost(string $postId): int
    {
        $this->info("🔍 Test engagement data cho post: {$postId}");
        
        try {
            // Tìm ad có post_id này
            $ad = FacebookAd::where('post_id', $postId)->first();
            if (!$ad) {
                $this->error("❌ Không tìm thấy ad cho post_id: {$postId}");
                return Command::FAILURE;
            }
            
            $this->info("📝 Tìm thấy ad: {$ad->id}");
            return $this->testSpecificAd($ad->id);
            
        } catch (\Exception $e) {
            $this->error("❌ Lỗi: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    private function testMultipleAds(int $limit): int
    {
        $this->info("🔍 Test engagement data cho {$limit} ads...");
        
        try {
            $ads = FacebookAd::limit($limit)->get();
            
            if ($ads->isEmpty()) {
                $this->warn("⚠️  Không có ads nào để test");
                return Command::SUCCESS;
            }
            
            $results = [];
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($ads as $ad) {
                $this->info("📝 Test ad: {$ad->id}");
                
                try {
                    $engagementData = $this->api->getAdEngagementData($ad->id);
                    
                    if (isset($engagementData['error'])) {
                        $results[] = [
                            'ad_id' => $ad->id,
                            'status' => 'ERROR',
                            'error' => json_encode($engagementData['error'])
                        ];
                        $errorCount++;
                    } else {
                        $results[] = [
                            'ad_id' => $ad->id,
                            'status' => 'SUCCESS',
                            'likes' => $engagementData['likes'] ?? 0,
                            'shares' => $engagementData['shares'] ?? 0,
                            'comments' => $engagementData['comments'] ?? 0,
                            'reactions' => $engagementData['reactions'] ?? 0,
                        ];
                        $successCount++;
                    }
                    
                } catch (\Exception $e) {
                    $results[] = [
                        'ad_id' => $ad->id,
                        'status' => 'ERROR',
                        'error' => $e->getMessage()
                    ];
                    $errorCount++;
                }
                
                // Delay để tránh rate limit
                usleep(500000); // 0.5 giây
            }
            
            $this->info("📊 Kết quả test:");
            $this->table(
                ['Ad ID', 'Status', 'Likes', 'Shares', 'Comments', 'Reactions', 'Error'],
                array_map(function($result) {
                    return [
                        $result['ad_id'],
                        $result['status'],
                        $result['likes'] ?? '-',
                        $result['shares'] ?? '-',
                        $result['comments'] ?? '-',
                        $result['reactions'] ?? '-',
                        $result['error'] ?? '-',
                    ];
                }, $results)
            );
            
            $this->info("✅ Hoàn thành! Thành công: {$successCount}, Lỗi: {$errorCount}");
            
            return $errorCount === 0 ? Command::SUCCESS : Command::FAILURE;
            
        } catch (\Exception $e) {
            $this->error("❌ Lỗi: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
