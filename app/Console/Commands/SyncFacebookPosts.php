<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FacebookAd;
use App\Services\FacebookAdsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncFacebookPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'facebook:sync-posts 
                            {--limit=0 : Giới hạn số lượng posts để sync (0 = tất cả)}
                            {--post-id= : ID của post cụ thể để sync}
                            {--force : Bắt buộc sync lại ngay cả khi đã sync gần đây}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Đồng bộ data chi tiết của Facebook Posts (caption, likes, shares, comments, insights)';

    private FacebookAdsService $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = new FacebookAdsService();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Bắt đầu đồng bộ Facebook Posts...');
        
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
            Log::error('Lỗi trong SyncFacebookPosts: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
    
    /**
     * Đồng bộ một post cụ thể
     */
    private function syncSpecificPost(string $postId, bool $force = false): int
    {
        $this->info("📝 Đang đồng bộ post: {$postId}");
        
        try {
            // Kiểm tra xem post có tồn tại trong database không
            // Post ID được lưu dưới dạng JSON string, nên cần tìm kiếm chính xác
            $ad = FacebookAd::where('post_id', json_encode($postId))->first();
            
            if (!$ad) {
                $this->warn("⚠️  Post ID {$postId} không tìm thấy trong database");
                return Command::FAILURE;
            }
            
            $result = $this->syncPostData($ad, $force);
            
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
    
    /**
     * Đồng bộ tất cả posts
     */
    private function syncAllPosts(int $limit, bool $force = false): int
    {
        $this->info('📊 Đang lấy danh sách posts cần đồng bộ...');
        
        // Lấy posts có post_id nhưng chưa có data chi tiết hoặc cần sync lại
        $query = FacebookAd::whereNotNull('post_id')
            ->where(function($q) use ($force) {
                if ($force) {
                    // Nếu force, lấy tất cả
                    return $q;
                } else {
                    // Nếu không force, chỉ lấy những post chưa có data hoặc sync lâu rồi
                    return $q->where(function($subQ) {
                        $subQ->whereNull('post_message')
                            ->orWhereNull('post_likes')
                            ->orWhere('last_insights_sync', '<', now()->subDays(1));
                    });
                }
            });
        
        if ($limit > 0) {
            $query->limit($limit);
        }
        
        $posts = $query->get();
        $totalPosts = $posts->count();
        
        if ($totalPosts === 0) {
            $this->info('✅ Không có posts nào cần đồng bộ');
            return Command::SUCCESS;
        }
        
        $this->info("📝 Tìm thấy {$totalPosts} posts cần đồng bộ");
        
        $successCount = 0;
        $errorCount = 0;
        
        $bar = $this->output->createProgressBar($totalPosts);
        $bar->start();
        
        foreach ($posts as $post) {
            try {
                $result = $this->syncPostData($post, $force);
                if ($result) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
                
                $bar->advance();
                
                // Rate limiting
                usleep(500000); // 0.5 giây giữa các requests
                
            } catch (\Exception $e) {
                $errorCount++;
                Log::error("Lỗi khi sync post {$post->post_id}: " . $e->getMessage());
                $bar->advance();
            }
        }
        
        $bar->finish();
        $this->newLine();
        
        // Hiển thị kết quả
        $this->info('✅ Hoàn thành đồng bộ posts!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Tổng posts', $totalPosts],
                ['Thành công', $successCount],
                ['Lỗi', $errorCount],
                ['Tỷ lệ thành công', round(($successCount / $totalPosts) * 100, 2) . '%'],
            ]
        );
        
        return Command::SUCCESS;
    }
    
    /**
     * Đồng bộ data cho một post cụ thể
     */
    private function syncPostData(FacebookAd $ad, bool $force = false): bool
    {
        try {
            // Chuẩn hóa post_id/page_id do có thể lưu dưới dạng JSON string
            $postIdRaw = $ad->post_id;
            $pageIdRaw = $ad->page_id;
            $postIdOnly = is_string($postIdRaw) ? trim($postIdRaw, "\"") : (string) $postIdRaw;
            $pageIdOnly = is_string($pageIdRaw) ? trim($pageIdRaw, "\"") : (string) $pageIdRaw;
            // Dùng composite id: {pageId}_{postId} để gọi Graph API cho posts
            $postId = $pageIdOnly && $postIdOnly ? ($pageIdOnly . '_' . $postIdOnly) : $postIdOnly;
            
            // Kiểm tra xem có cần sync không
            if (!$force && $ad->last_insights_sync && $ad->last_insights_sync->isAfter(now()->subHours(6))) {
                $this->line("⏭️  Post {$postId} đã được sync gần đây, bỏ qua");
                return true;
            }
            
            $this->line("🔄 Đang sync post: {$postId}");
            
            // 1. Lấy post details
            $postDetails = $this->api->getPostDetails($postId);
            
            // Debug: In ra response để kiểm tra
            $this->line("🔍 Post Details Response: " . json_encode($postDetails));
            
            if (isset($postDetails['error'])) {
                $errorMessage = $postDetails['error']['message'] ?? 'Unknown error';
                $this->warn("⚠️  Không thể lấy post details cho {$postId}: {$errorMessage}");
                
                // Kiểm tra xem có phải lỗi quyền không
                if (strpos($errorMessage, 'pages_read_engagement') !== false || 
                    strpos($errorMessage, 'Page Public Content Access') !== false) {
                    $this->error("🔒 Lỗi quyền truy cập Facebook API:");
                    $this->error("   - Cần permission: pages_read_engagement");
                    $this->error("   - Hoặc cần feature: Page Public Content Access");
                    $this->error("   - Xem: https://developers.facebook.com/docs/apps/review/login-permissions#manage-pages");
                }
                
                return false;
            }
            
            // 2. Lấy post insights
            $postInsights = $this->api->getPostInsightsExtended($postId);
            
            if (isset($postInsights['error'])) {
                $errorMessage = $postInsights['error']['message'] ?? 'Unknown error';
                $this->warn("⚠️  Không thể lấy post insights cho {$postId}: {$errorMessage}");
                
                // Kiểm tra xem có phải lỗi quyền không
                if (strpos($errorMessage, 'pages_read_engagement') !== false || 
                    strpos($errorMessage, 'Page Public Content Access') !== false) {
                    $this->error("🔒 Lỗi quyền truy cập Facebook API cho insights:");
                    $this->error("   - Cần permission: pages_read_engagement");
                    $this->error("   - Hoặc cần feature: Page Public Content Access");
                }
                
                // Vẫn tiếp tục với post details nếu có
            }
            
            // 2b. Lấy engagement counts trực tiếp (likes, comments, shares)
            $engagementCounts = $this->api->getPostEngagementCounts($postId);
            
            // 3. Cập nhật database
            $updateData = $this->preparePostUpdateData($postDetails, $postInsights);
            // Ghi đè/điền likes, comments, shares nếu có
            if (!empty($engagementCounts)) {
                $updateData['post_likes'] = $engagementCounts['reactions'] ?? ($updateData['post_likes'] ?? 0);
                $updateData['post_comments'] = $engagementCounts['comments'] ?? ($updateData['post_comments'] ?? 0);
                $updateData['post_shares'] = $engagementCounts['shares'] ?? ($updateData['post_shares'] ?? 0);
            }
            
            $ad->update($updateData);
            
            $this->line("✅ Đã cập nhật post: {$postId}");
            return true;
            
        } catch (\Exception $e) {
            $this->error("❌ Lỗi khi sync post {$ad->post_id}: " . $e->getMessage());
            Log::error("Lỗi sync post data", [
                'post_id' => $ad->post_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    /**
     * Chuẩn bị dữ liệu để cập nhật
     */
    private function preparePostUpdateData(array $postDetails, ?array $postInsights): array
    {
        $data = [
            'post_message' => $postDetails['message'] ?? null,
            'post_type' => $postDetails['type'] ?? null,
            'post_status_type' => $postDetails['status_type'] ?? null,
            'post_attachments' => isset($postDetails['attachments']) ? json_encode($postDetails['attachments']) : null,
            'post_permalink_url' => $postDetails['permalink_url'] ?? null,
            'post_created_time' => isset($postDetails['created_time']) ? Carbon::parse($postDetails['created_time']) : null,
            'post_updated_time' => isset($postDetails['updated_time']) ? Carbon::parse($postDetails['updated_time']) : null,
            'last_insights_sync' => now(),
        ];
        
        // Thêm insights data nếu có
        if ($postInsights && !isset($postInsights['error']) && isset($postInsights['data'][0])) {
            $insight = $postInsights['data'][0];
            
            $data = array_merge($data, [
                'post_impressions' => (int) ($insight['impressions'] ?? 0),
                'post_reach' => (int) ($insight['reach'] ?? 0),
                'post_clicks' => (int) ($insight['clicks'] ?? 0),
                'post_unique_clicks' => (int) ($insight['unique_clicks'] ?? 0),
                'post_likes' => (int) ($insight['likes'] ?? 0),
                'post_shares' => (int) ($insight['shares'] ?? 0),
                'post_comments' => (int) ($insight['comments'] ?? 0),
                'post_reactions' => (int) ($insight['reactions'] ?? 0),
                'post_saves' => (int) ($insight['saves'] ?? 0),
                'post_hides' => (int) ($insight['hides'] ?? 0),
                'post_hide_all_clicks' => (int) ($insight['hide_all_clicks'] ?? 0),
                'post_unlikes' => (int) ($insight['unlikes'] ?? 0),
                'post_negative_feedback' => (int) ($insight['negative_feedback'] ?? 0),
                'post_video_views' => (int) ($insight['video_views'] ?? 0),
                'post_video_view_time' => (int) ($insight['video_view_time'] ?? 0),
                'post_video_avg_time_watched' => (float) ($insight['video_avg_time_watched'] ?? 0),
                'post_video_p25_watched_actions' => (int) ($insight['video_p25_watched_actions'] ?? 0),
                'post_video_p50_watched_actions' => (int) ($insight['video_p50_watched_actions'] ?? 0),
                'post_video_p75_watched_actions' => (int) ($insight['video_p75_watched_actions'] ?? 0),
                'post_video_p95_watched_actions' => (int) ($insight['video_p95_watched_actions'] ?? 0),
                'post_video_p100_watched_actions' => (int) ($insight['video_p100_watched_actions'] ?? 0),
                'post_engagement_rate' => (float) ($insight['engagement_rate'] ?? 0),
                'post_ctr' => (float) ($insight['ctr'] ?? 0),
                'post_cpm' => (float) ($insight['cpm'] ?? 0),
                'post_cpc' => (float) ($insight['cpc'] ?? 0),
                'post_spend' => (float) ($insight['spend'] ?? 0),
                'post_frequency' => (float) ($insight['frequency'] ?? 0),
                'post_actions' => isset($insight['actions']) ? json_encode($insight['actions']) : null,
                'post_action_values' => isset($insight['action_values']) ? json_encode($insight['action_values']) : null,
                'post_cost_per_action_type' => isset($insight['cost_per_action_type']) ? json_encode($insight['cost_per_action_type']) : null,
                'post_cost_per_unique_action_type' => isset($insight['cost_per_unique_action_type']) ? json_encode($insight['cost_per_unique_action_type']) : null,
                'post_breakdowns' => isset($insight['breakdowns']) ? json_encode($insight['breakdowns']) : null,
            ]);
        }
        
        return $data;
    }
}
