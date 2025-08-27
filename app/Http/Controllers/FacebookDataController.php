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
        ];
        
        return response()->json($data);
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
} 