<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FacebookAd;
use App\Services\FacebookAdsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestVideoMetricsAPI extends Command
{
    protected $signature = 'facebook:test-video-metrics {--ad-id= : ID của ad cụ thể} {--limit=5 : Số lượng ad test}';
    protected $description = 'Test API để xem Facebook có trả về video metrics nâng cao không';

    public function handle(): int
    {
        $adId = $this->option('ad-id');
        $limit = (int) $this->option('limit');
        
        $api = new FacebookAdsService();
        
        if ($adId) {
            $this->testSingleAd($api, $adId);
        } else {
            $this->testMultipleAds($api, $limit);
        }
        
        return Command::SUCCESS;
    }
    
    private function testSingleAd(FacebookAdsService $api, string $adId): void
    {
        $this->info("🎯 Testing Ad ID: {$adId}");
        
        try {
            // Test basic insights với fields cơ bản
            $insights = $api->getInsightsWithActionBreakdowns($adId, ['action_type']);
            
            if (isset($insights['data']) && !empty($insights['data'])) {
                $insight = $insights['data'][0];
                
                $this->info("✅ Có data insights");
                $this->info("📊 Fields có sẵn:");
                
                $videoFields = [];
                foreach ($insight as $field => $value) {
                    if (strpos($field, 'video_') !== false) {
                        $videoFields[$field] = $value;
                    }
                }
                
                if (!empty($videoFields)) {
                    foreach ($videoFields as $field => $value) {
                        $this->line("  - {$field}: " . json_encode($value));
                    }
                } else {
                    $this->warn("⚠️  Không có video fields nào");
                }
                
                // Test từng field video metrics nâng cao riêng biệt
                $this->info("\n🔍 Test từng field video metrics nâng cao:");
                $advancedFields = [
                    'video_play_actions',
                    'video_watch_at_75_percent_actions',
                    'video_watch_at_100_percent_actions',
                    'video_sound_on_actions',
                    'video_sound_off_actions',
                    'video_skip_actions',
                    'video_mute_actions',
                    'video_unmute_actions',
                    'video_attributed_views',
                    'video_attributed_view_time',
                    'video_retention_graph',
                    'video_quality_actions',
                    'video_engagement_rate',
                    'video_completion_rate',
                    'video_performance_p25',
                    'video_performance_p50',
                    'video_performance_p75',
                    'video_performance_p95'
                ];
                
                foreach ($advancedFields as $field) {
                    $this->line("  🔍 Testing field: {$field}");
                    
                    try {
                        // Sử dụng FacebookAdsService để test từng field
                        $testUrl = "https://graph.facebook.com/v20.0/{$adId}/insights";
                        $testParams = [
                            'access_token' => $api->getAccessToken(), // Sử dụng access token từ service
                            'fields' => $field,
                            'time_range' => json_encode([
                                'since' => date('Y-m-d', strtotime('-1 year')),
                                'until' => date('Y-m-d')
                            ])
                        ];
                        
                        $response = Http::get($testUrl, $testParams);
                        $testResult = $response->json();
                        
                        if (isset($testResult['data']) && !empty($testResult['data'])) {
                            $testInsight = $testResult['data'][0];
                            if (isset($testInsight[$field])) {
                                $this->line("    ✅ {$field}: " . json_encode($testInsight[$field]));
                            } else {
                                $this->line("    ❌ {$field}: Field không có trong response");
                            }
                        } else {
                            $this->line("    ❌ {$field}: Không có data hoặc có lỗi");
                            if (isset($testResult['error'])) {
                                $this->line("       Lỗi: " . json_encode($testResult['error']));
                            }
                        }
                        
                        // Delay để tránh rate limit
                        usleep(100000); // 0.1 giây
                        
                    } catch (\Exception $e) {
                        $this->line("    ❌ {$field}: Exception - " . $e->getMessage());
                    }
                }
                
            } else {
                $this->error("❌ Không có data insights");
                if (isset($insights['error'])) {
                    $this->error("Lỗi: " . json_encode($insights['error']));
                }
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Exception: " . $e->getMessage());
            Log::error("Test video metrics API failed", [
                'ad_id' => $adId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    private function testMultipleAds(FacebookAdsService $api, int $limit): void
    {
        $this->info("🔍 Testing {$limit} ads từ database");
        
        $ads = FacebookAd::limit($limit)->get();
        
        if ($ads->isEmpty()) {
            $this->warn("⚠️  Không có ads nào trong database");
            return;
        }
        
        foreach ($ads as $ad) {
            $this->line("\n" . str_repeat('-', 50));
            $this->testSingleAd($api, $ad->id);
        }
    }
}
