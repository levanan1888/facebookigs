<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\FacebookAdsSyncService;
use App\Services\FacebookAdsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestFacebookSyncDirect extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'facebook:sync-direct 
                            {--limit=0 : Giới hạn số lượng ads để test}
                            {--step=all : Bước cụ thể để test (ads, post, insights, creative)}
                            {--adset-id= : ID của adset cụ thể để test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Facebook sync trực tiếp mà không cần job';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $step = $this->option('step');
        $adsetId = $this->option('adset-id');
        
        if ($step === 'ads' && $adsetId) {
            return $this->testAdsStep($adsetId);
        }
        
        if ($step === 'post' && $adsetId) {
            return $this->testPostStep($adsetId);
        }
        
        if ($step === 'insights' && $adsetId) {
            return $this->testInsightsStep($adsetId);
        }
        
        if ($step === 'creative' && $adsetId) {
            return $this->testCreativeStep($adsetId);
        }
        
        // Test toàn bộ nếu không có step cụ thể
        return $this->testFullSync();
    }
    
    /**
     * Test bước lấy ads
     */
    private function testAdsStep(string $adsetId): int
    {
        $this->info("🔍 Test bước lấy ads cho adset: {$adsetId}");
        
        try {
            $api = new FacebookAdsService();
            $ads = $api->getAdsByAdSet($adsetId);
            
            $this->info("📊 Response Facebook Ads:");
            $this->table(
                ['Field', 'Value'],
                [
                    ['Total Ads', count($ads['data'] ?? [])],
                    ['Has Error', isset($ads['error']) ? 'Yes' : 'No'],
                    ['First Ad ID', $ads['data'][0]['id'] ?? 'N/A'],
                    ['First Ad Name', $ads['data'][0]['name'] ?? 'N/A'],
                ]
            );
            
            if (isset($ads['data'][0])) {
                $this->info("📋 Fields có sẵn trong Ad:");
                $this->table(['Field'], array_map(fn($field) => [$field], array_keys($ads['data'][0])));
                
                $this->info("🎨 Creative fields:");
                if (isset($ads['data'][0]['creative'])) {
                    $this->table(['Creative Field'], array_map(fn($field) => [$field], array_keys($ads['data'][0]['creative'])));
                }
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ Lỗi: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    /**
     * Test bước lấy post details
     */
    private function testPostStep(string $adsetId): int
    {
        $this->info("🔍 Test bước lấy post details cho adset: {$adsetId}");
        
        try {
            $api = new FacebookAdsService();
            $ads = $api->getAdsByAdSet($adsetId);
            
            if (empty($ads['data'])) {
                $this->warn("⚠️  Không có ads nào trong adset");
                return Command::SUCCESS;
            }
            
            $firstAd = $ads['data'][0];
            $this->info("📋 Testing với ad: {$firstAd['id']} - {$firstAd['name']}");
            
            // Extract post ID
            $creative = $firstAd['creative'] ?? [];
            $postId = null;
            
            if (isset($creative['object_story_id'])) {
                $postId = $creative['object_story_id'];
            } elseif (isset($creative['effective_object_story_id'])) {
                $postId = $creative['effective_object_story_id'];
            }
            
            if ($postId) {
                $this->info("📝 Post ID: {$postId}");
                $postData = $api->getPostDetails($postId);
                
                $this->info("📊 Post Details Response:");
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Has Error', isset($postData['error']) ? 'Yes' : 'No'],
                        ['Post ID', $postData['id'] ?? 'N/A'],
                        ['Type', $postData['type'] ?? 'N/A'],
                        ['Message Length', isset($postData['message']) ? strlen($postData['message']) : 'N/A'],
                    ]
                );
                
                if (!isset($postData['error'])) {
                    $this->info("📋 Post fields có sẵn:");
                    $this->table(['Field'], array_map(fn($field) => [$field], array_keys($postData)));
                }
            } else {
                $this->warn("⚠️  Không tìm thấy post ID trong creative");
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ Lỗi: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    /**
     * Test bước lấy insights
     */
    private function testInsightsStep(string $adsetId): int
    {
        $this->info("🔍 Test bước lấy insights cho adset: {$adsetId}");
        
        try {
            $api = new FacebookAdsService();
            $ads = $api->getAdsByAdSet($adsetId);
            
            if (empty($ads['data'])) {
                $this->warn("⚠️  Không có ads nào trong adset");
                return Command::SUCCESS;
            }
            
            $firstAd = $ads['data'][0];
            $this->info("📋 Testing với ad: {$firstAd['id']} - {$firstAd['name']}");
            
            // Test ad insights
            $adInsights = $api->getInsightsForAdsBatch([$firstAd['id']]);
            $adInsightsData = $adInsights[$firstAd['id']] ?? null;
            
                        $this->info("📊 Ad Insights Response:");
            $this->table(
                ['Field', 'Value'],
                [
                    ['Has Error', isset($adInsightsData['error']) ? 'Yes' : 'No'],
                    ['Data Count', isset($adInsightsData['data']) ? count($adInsightsData['data']) : 0],
                ]
            );
            
            // Debug chi tiết lỗi
            if (isset($adInsightsData['error'])) {
                $this->error("❌ Chi tiết lỗi:");
                $error = $adInsightsData['error'];
                $this->table(
                    ['Error Field', 'Value'],
                    [
                        ['Code', $error['code'] ?? 'N/A'],
                        ['Type', $error['type'] ?? 'N/A'],
                        ['Message', $error['message'] ?? 'N/A'],
                        ['Error Subcode', $error['error_subcode'] ?? 'N/A'],
                        ['Fbtrace ID', $error['fbtrace_id'] ?? 'N/A'],
                    ]
                );
            }
            
            if ($adInsightsData && !isset($adInsightsData['error']) && isset($adInsightsData['data'][0])) {
                $firstInsight = $adInsightsData['data'][0];
                $this->info("📋 Insight fields có sẵn:");
                $this->table(['Field'], array_map(fn($field) => [$field], array_keys($firstInsight)));
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ Lỗi: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    /**
     * Test bước lấy creative data
     */
    private function testCreativeStep(string $adsetId): int
    {
        $this->info("🔍 Test bước lấy creative data cho adset: {$adsetId}");
        
        try {
            $api = new FacebookAdsService();
            $ads = $api->getAdsByAdSet($adsetId);
            
            if (empty($ads['data'])) {
                $this->warn("⚠️  Không có ads nào trong adset");
                return Command::SUCCESS;
            }
            
            $firstAd = $ads['data'][0];
            $this->info("📋 Testing với ad: {$firstAd['id']} - {$firstAd['name']}");
            
            $creative = $firstAd['creative'] ?? [];
            
            $this->info("🎨 Creative Response:");
            $this->table(
                ['Field', 'Value'],
                [
                    ['Has Creative', !empty($creative) ? 'Yes' : 'No'],
                    ['Creative Keys', implode(', ', array_keys($creative))],
                    ['Has Object Story ID', isset($creative['object_story_id']) ? 'Yes' : 'No'],
                    ['Has Object Story Spec', isset($creative['object_story_spec']) ? 'Yes' : 'No'],
                ]
            );
            
            if (isset($creative['object_story_spec'])) {
                $spec = $creative['object_story_spec'];
                $this->info("📋 Object Story Spec fields:");
                $this->table(['Field'], array_map(fn($field) => [$field], array_keys($spec)));
                
                if (isset($spec['link_data'])) {
                    $this->info("🔗 Link Data fields:");
                    $this->table(['Field'], array_map(fn($field) => [$field], array_keys($spec['link_data'])));
                }
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ Lỗi: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    /**
     * Test toàn bộ sync
     */
    private function testFullSync(): int
    {
        $this->info('🚀 Bắt đầu test Facebook sync trực tiếp...');
        
        try {
            // Tạo progress callback để hiển thị tiến độ
            $progressCallback = function(array $progress) {
                $this->info("📊 {$progress['message']}");
                
                if (isset($progress['counts'])) {
                    $counts = $progress['counts'];
                    $this->table(
                        ['Businesses', 'Accounts', 'Campaigns', 'Ad Sets', 'Ads'],
                        [[
                            $counts['businesses'] ?? 0,
                            $counts['accounts'] ?? 0,
                            $counts['campaigns'] ?? 0,
                            $counts['adsets'] ?? 0,
                            $counts['ads'] ?? 0,
                        ]]
                    );
                }
                
                if (!empty($progress['errors'])) {
                    $this->warn('⚠️  Có lỗi xảy ra:');
                    foreach ($progress['errors'] as $error) {
                        $errorMessage = is_array($error['error']) ? json_encode($error['error']) : $error['error'];
                        $this->error("  - {$error['stage']}: {$errorMessage}");
                    }
                }
            };
            
            // Chạy sync
            $syncService = new FacebookAdsSyncService(new FacebookAdsService());
            $result = $syncService->syncFacebookData($progressCallback);
            
            // Hiển thị kết quả cuối cùng
            $this->newLine();
            $this->info('✅ Hoàn thành sync!');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Businesses', $result['businesses']],
                    ['Ad Accounts', $result['accounts']],
                    ['Campaigns', $result['campaigns']],
                    ['Ad Sets', $result['adsets']],
                    ['Ads', $result['ads']],
                    ['Thời gian', ($result['duration'] ?? 0) . ' giây'],
                    ['Lỗi', count($result['errors'] ?? [])],
                ]
            );
            
            if (!empty($result['errors'])) {
                $this->warn('⚠️  Chi tiết lỗi:');
                foreach ($result['errors'] as $error) {
                    $errorMessage = is_array($error['error']) ? json_encode($error['error']) : $error['error'];
                    $this->error("  - {$error['stage']}: {$errorMessage}");
                }
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ Lỗi khi sync: ' . $e->getMessage());
            Log::error('Lỗi trong command TestFacebookSyncDirect: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
}
