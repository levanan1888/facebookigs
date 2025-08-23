<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FacebookAdInsight;
use App\Models\FacebookAd;
use Illuminate\Console\Command;

class DebugAdInsights extends Command
{
    protected $signature = 'debug:ad-insights {ad_id?}';
    protected $description = 'Debug Ad Insights data processing';

    public function handle(): int
    {
        $adId = $this->argument('ad_id');
        
        if ($adId) {
            $ad = FacebookAd::find($adId);
            if (!$ad) {
                $this->error("Ad ID {$adId} không tồn tại");
                return self::FAILURE;
            }
            $this->debugSingleAd($ad);
        } else {
            $this->debugAllAdInsights();
        }

        return self::SUCCESS;
    }

    private function debugSingleAd(FacebookAd $ad): void
    {
        $this->info("🔍 Debug Ad: {$ad->id} - {$ad->name}");
        
        $insight = FacebookAdInsight::where('ad_id', $ad->id)->first();
        if (!$insight) {
            $this->warn("Không có insight data cho ad này");
            return;
        }

        $this->info("📊 Insight Data:");
        $this->line("  Date: {$insight->date}");
        $this->line("  Spend: {$insight->spend}");
        $this->line("  Reach: {$insight->reach}");
        $this->line("  Impressions: {$insight->impressions}");
        $this->line("  Clicks: {$insight->clicks}");
        
        $this->info("🎥 Video Metrics:");
        $this->line("  Video Views: {$insight->video_views}");
        $this->line("  Video Plays: {$insight->video_plays}");
        $this->line("  Video Plays at 25%: {$insight->video_plays_at_25}");
        $this->line("  Video Plays at 50%: {$insight->video_plays_at_50}");
        $this->line("  Video Plays at 75%: {$insight->video_plays_at_75}");
        $this->line("  Video Plays at 100%: {$insight->video_plays_at_100}");
        $this->line("  Thruplays: {$insight->thruplays}");
        
        $this->info("📋 Actions Data:");
        $actions = json_decode($insight->actions, true);
        if ($actions) {
            foreach ($actions as $action) {
                $this->line("  {$action['action_type']}: {$action['value']}");
            }
        } else {
            $this->line("  Actions: NULL hoặc empty");
        }
        
        $this->info("📊 Breakdowns:");
        $breakdowns = json_decode($insight->breakdowns, true);
        if ($breakdowns) {
            $this->line("  Breakdowns: " . json_encode($breakdowns, JSON_PRETTY_PRINT));
        } else {
            $this->line("  Breakdowns: NULL hoặc empty");
        }
    }

    private function debugAllAdInsights(): void
    {
        $this->info("🔍 Debug tất cả Ad Insights");
        
        $insights = FacebookAdInsight::all();
        $this->info("Tổng số insights: " . $insights->count());
        
        $hasVideoData = 0;
        $hasActions = 0;
        $hasBreakdowns = 0;
        
        foreach ($insights as $insight) {
            if ($insight->video_views > 0 || $insight->video_plays > 0) {
                $hasVideoData++;
            }
            if ($insight->actions && $insight->actions !== 'null') {
                $hasActions++;
            }
            if ($insight->breakdowns && $insight->breakdowns !== 'null') {
                $hasBreakdowns++;
            }
        }
        
        $this->info("📊 Thống kê:");
        $this->line("  Insights có video data: {$hasVideoData}");
        $this->line("  Insights có actions: {$hasActions}");
        $this->line("  Insights có breakdowns: {$hasBreakdowns}");
        
        // Kiểm tra sample data
        $sampleInsight = $insights->first();
        if ($sampleInsight) {
            $this->info("📋 Sample Insight Data:");
            $this->line("  Ad ID: {$sampleInsight->ad_id}");
            $this->line("  Date: {$sampleInsight->date}");
            $this->line("  Spend: {$sampleInsight->spend}");
            $this->line("  Video Views: {$sampleInsight->video_views}");
            $this->line("  Actions: " . ($sampleInsight->actions ?: 'NULL'));
        }
    }
}


