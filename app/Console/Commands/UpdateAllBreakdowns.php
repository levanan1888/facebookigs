<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FacebookAd;
use App\Models\FacebookAdInsight;
use App\Services\FacebookAdsService;
use Illuminate\Console\Command;

class UpdateAllBreakdowns extends Command
{
    protected $signature = 'update:all-breakdowns';
    protected $description = 'Update all existing insights with breakdown data';

    public function handle(): int
    {
        $this->info("🔄 Updating all insights with breakdown data...");
        
        $insights = FacebookAdInsight::all();
        $total = $insights->count();
        $updated = 0;
        
        $this->info("📊 Total insights to process: {$total}");
        
        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();
        
        $api = new FacebookAdsService();
        
        foreach ($insights as $insight) {
            $ad = FacebookAd::find($insight->ad_id);
            if (!$ad) {
                $progressBar->advance();
                continue;
            }
            
            $allBreakdowns = [];
            
            // Get Age/Gender breakdown
            $ageGenderBreakdown = $api->getInsightsWithAgeGenderBreakdown($ad->id, 'ad');
            if ($ageGenderBreakdown && !isset($ageGenderBreakdown['error'])) {
                $allBreakdowns = array_merge($allBreakdowns, $this->extractBreakdowns($ageGenderBreakdown['data'] ?? []));
            }
            
            // Get Region breakdown
            $regionBreakdown = $api->getInsightsWithRegionBreakdown($ad->id, 'ad');
            if ($regionBreakdown && !isset($regionBreakdown['error'])) {
                $allBreakdowns = array_merge($allBreakdowns, $this->extractBreakdowns($regionBreakdown['data'] ?? []));
            }
            
            // Get Action breakdown
            $actionBreakdown = $api->getInsightsWithActionBreakdown($ad->id, 'ad');
            if ($actionBreakdown && !isset($actionBreakdown['error'])) {
                $allBreakdowns = array_merge($allBreakdowns, $this->extractBreakdowns($actionBreakdown['data'] ?? []));
            }
            
            if (!empty($allBreakdowns)) {
                $insight->update([
                    'breakdowns' => json_encode($allBreakdowns)
                ]);
                $updated++;
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine();
        
        $this->info("✅ Update completed!");
        $this->info("📊 Statistics:");
        $this->line("  Total insights processed: {$total}");
        $this->line("  Insights updated: {$updated}");
        
        // Verify results
        $this->info("🔍 Verification:");
        $insightsWithBreakdowns = FacebookAdInsight::whereNotNull('breakdowns')->count();
        $this->line("  Insights with breakdowns: {$insightsWithBreakdowns}");
        
        return self::SUCCESS;
    }
    
    private function extractBreakdowns(array $data): array
    {
        $breakdowns = [];
        
        foreach ($data as $insight) {
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
        }
        
        return $breakdowns;
    }
}




