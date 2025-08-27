<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\FacebookDataFilterRequest;
use App\Services\FacebookDataService;
use App\Http\Resources\FacebookPostResource;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FacebookDataController extends Controller
{
    public function __construct(
        private readonly FacebookDataService $facebookDataService
    ) {
        //
    }

    /**
     * Hiển thị màn hình quản lý dữ liệu Facebook
     */
    public function index(FacebookDataFilterRequest $request): View
    {
        $filters = $request->validated();
        
        // Đảm bảo tất cả keys đều có, ngay cả khi null
        $filters = array_merge([
            'page_id' => null,
            'date_from' => null,
            'date_to' => null,
            'post_type' => null,
            'status' => null,
            'search' => null,
        ], $filters);
        
        $data = $this->facebookDataService->getFacebookData($filters);
        
        return view('facebook.data-management.index', compact('data', 'filters'));
    }

    /**
     * API endpoint để lấy danh sách posts theo page
     */
    public function getPostsByPage(Request $request)
    {
        $pageId = $request->input('page_id');
        $filters = $request->only(['date_from', 'date_to', 'post_type', 'status', 'search']);
        
        $posts = $this->facebookDataService->getPostsByPage($pageId, $filters);
        
        return FacebookPostResource::collection($posts);
    }

    /**
     * API endpoint để lấy data cho page (posts + stats)
     */
    public function getPageData(Request $request)
    {
        $pageId = $request->input('page_id');
        $filters = $request->only(['date_from', 'date_to', 'post_type', 'status', 'search']);
        
        $data = [
            'posts' => $this->facebookDataService->getPostsByPage($pageId, $filters),
            'spending_stats' => $this->facebookDataService->getPostSpendingStats($pageId, $filters['date_from'] ?? null, $filters['date_to'] ?? null),
            'page_summary' => $this->facebookDataService->getPageSummary($pageId),
            'breakdowns' => [
                'device' => $this->facebookDataService->getDeviceBreakdown($pageId, $filters['date_from'] ?? null, $filters['date_to'] ?? null),
                'region' => $this->facebookDataService->getRegionBreakdown($pageId, $filters['date_from'] ?? null, $filters['date_to'] ?? null),
                'gender_age' => $this->facebookDataService->getGenderAgeBreakdown($pageId, $filters['date_from'] ?? null, $filters['date_to'] ?? null),
                'correlation' => $this->facebookDataService->getBudgetResultConversionCorrelation($pageId, $filters['date_from'] ?? null, $filters['date_to'] ?? null),
            ],
        ];
        
        return response()->json($data);
    }

    /**
     * API: Lấy tóm tắt AI theo góc nhìn marketing quản lý
     */
    public function getAiSummary(Request $request)
    {
        $pageId = (string) $request->input('page_id');
        $since = $request->input('date_from');
        $until = $request->input('date_to');
        $metrics = $request->input('metrics', []);

        try {
            $summary = app(\App\Services\GeminiService::class)->generateMarketingSummary($pageId, $since, $until, $metrics);
            return response()->json(['summary' => $summary]);
        } catch (\Throwable $e) {
            return response()->json(['summary' => 'Không tạo được nhận định AI lúc này.'], 200);
        }
    }

    /**
     * API endpoint để lấy thống kê chi phí theo bài viết
     */
    public function getPostSpendingStats(Request $request)
    {
        $pageId = $request->input('page_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        
        $stats = $this->facebookDataService->getPostSpendingStats($pageId, $dateFrom, $dateTo);
        
        return response()->json($stats);
    }

    /**
     * API endpoint để lấy chi tiết chiến dịch quảng cáo
     */
    public function getAdCampaigns(Request $request)
    {
        $postId = $request->get('post_id');
        $pageId = $request->get('page_id');
        
        if (!$postId || !$pageId) {
            return response()->json(['error' => 'Missing post_id or page_id'], 400);
        }
        
        $data = $this->facebookDataService->getAdCampaigns($postId, $pageId);
        return response()->json($data);
    }

    public function getAdBreakdowns(Request $request)
    {
        $adId = $request->get('ad_id');
        
        if (!$adId) {
            return response()->json(['error' => 'Missing ad_id'], 400);
        }
        
        $data = $this->facebookDataService->getAdBreakdowns($adId);
        return response()->json($data);
    }

    public function getAdInsights(Request $request)
    {
        $adId = $request->get('ad_id');
        
        if (!$adId) {
            return response()->json(['error' => 'Missing ad_id'], 400);
        }
        
        $data = $this->facebookDataService->getAdInsights($adId);
        return response()->json($data);
    }

    public function showPostDetail(string $postId, string $pageId)
    {
        try {
            // Lấy thông tin post
            $post = $this->facebookDataService->getPostById($postId, $pageId);
            
            if (!$post) {
                abort(404, 'Post không tồn tại');
            }
            
            // Lấy breakdown data
            $breakdowns = $this->facebookDataService->getPostBreakdowns($postId, $pageId);
            $detailedBreakdowns = $this->facebookDataService->getPostDetailedBreakdowns($postId, $pageId);
            
            // Lấy insights data
            $insights = $this->facebookDataService->getPostInsights($postId, $pageId);
            
            // Lấy actions data
            $actions = $this->facebookDataService->getPostActions($postId, $pageId);
            
            return view('facebook.data-management.post-detail', [
                'post' => $post,
                'breakdowns' => $breakdowns,
                'detailedBreakdowns' => $detailedBreakdowns,
                'insights' => $insights,
                'actions' => $actions,
                'pageId' => $pageId
            ]);
            
        } catch (\Exception $e) {
            abort(500, 'Lỗi khi tải dữ liệu post: ' . $e->getMessage());
        }
    }
} 