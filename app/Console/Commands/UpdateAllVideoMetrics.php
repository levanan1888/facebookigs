<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FacebookAdInsight;
use Illuminate\Console\Command;

class UpdateAllVideoMetrics extends Command
{
    protected $signature = 'update:all-video-metrics';
    protected $description = 'Update all existing insights with video metrics from actions';

    public function handle(): int
    {
        $this->info("🔄 Updating all insights with video metrics...");
        
        $insights = FacebookAdInsight::all();
        $total = $insights->count();
        $updated = 0;
        $hasVideoData = 0;
        
        $this->info("📊 Total insights to process: {$total}");
        
        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();
        
        foreach ($insights as $insight) {
            $actions = json_decode($insight->actions, true);
            if ($actions) {
                $videoMetrics = $this->extractVideoMetricsFromActions($actions);
                
                // Chỉ update nếu có video data
                if ($videoMetrics['video_views'] > 0 || $videoMetrics['video_plays'] > 0) {
                    $hasVideoData++;
                    
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
                    $updated++;
                }
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine();
        
        $this->info("✅ Update completed!");
        $this->info("📊 Statistics:");
        $this->line("  Total insights processed: {$total}");
        $this->line("  Insights with video data: {$hasVideoData}");
        $this->line("  Insights updated: {$updated}");
        
        // Verify results
        $this->info("🔍 Verification:");
        $insightsWithVideo = FacebookAdInsight::where('video_views', '>', 0)->count();
        $this->line("  Insights with video_views > 0: {$insightsWithVideo}");
        
        $totalVideoViews = FacebookAdInsight::sum('video_views');
        $this->line("  Total video views: {$totalVideoViews}");
        
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





