<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FacebookAd;
use App\Models\FacebookAdInsight;
use App\Services\FacebookAdsService;
use App\Services\FacebookAdsSyncService;
use Illuminate\Console\Command;

class TestSaveAdInsights extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'facebook:test-save-ad-insights {ad_id : Facebook Ad ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test lưu insights cho một ad cụ thể vào database';

    /**
     * Execute the console command.
     */
    public function handle(FacebookAdsService $api, FacebookAdsSyncService $syncService): int
    {
        $adId = $this->argument('ad_id');
        
        $this->info("🔍 Testing save insights cho Ad ID: {$adId}");
        
        // Kiểm tra xem ad có tồn tại trong database không
        $facebookAd = FacebookAd::find($adId);
        
        if (!$facebookAd) {
            $this->error("❌ Ad ID {$adId} không tồn tại trong database!");
            $this->info("💡 Hãy chạy lệnh sync ads trước: php artisan facebook:sync-ads");
            return self::FAILURE;
        }
        
        $this->info("✅ Tìm thấy ad trong database: {$facebookAd->name}");
        
        // Lấy insights từ API
        $this->info("\n📊 Lấy insights từ Facebook API...");
        $adInsights = $api->getInsightsForAd($adId);
        
        if (isset($adInsights['error'])) {
            $this->error("❌ Lỗi khi lấy insights: " . json_encode($adInsights['error']));
            return self::FAILURE;
        }
        
        if (!isset($adInsights['data']) || empty($adInsights['data'])) {
            $this->warn("⚠️ Không có dữ liệu insights cho ad này");
            return self::SUCCESS;
        }
        
        $this->info("✅ Lấy được " . count($adInsights['data']) . " bản ghi insights");
        
        // Hiển thị dữ liệu mẫu
        $sample = $adInsights['data'][0];
        $this->info("\n📋 Dữ liệu mẫu:");
        foreach ($sample as $key => $value) {
            if (is_array($value)) {
                $this->line("  {$key}: " . json_encode($value));
            } else {
                $this->line("  {$key}: {$value}");
            }
        }
        
        // Lưu vào database
        $this->info("\n💾 Lưu insights vào database...");
        
        try {
            $result = ['ad_insights' => 0];
            
            // Lưu insights trực tiếp
            if (isset($adInsights['data']) && !empty($adInsights['data'])) {
                foreach ($adInsights['data'] as $insight) {
                    // Extract video metrics từ actions
                    $videoMetrics = $this->extractVideoMetricsFromActions($insight['actions'] ?? []);
                    
                    FacebookAdInsight::updateOrCreate(
                        [
                            'ad_id' => $facebookAd->id,
                            'date' => $insight['date'] ?? now()->toDateString(),
                        ],
                        [
                            'spend' => (float) ($insight['spend'] ?? 0),
                            'reach' => (int) ($insight['reach'] ?? 0),
                            'impressions' => (int) ($insight['impressions'] ?? 0),
                            'clicks' => (int) ($insight['clicks'] ?? 0),
                            'unique_clicks' => (int) ($insight['unique_clicks'] ?? 0),
                            'conversions' => (int) ($insight['conversions'] ?? 0),
                            'conversion_values' => (float) ($insight['conversion_values'] ?? 0),
                            'cost_per_conversion' => (float) ($insight['cost_per_conversion'] ?? 0),
                            'outbound_clicks' => (int) ($insight['outbound_clicks'] ?? 0),
                            'unique_outbound_clicks' => (int) ($insight['unique_outbound_clicks'] ?? 0),
                            'inline_link_clicks' => (int) ($insight['inline_link_clicks'] ?? 0),
                            'unique_inline_link_clicks' => (int) ($insight['unique_inline_link_clicks'] ?? 0),
                            'ctr' => (float) ($insight['ctr'] ?? 0),
                            'cpc' => (float) ($insight['cpc'] ?? 0),
                            'cpm' => (float) ($insight['cpm'] ?? 0),
                            'frequency' => (float) ($insight['frequency'] ?? 0),
                            'actions' => isset($insight['actions']) ? json_encode($insight['actions']) : null,
                            'action_values' => isset($insight['action_values']) ? json_encode($insight['action_values']) : null,
                            // Video metrics từ actions
                            'video_views' => $videoMetrics['video_views'],
                            'video_plays' => $videoMetrics['video_plays'],
                            'thruplays' => $videoMetrics['thruplays'],
                        ]
                    );
                    $result['ad_insights']++;
                }
            }
            
            $this->info("✅ Đã lưu thành công {$result['ad_insights']} bản ghi insights");
            
            // Kiểm tra dữ liệu đã lưu
            $savedInsights = FacebookAdInsight::where('ad_id', $facebookAd->id)->get();
            $this->info("📊 Tổng số insights trong database: " . $savedInsights->count());
            
            if ($savedInsights->count() > 0) {
                $latest = $savedInsights->sortByDesc('id')->first();
                $this->info("\n📋 Dữ liệu mới nhất đã lưu:");
                $this->line("  ID: {$latest->id}");
                $this->line("  Date: {$latest->date}");
                $this->line("  Spend: {$latest->spend}");
                $this->line("  Reach: {$latest->reach}");
                $this->line("  Impressions: {$latest->impressions}");
                $this->line("  Clicks: {$latest->clicks}");
                $this->line("  Video Views: {$latest->video_views}");
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Lỗi khi lưu insights: " . $e->getMessage());
            return self::FAILURE;
        }
        
        return self::SUCCESS;
    }
    
    /**
     * Extract video metrics từ actions array
     */
    private function extractVideoMetricsFromActions(array $actions): array
    {
        $videoMetrics = [
            'video_views' => 0,
            'video_plays' => 0,
            'thruplays' => 0,
        ];
        
        foreach ($actions as $action) {
            if (isset($action['action_type']) && isset($action['value'])) {
                switch ($action['action_type']) {
                    case 'video_view':
                        $videoMetrics['video_views'] = (int) $action['value'];
                        break;
                    case 'video_play':
                        $videoMetrics['video_plays'] = (int) $action['value'];
                        break;
                    case 'video_view_time':
                        // Có thể sử dụng để tính toán thời gian xem
                        break;
                }
            }
        }
        
        return $videoMetrics;
    }
}
