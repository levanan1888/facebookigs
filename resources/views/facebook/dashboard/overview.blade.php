<x-layouts.app :title="__('Facebook Dashboard - Overview')">
    <div class="p-6">
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Tổng quan Facebook Ads</h2>
                    <p class="text-gray-600">Thống kê tổng hợp và phân tích dữ liệu Facebook</p>
                </div>
                <div class="flex space-x-3">
                    <button id="btnToggleFilter" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200" title="Bộ lọc">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L14 13.414V19a1 1 0 01-1.447.894l-4-2A1 1 0 018 17v-3.586L3.293 6.707A1 1 0 013 6V4z" />
                        </svg>
                        Bộ lọc
                    </button>
                    <button id="btnGuide" class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Hướng dẫn
                    </button>
                    <button id="btnRefresh" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Làm mới
                    </button>
                </div>
            </div>

            @can('analytics.filter')
            <div id="filterPanel" class="mt-4 bg-white rounded-lg shadow p-4 hidden">
                <form method="GET" action="{{ route('facebook.overview') }}">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
                        @can('analytics.filter.time')
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Từ ngày</label>
                            <input type="date" name="from" value="{{ $data['filters']['from'] ?? '' }}" class="w-full border rounded px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Đến ngày</label>
                            <input type="date" name="to" value="{{ $data['filters']['to'] ?? '' }}" class="w-full border rounded px-3 py-2 text-sm" />
                        </div>
                        @endcan
                        @can('analytics.filter.scope')
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Tài khoản</label>
                            <select name="account_id" class="w-full border rounded px-3 py-2 text-sm">
                                <option value="">Tất cả</option>
                                @foreach(($data['filters']['accounts'] ?? []) as $acc)
                                    <option value="{{ $acc->id }}" {{ ($data['filters']['accountId'] ?? null) === $acc->id ? 'selected' : '' }}>
                                        {{ $acc->name }} ({{ $acc->account_id }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Chiến dịch</label>
                            <select name="campaign_id" class="w-full border rounded px-3 py-2 text-sm">
                                <option value="">Tất cả</option>
                                @foreach(($data['filters']['campaigns'] ?? []) as $c)
                                    <option value="{{ $c->id }}" {{ ($data['filters']['campaignId'] ?? null) === $c->id ? 'selected' : '' }}>
                                        {{ $c->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @endcan
                        <div class="flex space-x-2">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Lọc</button>
                            <a href="{{ route('facebook.overview') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">Xóa</a>
                        </div>
                    </div>
                </form>
            </div>
            @endcan

            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Business Managers</p>
                            <p class="text-2xl font-bold text-gray-900">{{ number_format($data['totals']['businesses'] ?? 0) }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-2 bg-green-100 rounded-lg">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Tài khoản quảng cáo</p>
                            <p class="text-2xl font-bold text-gray-900">{{ number_format($data['totals']['accounts'] ?? 0) }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-2 bg-purple-100 rounded-lg">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Chiến dịch</p>
                            <p class="text-2xl font-bold text-gray-900">{{ number_format($data['totals']['campaigns'] ?? 0) }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-2 bg-orange-100 rounded-lg">
                            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.122 2.122"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Bài đăng</p>
                            <p class="text-2xl font-bold text-gray-900">{{ number_format($data['totals']['posts']) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-2 bg-red-100 rounded-lg">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Tổng chi tiêu</p>
                            <p class="text-2xl font-bold text-gray-900">${{ number_format($data['stats']['total_spend'] ?? 0, 2) }}</p>
                            <p class="text-xs text-gray-500 mt-1">
                                @if(($data['performanceStats']['totalSpend'] ?? 0) > 0)
                                    Dữ liệu từ {{ $data['performanceStats']['totalImpressions'] ?? 0 }} hiển thị
                                @else
                                    Chưa có dữ liệu chi tiêu
                                @endif
                            </p>
                            <p class="text-xs text-gray-400 mt-1">Cập nhật: {{ now()->format('d/m/Y H:i:s') }}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-2 bg-indigo-100 rounded-lg">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Tổng hiển thị</p>
                            <p class="text-2xl font-bold text-gray-900">{{ number_format($data['stats']['total_impressions'] ?? 0) }}</p>
                            <p class="text-xs text-gray-400 mt-1">
                                @if(($data['performanceStats']['totalImpressions'] ?? 0) > 0)
                                    Dữ liệu từ Facebook API
                                @else
                                    Chưa có dữ liệu
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-2 bg-yellow-100 rounded-lg">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Tổng lượt click</p>
                            <p class="text-2xl font-bold text-gray-900">{{ number_format($data['stats']['total_clicks'] ?? 0) }}</p>
                            <p class="text-xs text-gray-400 mt-1">
                                @if(($data['performanceStats']['totalClicks'] ?? 0) > 0)
                                    CTR: {{ number_format(($data['stats']['avg_ctr'] ?? 0) * 100, 2) }}%
                                @else
                                    Chưa có dữ liệu
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Hoạt động 7 ngày gần nhất</h3>
                    <div class="h-72"><canvas id="activityChart"></canvas></div>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Phân bố trạng thái Campaigns</h3>
                    <div class="h-72"><canvas id="statusChart"></canvas></div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6 border-b border-gray-200"><h3 class="text-lg font-semibold text-gray-900">Top 5 Quảng cáo (Theo thời gian đồng bộ)</h3></div>
                    <div class="p-6">
                        <div class="space-y-4">
                            @forelse($data['stats']['recent_ads'] ?? [] as $ad)
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                    <div class="flex-1">
                                        <h4 class="font-medium text-gray-900">{{ Str::limit($ad->name, 40) }}</h4>
                                        <p class="text-sm text-gray-600">{{ $ad->campaign->name ?? 'Campaign không xác định' }}</p>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $ad->status === 'ACTIVE' ? 'bg-green-100 text-green-800' : ($ad->status === 'PAUSED' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') }}">{{ $ad->status }}</span>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-semibold text-gray-900">{{ $ad->last_insights_sync ? $ad->last_insights_sync->format('d/m/Y') : 'N/A' }}</p>
                                        <p class="text-xs text-gray-500">Đồng bộ lúc</p>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-8 text-gray-500">
                                    <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                                    <p>Chưa có dữ liệu quảng cáo</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6 border-b border-gray-200"><h3 class="text-lg font-semibold text-gray-900">Top 5 Posts (Theo tương tác)</h3></div>
                    <div class="p-6">
                        <div class="space-y-4">
                            @forelse($data['stats']['top_posts'] ?? [] as $post)
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                    <div class="flex-1">
                                        <h4 class="font-medium text-gray-900">{{ Str::limit($post->post_message, 50) ?: 'No message' }}</h4>
                                        <p class="text-sm text-gray-600">Post ID: {{ $post->post_id }}</p>
                                        <div class="flex space-x-4 mt-2 text-sm text-gray-500">
                                            <span>👍 {{ number_format($post->post_likes ?? 0) }}</span>
                                            <span>🔄 {{ number_format($post->post_shares ?? 0) }}</span>
                                            <span>💬 {{ number_format($post->post_comments ?? 0) }}</span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-semibold text-gray-900">{{ number_format(($post->post_likes ?? 0) + ($post->post_shares ?? 0) + ($post->post_comments ?? 0)) }}</p>
                                        <p class="text-xs text-gray-500">Tổng cộng</p>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-8 text-gray-500">
                                    <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                                    <p>Chưa có dữ liệu bài đăng</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="guideModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
            <div class="relative top-10 mx-auto p-6 border w-11/12 md:w-3/4 lg:w-2/3 xl:w-1/2 shadow-lg rounded-md bg-white max-h-[90vh] overflow-y-auto">
                <div class="mt-3">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-semibold text-gray-900">Hướng dẫn xem dữ liệu Facebook</h3>
                        <button id="closeGuideModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    <div class="space-y-4 text-sm text-gray-600">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <h4 class="font-semibold text-blue-900 mb-2">📊 Tổng quan (Overview)</h4>
                            <ul class="list-disc list-inside space-y-1 ml-4">
                                <li>Xem thống kê tổng hợp: Business Managers, Ad Accounts, Campaigns, Posts</li>
                                <li>Biểu đồ hoạt động 7 ngày gần nhất</li>
                                <li>Phân bố trạng thái Campaigns</li>
                                <li>Top 5 Campaigns và Posts theo hiệu suất</li>
                            </ul>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <h4 class="font-semibold text-green-900 mb-2">📋 Dữ liệu thô (Data Raw)</h4>
                            <ul class="list-disc list-inside space-y-1 ml-4">
                                <li>Xem danh sách chi tiết tất cả dữ liệu đã đồng bộ</li>
                                <li>Nhấn "Đồng bộ Facebook" để cập nhật dữ liệu mới</li>
                                <li>Theo dõi tiến độ đồng bộ real-time</li>
                                <li>Xem lỗi nếu có trong quá trình đồng bộ</li>
                            </ul>
                        </div>
                        <div class="bg-purple-50 p-4 rounded-lg">
                            <h4 class="font-semibold text-purple-900 mb-2">🔗 Phân cấp (Hierarchy)</h4>
                            <ul class="list-disc list-inside space-y-1 ml-4">
                                <li>Điều hướng theo cấu trúc: Business Manager → Ad Accounts → Campaigns → Ad Sets → Posts</li>
                                <li>Click vào từng mục để xem chi tiết cấp con</li>
                                <li>Xem thống kê tổng hợp cho mỗi cấp</li>
                            </ul>
                        </div>
                        <div class="bg-orange-50 p-4 rounded-lg">
                            <h4 class="font-semibold text-orange-900 mb-2">📈 Phân tích (Analytics)</h4>
                            <ul class="list-disc list-inside space-y-1 ml-4">
                                <li>Xem metrics hiệu suất: Spend, Impressions, Clicks, Reach</li>
                                <li>Phân tích CTR, CPC, CPM</li>
                                <li>Đánh giá hiệu quả chi phí</li>
                                <li>Nhận khuyến nghị cải thiện</li>
                            </ul>
                        </div>
                        <div class="bg-yellow-50 p-4 rounded-lg">
                            <h4 class="font-semibold text-yellow-900 mb-2">💡 Mẹo sử dụng</h4>
                            <ul class="list-disc list-inside space-y-1 ml-4">
                                <li>Sử dụng nút "Làm mới" để cập nhật dữ liệu mà không reload trang</li>
                                <li>Đồng bộ dữ liệu thường xuyên để có thông tin mới nhất</li>
                                <li>Kiểm tra trang "Data Raw" để xem dữ liệu chi tiết</li>
                                <li>Sử dụng trang "Hierarchy" để điều hướng dữ liệu theo cấu trúc</li>
                            </ul>
                        </div>
                    </div>
                    <div class="flex justify-end mt-6">
                        <button id="closeGuideModalBtn" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Đã hiểu</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        function initFacebookOverviewCharts() {
            // modal/UX handlers (once)
            const guideModal = document.getElementById('guideModal');
            const btnGuide = document.getElementById('btnGuide');
            const closeGuideModal = document.getElementById('closeGuideModal');
            const closeGuideModalBtn = document.getElementById('closeGuideModalBtn');
            const btnRefresh = document.getElementById('btnRefresh');
            const btnToggleFilter = document.getElementById('btnToggleFilter');
            const filterPanel = document.getElementById('filterPanel');

            if (btnGuide && guideModal && closeGuideModal && closeGuideModalBtn) {
                btnGuide.onclick = () => guideModal.classList.remove('hidden');
                const closeModal = () => guideModal.classList.add('hidden');
                closeGuideModal.onclick = closeModal;
                closeGuideModalBtn.onclick = closeModal;
                guideModal.onclick = (e) => { if (e.target === guideModal) closeModal(); };
            }

            if (btnRefresh) {
                btnRefresh.onclick = async function() {
                    btnRefresh.disabled = true;
                    btnRefresh.innerHTML = '<svg class="w-4 h-4 inline mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>Đang tải...';
                    try { window.location.href = '{{ route('facebook.overview') }}'; }
                    catch (error) { btnRefresh.disabled = false; btnRefresh.innerHTML = 'Làm mới'; }
                };
            }

            if (btnToggleFilter && filterPanel) {
                btnToggleFilter.onclick = () => filterPanel.classList.toggle('hidden');
            }

            // Charts
            window.__fbCharts ||= {};
            const activityEl = document.getElementById('activityChart');
            const statusEl = document.getElementById('statusChart');
            if (activityEl) {
                const activityCtx = activityEl.getContext('2d');
                const activityData = @json($data['last7Days']);
                window.__fbCharts.activity && window.__fbCharts.activity.destroy();
                window.__fbCharts.activity = new Chart(activityCtx, { type: 'line', data: { labels: activityData.map(item => item.date), datasets: [
                    { label: 'Chiến dịch', data: activityData.map(item => item.campaigns), borderColor: 'rgb(59,130,246)', backgroundColor: 'rgba(59,130,246,0.15)', pointRadius: 3, borderWidth: 2, fill: true, tension: 0.35 },
                    { label: 'Quảng cáo', data: activityData.map(item => item.ads), borderColor: 'rgb(16,185,129)', backgroundColor: 'rgba(16,185,129,0.15)', pointRadius: 3, borderWidth: 2, fill: true, tension: 0.35 },
                    { label: 'Bài đăng', data: activityData.map(item => item.posts), borderColor: 'rgb(245,158,11)', backgroundColor: 'rgba(245,158,11,0.15)', pointRadius: 3, borderWidth: 2, fill: true, tension: 0.35 },
                    { label: 'Chi tiêu ($)', data: activityData.map(item => item.spend || 0), borderColor: 'rgb(239,68,68)', backgroundColor: 'rgba(239,68,68,0.15)', pointRadius: 3, borderWidth: 2, fill: true, tension: 0.35, yAxisID: 'y1' }
                ] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' }, tooltip: { mode: 'index', intersect: false } }, interaction: { mode: 'index', intersect: false }, scales: { y: { type: 'linear', display: true, position: 'left', beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } }, y1: { type: 'linear', display: true, position: 'right', beginAtZero: true, grid: { drawOnChartArea: false } }, x: { grid: { display: false } } } } });
            }
            if (statusEl) {
                const statusCtx = statusEl.getContext('2d');
                const statusData = @json($data['statusStats']['campaigns'] ?? []);
                window.__fbCharts.status && window.__fbCharts.status.destroy();
                window.__fbCharts.status = new Chart(statusCtx, { type: 'doughnut', data: { labels: Object.keys(statusData), datasets: [{ data: Object.values(statusData), backgroundColor: ['rgb(16,185,129)','rgb(245,158,11)','rgb(239,68,68)','rgb(107,114,128)'], borderWidth: 1, borderColor: '#fff' }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${ctx.parsed.toLocaleString()}` } } } } });
            }
        }

        function ensureChartAndInit() {
            if (window.Chart) { initFacebookOverviewCharts(); return; }
            const s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/chart.js';
            s.onload = initFacebookOverviewCharts;
            document.head.appendChild(s);
        }
        document.addEventListener('DOMContentLoaded', ensureChartAndInit);
        window.addEventListener('livewire:navigated', ensureChartAndInit); // fix SPA re-init
        </script>
    </div>
</x-layouts.app>


