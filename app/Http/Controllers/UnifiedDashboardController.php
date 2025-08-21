<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FacebookAd;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnifiedDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $data = $this->getCrossPlatformOverviewData($request);

        return view('unified.dashboard', compact('data'));
    }

    /**
     * Tổng quan đa nền tảng: Facebook (thật), Google/TikTok (placeholder) cho UI tổng hợp.
     */
    private function getCrossPlatformOverviewData(Request $request): array
    {
        $from = $request->get('from') ?: now()->subDays(29)->toDateString();
        $to = $request->get('to') ?: now()->toDateString();

        $fb = [
            'spend' => (float) FacebookAd::whereBetween('last_insights_sync', [$from, $to])->sum('ad_spend'),
            'impressions' => (int) FacebookAd::whereBetween('last_insights_sync', [$from, $to])->sum('ad_impressions'),
            'clicks' => (int) FacebookAd::whereBetween('last_insights_sync', [$from, $to])->sum('ad_clicks'),
            'reach' => (int) FacebookAd::whereBetween('last_insights_sync', [$from, $to])->sum('ad_reach'),
            'ctr' => (float) FacebookAd::whereBetween('last_insights_sync', [$from, $to])->avg('ad_ctr'),
        ];

        $google = ['spend' => 0, 'impressions' => 0, 'clicks' => 0, 'reach' => 0, 'ctr' => 0];
        $tiktok = ['spend' => 0, 'impressions' => 0, 'clicks' => 0, 'reach' => 0, 'ctr' => 0];

        $totals = [
            'spend' => $fb['spend'] + $google['spend'] + $tiktok['spend'],
            'impressions' => $fb['impressions'] + $google['impressions'] + $tiktok['impressions'],
            'clicks' => $fb['clicks'] + $google['clicks'] + $tiktok['clicks'],
            'reach' => $fb['reach'] + $google['reach'] + $tiktok['reach'],
        ];

        $series = collect(range(6, 0))->map(function (int $d) use ($from, $to) {
            $date = now()->subDays($d)->toDateString();
            $spend = ($date >= $from && $date <= $to)
                ? (float) FacebookAd::whereDate('last_insights_sync', $date)->sum('ad_spend')
                : 0;
            return ['date' => $date, 'spend' => $spend];
        });

        return [
            'filters' => ['from' => $from, 'to' => $to],
            'platforms' => ['facebook' => $fb, 'google' => $google, 'tiktok' => $tiktok],
            'totals' => $totals,
            'series' => $series,
        ];
    }
}


