<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FacebookAd;
use App\Models\FacebookAdAccount;
use App\Models\FacebookAdSet;
use App\Models\FacebookBusiness;
use App\Models\FacebookCampaign;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UnifiedDataService
{
    public function getUnifiedData(): array
    {
        $startDate = now()->subDays(30)->toDateString();
        
        // Sử dụng FacebookAd làm trung tâm
        $totals = [
            'businesses' => FacebookBusiness::count(),
            'accounts' => FacebookAdAccount::count(),
            'campaigns' => FacebookCampaign::count(),
            'adsets' => FacebookAdSet::count(),
            'ads' => FacebookAd::count(),
            'pages' => FacebookAd::whereNotNull('page_id')->distinct('page_id')->count(),
            'posts' => FacebookAd::whereNotNull('post_id')->distinct('post_id')->count(),
            'insights' => FacebookAd::whereNotNull('last_insights_sync')->count(),
            'spend' => FacebookAd::where('last_insights_sync', '>=', $startDate)->sum('ad_spend') ?? 0,
            'impressions' => FacebookAd::where('last_insights_sync', '>=', $startDate)->sum('ad_impressions') ?? 0,
            'clicks' => FacebookAd::where('last_insights_sync', '>=', $startDate)->sum('ad_clicks') ?? 0,
            'reach' => FacebookAd::where('last_insights_sync', '>=', $startDate)->sum('ad_reach') ?? 0,
            'ctr' => FacebookAd::where('last_insights_sync', '>=', $startDate)->avg('ad_ctr') ?? 0,
            'cpc' => FacebookAd::where('last_insights_sync', '>=', $startDate)->avg('ad_cpc') ?? 0,
            'cpm' => FacebookAd::where('last_insights_sync', '>=', $startDate)->avg('ad_cpm') ?? 0,
        ];

        // Time series data
        $timeSeries = collect(range(6, 0))->map(function ($daysAgo) {
            $date = now()->subDays($daysAgo)->toDateString();
            return [
                'date' => $date,
                'spend' => FacebookAd::whereDate('last_insights_sync', $date)->sum('ad_spend') ?? 0,
                'impressions' => FacebookAd::whereDate('last_insights_sync', $date)->sum('ad_impressions') ?? 0,
                'clicks' => FacebookAd::whereDate('last_insights_sync', $date)->sum('ad_clicks') ?? 0,
                'reach' => FacebookAd::whereDate('last_insights_sync', $date)->sum('ad_reach') ?? 0,
                'posts' => FacebookAd::whereDate('last_insights_sync', $date)->whereNotNull('post_id')->count(),
            ];
        });

        // Top posts
        $topPosts = FacebookAd::whereNotNull('post_id')
            ->orderByRaw('(post_likes + post_shares + post_comments) DESC')
            ->limit(10)
            ->get([
                'post_id', 'post_message', 'post_type', 'post_likes', 'post_shares', 'post_comments',
                'post_impressions', 'post_reach', 'ad_spend'
            ]);

        return [
            'totals' => $totals,
            'timeSeries' => $timeSeries,
            'topPosts' => $topPosts,
        ];
    }

    public function getComparisonData(): array
    {
        $startDate = now()->subDays(30)->toDateString();
        $previousStartDate = now()->subDays(60)->toDateString();
        
        // Current period
        $current = [
            'spend' => FacebookAd::where('last_insights_sync', '>=', $startDate)->sum('ad_spend') ?? 0,
            'impressions' => FacebookAd::where('last_insights_sync', '>=', $startDate)->sum('ad_impressions') ?? 0,
            'clicks' => FacebookAd::where('last_insights_sync', '>=', $startDate)->sum('ad_clicks') ?? 0,
            'reach' => FacebookAd::where('last_insights_sync', '>=', $startDate)->sum('ad_reach') ?? 0,
        ];

        // Previous period
        $previous = [
            'spend' => FacebookAd::where('last_insights_sync', '>=', $previousStartDate)
                ->where('last_insights_sync', '<', $startDate)->sum('ad_spend') ?? 0,
            'impressions' => FacebookAd::where('last_insights_sync', '>=', $previousStartDate)
                ->where('last_insights_sync', '<', $startDate)->sum('ad_impressions') ?? 0,
            'clicks' => FacebookAd::where('last_insights_sync', '>=', $previousStartDate)
                ->where('last_insights_sync', '<', $startDate)->sum('ad_clicks') ?? 0,
            'reach' => FacebookAd::where('last_insights_sync', '>=', $previousStartDate)
                ->where('last_insights_sync', '<', $startDate)->sum('ad_reach') ?? 0,
        ];

        return [
            'current' => $current,
            'previous' => $previous,
        ];
    }

    public function getFilteredData(array $filters): array
    {
        $query = FacebookAd::query();

        if (isset($filters['date_from'])) {
            $query->where('last_insights_sync', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('last_insights_sync', '<=', $filters['date_to']);
        }

        if (isset($filters['account_id'])) {
            $query->where('account_id', $filters['account_id']);
        }

        if (isset($filters['campaign_id'])) {
            $query->where('campaign_id', $filters['campaign_id']);
        }

        $data = $query->get();

        return [
            'total_ads' => $data->count(),
            'total_posts' => $data->whereNotNull('post_id')->count(),
            'total_spend' => $data->sum('ad_spend'),
            'total_impressions' => $data->sum('ad_impressions'),
            'avg_ctr' => $data->avg('ad_ctr'),
            'data' => $data,
        ];
    }

    public function getDataSourcesStatus(): array
    {
        return [
            'facebook' => [
                'connected' => true,
                'last_sync' => FacebookAd::max('last_insights_sync'),
                'data_count' => FacebookAd::count(),
            ],
            'google' => [
                'connected' => false,
                'last_sync' => null,
                'data_count' => 0,
            ],
            'tiktok' => [
                'connected' => false,
                'last_sync' => null,
                'data_count' => 0,
            ],
        ];
    }

    public function getDailyStats(string $date): array
    {
        $insights = FacebookAd::whereDate('last_insights_sync', $date)->first();
        $posts = FacebookAd::whereDate('last_insights_sync', $date)->whereNotNull('post_id')->get();

        return [
            'date' => $date,
            'insights' => $insights ? [
                'spend' => $insights->ad_spend ?? 0,
                'impressions' => $insights->ad_impressions ?? 0,
                'clicks' => $insights->ad_clicks ?? 0,
                'reach' => $insights->ad_reach ?? 0,
            ] : null,
            'posts' => $posts->map(function ($post) {
                return [
                    'post_id' => $post->post_id,
                    'message' => $post->post_message,
                    'type' => $post->post_type,
                    'likes' => $post->post_likes ?? 0,
                    'shares' => $post->post_shares ?? 0,
                    'comments' => $post->post_comments ?? 0,
                ];
            }),
        ];
    }

    public function getAnalyticsSummary(): array
    {
        $startDate = now()->subDays(30)->toDateString();
        
        return [
            'total_spend' => FacebookAd::where('last_insights_sync', '>=', $startDate)->sum('ad_spend') ?? 0,
            'total_impressions' => FacebookAd::where('last_insights_sync', '>=', $startDate)->sum('ad_impressions') ?? 0,
            'avg_ctr' => FacebookAd::where('last_insights_sync', '>=', $startDate)->avg('ad_ctr') ?? 0,
            'total_posts' => FacebookAd::where('last_insights_sync', '>=', $startDate)->whereNotNull('post_id')->count(),
            'total_engagement' => FacebookAd::where('last_insights_sync', '>=', $startDate)
                ->whereNotNull('post_id')
                ->sum(DB::raw('post_likes + post_shares + post_comments')),
        ];
    }

    public function getInsightsData(array $filters = []): array
    {
        $query = FacebookAd::query();

        if (isset($filters['date_from'])) {
            $query->where('last_insights_sync', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('last_insights_sync', '<=', $filters['date_to']);
        }

        return $query->get()->toArray();
    }
}
