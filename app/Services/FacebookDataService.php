<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FacebookAdInsight;
use App\Models\FacebookAd;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class FacebookDataService
{
    /**
     * Lấy dữ liệu Facebook theo filter
     */
    public function getFacebookData(array $filters): array
    {
        $pages = $this->getAvailablePages();
        $selectedPageId = $filters['page_id'] ?? null;
        
        $data = [
            'pages' => $pages,
            'selected_page' => null,
            'posts' => collect(),
            'spending_stats' => [],
        ];
        
        if ($selectedPageId) {
            $data['selected_page'] = $pages->firstWhere('id', $selectedPageId);
            $data['posts'] = $this->getPostsByPage($selectedPageId, $filters);
            $data['spending_stats'] = $this->getPostSpendingStats(
                $selectedPageId, 
                $filters['date_from'] ?? null, 
                $filters['date_to'] ?? null
            );
        }
        
        return $data;
    }

    /**
     * Lấy danh sách các Page có sẵn từ facebook_ad_insights
     */
    public function getAvailablePages(): Collection
    {
        $pages = FacebookAd::select('page_id as id')
            ->whereNotNull('page_id')
            ->whereHas('insights')
            ->groupBy('page_id')
            ->orderBy('page_id')
            ->get();
            
        // Tạo Eloquent Collection thay vì Support Collection
        return new \Illuminate\Database\Eloquent\Collection(
            $pages->map(function ($ad) {
                // Đếm số insights cho page này
                $insightsCount = FacebookAdInsight::join('facebook_ads', 'facebook_ad_insights.ad_id', '=', 'facebook_ads.id')
                    ->where('facebook_ads.page_id', $ad->id)
                    ->count();
                
                return (object) [
                    'id' => $ad->id,
                    'name' => 'Page ' . $ad->id,
                    'category' => 'Unknown',
                    'fan_count' => 0,
                    'ads_count' => $insightsCount
                ];
            })
        );
    }

    /**
     * Lấy danh sách bài viết theo Page từ facebook_ad_insights
     */
    public function getPostsByPage(string $pageId, array $filters = []): Collection
    {
        $query = FacebookAd::where('page_id', $pageId)
            ->whereNotNull('post_id')
            ->with(['insights' => function ($query) use ($filters) {
                if (!empty($filters['date_from'])) {
                    $query->where('date', '>=', $filters['date_from']);
                }
                if (!empty($filters['date_to'])) {
                    $query->where('date', '<=', $filters['date_to']);
                }
            }])
            ->with('creative');

        // Lọc theo trạng thái
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Tìm kiếm
        if (!empty($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }

        return $query->orderBy('created_time', 'desc')->get();
    }

    /**
     * Lấy thống kê chi phí theo bài viết từ facebook_ad_insights
     */
    public function getPostSpendingStats(string $pageId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = FacebookAdInsight::join('facebook_ads', 'facebook_ad_insights.ad_id', '=', 'facebook_ads.id')
            ->where('facebook_ads.page_id', $pageId)
            ->whereNotNull('facebook_ads.post_id');

        if ($dateFrom) {
            $query->where('facebook_ad_insights.date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('facebook_ad_insights.date', '<=', $dateTo);
        }

        $stats = $query->select(
                'facebook_ads.post_id',
                'facebook_ads.name as message',
                'facebook_ads.created_time',
                DB::raw('SUM(facebook_ad_insights.spend) as total_spend'),
                DB::raw('SUM(facebook_ad_insights.impressions) as total_impressions'),
                DB::raw('SUM(facebook_ad_insights.reach) as total_reach'),
                DB::raw('SUM(facebook_ad_insights.clicks) as total_clicks'),
                DB::raw('SUM(facebook_ad_insights.conversions) as total_conversions'),
                DB::raw('AVG(facebook_ad_insights.cpc) as avg_cpc'),
                DB::raw('AVG(facebook_ad_insights.cpm) as avg_cpm')
            )
            ->groupBy('facebook_ads.post_id', 'facebook_ads.name', 'facebook_ads.created_time')
            ->orderBy('total_spend', 'desc')
            ->get();

        return [
            'posts' => $stats,
            'summary' => [
                'total_spend' => $stats->sum('total_spend'),
                'total_impressions' => $stats->sum('total_impressions'),
                'total_reach' => $stats->sum('total_reach'),
                'total_clicks' => $stats->sum('total_clicks'),
                'total_conversions' => $stats->sum('total_conversions'),
                'avg_cpc' => $stats->avg('avg_cpc'),
                'avg_cpm' => $stats->avg('avg_cpm'),
            ]
        ];
    }

    /**
     * Lấy thống kê tổng quan theo Page
     */
    public function getPageOverview(string $pageId): array
    {
        $insights = FacebookAdInsight::join('facebook_ads', 'facebook_ad_insights.ad_id', '=', 'facebook_ads.id')
            ->where('facebook_ads.page_id', $pageId)
            ->select(
                DB::raw('SUM(spend) as total_spend'),
                DB::raw('SUM(impressions) as total_impressions'),
                DB::raw('SUM(reach) as total_reach'),
                DB::raw('SUM(clicks) as total_clicks'),
                DB::raw('SUM(conversions) as total_conversions'),
                DB::raw('AVG(cpc) as avg_cpc'),
                DB::raw('AVG(cpm) as avg_cpm'),
                DB::raw('AVG(ctr) as avg_ctr')
            )
            ->first();

        return [
            'total_spend' => $insights->total_spend ?? 0,
            'total_impressions' => $insights->total_impressions ?? 0,
            'total_reach' => $insights->total_reach ?? 0,
            'total_clicks' => $insights->total_clicks ?? 0,
            'total_conversions' => $insights->total_conversions ?? 0,
            'avg_cpc' => $insights->avg_cpc ?? 0,
            'avg_cpm' => $insights->avg_cpm ?? 0,
            'avg_ctr' => $insights->avg_ctr ?? 0,
        ];
    }
} 