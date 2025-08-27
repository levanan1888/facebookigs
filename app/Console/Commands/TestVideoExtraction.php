<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FacebookAdInsight;
use Illuminate\Console\Command;

class TestVideoExtraction extends Command
{
    protected $signature = 'test:video-extraction';
    protected $description = 'Test video metrics extraction with real data';

    public function handle(): int
    {
        $this->info("🔍 Testing video metrics extraction with real data...");
        
        $insight = FacebookAdInsight::first();
        if (!$insight) {
            $this->error("Không có insight data để test");
            return self::FAILURE;
        }

        $this->info("📋 Original Insight Data:");
        $this->line("  Ad ID: {$insight->ad_id}");
        $this->line("  Video Views: {$insight->video_views}");
        $this->line("  Video Plays: {$insight->video_plays}");
        
        $actions = json_decode($insight->actions, true);
        if (!$actions) {
            $this->error("Không có actions data");
            return self::FAILURE;
        }

        $this->info("📊 Actions Data:");
        foreach ($actions as $action) {
            $this->line("  {$action['action_type']}: {$action['value']}");
        }

        // Test extract function
        $videoMetrics = $this->extractVideoMetricsFromActions($actions);
        
        $this->info("🎥 Extracted Video Metrics:");
        foreach ($videoMetrics as $key => $value) {
            $this->line("  {$key}: {$value}");
        }

        // Update database với extracted data
        $this->info("💾 Updating database...");
        $insight->update([
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
        ]);

        $this->info("✅ Updated successfully!");
        
        // Verify
        $updatedInsight = FacebookAdInsight::find($insight->id);
        $this->info("📊 Updated Insight Data:");
        $this->line("  Video Views: {$updatedInsight->video_views}");
        $this->line("  Video Plays: {$updatedInsight->video_plays}");

        return self::SUCCESS;
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
                    $videoMetrics['video_plays'] = $value; // Sử dụng video_view làm video_plays
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
}





