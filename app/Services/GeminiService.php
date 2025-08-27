<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeminiService
{
    /**
     * Gọi Gemini để tạo nhận định marketing cấp quản lý
     */
    public function generateMarketingSummary(string $pageId, ?string $since, ?string $until, array $metrics): string
    {
        $apiKey = config('services.gemini.api_key') ?: env('GEMINI_API_KEY');
        if (!$apiKey) {
            return 'Chưa cấu hình GEMINI_API_KEY trong .env';
        }

        $prompt = $this->buildPrompt($pageId, $since, $until, $metrics);

        try {
            // Attempt: 1.5-flash with retry + timeouts
            $text = $this->callGemini('gemini-1.5-flash', $apiKey, $prompt);
            if ($text === null) {
                // Fallback: gemini-pro
                $text = $this->callGemini('gemini-pro', $apiKey, $prompt);
            }
            if ($text !== null) {
                return $text;
            }
            // If all failed, build a local quick summary as graceful fallback
            return $this->buildLocalFallbackSummary($metrics, $since, $until);
        } catch (\Throwable $e) {
            // Graceful fallback instead of surfacing cURL 28
            return $this->buildLocalFallbackSummary($metrics, $since, $until);
        }
    }

    private function buildPrompt(string $pageId, ?string $since, ?string $until, array $metrics): string
    {
        $period = ($since && $until) ? "Từ {$since} đến {$until}" : '7-30 ngày gần đây';
        $json = json_encode($metrics, JSON_UNESCAPED_UNICODE);
        return "Vai trò: Bạn là lãnh đạo marketing (CMO) 10+ năm kinh nghiệm. Mục tiêu: đánh giá hiệu quả chiến dịch và đặc biệt là tác động của nội dung video lên người dùng, bằng ngôn ngữ đơn giản dễ hiểu cho cả người không chuyên.\n\nBối cảnh: Page {$pageId}, giai đoạn {$period}. Input JSON (đầy đủ tham số từ màn post detail): {$json}.\n\nPhạm vi phân tích (nếu có dữ liệu):\n- Hiệu quả tổng quan: spend, impressions, reach, clicks, CTR, CPC, CPM, conversions, conversion_values, ROAS.\n- Video & mức độ xem: video_views, video_plays, p25/p50/p75/p95/p100, 30s, avg_time, view_time. Khi diễn giải, quy đổi các mốc % thành 50%/70%/80% theo logic gần nhất (vd: 70%≈giữa p50–p75, 80%≈giữa p75–p95) để người đọc dễ hình dung.\n- Phân khúc: thiết bị, khu vực/tỉnh, giới tính–độ tuổi, vị trí/nền tảng.\n- Hành vi: actions theo thời gian.\n- Chất lượng dữ liệu: unknown/missing/outliers.\n\nĐầu ra (tiếng Việt, cực kỳ rõ ràng, có số liệu):\n1) **Kết luận nhanh về hiệu quả** (chiến dịch/video có hiệu quả hay không, lý do).\n2) **Tác động video lên người dùng**: nêu rõ tỉ lệ xem đến 50%/70%/80%/100%, thời gian xem TB, điểm rơi người dùng rời bỏ (drop-off), nội dung nào giữ người dùng tốt.\n3) **Insight theo phân khúc**: thiết bị/khu vực/giới tính–độ tuổi/placement; nêu nơi hiệu quả nhất và kém nhất.\n4) **Khuyến nghị hành động cụ thể** (5–7 mục): tối ưu nội dung, A/B test (thumbnail, độ dài, hook 3s), tối ưu đối tượng/lịch chạy/ngân sách, cải thiện tracking/phễu.\n5) **KPI theo dõi tuần tới**: 3 KPI ngắn gọn (vd: CTR, % xem ≥70%, CPC/CPV/CPA).\n\nQuy tắc trình bày: gạch đầu dòng '*' hoặc '-', dùng **đậm** cho ý chính; không dùng thuật ngữ khó; diễn giải số thành câu dễ hiểu (ví dụ: \"100 người xem có ~28 người xem ≥70%\"). Không bịa số liệu nếu không có trong dữ liệu.";
    }

    /**
     * Gọi Gemini với retry/timeout. Trả về text hoặc null nếu lỗi.
     */
    private function callGemini(string $model, string $apiKey, string $prompt): ?string
    {
        // Use header x-goog-api-key per official REST spec
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent';
        $response = Http::retry(1, 800)
            ->connectTimeout(5)
            ->timeout(12)
            ->withHeaders([
                'x-goog-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->asJson()
            ->post($endpoint, [
                'contents' => [
                    ['parts' => [['text' => $prompt]]]
                ]
            ]);

        if (!$response->ok()) {
            return null;
        }
        $data = $response->json();
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    }

    /**
     * Fallback nội bộ khi AI không phản hồi: tạo nhận định đơn giản dựa trên số liệu.
     */
    private function buildLocalFallbackSummary(array $metrics, ?string $since, ?string $until): string
    {
        $period = ($since && $until) ? "{$since} → {$until}" : 'giai đoạn gần đây';
        $summary = $metrics['summary'] ?? ($metrics['page_summary'] ?? []);
        $totalSpend = (float)($summary['total_spend'] ?? 0);
        $impressions = (int)($summary['total_impressions'] ?? 0);
        $clicks = (int)($summary['total_clicks'] ?? 0);
        $conversions = (int)($summary['total_conversions'] ?? 0);
        $avgCtr = (float)($summary['avg_ctr'] ?? ($impressions > 0 ? ($clicks / max(1,$impressions)) : 0));
        $avgCpc = (float)($summary['avg_cpc'] ?? ($clicks > 0 ? ($totalSpend / $clicks) : 0));

        $lines = [];
        $lines[] = '* **Insight:** Chi phí hiện tại ' . number_format((int)$totalSpend) . ' VND, CTR ~ ' . number_format($avgCtr * 100, 2) . '%, CPC ~ ' . number_format((int)$avgCpc) . ' VND.';
        $lines[] = '  **Hành động:** Tăng chất lượng nội dung (A/B headline/creative), tối ưu đối tượng và lịch chạy để hạ CPC, nâng CTR.';
        if ($impressions > 0 && $clicks > 0 && $conversions === 0) {
            $lines[] = '* **Insight:** Có click nhưng ít/nhiều chuyển đổi.';
            $lines[] = '  **Hành động:** Rà soát landing page, gắn tracking (Pixel/GA4), tối ưu form/CTA, kiểm thử phễu.';
        }
        $lines[] = '* **Insight:** Thời gian ' . $period . ' thiếu nhận định AI do kết nối chậm.';
        $lines[] = '  **Hành động:** Dùng nhận định nhanh này, thử lại AI sau.';

        return implode("\n", $lines);
    }
}


