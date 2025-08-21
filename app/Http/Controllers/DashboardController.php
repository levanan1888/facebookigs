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

class DashboardController extends Controller
{

    public function index(Request $request): View
    {
        $tab = $request->get('tab', 'overview');
        
        // Tạm thời sử dụng dữ liệu trực tiếp thay vì service
        $data = match ($tab) {
            'overview' => $this->getCrossPlatformOverviewData($request),
            'fb-overview' => $this->getOverviewData($request),
            'unified-data' => $this->getUnifiedData(),
            'data-raw' => $this->getRawData(),
            'hierarchy' => $this->getHierarchyData(),
            'analytics' => $this->getAnalyticsData(),
            'comparison' => $this->getComparisonData(),
            default => $this->getCrossPlatformOverviewData($request),
        };

        return view('dashboard', compact('data', 'tab'));
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
        $selectedAccountId = $request->get('account_id'); // expects FacebookAdAccount.id (act_...)
        $selectedCampaignId = $request->get('campaign_id');

        // Tổng quan tổng hợp - Sử dụng FacebookAd làm trung tâm
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

        // Tập ad IDs theo filter
        $adIdsQuery = FacebookAd::query();
        if ($selectedAccountId) {
            $adIdsQuery->where('account_id', $selectedAccountId);
        }
        if ($selectedCampaignId) {
            $adIdsQuery->where('campaign_id', $selectedCampaignId);
        }
        $filteredAdIds = $adIdsQuery->pluck('id');

        // Thống kê theo thời gian (7 ngày gần nhất trong khoảng chọn)
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
            ],
        ];
    }

    private function getCrossPlatformOverviewData(Request $request): array
    {
        $from = $request->get('from', now()->subDays(29)->toDateString());
        $to = $request->get('to', now()->toDateString());

        // Facebook data từ bảng FacebookAd
        $fb = FacebookAd::query()
            ->whereBetween('last_insights_sync', [$from, $to])
            ->selectRaw('
                SUM(ad_spend) as spend,
                SUM(ad_impressions) as impressions,
                SUM(ad_clicks) as clicks,
                SUM(ad_reach) as reach,
                AVG(ad_ctr) as ctr,
                AVG(ad_cpc) as cpc,
                AVG(ad_cpm) as cpm
            ')
            ->first();

        $fb = [
            'spend' => $fb->spend ?? 0,
            'impressions' => $fb->impressions ?? 0,
            'clicks' => $fb->clicks ?? 0,
            'reach' => $fb->reach ?? 0,
            'ctr' => $fb->ctr ?? 0,
            'cpc' => $fb->cpc ?? 0,
            'cpm' => $fb->cpm ?? 0,
        ];

        // Placeholder for Google/TikTok (0 nếu chưa kết nối)
        $google = ['spend' => 0, 'impressions' => 0, 'clicks' => 0, 'reach' => 0, 'ctr' => 0];
        $tiktok = ['spend' => 0, 'impressions' => 0, 'clicks' => 0, 'reach' => 0, 'ctr' => 0];

        $totals = [
            'spend' => $fb['spend'] + $google['spend'] + $tiktok['spend'],
            'impressions' => $fb['impressions'] + $google['impressions'] + $tiktok['impressions'],
            'clicks' => $fb['clicks'] + $google['clicks'] + $tiktok['clicks'],
            'reach' => $fb['reach'] + $google['reach'] + $tiktok['reach'],
        ];

        $series = collect(range(6, 0))->map(function ($d) use ($from, $to) {
            $date = now()->subDays($d)->toDateString();
            $spend = ($date >= $from && $date <= $to) ? 
                (float) FacebookAd::whereDate('last_insights_sync', $date)->sum('ad_spend') : 0;
            return ['date' => $date, 'spend' => $spend];
        });

        return [
            'filters' => ['from' => $from, 'to' => $to],
            'platforms' => ['facebook' => $fb, 'google' => $google, 'tiktok' => $tiktok],
            'totals' => $totals,
            'series' => $series,
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
                ->whereNotNull('post_id') // Chỉ lấy ads có post
                ->latest('last_insights_sync')
                ->limit(100)
                ->get(),
            'pages' => FacebookAd::select('page_id', 'post_message as name', 'post_type as category')
                ->whereNotNull('page_id')
                ->distinct('page_id')
                ->limit(50)
                ->get(),
            'posts' => FacebookAd::select(
                'post_id',
                'post_message',
                'post_type',
                'post_created_time',
                'post_likes',
                'post_shares',
                'post_comments',
                'post_reactions',
                'post_impressions',
                'post_reach',
                'post_clicks'
            )
                ->whereNotNull('post_id')
                ->latest('post_created_time')
                ->limit(100)
                ->get(),
        ];

        return [
            'totals' => $totalsRaw,
            'data' => $rawData,
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

    public function syncFacebook(): \Illuminate\Http\RedirectResponse
    {
        // Redirect to data-raw tab after sync
        return redirect()->route('dashboard', ['tab' => 'data-raw']);
    }

    /**
     * Debug method để kiểm tra dữ liệu campaigns
     */
    public function debugCampaigns(): \Illuminate\Http\JsonResponse
    {
        try {
            $campaigns = FacebookCampaign::with(['adAccount:id,name'])
                ->latest('created_at')
                ->limit(10)
                ->get();

            $totalCampaigns = FacebookCampaign::count();
            $totalAdAccounts = FacebookAdAccount::count();

            return response()->json([
                'success' => true,
                'campaigns' => $campaigns,
                'totalCampaigns' => $totalCampaigns,
                'totalAdAccounts' => $totalAdAccounts,
            ]);
        } catch (\Exception $e) {
            Log::error('Debug campaigns error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function getUnifiedData(): array
    {
        // Dữ liệu tổng hợp từ bảng FacebookAd
        $unifiedStats = FacebookAd::selectRaw('
            COUNT(*) as total_ads,
            COUNT(DISTINCT post_id) as total_posts,
            COUNT(DISTINCT page_id) as total_pages,
            COUNT(DISTINCT campaign_id) as total_campaigns,
            COUNT(DISTINCT adset_id) as total_adsets,
            COUNT(DISTINCT account_id) as total_accounts,
            SUM(ad_spend) as total_spend,
            SUM(ad_impressions) as total_impressions,
            SUM(ad_clicks) as total_clicks,
            SUM(ad_reach) as total_reach,
            AVG(ad_ctr) as avg_ctr,
            AVG(ad_cpc) as avg_cpc,
            AVG(ad_cpm) as avg_cpm
        ')->first();

        return [
            'totals' => [
                'ads' => $unifiedStats->total_ads ?? 0,
                'posts' => $unifiedStats->total_posts ?? 0,
                'pages' => $unifiedStats->total_pages ?? 0,
                'campaigns' => $unifiedStats->total_campaigns ?? 0,
                'adsets' => $unifiedStats->total_adsets ?? 0,
                'accounts' => $unifiedStats->total_accounts ?? 0,
            ],
            'metrics' => [
                'spend' => $unifiedStats->total_spend ?? 0,
                'impressions' => $unifiedStats->total_impressions ?? 0,
                'clicks' => $unifiedStats->total_clicks ?? 0,
                'reach' => $unifiedStats->total_reach ?? 0,
                'ctr' => $unifiedStats->avg_ctr ?? 0,
                'cpc' => $unifiedStats->avg_cpc ?? 0,
                'cpm' => $unifiedStats->avg_cpm ?? 0,
            ],
        ];
    }

    private function getComparisonData(): array
    {
        // Dữ liệu so sánh từ bảng FacebookAd
        $last7Days = collect(range(6, 0))->map(function ($daysAgo) {
            $date = now()->subDays($daysAgo)->toDateString();
            $stats = FacebookAd::whereDate('last_insights_sync', $date)
                ->selectRaw('
                    COUNT(*) as ads_count,
                    COUNT(DISTINCT post_id) as posts_count,
                    SUM(ad_spend) as spend,
                    SUM(ad_impressions) as impressions,
                    SUM(ad_clicks) as clicks,
                    SUM(ad_reach) as reach
                ')
                ->first();

            return [
                'date' => $date,
                'ads' => $stats->ads_count ?? 0,
                'posts' => $stats->posts_count ?? 0,
                'spend' => $stats->spend ?? 0,
                'impressions' => $stats->impressions ?? 0,
                'clicks' => $stats->clicks ?? 0,
                'reach' => $stats->reach ?? 0,
            ];
        });

        return [
            'last7Days' => $last7Days,
            'summary' => [
                'total_ads' => $last7Days->sum('ads'),
                'total_posts' => $last7Days->sum('posts'),
                'total_spend' => $last7Days->sum('spend'),
                'total_impressions' => $last7Days->sum('impressions'),
                'total_clicks' => $last7Days->sum('clicks'),
                'total_reach' => $last7Days->sum('reach'),
            ],
        ];
    }
} 