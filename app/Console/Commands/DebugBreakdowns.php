<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FacebookAdInsight;
use App\Services\FacebookAdsService;
use Illuminate\Console\Command;

class DebugBreakdowns extends Command
{
    protected $signature = 'debug:breakdowns {ad_id?}';
    protected $description = 'Debug breakdown data processing';

    public function handle(): int
    {
        $adId = $this->argument('ad_id');
        
        if ($adId) {
            $this->debugSingleAdBreakdowns($adId);
        } else {
            $this->debugAllBreakdowns();
        }

        return self::SUCCESS;
    }

    private function debugSingleAdBreakdowns(string $adId): void
    {
        $this->info("🔍 Debug breakdowns cho Ad ID: {$adId}");
        
        $api = new FacebookAdsService();
        
        // Test các loại breakdown
        $this->info("📊 Testing Age/Gender Breakdown...");
        $ageGenderBreakdown = $api->getInsightsWithAgeGenderBreakdown($adId, 'ad');
        $this->displayBreakdownResponse('Age/Gender', $ageGenderBreakdown);
        
        $this->info("📊 Testing Region Breakdown...");
        $regionBreakdown = $api->getInsightsWithRegionBreakdown($adId, 'ad');
        $this->displayBreakdownResponse('Region', $regionBreakdown);
        
        $this->info("📊 Testing Platform Position Breakdown...");
        $platformBreakdown = $api->getInsightsWithPlatformPositionBreakdown($adId, 'ad');
        $this->displayBreakdownResponse('Platform Position', $platformBreakdown);
        
        $this->info("📊 Testing Action Breakdown...");
        $actionBreakdown = $api->getInsightsWithActionBreakdown($adId, 'ad');
        $this->displayBreakdownResponse('Action', $actionBreakdown);
    }

    private function debugAllBreakdowns(): void
    {
        $this->info("🔍 Debug tất cả breakdown data...");
        
        $insights = FacebookAdInsight::all();
        $total = $insights->count();
        $hasBreakdowns = 0;
        
        foreach ($insights as $insight) {
            if ($insight->breakdowns && $insight->breakdowns !== 'null') {
                $hasBreakdowns++;
                $breakdowns = json_decode($insight->breakdowns, true);
                if ($breakdowns && !empty($breakdowns)) {
                    $this->info("📋 Ad ID: {$insight->ad_id} có breakdowns:");
                    $this->line("  " . json_encode($breakdowns, JSON_PRETTY_PRINT));
                    break; // Chỉ hiển thị 1 sample
                }
            }
        }
        
        $this->info("📊 Statistics:");
        $this->line("  Total insights: {$total}");
        $this->line("  Insights with breakdowns: {$hasBreakdowns}");
        
        if ($hasBreakdowns === 0) {
            $this->warn("❌ Không có breakdown data nào được lưu!");
            $this->info("🔍 Có thể do:");
            $this->line("  1. Breakdown API calls không thành công");
            $this->line("  2. Logic process breakdowns có lỗi");
            $this->line("  3. Model không có breakdowns trong fillable");
        }
    }

    private function displayBreakdownResponse(string $type, array $response): void
    {
        if (isset($response['error'])) {
            $this->error("❌ {$type} Breakdown Error:");
            $this->line("  " . json_encode($response['error'], JSON_PRETTY_PRINT));
        } else {
            $this->info("✅ {$type} Breakdown Success:");
            $this->line("  Data count: " . (isset($response['data']) ? count($response['data']) : 0));
            
            if (isset($response['data']) && !empty($response['data'])) {
                $sample = $response['data'][0];
                $this->line("  Sample data keys: " . implode(', ', array_keys($sample)));
                
                if (isset($sample['breakdowns'])) {
                    $this->line("  Breakdowns: " . json_encode($sample['breakdowns'], JSON_PRETTY_PRINT));
                }
            }
        }
        $this->newLine();
    }
}




