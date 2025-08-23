<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FacebookAd;
use App\Services\FacebookAdsService;
use Illuminate\Console\Command;

class TestSingleAdInsights extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'facebook:test-single-ad {ad_id : Facebook Ad ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test lấy insights cho một ad cụ thể';

    /**
     * Execute the console command.
     */
    public function handle(FacebookAdsService $api): int
    {
        $adId = $this->argument('ad_id');
        
        $this->info("🔍 Testing insights cho Ad ID: {$adId}");
        
        // Test Ad Insights
        $this->info("\n📊 Testing Ad Insights...");
        $adInsights = $api->getInsightsForAd($adId);
        
        $this->info("Ad Insights Response:");
        $this->line("Has error: " . (isset($adInsights['error']) ? 'Yes' : 'No'));
        if (isset($adInsights['error'])) {
            $this->error("Error: " . json_encode($adInsights['error']));
        } else {
            $this->info("Data count: " . (isset($adInsights['data']) ? count($adInsights['data']) : 0));
            if (isset($adInsights['data']) && !empty($adInsights['data'])) {
                $this->info("Sample data:");
                $sample = $adInsights['data'][0];
                foreach ($sample as $key => $value) {
                    $this->line("  {$key}: " . (is_array($value) ? json_encode($value) : $value));
                }
            }
        }
        
        // Test Post Insights (nếu có post)
        $this->info("\n📝 Testing Post Insights...");
        
        // Lấy ad details để tìm post_id
        $adDetails = $api->getAdDetails($adId);
        
        $this->info("Ad Details Response:");
        $this->line("Has error: " . (isset($adDetails['error']) ? 'Yes' : 'No'));
        if (isset($adDetails['error'])) {
            $this->error("Error: " . json_encode($adDetails['error']));
        } else {
            $this->info("Ad Details:");
            foreach ($adDetails as $key => $value) {
                $this->line("  {$key}: " . (is_array($value) ? json_encode($value) : $value));
            }
        }
        
        if (isset($adDetails['creative']['object_story_id'])) {
            $storyId = $adDetails['creative']['object_story_id'];
            $this->info("Found story_id: {$storyId}");
            $parts = explode('_', $storyId);
            if (count($parts) >= 2) {
                $postId = $parts[1];
                $pageId = $parts[0];
                $fullPostId = $storyId; // Sử dụng story_id đầy đủ
                $this->info("Extracted Post ID: {$postId}");
                $this->info("Page ID: {$pageId}");
                $this->info("Full Post ID: {$fullPostId}");
                
                $postInsights = $api->getPostInsightsExtended($fullPostId);
                
                $this->info("Post Insights Response:");
                $this->line("Has error: " . (isset($postInsights['error']) ? 'Yes' : 'No'));
                if (isset($postInsights['error'])) {
                    $this->error("Error: " . json_encode($postInsights['error']));
                } else {
                    $this->info("Data count: " . (isset($postInsights['data']) ? count($postInsights['data']) : 0));
                    if (isset($postInsights['data']) && !empty($postInsights['data'])) {
                        $this->info("Sample data:");
                        $sample = $postInsights['data'][0];
                        foreach ($sample as $key => $value) {
                            $this->line("  {$key}: " . (is_array($value) ? json_encode($value) : $value));
                        }
                    }
                }
            } else {
                $this->warn("Could not extract post ID from story_id: {$storyId}");
            }
        } else {
            $this->warn("No post found for this ad");
        }
        
        // Test breakdown insights
        $this->info("\n📈 Testing Breakdown Insights...");
        
        $ageGenderInsights = $api->getInsightsWithAgeGenderBreakdown($adId, 'ad');
        $this->info("Age/Gender Breakdown:");
        $this->line("Has error: " . (isset($ageGenderInsights['error']) ? 'Yes' : 'No'));
        if (isset($ageGenderInsights['data'])) {
            $this->info("Data count: " . count($ageGenderInsights['data']));
        }
        
        $regionInsights = $api->getInsightsWithRegionBreakdown($adId, 'ad');
        $this->info("Region Breakdown:");
        $this->line("Has error: " . (isset($regionInsights['error']) ? 'Yes' : 'No'));
        if (isset($regionInsights['data'])) {
            $this->info("Data count: " . count($regionInsights['data']));
        }
        
        $platformInsights = $api->getInsightsWithPlatformPositionBreakdown($adId, 'ad');
        $this->info("Platform Position Breakdown:");
        $this->line("Has error: " . (isset($platformInsights['error']) ? 'Yes' : 'No'));
        if (isset($platformInsights['data'])) {
            $this->info("Data count: " . count($platformInsights['data']));
        }
        
        return self::SUCCESS;
    }
}