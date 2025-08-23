<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FacebookAd;
use App\Services\FacebookAdsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestFacebookApiResponse extends Command
{
    protected $signature = 'facebook:test-api-response {ad_id?} {--days=30}';
    protected $description = 'Test Facebook API response và debug vấn đề lưu dữ liệu';

    public function handle(): int
    {
        $adId = $this->argument('ad_id');
        $days = $this->option('days');
        
        if (!$adId) {
            // Lấy ad đầu tiên từ database
            $ad = FacebookAd::first();
            if (!$ad) {
                $this->error('Không có ad nào trong database!');
                return 1;
            }
            $adId = $ad->id;
        }

        $this->info("Testing Facebook API response cho ad ID: {$adId}");
        $this->info("Số ngày lấy dữ liệu: {$days}");

        try {
            $api = new FacebookAdsService();
            
            // Kiểm tra ad trong database
            $ad = FacebookAd::find($adId);
            if (!$ad) {
                $this->error("Không tìm thấy ad ID {$adId} trong database!");
                return 1;
            }
            
            $this->info("Ad trong database:");
            $this->line("ID: " . $ad->id);
            $this->line("Name: " . $ad->name);
            $this->line("Status: " . $ad->status);
            $this->line("Created: " . $ad->created_at);
            
            // Test 1: Basic insights với thời gian cụ thể
            $this->info("\n=== TEST 1: Basic Insights với thời gian ===");
            $endDate = now();
            $startDate = now()->subDays($days);
            
            $this->info("Thời gian: {$startDate->toDateString()} đến {$endDate->toDateString()}");
            
            // Gọi API trực tiếp với thời gian
            $basicInsights = $api->getInsightsForAd($adId);
            $this->info("Basic insights response:");
            $this->line(json_encode($basicInsights, JSON_PRETTY_PRINT));
            
            // Test 2: Kiểm tra Facebook API call trực tiếp
            $this->info("\n=== TEST 2: Debug Facebook API Call ===");
            try {
                $token = config('services.facebook.ads_token');
                $this->info("Token length: " . strlen($token));
                $this->info("Token preview: " . substr($token, 0, 20) . "...");
                
                // Test token với user info
                $userResponse = Http::get("https://graph.facebook.com/v23.0/me", [
                    'access_token' => $token
                ]);
                
                if ($userResponse->successful()) {
                    $this->info("Token valid - User: " . json_encode($userResponse->json()));
                } else {
                    $this->error("Token không hợp lệ: " . $userResponse->body());
                }
                
            } catch (\Exception $e) {
                $this->error("Lỗi khi kiểm tra token: " . $e->getMessage());
            }
            
            // Test 3: Kiểm tra ad account
            $this->info("\n=== TEST 3: Kiểm tra Ad Account ===");
            try {
                if ($ad->ad_account_id) {
                    $accountResponse = Http::get("https://graph.facebook.com/v23.0/{$ad->ad_account_id}", [
                        'access_token' => $token,
                        'fields' => 'id,name,account_status'
                    ]);
                    
                    if ($accountResponse->successful()) {
                        $this->info("Ad Account info:");
                        $this->line(json_encode($accountResponse->json(), JSON_PRETTY_PRINT));
                    } else {
                        $this->error("Không thể lấy ad account info: " . $accountResponse->body());
                    }
                } else {
                    $this->warn("Ad không có ad_account_id!");
                }
            } catch (\Exception $e) {
                $this->error("Lỗi khi lấy ad account info: " . $e->getMessage());
            }
            
            // Test 4: Gọi API insights trực tiếp với tham số GIỐNG HỆT hàm chính
            $this->info("\n=== TEST 4: Gọi API Insights Trực Tiếp (giống hệt hàm chính) ===");
            try {
                // Sử dụng tham số giống hệt getInsightsForAd
                $fields = [
                    'spend', 'reach', 'impressions', 'clicks', 'ctr', 'cpc', 'cpm', 'frequency',
                    'unique_clicks', 'unique_ctr', 'ad_name', 'ad_id',
                    'actions', 'action_values',
                    'cost_per_action_type', 'cost_per_unique_action_type',
                    'conversions', 'conversion_values', 'cost_per_conversion', 'purchase_roas',
                    'outbound_clicks', 'unique_outbound_clicks', 'inline_link_clicks', 'unique_inline_link_clicks',
                    'video_30_sec_watched_actions', 'video_avg_time_watched_actions',
                    'video_p25_watched_actions', 'video_p50_watched_actions', 
                    'video_p75_watched_actions', 'video_p95_watched_actions', 'video_p100_watched_actions',
                    'date_start', 'date_stop'
                ];
                
                $params = [
                    'access_token' => $token,
                    'fields' => implode(',', $fields),
                    'time_range' => json_encode([
                        'since' => date('Y-m-d', strtotime('-7 days')),
                        'until' => date('Y-m-d')
                    ])
                ];
                
                $this->info("Tham số truyền vào:");
                $this->line("Fields: " . implode(',', $fields));
                $this->line("Time range: " . json_encode($params['time_range']));
                
                $insightsResponse = Http::get("https://graph.facebook.com/v23.0/{$adId}/insights", $params);
                
                $this->info("Direct insights API response:");
                if ($insightsResponse->successful()) {
                    $this->line(json_encode($insightsResponse->json(), JSON_PRETTY_PRINT));
                } else {
                    $this->error("API error: " . $insightsResponse->body());
                }
                
            } catch (\Exception $e) {
                $this->error("Lỗi khi gọi insights API trực tiếp: " . $e->getMessage());
            }
            
            // Test 5: Kiểm tra ad có tồn tại không
            $this->info("\n=== TEST 5: Kiểm tra Ad có tồn tại không ===");
            try {
                $adResponse = Http::get("https://graph.facebook.com/v23.0/{$adId}", [
                    'access_token' => $token,
                    'fields' => 'id,name,status,adset_id,campaign_id'
                ]);
                
                $this->info("Ad info response:");
                if ($adResponse->successful()) {
                    $this->line(json_encode($adResponse->json(), JSON_PRETTY_PRINT));
                } else {
                    $this->error("Ad not found or no access: " . $adResponse->body());
                }
                
            } catch (\Exception $e) {
                $this->error("Lỗi khi kiểm tra ad: " . $e->getMessage());
            }
            
            // Test 6: Thử với adset insights
            $this->info("\n=== TEST 6: Thử với Adset Insights ===");
            try {
                if ($ad->adset_id) {
                    $this->info("Thử lấy insights từ adset ID: " . $ad->adset_id);
                    
                    $adsetInsightsResponse = Http::get("https://graph.facebook.com/v23.0/{$ad->adset_id}/insights", [
                        'access_token' => $token,
                        'fields' => 'spend,reach,impressions,clicks',
                        'time_range' => json_encode([
                            'since' => date('Y-m-d', strtotime('-7 days')),
                            'until' => date('Y-m-d')
                        ])
                    ]);
                    
                    $this->info("Adset insights response:");
                    if ($adsetInsightsResponse->successful()) {
                        $this->line(json_encode($adsetInsightsResponse->json(), JSON_PRETTY_PRINT));
                    } else {
                        $this->error("Adset insights error: " . $adsetInsightsResponse->body());
                    }
                } else {
                    $this->warn("Ad không có adset_id!");
                }
                
            } catch (\Exception $e) {
                $this->error("Lỗi khi lấy adset insights: " . $e->getMessage());
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Lỗi khi test: " . $e->getMessage());
            $this->line("Stack trace: " . $e->getTraceAsString());
            Log::error("Lỗi test Facebook API", [
                'ad_id' => $adId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }
}
