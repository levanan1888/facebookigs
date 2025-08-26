<x-layouts.app :title="'Quản lý dữ liệu Facebook'">
<div class="p-6">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Quản lý dữ liệu Facebook</h1>
        <p class="text-gray-600">Quản lý và phân tích dữ liệu từ các trang Facebook và bài viết</p>
    </div>

    <!-- Page Selection -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
        <div class="flex items-center space-x-4">
            <label for="page-select" class="text-sm font-medium text-gray-700 min-w-[120px]">
                Chọn Trang Facebook:
            </label>
            <select id="page-select" name="page_id" class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">-- Chọn trang --</option>
                @foreach($data['pages'] as $page)
                    <option value="{{ $page->id }}" 
                            {{ $filters['page_id'] == $page->id ? 'selected' : '' }}
                            data-fan-count="{{ $page->fan_count }}"
                            data-category="{{ $page->category }}">
                        {{ $page->name }} 
                        ({{ number_format($page->fan_count) }} fan{{ $page->ads_count > 0 ? ', ' . $page->ads_count . ' quảng cáo' : '' }})
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    @if($data['selected_page'])
        <!-- Page Overview -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-gray-900">{{ $data['selected_page']->name }}</h2>
                <div class="flex items-center space-x-2 text-sm text-gray-600">
                    <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full">{{ $data['selected_page']->category }}</span>
                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full">{{ number_format($data['selected_page']->fan_count) }} fan</span>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600">{{ number_format($data['posts']->count()) }}</div>
                    <div class="text-sm text-gray-600">Bài viết</div>
                </div>
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <div class="text-2xl font-bold text-green-600">{{ number_format($data['spending_stats']['summary']['total_spend'] ?? 0, 0) }}</div>
                    <div class="text-sm text-gray-600">Tổng chi phí (VND)</div>
                </div>
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <div class="text-2xl font-bold text-purple-600">{{ number_format($data['spending_stats']['summary']['total_impressions'] ?? 0) }}</div>
                    <div class="text-sm text-gray-600">Tổng hiển thị</div>
                </div>
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <div class="text-2xl font-bold text-orange-600">{{ number_format($data['spending_stats']['summary']['total_clicks'] ?? 0) }}</div>
                    <div class="text-sm text-gray-600">Tổng click</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Bộ lọc</h3>
            <form id="filter-form" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <input type="hidden" name="page_id" value="{{ $filters['page_id'] }}">
                
                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">Từ ngày</label>
                    <input type="date" id="date_from" name="date_from" 
                           value="{{ $filters['date_from'] ?? '' }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">Đến ngày</label>
                    <input type="date" id="date_to" name="date_to" 
                           value="{{ $filters['date_to'] ?? '' }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="post_type" class="block text-sm font-medium text-gray-700 mb-1">Loại bài viết</label>
                    <select id="post_type" name="post_type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Tất cả</option>
                        <option value="status" {{ ($filters['post_type'] ?? '') == 'status' ? 'selected' : '' }}>Trạng thái</option>
                        <option value="photo" {{ ($filters['post_type'] ?? '') == 'photo' ? 'selected' : '' }}>Hình ảnh</option>
                        <option value="video" {{ ($filters['post_type'] ?? '') == 'video' ? 'selected' : '' }}>Video</option>
                        <option value="link" {{ ($filters['post_type'] ?? '') == 'link' ? 'selected' : '' }}>Liên kết</option>
                        <option value="event" {{ ($filters['post_type'] ?? '') == 'event' ? 'selected' : '' }}>Sự kiện</option>
                        <option value="offer" {{ ($filters['post_type'] ?? '') == 'offer' ? 'selected' : '' }}>Ưu đãi</option>
                    </select>
                </div>
                
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Tìm kiếm</label>
                    <input type="text" id="search" name="search" 
                           value="{{ $filters['search'] ?? '' }}"
                           placeholder="Tìm trong nội dung..."
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                
                <div class="md:col-span-2 lg:col-span-4 flex justify-end space-x-3">
                    <button type="button" id="clear-filters" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Xóa bộ lọc
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Áp dụng
                    </button>
                </div>
            </form>
        </div>

        <!-- Posts List -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Danh sách bài viết</h3>
            
            @if($data['posts']->count() > 0)
                <div class="space-y-4">
                    @foreach($data['posts'] as $post)
                        <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-2 mb-2">
                                        <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                                            {{ ucfirst($post->type) }}
                                        </span>
                                        <span class="text-sm text-gray-500">
                                            {{ $post->created_time->format('d/m/Y H:i') }}
                                        </span>
                                    </div>
                                    
                                    <p class="text-gray-900 mb-3 line-clamp-3">
                                        {{ Str::limit($post->message, 200) ?: 'Không có nội dung' }}
                                    </p>
                                    
                                    <!-- Post Stats -->
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                        <div class="text-center">
                                            <div class="font-semibold text-blue-600">{{ number_format($post->likes_count ?? 0) }}</div>
                                            <div class="text-gray-600">Lượt thích</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="font-semibold text-green-600">{{ number_format($post->shares_count ?? 0) }}</div>
                                            <div class="text-gray-600">Chia sẻ</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="font-semibold text-purple-600">{{ number_format($post->comments_count ?? 0) }}</div>
                                            <div class="text-gray-600">Bình luận</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="font-semibold text-orange-600">{{ number_format($post->reactions_count ?? 0) }}</div>
                                            <div class="text-gray-600">Tương tác</div>
                                        </div>
                                    </div>
                                    
                                    <!-- Ad Insights Summary -->
                                    @if($post->ads->count() > 0)
                                        <div class="mt-3 p-3 bg-gray-50 rounded-lg">
                                            <div class="text-sm font-medium text-gray-700 mb-2">Thống kê quảng cáo:</div>
                                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
                                                @php
                                                    $totalSpend = $post->ads->sum(function($ad) {
                                                        return $ad->insights->sum('spend');
                                                    });
                                                    $totalImpressions = $post->ads->sum(function($ad) {
                                                        return $ad->insights->sum('impressions');
                                                    });
                                                    $totalClicks = $post->ads->sum(function($ad) {
                                                        return $ad->insights->sum('clicks');
                                                    });
                                                @endphp
                                                <div>
                                                    <span class="text-gray-600">Chi phí:</span>
                                                    <span class="font-semibold text-red-600 ml-1">{{ number_format($totalSpend, 0) }} VND</span>
                                                </div>
                                                <div>
                                                    <span class="text-gray-600">Hiển thị:</span>
                                                    <span class="font-semibold text-blue-600 ml-1">{{ number_format($totalImpressions) }}</span>
                                                </div>
                                                <div>
                                                    <span class="text-gray-600">Click:</span>
                                                    <span class="font-semibold text-green-600 ml-1">{{ number_format($totalClicks) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                
                                <div class="flex flex-col space-y-2 ml-4">
                                    @if($post->permalink_url)
                                        <a href="{{ $post->permalink_url }}" target="_blank" 
                                           class="inline-flex items-center px-3 py-2 text-sm font-medium text-blue-600 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                            </svg>
                                            Xem bài viết
                                        </a>
                                    @endif
                                    
                                    @if($data['selected_page'])
                                        <a href="https://facebook.com/{{ $data['selected_page']->id }}" target="_blank"
                                           class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-600 bg-gray-50 border border-gray-200 rounded-md hover:bg-gray-100">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                            </svg>
                                            Xem trang
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">Không có bài viết nào</h3>
                    <p class="mt-1 text-sm text-gray-500">Không tìm thấy bài viết nào phù hợp với bộ lọc hiện tại.</p>
                </div>
            @endif
        </div>

        <!-- Spending Statistics -->
        @if(!empty($data['spending_stats']['posts']))
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Thống kê chi phí theo bài viết</h3>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bài viết</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ngày đăng</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Chi phí (VND)</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hiển thị</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Click</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CPC (VND)</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CPM (VND)</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($data['spending_stats']['posts'] as $stat)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ Str::limit($stat->message, 50) ?: 'Không có nội dung' }}
                                        </div>
                                        @if($stat->permalink_url)
                                            <a href="{{ $stat->permalink_url }}" target="_blank" class="text-sm text-blue-600 hover:text-blue-800">
                                                Xem bài viết →
                                            </a>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ \Carbon\Carbon::parse($stat->created_time)->format('d/m/Y') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-red-600">
                                        {{ number_format($stat->total_spend, 0) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ number_format($stat->total_impressions) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ number_format($stat->total_clicks) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ number_format($stat->avg_cpc, 0) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ number_format($stat->avg_cpm, 0) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">Tổng cộng</td>
                                <td class="px-6 py-4"></td>
                                <td class="px-6 py-4 text-sm font-bold text-red-600">
                                    {{ number_format($data['spending_stats']['summary']['total_spend'], 0) }}
                                </td>
                                <td class="px-6 py-4 text-sm font-bold text-gray-900">
                                    {{ number_format($data['spending_stats']['summary']['total_impressions']) }}
                                </td>
                                <td class="px-6 py-4 text-sm font-bold text-gray-900">
                                    {{ number_format($data['spending_stats']['summary']['total_clicks']) }}
                                </td>
                                <td class="px-6 py-4 text-sm font-bold text-gray-900">
                                    {{ number_format($data['spending_stats']['summary']['avg_cpc'], 0) }}
                                </td>
                                <td class="px-6 py-4 text-sm font-bold text-gray-900">
                                    {{ number_format($data['spending_stats']['summary']['avg_cpm'], 0) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif
    @else
        <!-- No Page Selected -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">Chưa chọn trang Facebook</h3>
            <p class="mt-1 text-sm text-gray-500">Vui lòng chọn một trang Facebook từ dropdown bên trên để xem dữ liệu.</p>
        </div>
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const pageSelect = document.getElementById('page-select');
    const filterForm = document.getElementById('filter-form');
    const clearFiltersBtn = document.getElementById('clear-filters');
    
    // Auto-submit form when page changes
    pageSelect.addEventListener('change', function() {
        if (this.value) {
            filterForm.submit();
        }
    });
    
    // Clear filters
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', function() {
            const inputs = filterForm.querySelectorAll('input, select');
            inputs.forEach(input => {
                if (input.name !== 'page_id') {
                    input.value = '';
                }
            });
            filterForm.submit();
        });
    }
    
    // Date validation
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');
    
    if (dateFrom && dateTo) {
        dateFrom.addEventListener('change', function() {
            if (dateTo.value && this.value > dateTo.value) {
                dateTo.value = this.value;
            }
        });
        
        dateTo.addEventListener('change', function() {
            if (dateFrom.value && this.value < dateFrom.value) {
                dateFrom.value = this.value;
            }
        });
    }
});
</script>
</x-layouts.app> 