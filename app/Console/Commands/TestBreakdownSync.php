<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FacebookAd;
use App\Services\FacebookAdsService;
use Illuminate\Console\Command;

class TestBreakdownSync extends Command
{
    protected $signature = 'test:breakdown-sync {ad_id}';
    protected $description = 'Test breakdown sync for a specific ad';

    public function handle(): int
    {
        $adId = $this->argument('ad_id');
        
        $this->info("🔍 Testing breakdown sync cho Ad ID: {$adId}");
        
        $ad = FacebookAd::find($adId);
        if (!$ad) {
            $this->error("Ad ID {$adId} không tồn tại");
            return self::FAILURE;
        }
        
        $api = new FacebookAdsService();
        $result = [];
        
        // Test từng loại breakdown
        $this->info("📊 Testing Age/Gender Breakdown...");
        $ageGenderBreakdown = $api->getInsightsWithAgeGenderBreakdown($adId, 'ad');
        if ($ageGenderBreakdown && !isset($ageGenderBreakdown['error'])) {
            $this->processBreakdownData($ageGenderBreakdown, $ad, 'age_gender');
        } else {
            $this->error("Age/Gender breakdown failed: " . json_encode($ageGenderBreakdown['error'] ?? 'Unknown error'));
        }
        
        $this->info("📊 Testing Region Breakdown...");
        $regionBreakdown = $api->getInsightsWithRegionBreakdown($adId, 'ad');
        if ($regionBreakdown && !isset($regionBreakdown['error'])) {
            $this->processBreakdownData($regionBreakdown, $ad, 'region');
        } else {
            $this->error("Region breakdown failed: " . json_encode($regionBreakdown['error'] ?? 'Unknown error'));
        }
        
        $this->info("📊 Testing Action Breakdown...");
        $actionBreakdown = $api->getInsightsWithActionBreakdown($adId, 'ad');
        if ($actionBreakdown && !isset($actionBreakdown['error'])) {
            $this->processBreakdownData($actionBreakdown, $ad, 'action');
        } else {
            $this->error("Action breakdown failed: " . json_encode($actionBreakdown['error'] ?? 'Unknown error'));
        }
        
        return self::SUCCESS;
    }
    
    private function processBreakdownData(array $breakdownData, FacebookAd $ad, string $type): void
    {
        if (!isset($breakdownData['data']) || empty($breakdownData['data'])) {
            $this->warn("No data for {$type} breakdown");
            return;
        }
        
        $this->info("✅ Processing {$type} breakdown data...");
        $this->line("  Data count: " . count($breakdownData['data']));
        
        // Tìm insight theo ad_id (không theo date)
        $existingInsight = \App\Models\FacebookAdInsight::where('ad_id', $ad->id)->first();
        
        if (!$existingInsight) {
            $this->warn("  ⚠️ No existing insight found for ad_id: {$ad->id}");
            return;
        }
        
        $this->line("  ✅ Found existing insight with date: {$existingInsight->date}");
        
        $allBreakdowns = [];
        
        foreach ($breakdownData['data'] as $insight) {
            $breakdowns = [];
            
            // Extract breakdown info
            if (isset($insight['age'])) {
                $breakdowns[] = [
                    'dimension' => 'age',
                    'value' => $insight['age'],
                    'impressions' => (int) ($insight['impressions'] ?? 0),
                    'reach' => (int) ($insight['reach'] ?? 0),
                    'clicks' => (int) ($insight['clicks'] ?? 0),
                    'spend' => (float) ($insight['spend'] ?? 0),
                ];
            }
            
            if (isset($insight['gender'])) {
                $breakdowns[] = [
                    'dimension' => 'gender',
                    'value' => $insight['gender'],
                    'impressions' => (int) ($insight['impressions'] ?? 0),
                    'reach' => (int) ($insight['reach'] ?? 0),
                    'clicks' => (int) ($insight['clicks'] ?? 0),
                    'spend' => (float) ($insight['spend'] ?? 0),
                ];
            }
            
            if (isset($insight['region'])) {
                $breakdowns[] = [
                    'dimension' => 'region',
                    'value' => $insight['region'],
                    'impressions' => (int) ($insight['impressions'] ?? 0),
                    'reach' => (int) ($insight['reach'] ?? 0),
                    'clicks' => (int) ($insight['clicks'] ?? 0),
                    'spend' => (float) ($insight['spend'] ?? 0),
                ];
            }
            
            if (isset($insight['platform_position'])) {
                $breakdowns[] = [
                    'dimension' => 'platform_position',
                    'value' => $insight['platform_position'],
                    'impressions' => (int) ($insight['impressions'] ?? 0),
                    'reach' => (int) ($insight['reach'] ?? 0),
                    'clicks' => (int) ($insight['clicks'] ?? 0),
                    'spend' => (float) ($insight['spend'] ?? 0),
                ];
            }
            
            if (isset($insight['action_type'])) {
                $breakdowns[] = [
                    'dimension' => 'action_type',
                    'value' => $insight['action_type'],
                    'impressions' => (int) ($insight['impressions'] ?? 0),
                    'reach' => (int) ($insight['reach'] ?? 0),
                    'clicks' => (int) ($insight['clicks'] ?? 0),
                    'spend' => (float) ($insight['spend'] ?? 0),
                ];
            }
            
            $allBreakdowns = array_merge($allBreakdowns, $breakdowns);
        }
        
        if (!empty($allBreakdowns)) {
            // Update insight với tất cả breakdown data
            $existingInsight->update([
                'breakdowns' => json_encode($allBreakdowns)
            ]);
            $this->line("  ✅ Updated breakdowns with " . count($allBreakdowns) . " records");
            $this->line("  📋 Sample breakdown: " . json_encode($allBreakdowns[0] ?? [], JSON_PRETTY_PRINT));
        } else {
            $this->warn("  ⚠️ No breakdown data to save");
        }
    }
}
