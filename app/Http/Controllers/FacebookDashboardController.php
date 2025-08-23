<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FacebookAd;
use App\Models\FacebookAdAccount;
use App\Models\FacebookAdSet;
use App\Models\FacebookBusiness;
use App\Models\FacebookCampaign;
use App\Models\FacebookPost;
use App\Models\FacebookPage;
use App\Models\FacebookPostInsight;
use App\Models\FacebookAdInsight;
use App\Models\FacebookReportSummary;
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

        // Sử dụng cấu trúc mới đã normalize
        $totals = [
            'businesses' => FacebookBusiness::count(),
            'accounts' => FacebookAdAccount::count(),
            'campaigns' => FacebookCampaign::count(),
            'adsets' => FacebookAdSet::count(),
            'ads' => FacebookAd::count(),
            'pages' => FacebookPage::count(),
            'posts' => FacebookPost::count(),
            'post_insights' => FacebookPostInsight::count(),
            'ad_insights' => FacebookAdInsight::count(),
        ];

        // Lấy dữ liệu từ summary table cho hiệu suất tốt hơn
        $summaryQuery = FacebookReportSummary::query();
        if ($selectedAccountId) {
            $summaryQuery->where('account_id', $selectedAccountId);
        }
        if ($selectedCampaignId) {
            $summaryQuery->where('campaign_id', $selectedCampaignId);
        }
        
        $summaryData = $summaryQuery->whereBetween('date', [$from, $to])->get();

        $last7Days = collect(range(6, 0))->map(function ($daysAgo) use ($from, $to, $summaryData) {
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
            
            $dayData = $summaryData->where('date', $date)->first();
            
            return [
                'date' => $date,
                'ads' => $dayData ? $dayData->ads_count : 0,
                'posts' => $dayData ? $dayData->posts_count : 0,
                'campaigns' => $dayData ? $dayData->campaigns_count : 0,
                'spend' => $dayData ? $dayData->total_spend : 0,
            ];
        });

        // Tính toán stats tổng hợp
        $stats = [
            'total_spend' => $summaryData->sum('total_spend'),
            'total_impressions' => $summaryData->sum('total_impressions'),
            'total_clicks' => $summaryData->sum('total_clicks'),
            'total_reach' => $summaryData->sum('total_reach'),
            'avg_ctr' => $summaryData->avg('avg_ctr'),
            'avg_cpc' => $summaryData->avg('avg_cpc'),
            'avg_cpm' => $summaryData->avg('avg_cpm'),
        ];

        // Top performing ads
        $topAds = FacebookAd::with(['campaign', 'adSet', 'post'])
            ->whereHas('insights', function ($query) use ($from, $to) {
                $query->whereBetween('date', [$from, $to]);
            })
            ->withSum(['insights as total_spend' => function ($query) use ($from, $to) {
                $query->whereBetween('date', [$from, $to]);
            }], 'spend')
            ->orderByDesc('total_spend')
            ->limit(10)
            ->get();

        // Top performing posts
        $topPosts = FacebookPost::with(['page', 'insights'])
            ->whereHas('insights', function ($query) use ($from, $to) {
                $query->whereBetween('date', [$from, $to]);
            })
            ->withSum(['insights as total_engagement' => function ($query) use ($from, $to) {
                $query->whereBetween('date', [$from, $to]);
            }], DB::raw('likes + shares + comments'))
            ->orderByDesc('total_engagement')
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
                $query->with(['post', 'insights']);
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
        // Sử dụng summary table cho analytics
        $summaryData = FacebookReportSummary::whereBetween('date', [
            now()->subDays(30)->toDateString(),
            now()->toDateString()
        ])->get();

        $dailyStats = $summaryData->groupBy('date')->map(function ($dayData) {
            return [
                'date' => $dayData->first()->date,
                'total_spend' => $dayData->sum('total_spend'),
                'total_impressions' => $dayData->sum('total_impressions'),
                'total_clicks' => $dayData->sum('total_clicks'),
                'total_reach' => $dayData->sum('total_reach'),
                'avg_ctr' => $dayData->avg('avg_ctr'),
                'avg_cpc' => $dayData->avg('avg_cpc'),
                'avg_cpm' => $dayData->avg('avg_cpm'),
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

        // Performance by post type
        $postTypePerformance = FacebookPost::with(['insights'])
            ->withSum(['insights as total_engagement' => function ($query) {
                $query->whereBetween('date', [now()->subDays(30)->toDateString(), now()->toDateString()]);
            }], DB::raw('likes + shares + comments'))
            ->groupBy('type')
            ->select('type', DB::raw('count(*) as post_count'))
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
        $ads = FacebookAd::with(['campaign', 'adSet', 'post', 'insights'])
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


