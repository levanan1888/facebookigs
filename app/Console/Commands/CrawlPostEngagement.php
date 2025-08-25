<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FacebookAd;
use App\Models\FacebookAdInsight;
use App\Services\FacebookAdsService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CrawlPostEngagement extends Command
{
    /**
     * Tên lệnh.
     */
    protected $signature = 'facebook:crawl-post-engagement {url : Link bài viết Facebook hoặc để trống nếu muốn tự dựng từ ad_insights}
                            {--debug : Ghi log chi tiết (URL thử, độ dài HTML, số bắt được)}
                            {--cookie= : Cookie phiên duyệt web (tuỳ chọn) để crawl UI ổn định hơn}';

    /**
     * Mô tả lệnh.
     */
    protected $description = 'Crawl likes/shares/comments từ link post và lưu vào facebook_ad_insights';

    private FacebookAdsService $facebookService;

    public function __construct()
    {
        parent::__construct();
        $this->facebookService = new FacebookAdsService();
    }

    public function handle(): int
    {
        $arg = (string) $this->argument('url');
        $url = trim($arg);
        // Cho phép truyền cookie runtime để vượt cơ chế chặn
        $cookie = (string) ($this->option('cookie') ?? '');
        if ($cookie !== '') {
            config(['services.facebook.crawl_cookie' => $cookie]);
        }

        $postId = $this->extractPostIdFromUrl($url);
        $pageIdFromUrl = null;
        if ($url !== '' && preg_match('~facebook\.com/(\d+)/posts/(\d+)~', $url, $m)) {
            $pageIdFromUrl = $m[1];
            if ($postId === null) {
                $postId = $m[2];
            }
        }

        // Nếu không truyền URL hợp lệ, thử dựng link từ bảng facebook_ad_insights nếu có cột post_id/page_id
        if ($postId === null && Schema::hasColumn('facebook_ad_insights', 'post_id') && Schema::hasColumn('facebook_ad_insights', 'page_id')) {
            $this->info('Không có URL hợp lệ. Thử dựng link từ facebook_ad_insights (post_id, page_id).');

            // Lấy bản ghi mới nhất có đủ post_id, page_id
            $insightRow = FacebookAdInsight::query()
                ->whereNotNull('post_id')
                ->whereNotNull('page_id')
                ->latest('date')
                ->first();

            if ($insightRow) {
                $postId = (string) $insightRow->post_id;
                $pageId = (string) $insightRow->page_id;
                $url = $this->buildPermalinkFromIds($pageId, $postId);
                $this->line("Dựng link: {$url}");
            }
        }

        if ($postId === null) {
            $this->error('Không trích xuất được post_id. Vui lòng cung cấp URL hợp lệ hoặc đảm bảo ad_insights có post_id/page_id.');
            return self::FAILURE;
        }

        $this->info("Post ID: {$postId}");

        // Tìm các ads có liên kết tới post này (để ưu tiên lấy qua Ad Insights API)
        $ads = FacebookAd::query()->where('post_id', $postId)->get();
        if ($ads->isEmpty()) {
            $this->warn('Không tìm thấy ad nào liên kết với post này. Fallback Post API nếu có quyền.');
        }

        // Ưu tiên Post API để ra số hiển thị (UI) chính xác
        $engagement = null;
        // Xác định pageId nếu có để dựng permalink đúng
        $pageId = null;
        if ($ads->isNotEmpty()) {
            $pageId = (string) ($ads->first()->page_id ?? '');
            if (!$pageId && $ads->first()->creative) {
                $creative = $ads->first()->creative->creative_data;
                if (is_string($creative)) {
                    $decoded = json_decode($creative, true);
                    if (json_last_error() === JSON_ERROR_NONE) { $creative = $decoded; }
                }
                $story = $creative['object_story_id'] ?? ($creative['effective_object_story_id'] ?? null);
                if (is_string($story) && strpos($story, '_') !== false) {
                    $parts = explode('_', $story, 2);
                    $pageId = $parts[0] ?? null;
                }
            }
        }

        // Crawl theo UI nếu có thể (ưu tiên đúng số hiển thị)
        $candidateUrls = [];
        // Ưu tiên dùng đúng URL người dùng truyền vào (nếu có)
        if ($url !== '') {
            $candidateUrls[] = $url;
            // Thêm các biến thể mbasic/m cho chính URL này
            $candidateUrls[] = preg_replace('~^https?://(?:www\.|m\.|mbasic\.)facebook\.com~i', 'https://mbasic.facebook.com', $url);
            $candidateUrls[] = preg_replace('~^https?://(?:www\.|m\.|mbasic\.)facebook\.com~i', 'https://m.facebook.com', $url);
        }
        if ($pageId) {
            $candidateUrls[] = "https://www.facebook.com/{$pageId}/posts/{$postId}";
            $candidateUrls[] = "https://www.facebook.com/story.php?story_fbid={$postId}&id={$pageId}";
            $candidateUrls[] = "https://mbasic.facebook.com/{$pageId}/posts/{$postId}";
            $candidateUrls[] = "https://m.facebook.com/{$pageId}/posts/{$postId}";
            $candidateUrls[] = "https://mbasic.facebook.com/story.php?story_fbid={$postId}&id={$pageId}";
            $candidateUrls[] = "https://m.facebook.com/story.php?story_fbid={$postId}&id={$pageId}";
        }
        if ($pageIdFromUrl) {
            $candidateUrls[] = "https://www.facebook.com/{$pageIdFromUrl}/posts/{$postId}";
            $candidateUrls[] = "https://www.facebook.com/story.php?story_fbid={$postId}&id={$pageIdFromUrl}";
            $candidateUrls[] = "https://mbasic.facebook.com/{$pageIdFromUrl}/posts/{$postId}";
            $candidateUrls[] = "https://m.facebook.com/{$pageIdFromUrl}/posts/{$postId}";
            $candidateUrls[] = "https://mbasic.facebook.com/story.php?story_fbid={$postId}&id={$pageIdFromUrl}";
            $candidateUrls[] = "https://m.facebook.com/story.php?story_fbid={$postId}&id={$pageIdFromUrl}";
        }
        // Fallback dựa trên postId (Facebook có thể redirect đúng permalink)
        $candidateUrls[] = "https://www.facebook.com/{$postId}";

        $data = ['reactions' => 0, 'comments' => 0, 'shares' => 0];
        // Thử headless trước khi UI thuần HTTP nếu cần độ ổn định
        $headlessTry = $this->facebookService->getPostEngagementCountsViaHeadless($candidateUrls[0] ?? $url);
        if (($headlessTry['reactions'] ?? 0) > 0 || ($headlessTry['comments'] ?? 0) > 0 || ($headlessTry['shares'] ?? 0) > 0) {
            $data = $headlessTry;
        }
        $debug = (bool) $this->option('debug');
        foreach ($candidateUrls as $uiUrl) {
            if (($data['reactions'] ?? 0) > 0 || ($data['comments'] ?? 0) > 0 || ($data['shares'] ?? 0) > 0) { break; }
            $try = $this->facebookService->getPostEngagementCountsViaUI($uiUrl);
            if ($debug) {
                Log::info('Crawl UI attempt', [
                    'url' => $uiUrl,
                    'html_len' => $try['raw_html_len'] ?? 0,
                    'http_status' => $try['http_status'] ?? null,
                    'fetched_from' => $try['source_url'] ?? null,
                    'likes' => $try['reactions'] ?? 0,
                    'comments' => $try['comments'] ?? 0,
                    'shares' => $try['shares'] ?? 0,
                ]);
            }
            if (($try['reactions'] ?? 0) > 0 || ($try['comments'] ?? 0) > 0 || ($try['shares'] ?? 0) > 0) {
                $data = $try;
                break;
            }
        }
        if (($data['reactions'] ?? 0) === 0 && ($data['comments'] ?? 0) === 0 && ($data['shares'] ?? 0) === 0) {
            // Fallback Post API bằng postId
            $data = $this->facebookService->getPostEngagementCounts($postId);
        }
        if (!empty($data)) {
            $engagement = [
                'likes' => (int) ($data['reactions'] ?? 0),
                'shares' => (int) ($data['shares'] ?? 0),
                'comments' => (int) ($data['comments'] ?? 0),
                'reactions' => (int) ($data['reactions'] ?? 0),
            ];
        }

        // Fallback: Ad Insights API nếu Post API không trả về
        if ($engagement === null) {
            $this->info('Fallback sang Ad Insights API để lấy engagement.');
            foreach ($ads as $ad) {
                $tmp = $this->facebookService->getAdEngagementData($ad->id);
                if (!isset($tmp['error'])) {
                    $engagement = $tmp;
                    break;
                }
            }
        }

        $likes = (int) ($engagement['likes'] ?? 0);
        $shares = (int) ($engagement['shares'] ?? 0);
        $comments = (int) ($engagement['comments'] ?? 0);
        $reactions = (int) ($engagement['reactions'] ?? $likes);

        $this->line("Likes: {$likes} | Shares: {$shares} | Comments: {$comments}");

        $today = Carbon::now()->toDateString();

        // Chọn ad để lưu (nếu có). Nếu không có ad, nhưng bảng ad_insights có post_id/page_id thì lưu theo ad gần nhất có liên quan hoặc bỏ qua
        $targetAdId = $ads->first()->id ?? null;

        if ($targetAdId === null) {
            if (Schema::hasColumn('facebook_ad_insights', 'post_id') && Schema::hasColumn('facebook_ad_insights', 'page_id')) {
                // Lưu/ cập nhật dòng theo post_id + date (không cần ad_id cụ thể) nếu schema cho phép
                $insight = FacebookAdInsight::query()->firstOrNew([
                    'post_id' => $postId,
                    'date' => $today,
                ]);

                // Nếu có page_id trong URL dựng được hoặc trong ads
                $pageId = null;
                if (isset($url) && preg_match('~facebook\.com/(\d+)/posts/(\d+)~', $url, $m)) {
                    $pageId = $m[1];
                }
                if ($pageId === null && $ads->isNotEmpty()) {
                    $pageId = $ads->first()->page_id;
                }
                if ($pageId !== null) {
                    $insight->page_id = (string) $pageId;
                }

                $actions = (array) ($insight->actions ?? []);
                $actions['like'] = $likes;
                $actions['reaction'] = $reactions;
                $actions['post_comment'] = $comments;
                $actions['post_share'] = $shares;

                $insight->actions = $actions;
                $insight->save();

                $this->info("Đã lưu engagement vào facebook_ad_insights cho post {$postId} ngày {$today}.");
                return self::SUCCESS;
            }

            $this->warn('Không có ad_id và bảng ad_insights không có post_id/page_id. Bỏ qua lưu.');
            return self::SUCCESS;
        }

        // Upsert theo ad_id + date
        $insight = FacebookAdInsight::query()->firstOrNew([
            'ad_id' => $targetAdId,
            'date' => $today,
        ]);

        // Nếu schema có post_id/page_id, set để thuận tiện lần sau
        if (Schema::hasColumn('facebook_ad_insights', 'post_id')) {
            $insight->post_id = $postId;
        }
        if (Schema::hasColumn('facebook_ad_insights', 'page_id')) {
            $insight->page_id = (string) ($ads->first()->page_id ?? '');
        }

        $actions = (array) ($insight->actions ?? []);
        $actions['like'] = $likes;
        $actions['reaction'] = $reactions;
        $actions['post_comment'] = $comments;
        $actions['post_share'] = $shares;

        $insight->actions = $actions;
        $insight->save();

        $this->info("Đã lưu engagement vào facebook_ad_insights cho ad {$targetAdId} ngày {$today}.");
        return self::SUCCESS;
    }

    /**
     * Trích xuất post_id từ nhiều định dạng URL Facebook phổ biến.
     */
    private function extractPostIdFromUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (preg_match('~/(?:posts|videos|photos)/(\d+)~', $url, $m)) {
            return $m[1];
        }

        $parts = parse_url($url);
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $qs);
            if (!empty($qs['story_fbid']) && is_numeric($qs['story_fbid'])) {
                return (string) $qs['story_fbid'];
            }
            if (!empty($qs['post_id']) && is_numeric($qs['post_id'])) {
                return (string) $qs['post_id'];
            }
        }

        if (preg_match('~(?:object_story_id|story_fbid)=(\d+)_(\d+)~', $url, $m)) {
            return $m[2];
        }

        if (preg_match('~/(\d+)(?:/?(?:\?|$))~', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Dựng permalink từ page_id và post_id.
     */
    private function buildPermalinkFromIds(string $pageId, string $postId): string
    {
        return sprintf('https://www.facebook.com/%s/posts/%s', $pageId, $postId);
    }
}
