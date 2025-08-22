<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FacebookAd;
use App\Models\FacebookAdAccount;
use App\Models\FacebookAdSet;
use App\Models\FacebookBusiness;
use App\Models\FacebookCampaign;
use Illuminate\Console\Command;

class CheckFacebookData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'facebook:check-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kiểm tra dữ liệu Facebook đã được lưu trong database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Kiểm tra dữ liệu Facebook trong database...');
        
        // Kiểm tra Business Managers
        $businessCount = FacebookBusiness::count();
        $this->info("📊 Business Managers: {$businessCount}");
        if ($businessCount > 0) {
            $businesses = FacebookBusiness::take(3)->get();
            $this->table(
                ['ID', 'Name', 'Status', 'Created'],
                $businesses->map(fn($b) => [
                    $b->id,
                    $b->name ?? 'N/A',
                    $b->verification_status ?? 'N/A',
                    $b->created_time?->format('Y-m-d H:i:s') ?? 'N/A'
                ])->toArray()
            );
        }
        
        // Kiểm tra Ad Accounts
        $accountCount = FacebookAdAccount::count();
        $this->info("📊 Ad Accounts: {$accountCount}");
        if ($accountCount > 0) {
            $accounts = FacebookAdAccount::take(3)->get();
            $this->table(
                ['ID', 'Account ID', 'Name', 'Status', 'Business ID'],
                $accounts->map(fn($a) => [
                    $a->id,
                    $a->account_id ?? 'N/A',
                    $a->name ?? 'N/A',
                    $a->account_status ?? 'N/A',
                    $a->business_id ?? 'N/A'
                ])->toArray()
            );
        }
        
        // Kiểm tra Campaigns
        $campaignCount = FacebookCampaign::count();
        $this->info("📊 Campaigns: {$campaignCount}");
        if ($campaignCount > 0) {
            $campaigns = FacebookCampaign::take(3)->get();
            $this->table(
                ['ID', 'Name', 'Status', 'Objective', 'Account ID'],
                $campaigns->map(fn($c) => [
                    $c->id,
                    $c->name ?? 'N/A',
                    $c->status ?? 'N/A',
                    $c->objective ?? 'N/A',
                    $c->ad_account_id ?? 'N/A'
                ])->toArray()
            );
        }
        
        // Kiểm tra Ad Sets
        $adsetCount = FacebookAdSet::count();
        $this->info("📊 Ad Sets: {$adsetCount}");
        if ($adsetCount > 0) {
            $adsets = FacebookAdSet::take(3)->get();
            $this->table(
                ['ID', 'Name', 'Status', 'Campaign ID'],
                $adsets->map(fn($as) => [
                    $as->id,
                    $as->name ?? 'N/A',
                    $as->status ?? 'N/A',
                    $as->campaign_id ?? 'N/A'
                ])->toArray()
            );
        }
        
        // Kiểm tra Ads
        $adCount = FacebookAd::count();
        $this->info("📊 Ads: {$adCount}");
        if ($adCount > 0) {
            $ads = FacebookAd::take(3)->get();
            $this->table(
                ['ID', 'Name', 'Status', 'Ad Set ID', 'Has Insights'],
                $ads->map(fn($ad) => [
                    $ad->id,
                    $ad->name ?? 'N/A',
                    $ad->status ?? 'N/A',
                    $ad->adset_id ?? 'N/A',
                    $ad->last_insights_sync ? 'Có' : 'Không'
                ])->toArray()
            );
            
            // Kiểm tra chi tiết 1 ad
            $sampleAd = FacebookAd::first();
            if ($sampleAd) {
                $this->info("\n📋 Chi tiết Ad mẫu:");
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['ID', $sampleAd->id],
                        ['Name', $sampleAd->name ?? 'N/A'],
                        ['Status', $sampleAd->status ?? 'N/A'],
                        ['Effective Status', $sampleAd->effective_status ?? 'N/A'],
                        ['Ad Spend', $sampleAd->ad_spend ?? 0],
                        ['Ad Impressions', $sampleAd->ad_impressions ?? 0],
                        ['Ad Clicks', $sampleAd->ad_clicks ?? 0],
                        ['Post Likes', $sampleAd->post_likes ?? 0],
                        ['Post Shares', $sampleAd->post_shares ?? 0],
                        ['Post Comments', $sampleAd->post_comments ?? 0],
                        ['Last Insights Sync', $sampleAd->last_insights_sync?->format('Y-m-d H:i:s') ?? 'N/A'],
                    ]
                );
            }
        }
        
        $this->info('✅ Kiểm tra hoàn thành!');
        return self::SUCCESS;
    }
}
