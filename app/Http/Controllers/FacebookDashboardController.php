<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FacebookAd;
use App\Models\FacebookAdAccount;
use App\Models\FacebookAdSet;
use App\Models\FacebookBusiness;
use App\Models\FacebookCampaign;
use App\Models\FacebookAdInsight;
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

        // Chỉ sử dụng dữ liệu từ facebook_ad_insights
        $totals = [
            'businesses' => FacebookBusiness::count(),
            'accounts' => FacebookAdAccount::count(),
            'campaigns' => FacebookCampaign::count(),
            'adsets' => FacebookAdSet::count(),
            'ads' => FacebookAd::count(),
            'pages' => FacebookAd::whereNotNull('page_id')->distinct('page_id')->count(),
            'posts' => FacebookAd::whereNotNull('post_id')->distinct('post_id')->count(),
            'ad_insights' => FacebookAdInsight::count(),
        ];

        // Lấy dữ liệu từ facebook_ad_insights
        $insightsQuery = FacebookAdInsight::join('facebook_ads', 'facebook_ad_insights.ad_id', '=', 'facebook_ads.id');
        
        if ($selectedAccountId) {
            $insightsQuery->where('facebook_ads.account_id', $selectedAccountId);
        }
        if ($selectedCampaignId) {
            $insightsQuery->where('facebook_ads.campaign_id', $selectedCampaignId);
        }
        
        $insightsData = $insightsQuery->whereBetween('facebook_ad_insights.date', [$from, $to])->get();

        $last7Days = collect(range(6, 0))->map(function ($daysAgo) use ($from, $to, $insightsData) {
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
            
            $dayData = $insightsData->where('date', $date);
            
            return [
                'date' => $date,
                'ads' => $dayData->count(),
                'posts' => $dayData->whereNotNull('post_id')->count(),
                'campaigns' => $dayData->pluck('campaign_id')->unique()->count(),
                'spend' => $dayData->sum('spend'),
            ];
        });

        // Tính toán stats tổng hợp
        $stats = [
            'total_spend' => $insightsData->sum('spend'),
            'total_impressions' => $insightsData->sum('impressions'),
            'total_clicks' => $insightsData->sum('clicks'),
            'total_reach' => $insightsData->sum('reach'),
            'avg_ctr' => $insightsData->avg('ctr'),
            'avg_cpc' => $insightsData->avg('cpc'),
            'avg_cpm' => $insightsData->avg('cpm'),
        ];

        // Top performing ads
        $topAds = FacebookAd::with(['campaign', 'adSet'])
            ->whereHas('insights', function ($query) use ($from, $to) {
                $query->whereBetween('date', [$from, $to]);
            })
            ->withSum(['insights as total_spend' => function ($query) use ($from, $to) {
                $query->whereBetween('date', [$from, $to]);
            }], 'spend')
            ->orderByDesc('total_spend')
            ->limit(10)
            ->get();

        // Top performing posts - sử dụng dữ liệu từ facebook_ad_insights
        $topPosts = FacebookAd::whereNotNull('post_id')
            ->whereHas('insights', function ($query) use ($from, $to) {
                $query->whereBetween('date', [$from, $to]);
            })
            ->withSum(['insights as total_spend' => function ($query) use ($from, $to) {
                $query->whereBetween('date', [$from, $to]);
            }], 'spend')
            ->withSum(['insights as total_impressions' => function ($query) use ($from, $to) {
                $query->whereBetween('date', [$from, $to]);
            }], 'impressions')
            ->withSum(['insights as total_clicks' => function ($query) use ($from, $to) {
                $query->whereBetween('date', [$from, $to]);
            }], 'clicks')
            ->orderByDesc('total_spend')
            ->limit(10)
            ->get();

        // Lấy accounts và campaigns cho filter
        $accounts = FacebookAdAccount::select('id', 'name', 'account_id')->get();
        $campaigns = FacebookCampaign::select('id', 'name')->get();

        return [
            'totals' => $totals,
            'stats' => $stats,
            'last7Days' => $last7Days,
            'topAds' => $topAds,
            'topPosts' => $topPosts,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'account_id' => $selectedAccountId,
                'campaign_id' => $selectedCampaignId,
                'accounts' => $accounts,
                'campaigns' => $campaigns,
            ]
        ];
    }

    private function getHierarchyData(): array
    {
        // Sử dụng cấu trúc mới với relationships
        $businesses = FacebookBusiness::withCount(['adAccounts'])
            ->with(['adAccounts' => function ($query) {
                $query->withCount(['campaigns']);
            }])
            ->get();

        $campaigns = FacebookCampaign::withCount(['adSets', 'ads'])
            ->with(['adSets' => function ($query) {
                $query->withCount('ads');
            }])
            ->get();

        $adSets = FacebookAdSet::withCount('ads')
            ->with(['ads' => function ($query) {
                $query->with('insights');
            }])
            ->get();

        return [
            'businesses' => $businesses,
            'campaigns' => $campaigns,
            'adsets' => $adSets,
        ];
    }

    private function getAnalyticsData(): array
    {
        // Sử dụng facebook_ad_insights cho analytics
        $insightsData = FacebookAdInsight::join('facebook_ads', 'facebook_ad_insights.ad_id', '=', 'facebook_ads.id')
            ->whereBetween('facebook_ad_insights.date', [
                now()->subDays(30)->toDateString(),
                now()->toDateString()
            ])
            ->get();

        $dailyStats = $insightsData->groupBy('date')->map(function ($dayData) {
            return [
                'date' => $dayData->first()->date,
                'total_spend' => $dayData->sum('spend'),
                'total_impressions' => $dayData->sum('impressions'),
                'total_clicks' => $dayData->sum('clicks'),
                'total_reach' => $dayData->sum('reach'),
                'avg_ctr' => $dayData->avg('ctr'),
                'avg_cpc' => $dayData->avg('cpc'),
                'avg_cpm' => $dayData->avg('cpm'),
            ];
        })->values();

        // Performance by campaign
        $campaignPerformance = FacebookCampaign::with(['ads.insights'])
            ->withSum(['ads.insights as total_spend' => function ($query) {
                $query->whereBetween('date', [now()->subDays(30)->toDateString(), now()->toDateString()]);
            }], 'spend')
            ->withSum(['ads.insights as total_impressions' => function ($query) {
                $query->whereBetween('date', [now()->subDays(30)->toDateString(), now()->toDateString()]);
            }], 'impressions')
            ->withSum(['ads.insights as total_clicks' => function ($query) {
                $query->whereBetween('date', [now()->subDays(30)->toDateString(), now()->toDateString()]);
            }], 'clicks')
            ->orderByDesc('total_spend')
            ->limit(20)
            ->get();

        // Performance by post type - sử dụng dữ liệu từ facebook_ads
        $postTypePerformance = FacebookAd::whereNotNull('post_id')
            ->whereHas('insights', function ($query) {
                $query->whereBetween('date', [now()->subDays(30)->toDateString(), now()->toDateString()]);
            })
            ->withSum(['insights as total_spend' => function ($query) {
                $query->whereBetween('date', [now()->subDays(30)->toDateString(), now()->toDateString()]);
            }], 'spend')
            ->groupBy('post_id')
            ->select('post_id', DB::raw('count(*) as ad_count'), DB::raw('sum(total_spend) as total_spend'))
            ->get();

        return [
            'dailyStats' => $dailyStats,
            'campaignPerformance' => $campaignPerformance,
            'postTypePerformance' => $postTypePerformance,
        ];
    }

    private function getRawData(): array
    {
        // Sử dụng cấu trúc mới với pagination
        $ads = FacebookAd::with(['campaign', 'adSet', 'insights'])
            ->orderBy('created_time', 'desc')
            ->paginate(50);

        $posts = FacebookPost::with(['page', 'insights'])
            ->orderBy('created_time', 'desc')
            ->paginate(50);

        $insights = FacebookAdInsight::with(['ad'])
            ->orderBy('date', 'desc')
            ->paginate(50);

        return [
            'ads' => $ads,
            'posts' => $posts,
            'insights' => $insights,
        ];
    }
}


