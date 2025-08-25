<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestVideoMetricsExtraction extends Command
{
    protected $signature = 'test:video-metrics';
    protected $description = 'Test extraction of video metrics from actions';

    public function handle(): int
    {
        // Sample actions data từ Ad Insights
        $sampleActions = [
            ["action_type" => "onsite_conversion.total_messaging_connection", "value" => "4"],
            ["action_type" => "onsite_app_purchase", "value" => "1"],
            ["action_type" => "page_engagement", "value" => "237"],
            ["action_type" => "post_engagement", "value" => "235"],
            ["action_type" => "comment", "value" => "1"],
            ["action_type" => "video_view", "value" => "225"],
            ["action_type" => "post_reaction", "value" => "1"]
        ];

        $this->info("🔍 Testing video metrics extraction...");
        $this->info("Sample actions data:");
        foreach ($sampleActions as $action) {
            $this->line("  {$action['action_type']}: {$action['value']}");
        }

        // Extract video metrics
        $videoMetrics = $this->extractVideoMetricsFromActions($sampleActions);

        $this->info("\n📊 Extracted video metrics:");
        foreach ($videoMetrics as $key => $value) {
            $this->line("  {$key}: {$value}");
        }

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




