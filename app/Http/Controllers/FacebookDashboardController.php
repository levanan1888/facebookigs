<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FacebookAd;
use App\Models\FacebookAdAccount;
use App\Models\FacebookAdSet;
use App\Models\FacebookBusiness;
use App\Models\FacebookCampaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class FacebookDashboardController extends Controller
{
    public function overview(Request $request): View
    {
        $data = $this->getOverviewData($request);
        return view('facebook.dashboard.overview', compact('data'));
    }

    public function hierarchy(Request $request): View
    {
        $data = $this->getHierarchyData();
        return view('facebook.dashboard.hierarchy', compact('data'));
    }

    public function analytics(Request $request): View
    {
        $data = $this->getAnalyticsData();
        return view('facebook.dashboard.analytics', compact('data'));
    }

    public function dataRaw(Request $request): View
    {
        $data = $this->getRawData();
        return view('facebook.dashboard.data-raw', compact('data'));
    }

    private function getOverviewData(Request $request = null): array
    {
        $request = $request ?? request();
        $from = $request->get('from');
        $to = $request->get('to');
        if (!$from || !$to) {
            $to = now()->toDateString();
            $from = now()->subDays(29)->toDateString();
        }
        $selectedAccountId = $request->get('account_id');
        $selectedCampaignId = $request->get('campaign_id');

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
        ];

        $adIdsQuery = FacebookAd::query();
        if ($selectedAccountId) {
            $adIdsQuery->where('account_id', $selectedAccountId);
        }
        if ($selectedCampaignId) {
            $adIdsQuery->where('campaign_id', $selectedCampaignId);
        }
        $filteredAdIds = $adIdsQuery->pluck('id');

        $last7Days = collect(range(6, 0))->map(function ($daysAgo) use ($from, $to, $filteredAdIds) {
            $date = now()->subDays($daysAgo)->toDateString();
            if ($date < $from || $date > $to) {
                return [
                    'date' => $date,
                    'ads' => 0,
                    'posts' => 0,
                    'campaigns' => 0,
                    'spend' => 0,
                ];
            }
            
            $adQuery = FacebookAd::query()->whereDate('last_insights_sync', $date);
            if ($filteredAdIds->isNotEmpty()) {
                $adQuery->whereIn('id', $filteredAdIds);
            }
            
            return [
                'date' => $date,
                'ads' => $adQuery->count(),
                'posts' => $adQuery->whereNotNull('post_id')->count(),
                'campaigns' => $adQuery->distinct('campaign_id')->count(),
                'spend' => $adQuery->sum('ad_spend'),
            ];
        });

        // Thống kê tổng hợp
        $totalStats = FacebookAd::query()
            ->when($filteredAdIds->isNotEmpty(), function ($query) use ($filteredAdIds) {
                $query->whereIn('id', $filteredAdIds);
            })
            ->selectRaw('
                COUNT(*) as total_ads,
                COUNT(DISTINCT post_id) as total_posts,
                COUNT(DISTINCT campaign_id) as total_campaigns,
                SUM(ad_spend) as total_spend,
                SUM(ad_impressions) as total_impressions,
                SUM(ad_clicks) as total_clicks,
                SUM(ad_reach) as total_reach,
                AVG(ad_ctr) as avg_ctr,
                AVG(ad_cpc) as avg_cpc,
                AVG(ad_cpm) as avg_cpm
            ')
            ->first();

        // Lấy recent ads và top posts
        $recentAds = FacebookAd::with(['campaign:id,name'])
            ->when($filteredAdIds->isNotEmpty(), function ($query) use ($filteredAdIds) {
                $query->whereIn('id', $filteredAdIds);
            })
            ->latest('last_insights_sync')
            ->limit(5)
            ->get();

        $topPosts = FacebookAd::whereNotNull('post_id')
            ->when($filteredAdIds->isNotEmpty(), function ($query) use ($filteredAdIds) {
                $query->whereIn('id', $filteredAdIds);
            })
            ->orderByRaw('(post_likes + post_shares + post_comments) DESC')
            ->limit(5)
            ->get();

        return [
            'filters' => ['from' => $from, 'to' => $to],
            'totals' => $totals,
            'last7Days' => $last7Days,
            'stats' => [
                'total_ads' => $totalStats->total_ads ?? 0,
                'total_posts' => $totalStats->total_posts ?? 0,
                'total_campaigns' => $totalStats->total_campaigns ?? 0,
                'total_spend' => $totalStats->total_spend ?? 0,
                'total_impressions' => $totalStats->total_impressions ?? 0,
                'total_clicks' => $totalStats->total_clicks ?? 0,
                'total_reach' => $totalStats->total_reach ?? 0,
                'avg_ctr' => $totalStats->avg_ctr ?? 0,
                'avg_cpc' => $totalStats->avg_cpc ?? 0,
                'avg_cpm' => $totalStats->avg_cpm ?? 0,
                'recent_ads' => $recentAds,
                'top_posts' => $topPosts,
            ],
        ];
    }

    private function getHierarchyData(): array
    {
        return [
            'businesses' => FacebookBusiness::withCount('adAccounts')->get(),
            'totalAccounts' => FacebookAdAccount::count(),
            'totalCampaigns' => FacebookCampaign::count(),
            'totalAdSets' => FacebookAdSet::count(),
            'totalAds' => FacebookAd::count(),
            'totalPosts' => FacebookAd::whereNotNull('post_id')->distinct('post_id')->count(),
        ];
    }

    private function getAnalyticsData(): array
    {
        // Dữ liệu analytics từ bảng FacebookAd
        $stats = FacebookAd::selectRaw('
            SUM(ad_spend) as total_spend,
            SUM(ad_impressions) as total_impressions,
            SUM(ad_clicks) as total_clicks,
            SUM(ad_reach) as total_reach,
            AVG(ad_ctr) as avg_ctr,
            AVG(ad_cpc) as avg_cpc,
            AVG(ad_cpm) as avg_cpm
        ')->first();

        return [
            'totalSpend' => $stats->total_spend ?? 0,
            'totalImpressions' => $stats->total_impressions ?? 0,
            'totalClicks' => $stats->total_clicks ?? 0,
            'totalReach' => $stats->total_reach ?? 0,
            'avgCTR' => $stats->avg_ctr ?? 0,
            'avgCPC' => $stats->avg_cpc ?? 0,
            'avgCPM' => $stats->avg_cpm ?? 0,
        ];
    }

    private function getRawData(): array
    {
        // Tổng số từ bảng FacebookAd làm trung tâm
        $totalsRaw = [
            'businesses' => FacebookBusiness::count(),
            'accounts' => FacebookAdAccount::count(),
            'campaigns' => FacebookCampaign::count(),
            'adsets' => FacebookAdSet::count(),
            'ads' => FacebookAd::count(),
            'pages' => FacebookAd::whereNotNull('page_id')->distinct('page_id')->count(),
            'posts' => FacebookAd::whereNotNull('post_id')->distinct('post_id')->count(),
            'insights' => FacebookAd::whereNotNull('last_insights_sync')->count(),
        ];

        // Dữ liệu từ bảng FacebookAd với các thông tin liên quan
        $rawData = [
            'businesses' => FacebookBusiness::withCount('adAccounts')->latest('created_at')->limit(10)->get(),
            'accounts' => FacebookAdAccount::withCount('campaigns')->latest('created_at')->limit(20)->get(),
            'campaigns' => FacebookCampaign::with(['adAccount:id,name'])->latest('created_at')->limit(50)->get(),
            'adsets' => FacebookAdSet::with(['campaign:id,name'])->latest('created_at')->limit(50)->get(),
            'ads' => FacebookAd::with(['campaign:id,name', 'adSet:id,name'])
                ->latest('last_insights_sync')
                ->limit(100)
                ->get(),
            'posts' => FacebookAd::with(['campaign:id,name', 'adSet:id,name'])
                ->where(function($query) {
                    $query->whereNotNull('post_id')
                          ->orWhereNotNull('creative_link_url');
                })
                ->latest('last_insights_sync')
                ->limit(100)
                ->get(),
        ];

        return [
            'totals' => $totalsRaw,
            'data' => $rawData,
        ];
    }
}


