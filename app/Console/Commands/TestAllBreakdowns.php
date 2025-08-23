<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FacebookAd;
use App\Services\FacebookAdsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestAllBreakdowns extends Command
{
    protected $signature = 'test:all-breakdowns {ad_id?}';
    protected $description = 'Test tất cả các breakdown mà Facebook cho phép';

    private FacebookAdsService $facebookAdsService;

    public function __construct(FacebookAdsService $facebookAdsService)
    {
        parent::__construct();
        $this->facebookAdsService = $facebookAdsService;
    }

    public function handle(): int
    {
        $adId = $this->argument('ad_id');
        
        if (!$adId) {
            // Lấy ad ID đầu tiên từ database
            $ad = FacebookAd::first();
            if (!$ad) {
                $this->error('❌ Không tìm thấy ad nào trong database');
                return 1;
            }
            $adId = $ad->ad_id;
        }

        $this->info("🔍 Testing tất cả breakdowns cho Ad ID: {$adId}");

        // Test các breakdown chính
        $breakdowns = [
            'Demographics' => [
                'Age/Gender' => fn() => $this->facebookAdsService->getInsightsWithAgeGenderBreakdown($adId),
                'Age only' => fn() => $this->facebookAdsService->getInsightsForAdWithBreakdowns($adId, ['age']),
                'Gender only' => fn() => $this->facebookAdsService->getInsightsForAdWithBreakdowns($adId, ['gender'])
            ],
            'Geographic' => [
                'Country' => fn() => $this->facebookAdsService->getInsightsWithCountryBreakdown($adId),
                'Region' => fn() => $this->facebookAdsService->getInsightsWithRegionBreakdown($adId),
                'DMA (US only)' => fn() => $this->facebookAdsService->getInsightsWithDMABreakdown($adId)
            ],
            'Platform' => [
                'Publisher Platform' => fn() => $this->facebookAdsService->getInsightsWithPublisherPlatformBreakdown($adId),
                'Platform Position' => fn() => $this->facebookAdsService->getInsightsWithPlatformPositionBreakdown($adId),
                'Device Platform' => fn() => $this->facebookAdsService->getInsightsWithDevicePlatformBreakdown($adId),
                'Impression Device' => fn() => $this->facebookAdsService->getInsightsWithImpressionDeviceBreakdown($adId)
            ],
            'Time' => [
                'Hourly (Advertiser TZ)' => fn() => $this->facebookAdsService->getInsightsWithHourlyStatsAdvertiserBreakdown($adId),
                'Hourly (Audience TZ)' => fn() => $this->facebookAdsService->getInsightsWithHourlyStatsAudienceBreakdown($adId)
            ],
            'Actions' => [
                'Action Type' => fn() => $this->facebookAdsService->getInsightsWithActionBreakdown($adId, 'ad', ['action_type']),
                'Action Device' => fn() => $this->facebookAdsService->getInsightsWithActionBreakdown($adId, 'ad', ['action_device']),
                'Action Reaction' => fn() => $this->facebookAdsService->getInsightsWithActionReactionBreakdown($adId),
                'Action Video Sound' => fn() => $this->facebookAdsService->getInsightsWithActionVideoSoundBreakdown($adId),
                'Action Video Type' => fn() => $this->facebookAdsService->getInsightsWithActionVideoTypeBreakdown($adId),
                'Action Destination' => fn() => $this->facebookAdsService->getInsightsWithActionDestinationBreakdown($adId),
                'Action Target ID' => fn() => $this->facebookAdsService->getInsightsWithActionTargetIdBreakdown($adId)
            ],
            'Campaign Specific' => [
                'Product ID' => fn() => $this->facebookAdsService->getInsightsWithProductIdBreakdown($adId),
                'User Segment' => fn() => $this->facebookAdsService->getInsightsWithUserSegmentBreakdown($adId),
                'Place Page ID' => fn() => $this->facebookAdsService->getInsightsWithPlacePageBreakdown($adId),
                'Frequency Value' => fn() => $this->facebookAdsService->getInsightsWithFrequencyValueBreakdown($adId)
            ],
            'Carousel/Canvas' => [
                'Carousel Card ID' => fn() => $this->facebookAdsService->getInsightsWithCarouselCardBreakdown($adId),
                'Carousel Card Name' => fn() => $this->facebookAdsService->getInsightsWithCarouselCardNameBreakdown($adId),
                'Canvas Component' => fn() => $this->facebookAdsService->getInsightsWithCanvasComponentBreakdown($adId)
            ],
            'App Tracking' => [
                'App ID' => fn() => $this->facebookAdsService->getInsightsWithAppIdBreakdown($adId),
                'SKAN Conversion ID' => fn() => $this->facebookAdsService->getInsightsWithSkanConversionIdBreakdown($adId),
                'SKAN Campaign ID' => fn() => $this->facebookAdsService->getInsightsWithSkanCampaignIdBreakdown($adId),
                'Conversion ID Modeled' => fn() => $this->facebookAdsService->getInsightsWithConversionIdModeledBreakdown($adId)
            ],
            'Dynamic Creative' => [
                'Video Asset' => fn() => $this->facebookAdsService->getInsightsWithDynamicCreativeBreakdown($adId, 'video_asset'),
                'Image Asset' => fn() => $this->facebookAdsService->getInsightsWithDynamicCreativeBreakdown($adId, 'image_asset'),
                'Title Asset' => fn() => $this->facebookAdsService->getInsightsWithDynamicCreativeBreakdown($adId, 'title_asset'),
                'Body Asset' => fn() => $this->facebookAdsService->getInsightsWithDynamicCreativeBreakdown($adId, 'body_asset')
            ]
        ];

        $results = [];
        $totalTests = 0;
        $successfulTests = 0;

        foreach ($breakdowns as $category => $categoryBreakdowns) {
            $this->info("\n📊 Testing {$category}:");
            
            foreach ($categoryBreakdowns as $breakdownName => $callback) {
                $totalTests++;
                $this->line("  Testing {$breakdownName}...");
                
                try {
                    $response = $callback();
                    
                    if (isset($response['error'])) {
                        $this->line("    ❌ Error: " . ($response['error']['message'] ?? 'Unknown error'));
                        $results[$category][$breakdownName] = [
                            'status' => 'error',
                            'error' => $response['error']['message'] ?? 'Unknown error'
                        ];
                    } else {
                        $dataCount = count($response['data'] ?? []);
                        $this->line("    ✅ Success: {$dataCount} records");
                        $results[$category][$breakdownName] = [
                            'status' => 'success',
                            'data_count' => $dataCount
                        ];
                        $successfulTests++;
                    }
                } catch (\Exception $e) {
                    $this->line("    ❌ Exception: " . $e->getMessage());
                    $results[$category][$breakdownName] = [
                        'status' => 'exception',
                        'error' => $e->getMessage()
                    ];
                }
                
                // Delay để tránh rate limit
                usleep(500000); // 0.5 giây
            }
        }

        // Hiển thị tổng kết
        $this->info("\n📈 Tổng kết:");
        $this->info("  Tổng số tests: {$totalTests}");
        $this->info("  Thành công: {$successfulTests}");
        $this->info("  Thất bại: " . ($totalTests - $successfulTests));
        $this->info("  Tỷ lệ thành công: " . round(($successfulTests / $totalTests) * 100, 2) . "%");

        // Hiển thị chi tiết từng category
        $this->info("\n📋 Chi tiết theo category:");
        foreach ($results as $category => $categoryResults) {
            $categorySuccess = 0;
            $categoryTotal = count($categoryResults);
            
            foreach ($categoryResults as $breakdownName => $result) {
                if ($result['status'] === 'success') {
                    $categorySuccess++;
                }
            }
            
            $this->line("  {$category}: {$categorySuccess}/{$categoryTotal} thành công");
        }

        // Hiển thị danh sách breakdowns có sẵn
        $this->info("\n📚 Danh sách tất cả breakdowns có sẵn:");
        $availableBreakdowns = $this->facebookAdsService->getAllAvailableBreakdowns();
        
        foreach ($availableBreakdowns as $category => $breakdownList) {
            $this->line("  {$category}:");
            foreach ($breakdownList as $key => $description) {
                $this->line("    - {$key}: {$description}");
            }
        }

        return 0;
    }
}


