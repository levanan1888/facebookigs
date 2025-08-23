<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FacebookAd;
use App\Models\FacebookAdInsight;
use App\Services\FacebookAdsService;
use Illuminate\Console\Command;

class TestFullSync extends Command
{
    protected $signature = 'test:full-sync {ad_id}';
    protected $description = 'Test full sync for a specific ad including video metrics and breakdowns';

    public function handle(): int
    {
        $adId = $this->argument('ad_id');
        
        $this->info("🔍 Testing full sync cho Ad ID: {$adId}");
        
        // Xóa insight cũ để test fresh
        FacebookAdInsight::where('ad_id', $adId)->delete();
        $this->info("🗑️ Deleted old insights for testing");
        
        $api = new FacebookAdsService();
        
        // Test 1. Ad Insights cơ bản
        $this->info("📊 1. Testing Ad Insights...");
        $adInsights = $api->getInsightsForAd($adId);
        if ($adInsights && !isset($adInsights['error'])) {
            $this->processAdInsights($adInsights, $adId);
        }
        
        // Test 2. Breakdown data
        $this->info("📊 2. Testing Breakdown Data...");
        $allBreakdowns = [];
        
        // Age/Gender breakdown
        $ageGenderBreakdown = $api->getInsightsWithAgeGenderBreakdown($adId, 'ad');
        if ($ageGenderBreakdown && !isset($ageGenderBreakdown['error'])) {
            $allBreakdowns = array_merge($allBreakdowns, $this->extractBreakdowns($ageGenderBreakdown['data'] ?? []));
        }
        
        // Region breakdown
        $regionBreakdown = $api->getInsightsWithRegionBreakdown($adId, 'ad');
        if ($regionBreakdown && !isset($regionBreakdown['error'])) {
            $allBreakdowns = array_merge($allBreakdowns, $this->extractBreakdowns($regionBreakdown['data'] ?? []));
        }
        
        // Action breakdown
        $actionBreakdown = $api->getInsightsWithActionBreakdown($adId, 'ad');
        if ($actionBreakdown && !isset($actionBreakdown['error'])) {
            $allBreakdowns = array_merge($allBreakdowns, $this->extractBreakdowns($actionBreakdown['data'] ?? []));
        }
        
        // Update breakdowns
        if (!empty($allBreakdowns)) {
            $existingInsight = FacebookAdInsight::where('ad_id', $adId)->first();
            if ($existingInsight) {
                $existingInsight->update(['breakdowns' => json_encode($allBreakdowns)]);
                $this->info("✅ Updated breakdowns: " . count($allBreakdowns) . " records");
            }
        }
        
        // Verify results
        $insight = FacebookAdInsight::where('ad_id', $adId)->first();
        if ($insight) {
            $this->info("🔍 Final Results:");
            $this->line("  Video Views: {$insight->video_views}");
            $this->line("  Video Plays: {$insight->video_plays}");
            
            $hasBreakdowns = $insight->breakdowns && $insight->breakdowns !== 'null';
            $this->line("  Has Breakdowns: " . ($hasBreakdowns ? 'Yes' : 'No'));
            
            if ($hasBreakdowns) {
                $breakdowns = json_decode($insight->breakdowns, true);
                $this->line("  Breakdowns count: " . count($breakdowns));
            }
        } else {
            $this->warn("⚠️ No insight created");
        }
        
        return self::SUCCESS;
    }
    
    private function processAdInsights(array $adInsights, string $adId): void
    {
        if (!isset($adInsights['data']) || empty($adInsights['data'])) {
            return;
        }

        foreach ($adInsights['data'] as $insight) {
            // Extract video metrics từ actions
            $videoMetrics = $this->extractVideoMetricsFromActions($insight['actions'] ?? []);
            
            FacebookAdInsight::updateOrCreate(
                [
                    'ad_id' => $adId,
                    'date' => $insight['date'] ?? now()->toDateString(),
                ],
                [
                    'spend' => (float) ($insight['spend'] ?? 0),
                    'reach' => (int) ($insight['reach'] ?? 0),
                    'impressions' => (int) ($insight['impressions'] ?? 0),
                    'clicks' => (int) ($insight['clicks'] ?? 0),
                    'unique_clicks' => (int) ($insight['unique_clicks'] ?? 0),
                    'ctr' => (float) ($insight['ctr'] ?? 0),
                    'cpc' => (float) ($insight['cpc'] ?? 0),
                    'cpm' => (float) ($insight['cpm'] ?? 0),
                    'frequency' => (float) ($insight['frequency'] ?? 0),
                    'actions' => isset($insight['actions']) ? json_encode($insight['actions']) : null,
                    'action_values' => isset($insight['action_values']) ? json_encode($insight['action_values']) : null,
                    // Video metrics từ actions
                    'video_views' => $videoMetrics['video_views'],
                    'video_plays' => $videoMetrics['video_plays'],
                    'video_plays_at_25' => $videoMetrics['video_plays_at_25'],
                    'video_plays_at_50' => $videoMetrics['video_plays_at_50'],
                    'video_plays_at_75' => $videoMetrics['video_plays_at_75'],
                    'video_plays_at_100' => $videoMetrics['video_plays_at_100'],
                    'video_p25_watched_actions' => $videoMetrics['video_p25_watched_actions'],
                    'video_p50_watched_actions' => $videoMetrics['video_p50_watched_actions'],
                    'video_p75_watched_actions' => $videoMetrics['video_p75_watched_actions'],
                    'video_p95_watched_actions' => $videoMetrics['video_p95_watched_actions'],
                    'video_p100_watched_actions' => $videoMetrics['video_p100_watched_actions'],
                    'thruplays' => $videoMetrics['thruplays'],
                ]
            );
        }
    }
    
    private function extractVideoMetricsFromActions(array $actions): array
    {
        $videoMetrics = [
            'video_views' => 0,
            'video_view_time' => 0,
            'video_avg_time_watched' => 0,
            'video_plays' => 0,
            'video_plays_at_25' => 0,
            'video_plays_at_50' => 0,
            'video_plays_at_75' => 0,
            'video_plays_at_100' => 0,
            'video_p25_watched_actions' => 0,
            'video_p50_watched_actions' => 0,
            'video_p75_watched_actions' => 0,
            'video_p95_watched_actions' => 0,
            'video_p100_watched_actions' => 0,
            'thruplays' => 0,
        ];

        foreach ($actions as $action) {
            $actionType = $action['action_type'] ?? '';
            $value = (int) ($action['value'] ?? 0);

            switch ($actionType) {
                case 'video_view':
                    $videoMetrics['video_views'] = $value;
                    $videoMetrics['video_plays'] = $value;
                    break;
                case 'video_play':
                    $videoMetrics['video_plays'] = $value;
                    break;
                case 'video_p25_watched_actions':
                    $videoMetrics['video_p25_watched_actions'] = $value;
                    $videoMetrics['video_plays_at_25'] = $value;
                    break;
                case 'video_p50_watched_actions':
                    $videoMetrics['video_p50_watched_actions'] = $value;
                    $videoMetrics['video_plays_at_50'] = $value;
                    break;
                case 'video_p75_watched_actions':
                    $videoMetrics['video_p75_watched_actions'] = $value;
                    $videoMetrics['video_plays_at_75'] = $value;
                    break;
                case 'video_p95_watched_actions':
                    $videoMetrics['video_p95_watched_actions'] = $value;
                    break;
                case 'video_p100_watched_actions':
                    $videoMetrics['video_p100_watched_actions'] = $value;
                    $videoMetrics['video_plays_at_100'] = $value;
                    break;
                case 'video_thruplay_watched_actions':
                case 'thruplay':
                    $videoMetrics['thruplays'] = $value;
                    break;
                case 'video_avg_time_watched_actions':
                    $videoMetrics['video_avg_time_watched'] = (float) $value;
                    break;
                case 'video_view_time':
                    $videoMetrics['video_view_time'] = $value;
                    break;
            }
        }

        return $videoMetrics;
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
