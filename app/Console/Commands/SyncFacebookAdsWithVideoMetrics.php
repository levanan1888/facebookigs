<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\FacebookAdsSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SyncFacebookAdsWithVideoMetrics extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'facebook:sync-with-video-metrics 
                            {--since= : Start date (Y-m-d format)}
                            {--until= : End date (Y-m-d format)}
                            {--limit=10 : Limit number of ads to process for testing}
                            {--sync-engagement : Sync post engagement data (likes, shares, comments)}
                            {--engagement-delay=1 : Delay between engagement API calls (seconds)}';

    /**
     * The console command description.
     */
    protected $description = 'Sync Facebook Ads data with complete video metrics and breakdowns';

    /**
     * Execute the console command.
     */
    public function handle(FacebookAdsSyncService $syncService): int
    {
        $this->info('Bắt đầu sync Facebook Ads với video metrics đầy đủ...');

        $since = $this->option('since') ?: now()->subDays(7)->format('Y-m-d');
        $until = $this->option('until') ?: now()->format('Y-m-d');
        $limit = (int) $this->option('limit');
        $syncEngagement = $this->option('sync-engagement');
        $engagementDelay = (int) $this->option('engagement-delay');

        $this->info("Time range: {$since} to {$until}");
        $this->info("Limit: {$limit} ads");
        if ($syncEngagement) {
            $this->info("Post engagement sync: ENABLED (delay: {$engagementDelay}s)");
        }

        try {
            // Progress callback
            $progressCallback = function ($data) {
                $this->info($data['message']);
                $this->table(
                    ['Metric', 'Count'],
                    [
                        ['Businesses', $data['counts']['businesses']],
                        ['Accounts', $data['counts']['accounts']],
                        ['Campaigns', $data['counts']['campaigns']],
                        ['Ad Sets', $data['counts']['adsets']],
                        ['Ads', $data['counts']['ads']],
                        ['Ad Insights', $data['counts']['ad_insights']],
                        ['Breakdowns', $data['counts']['breakdowns'] ?? 0],
                    ]
                );

                if (!empty($data['errors'])) {
                    $this->error('Errors:');
                    foreach ($data['errors'] as $error) {
                        $errorMsg = is_array($error) ? json_encode($error) : $error;
                        $this->error("- {$errorMsg}");
                    }
                }
            };

            // Sync data với video metrics đầy đủ
            $result = $syncService->syncFacebookData($progressCallback, $since, $until);

            $this->info('Sync completed!');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Businesses', $result['businesses']],
                    ['Accounts', $result['accounts']],
                    ['Campaigns', $result['campaigns']],
                    ['Ad Sets', $result['adsets']],
                    ['Ads', $result['ads']],
                    ['Ad Insights', $result['ad_insights']],
                    ['Breakdowns', $result['breakdowns'] ?? 0],
                ]
            );

            if (!empty($result['errors'])) {
                $this->error('Errors occurred:');
                foreach ($result['errors'] as $error) {
                    $errorMsg = is_array($error) ? json_encode($error) : $error;
                    $this->error("- {$errorMsg}");
                }
            }

            $this->info("Duration: {$result['duration']} seconds");
            
            // Sync post engagement nếu được yêu cầu
            if ($syncEngagement && $result['posts'] > 0) {
                $this->info('🔄 Bắt đầu sync post engagement data...');
                $this->syncPostEngagement($engagementDelay);
            }
            
            return 0;

        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            Log::error('Facebook sync error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
    
    /**
     * Sync post engagement data cho tất cả posts
     */
    private function syncPostEngagement(int $delay = 1): void
    {
        try {
            $posts = \App\Models\FacebookPost::whereNotNull('id')->get();
            $totalPosts = $posts->count();
            
            if ($totalPosts === 0) {
                $this->info('ℹ️  Không có posts nào để sync engagement');
                return;
            }
            
            $this->info("📊 Tìm thấy {$totalPosts} posts để sync engagement");
            
            $bar = $this->output->createProgressBar($totalPosts);
            $bar->start();
            
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($posts as $post) {
                try {
                    if ($this->syncSinglePostEngagement($post)) {
                        $successCount++;
                    } else {
                        $errorCount++;
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                    \Illuminate\Support\Facades\Log::error("Lỗi khi sync engagement cho post {$post->id}: " . $e->getMessage());
                }
                
                $bar->advance();
                
                // Delay để tránh rate limit
                if ($delay > 0) {
                    sleep($delay);
                }
            }
            
            $bar->finish();
            $this->newLine();
            
            $this->info("✅ Engagement sync hoàn thành! Thành công: {$successCount}, Lỗi: {$errorCount}");
            
        } catch (\Exception $e) {
            $this->error("❌ Lỗi khi sync engagement: " . $e->getMessage());
            \Illuminate\Support\Facades\Log::error('Lỗi trong syncPostEngagement: ' . $e->getMessage());
        }
    }
    
    /**
     * Sync engagement cho 1 post
     */
    private function syncSinglePostEngagement(\App\Models\FacebookPost $post): bool
    {
        try {
            // Lấy object_story_id từ creative của ad
            $ad = \App\Models\FacebookAd::where('post_id', $post->id)->first();
            if (!$ad || !$ad->creative) {
                \Illuminate\Support\Facades\Log::warning("Không tìm thấy ad hoặc creative cho post {$post->id}");
                return false;
            }
            
            $creative = $ad->creative->creative_data;
            $objectStoryId = $creative['object_story_id'] ?? $creative['effective_object_story_id'] ?? null;
            
            if (!$objectStoryId) {
                \Illuminate\Support\Facades\Log::warning("Không tìm thấy object_story_id cho post {$post->id}");
                return false;
            }
            
            // Tách post_id từ object_story_id (format: pageId_postId)
            $parts = explode('_', $objectStoryId);
            if (count($parts) < 2) {
                \Illuminate\Support\Facades\Log::warning("object_story_id không đúng format: {$objectStoryId}");
                return false;
            }
            
            $pageId = $parts[0];
            $postIdFromStory = $parts[1];
            
            // Kiểm tra post_id có khớp không
            if ($postIdFromStory !== $post->id) {
                \Illuminate\Support\Facades\Log::warning("Post ID không khớp: post->id={$post->id}, postIdFromStory={$postIdFromStory}");
                return false;
            }
            
            // Sử dụng Ad Insights API để lấy engagement data từ quyền admin BM
            $engagementData = $this->getAdEngagementData($ad->id);
            
            if (!$engagementData || isset($engagementData['error'])) {
                return false;
            }
            
            // Cập nhật post với engagement data
            $post->update([
                'likes_count' => $engagementData['likes'] ?? 0,
                'shares_count' => $engagementData['shares'] ?? 0,
                'comments_count' => $engagementData['comments'] ?? 0,
                'reactions_count' => $engagementData['reactions'] ?? 0,
                'engagement_updated_at' => now(),
            ]);

            // Upsert vào facebook_ad_insights cho cùng ngày
            $today = now()->toDateString();
            $insight = \App\Models\FacebookAdInsight::updateOrCreate(
                [
                    'ad_id' => $ad->id,
                    'date' => $today,
                ],
                []
            );

            // Nếu schema có các cột post_id/page_id thì lưu luôn để lần sau dựng link dễ hơn
            if (Schema::hasColumn('facebook_ad_insights', 'post_id')) {
                $insight->post_id = (string) $post->id;
            }
            if (Schema::hasColumn('facebook_ad_insights', 'page_id')) {
                $insight->page_id = (string) $pageId;
            }

            // Ghi metrics vào actions
            $actions = (array) ($insight->actions ?? []);
            $actions['like'] = (int) ($engagementData['likes'] ?? 0);
            $actions['reaction'] = (int) ($engagementData['reactions'] ?? ($engagementData['likes'] ?? 0));
            $actions['post_comment'] = (int) ($engagementData['comments'] ?? 0);
            $actions['post_share'] = (int) ($engagementData['shares'] ?? 0);
            $insight->actions = $actions;
            $insight->save();
            
            return true;
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Lỗi khi sync engagement cho post {$post->id}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Lấy engagement data từ Ad Insights API
     */
    private function getAdEngagementData(string $adId): ?array
    {
        try {
            // Sử dụng Ad Insights API để lấy engagement data từ quyền admin BM
            $engagementUrl = "https://graph.facebook.com/v23.0/{$adId}/insights";
                            $engagementResp = \Illuminate\Support\Facades\Http::timeout(60)
                    ->retry(1, 1000)
                    ->get($engagementUrl, [
                        'access_token' => config('services.facebook.ads_token'),
                        'fields' => 'actions,action_values',
                        'action_breakdowns' => 'action_type,action_reaction',
                        'time_range' => json_encode([
                            'since' => date('Y-m-d', strtotime('-2 years')),
                            'until' => date('Y-m-d')
                        ])
                    ]);
            
            // Log response để debug
            \Illuminate\Support\Facades\Log::info("Ad Insights API response", [
                'ad_id' => $adId,
                'status' => $engagementResp->status(),
                'body' => $engagementResp->json(),
            ]);
            
            if (!$engagementResp->successful()) {
                \Illuminate\Support\Facades\Log::warning("Không thể lấy engagement data từ Ad Insights API", [
                    'ad_id' => $adId,
                    'status' => $engagementResp->status(),
                    'response' => $engagementResp->json()
                ]);
                return null;
            }
            
            $engagementData = $engagementResp->json();
            
            // Parse engagement data từ actions
            $likes = 0;
            $shares = 0;
            $comments = 0;
            $reactions = 0;
            
            if (isset($engagementData['data'][0]['actions'])) {
                foreach ($engagementData['data'][0]['actions'] as $action) {
                    $actionType = $action['action_type'] ?? '';
                    $value = (int) ($action['value'] ?? 0);
                    
                    switch ($actionType) {
                        case 'like':
                        case 'post_reaction':
                            $likes += $value;
                            $reactions += $value;
                            break;
                        case 'share':
                        case 'post_share':
                            $shares += $value;
                            break;
                        case 'comment':
                        case 'post_comment':
                            $comments += $value;
                            break;
                        case 'reaction':
                            $reactions += $value;
                            break;
                    }
                }
            }
            
            $result = [
                'likes' => $likes,
                'shares' => $shares,
                'comments' => $comments,
                'reactions' => $reactions,
            ];
            
            \Illuminate\Support\Facades\Log::info("Đã lấy được engagement data từ Ad Insights", [
                'ad_id' => $adId,
                'data' => $result
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Lỗi khi lấy engagement data cho ad_id {$adId}: " . $e->getMessage());
            return null;
        }
    }
}
