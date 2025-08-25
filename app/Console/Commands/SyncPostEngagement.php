<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FacebookPost;
use App\Services\FacebookAdsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncPostEngagement extends Command
{
    protected $signature = 'facebook:sync-post-engagement 
                            {--limit=0 : Giới hạn số lượng posts để sync (0 = tất cả)}
                            {--post-id= : ID của post cụ thể để sync}
                            {--force : Bắt buộc sync lại ngay cả khi đã sync gần đây}';

    protected $description = 'Đồng bộ engagement metrics (likes, shares, comments) của Facebook Posts';

    private FacebookAdsService $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = new FacebookAdsService();
    }

    public function handle(): int
    {
        $this->info('🚀 Bắt đầu đồng bộ Post Engagement...');
        
        try {
            $limit = (int) $this->option('limit');
            $specificPostId = $this->option('post-id');
            $force = $this->option('force');
            
            if ($specificPostId) {
                return $this->syncSpecificPost($specificPostId, $force);
            }
            
            return $this->syncAllPosts($limit, $force);
            
        } catch (\Exception $e) {
            $this->error('❌ Lỗi: ' . $e->getMessage());
            Log::error('Lỗi trong SyncPostEngagement: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
    
    private function syncSpecificPost(string $postId, bool $force = false): int
    {
        $this->info("📝 Đang đồng bộ post: {$postId}");
        
        try {
            // Kiểm tra xem có phải object_story_id không (có dấu _)
            if (strpos($postId, '_') !== false) {
                // Đây là object_story_id, sử dụng logic tách
                $result = $this->syncPostByObjectStoryId($postId);
                
                if ($result) {
                    $this->info("✅ Đã đồng bộ thành công object_story_id: {$postId}");
                    return Command::SUCCESS;
                } else {
                    $this->error("❌ Không thể đồng bộ object_story_id: {$postId}");
                    return Command::FAILURE;
                }
            } else {
                // Đây là post_id thông thường
                $post = FacebookPost::where('id', $postId)->first();
                
                if (!$post) {
                    $this->warn("⚠️  Post ID {$postId} không tìm thấy trong database");
                    return Command::FAILURE;
                }
                
                $result = $this->syncPostEngagement($post, $force);
                
                if ($result) {
                    $this->info("✅ Đã đồng bộ thành công post: {$postId}");
                    return Command::SUCCESS;
                } else {
                    $this->error("❌ Không thể đồng bộ post: {$postId}");
                    return Command::FAILURE;
                }
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Lỗi khi đồng bộ post {$postId}: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    private function syncAllPosts(int $limit, bool $force = false): int
    {
        $query = FacebookPost::query();
        
        if (!$force) {
            $query->where('updated_at', '<=', now()->subHours(6)); // Sync lại sau 6 giờ
        }
        
        if ($limit > 0) {
            $query->limit($limit);
        }
        
        $posts = $query->get();
        $totalPosts = $posts->count();
        
        if ($totalPosts === 0) {
            $this->info('ℹ️  Không có posts nào cần đồng bộ');
            return Command::SUCCESS;
        }
        
        $this->info("📊 Tìm thấy {$totalPosts} posts cần đồng bộ");
        
        $bar = $this->output->createProgressBar($totalPosts);
        $bar->start();
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($posts as $post) {
            try {
                if ($this->syncPostEngagement($post, $force)) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            } catch (\Exception $e) {
                $errorCount++;
                Log::error("Lỗi khi sync post {$post->id}: " . $e->getMessage());
            }
            
            $bar->advance();
            
            // Delay để tránh rate limit
            usleep(500000); // 0.5 giây
        }
        
        $bar->finish();
        $this->newLine();
        
        $this->info("✅ Hoàn thành! Thành công: {$successCount}, Lỗi: {$errorCount}");
        
        return $errorCount === 0 ? Command::SUCCESS : Command::FAILURE;
    }
    
    private function syncPostEngagement(FacebookPost $post, bool $force = false): bool
    {
        try {
            // Lấy object_story_id từ creative của ad
            $ad = \App\Models\FacebookAd::where('post_id', $post->id)->first();
            if (!$ad || !$ad->creative) {
                Log::warning("Không tìm thấy ad hoặc creative cho post {$post->id}");
                return false;
            }
            
            $creative = $ad->creative->creative_data;
            $objectStoryId = $creative['object_story_id'] ?? $creative['effective_object_story_id'] ?? null;
            
            if (!$objectStoryId) {
                Log::warning("Không tìm thấy object_story_id cho post {$post->id}");
                return false;
            }
            
            // Tách post_id từ object_story_id (format: pageId_postId)
            $parts = explode('_', $objectStoryId);
            if (count($parts) < 2) {
                Log::warning("object_story_id không đúng format: {$objectStoryId}");
                return false;
            }
            
            $pageId = $parts[0];
            $postIdFromStory = $parts[1];
            
            // Kiểm tra post_id có khớp không
            if ($postIdFromStory !== $post->id) {
                Log::warning("Post ID không khớp: post->id={$post->id}, postIdFromStory={$postIdFromStory}");
                return false;
            }
            
            // Lấy engagement data từ Facebook API sử dụng object_story_id đầy đủ
            $engagementData = $this->getPostEngagement($objectStoryId);
            
            if (!$engagementData) {
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
            
            // Cập nhật post insights nếu có
            $this->updatePostInsights($post, $engagementData);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error("Lỗi khi sync engagement cho post {$post->id}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Sync engagement cho post cụ thể bằng object_story_id
     */
    private function syncPostByObjectStoryId(string $objectStoryId): bool
    {
        try {
            // Tách post_id từ object_story_id (format: pageId_postId)
            $parts = explode('_', $objectStoryId);
            if (count($parts) < 2) {
                Log::warning("object_story_id không đúng format: {$objectStoryId}");
                return false;
            }
            
            $pageId = $parts[0];
            $postId = $parts[1];
            
            // Tìm post trong database bằng post_id đã tách
            $post = FacebookPost::where('id', $postId)->first();
            if (!$post) {
                Log::warning("Không tìm thấy post với ID: {$postId} (từ object_story_id: {$objectStoryId})");
                return false;
            }
            
            // Lấy engagement data từ Facebook API sử dụng object_story_id đầy đủ
            $engagementData = $this->getPostEngagement($objectStoryId);
            
            if (!$engagementData) {
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
            
            // Cập nhật post insights nếu có
            $this->updatePostInsights($post, $engagementData);
            
            Log::info("Đã sync engagement thành công cho post {$postId} với object_story_id {$objectStoryId}");
            return true;
            
        } catch (\Exception $e) {
            Log::error("Lỗi khi sync engagement cho object_story_id {$objectStoryId}: " . $e->getMessage());
            return false;
        }
    }
    
    private function getPostEngagement(string $objectStoryId): ?array
    {
        try {
            $this->info("🔍 Đang lấy engagement data cho object_story_id: {$objectStoryId}");
            
            // Tách post_id từ object_story_id
            $parts = explode('_', $objectStoryId);
            if (count($parts) < 2) {
                Log::warning("object_story_id không đúng format: {$objectStoryId}");
                return null;
            }
            
            $pageId = $parts[0];
            $postId = $parts[1];
            
            // Tìm ad có post_id này để lấy ad_id
            $ad = \App\Models\FacebookAd::where('post_id', $postId)->first();
            if (!$ad) {
                Log::warning("Không tìm thấy ad cho post_id: {$postId}");
                return null;
            }
            
            // Sử dụng Ad Insights API để lấy engagement data từ quyền admin BM
            // Đây là cách chính xác và được Facebook khuyến nghị
            $engagementUrl = "https://graph.facebook.com/v23.0/{$ad->id}/insights";
            $engagementResp = \Illuminate\Support\Facades\Http::timeout(60)
                ->retry(1, 1000)
                ->get($engagementUrl, [
                    'access_token' => config('services.facebook.ads_token'),
                    'fields' => 'actions,action_values',
                    'action_breakdowns' => 'action_type',
                    'time_range' => json_encode([
                        'since' => date('Y-m-d', strtotime('-2 years')),
                        'until' => date('Y-m-d')
                    ])
                ]);
            
            // Log response để debug
            Log::info("Ad Insights API response", [
                'object_story_id' => $objectStoryId,
                'post_id' => $postId,
                'ad_id' => $ad->id,
                'status' => $engagementResp->status(),
                'body' => $engagementResp->json(),
            ]);
            
            if (!$engagementResp->successful()) {
                Log::warning("Không thể lấy engagement data từ Ad Insights API", [
                    'ad_id' => $ad->id,
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
            
            if (isset($engagementData['data'][0]['actions'])) {
                foreach ($engagementData['data'][0]['actions'] as $action) {
                    switch ($action['action_type']) {
                        case 'like':
                        case 'reaction':
                        case 'post_reaction':
                            $likes += (int) ($action['value'] ?? 0);
                            break;
                        case 'share':
                        case 'post_share':
                            $shares += (int) ($action['value'] ?? 0);
                            break;
                        case 'comment':
                        case 'post_comment':
                            $comments += (int) ($action['value'] ?? 0);
                            break;
                    }
                }
            }
            
            $result = [
                'likes' => $likes,
                'shares' => $shares,
                'comments' => $comments,
                'reactions' => $likes, // reactions = likes
            ];
            
            Log::info("Đã lấy được engagement data từ Ad Insights", [
                'object_story_id' => $objectStoryId,
                'post_id' => $postId,
                'ad_id' => $ad->id,
                'data' => $result
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error("Lỗi khi lấy engagement data cho object_story_id {$objectStoryId}: " . $e->getMessage());
            return null;
        }
    }
    
    private function updatePostInsights(FacebookPost $post, array $engagementData): void
    {
        try {
            // Tìm hoặc tạo post insight cho ngày hôm nay
            $insight = \App\Models\FacebookPostInsight::firstOrCreate(
                [
                    'post_id' => $post->id,
                    'date' => now()->toDateString(),
                ],
                [
                    'likes' => $engagementData['likes'] ?? 0,
                    'shares' => $engagementData['shares'] ?? 0,
                    'comments' => $engagementData['comments'] ?? 0,
                    'reactions' => $engagementData['reactions'] ?? 0,
                ]
            );
            
            // Cập nhật nếu đã tồn tại
            if ($insight->wasRecentlyCreated === false) {
                $insight->update([
                    'likes' => $engagementData['likes'] ?? 0,
                    'shares' => $engagementData['shares'] ?? 0,
                    'comments' => $engagementData['comments'] ?? 0,
                    'reactions' => $engagementData['reactions'] ?? 0,
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error("Lỗi khi cập nhật post insights cho post {$post->id}: " . $e->getMessage());
        }
    }
}
