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

        // Top posts: gộp theo page_id + post_id để tránh trùng lặp nhiều ad cho cùng 1 bài
        $topPostsRaw = FacebookAd::whereNotNull('post_id')
            ->when($filteredAdIds->isNotEmpty(), function ($query) use ($filteredAdIds) {
                $query->whereIn('id', $filteredAdIds);
            })
            ->get([
                'id','name','status','effective_status','page_id','post_id','post_message','post_permalink_url',
                'post_likes','post_shares','post_comments','ad_spend','ad_impressions','ad_clicks','last_insights_sync'
            ]);

        $topPosts = $topPostsRaw
            ->filter(function ($p) { return !empty($p->page_id) && !empty($p->post_id); })
            ->groupBy(function ($p) { return ($p->page_id ?? '') . '|' . ($p->post_id ?? ''); })
            ->map(function ($group) {
                $rep = $group->sortByDesc(function ($p) { return $p->last_insights_sync ?? $p->id; })->first();
                $post = clone $rep;
                $post->post_likes = (int) $group->sum(function ($p) { return (int) ($p->post_likes ?? 0); });
                $post->post_shares = (int) $group->sum(function ($p) { return (int) ($p->post_shares ?? 0); });
                $post->post_comments = (int) $group->sum(function ($p) { return (int) ($p->post_comments ?? 0); });
                $post->ad_spend = (float) $group->sum(function ($p) { return (float) ($p->ad_spend ?? 0); });
                $post->ad_impressions = (int) $group->sum(function ($p) { return (int) ($p->ad_impressions ?? 0); });
                $post->ad_clicks = (int) $group->sum(function ($p) { return (int) ($p->ad_clicks ?? 0); });
                return $post;
            })
            ->sortByDesc(function ($p) { return ($p->post_likes ?? 0) + ($p->post_shares ?? 0) + ($p->post_comments ?? 0); })
            ->take(5)
            ->values();

        // Thống kê trạng thái campaigns
        $statusStats = [
            'campaigns' => FacebookCampaign::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
            'ads' => FacebookAd::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
        ];

        // Lấy accounts và campaigns cho filter
        $accounts = FacebookAdAccount::select('id', 'name', 'account_id')->get();
        $campaigns = FacebookCampaign::select('id', 'name')->get();

        return [
            'filters' => [
                'from' => $from, 
                'to' => $to,
                'accounts' => $accounts,
                'campaigns' => $campaigns,
                'accountId' => $selectedAccountId,
                'campaignId' => $selectedCampaignId,
            ],
            'totals' => $totals,
            'last7Days' => $last7Days,
            'statusStats' => $statusStats,
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
            'performanceStats' => [
                'totalSpend' => $totalStats->total_spend ?? 0,
                'totalImpressions' => $totalStats->total_impressions ?? 0,
                'totalClicks' => $totalStats->total_clicks ?? 0,
                'totalReach' => $totalStats->total_reach ?? 0,
            ],
        ];
    }

    private function getHierarchyData(): array
    {
        // Lấy campaigns với metrics
        $campaigns = FacebookCampaign::with(['adAccount:id,name'])
            ->withCount(['adSets', 'ads'])
            ->get()
            ->map(function ($campaign) {
                // Tính metrics cho campaign từ ads
                $campaignMetrics = FacebookAd::where('campaign_id', $campaign->id)
                    ->selectRaw('
                        SUM(ad_spend) as total_spend,
                        SUM(ad_impressions) as total_impressions,
                        SUM(ad_clicks) as total_clicks,
                        AVG(ad_ctr) as avg_ctr
                    ')
                    ->first();
                
                $campaign->total_spend = $campaignMetrics->total_spend ?? 0;
                $campaign->total_impressions = $campaignMetrics->total_impressions ?? 0;
                $campaign->total_clicks = $campaignMetrics->total_clicks ?? 0;
                $campaign->avg_ctr = $campaignMetrics->avg_ctr ?? 0;
                
                return $campaign;
            });

        // Lấy adsets với metrics
        $adSets = FacebookAdSet::with(['campaign:id,name'])
            ->withCount('ads')
            ->get()
            ->map(function ($adSet) {
                // Tính metrics cho adset từ ads
                $adSetMetrics = FacebookAd::where('adset_id', $adSet->id)
                    ->selectRaw('
                        SUM(ad_spend) as total_spend,
                        SUM(ad_impressions) as total_impressions,
                        SUM(ad_clicks) as total_clicks,
                        AVG(ad_ctr) as avg_ctr
                    ')
                    ->first();
                
                $adSet->total_spend = $adSetMetrics->total_spend ?? 0;
                $adSet->total_impressions = $adSetMetrics->total_impressions ?? 0;
                $adSet->total_clicks = $adSetMetrics->total_clicks ?? 0;
                $adSet->avg_ctr = $adSetMetrics->avg_ctr ?? 0;
                
                return $adSet;
            });

        return [
            'businesses' => FacebookBusiness::withCount('adAccounts')->get(),
            'campaigns' => $campaigns,
            'adSets' => $adSets,
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


