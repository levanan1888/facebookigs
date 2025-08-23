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
            // Lấy engagement data từ Facebook API
            $engagementData = $this->getPostEngagement($post->id);
            
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
    
    private function getPostEngagement(string $postId): ?array
    {
        try {
            // Lấy reactions count
            $reactionsUrl = "https://graph.facebook.com/v18.0/{$postId}/reactions";
            $reactionsResp = \Illuminate\Support\Facades\Http::timeout(60)
                ->retry(1, 1000)
                ->get($reactionsUrl, [
                    'access_token' => config('services.facebook.ads_token'),
                    'summary' => 'true',
                    'limit' => 0,
                ]);
            
            // Lấy comments count
            $commentsUrl = "https://graph.facebook.com/v18.0/{$postId}/comments";
            $commentsResp = \Illuminate\Support\Facades\Http::timeout(60)
                ->retry(1, 1000)
                ->get($commentsUrl, [
                    'access_token' => config('services.facebook.ads_token'),
                    'summary' => 'true',
                    'filter' => 'toplevel',
                    'limit' => 0,
                ]);
            
            // Lấy shares count
            $sharesUrl = "https://graph.facebook.com/v18.0/{$postId}";
            $sharesResp = \Illuminate\Support\Facades\Http::timeout(60)
                ->retry(1, 1000)
                ->get($sharesUrl, [
                    'access_token' => config('services.facebook.ads_token'),
                    'fields' => 'shares',
                ]);
            
            $reactions = $reactionsResp->successful() ? ($reactionsResp->json()['summary']['total_count'] ?? 0) : 0;
            $comments = $commentsResp->successful() ? ($commentsResp->json()['summary']['total_count'] ?? 0) : 0;
            $shares = $sharesResp->successful() ? ($sharesResp->json()['shares']['count'] ?? 0) : 0;
            
            return [
                'likes' => $reactions,
                'shares' => $shares,
                'comments' => $comments,
                'reactions' => $reactions,
            ];
            
        } catch (\Exception $e) {
            Log::error("Lỗi khi lấy engagement data cho post {$postId}: " . $e->getMessage());
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
