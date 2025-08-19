<div class="bg-white rounded-lg shadow border border-gray-200">
    <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-900">Data Raw (Facebook Ads API)</h2>
        <div class="flex items-center gap-3">
            @can('facebook.sync')
                <button id="btnSyncFacebook" type="button" class="px-3 py-1.5 rounded bg-blue-600 text-white text-sm hover:bg-blue-700">Đồng bộ Facebook (AJAX)</button>
            @endcan
            @if(empty($rawData))
                <span class="text-sm text-gray-500">No data loaded. Configure FACEBOOK_ADS_TOKEN and click Sync now.</span>
            @endif
        </div>
    </div>
    <div class="p-4">
        <div id="syncProgress" class="mb-4 hidden">
            <div class="flex items-center justify-between mb-1">
                <span class="text-sm font-medium text-gray-700">Tiến độ đồng bộ</span>
                <span id="syncPercent" class="text-sm text-gray-600">0%</span>
            </div>
            <div class="w-full bg-gray-200 rounded h-2 overflow-hidden">
                <div id="syncBar" class="bg-blue-600 h-2" style="width: 0%"></div>
            </div>
            <div class="mt-2 text-xs text-gray-600">
                <span>Giai đoạn: </span><span id="syncStage" class="font-medium">-</span>
            </div>
            <div id="syncCounts" class="mt-2 grid grid-cols-6 gap-2 text-xs text-gray-700"></div>
            <div id="syncErrors" class="mt-2 text-xs text-red-600"></div>
        </div>
        @if(session('success'))
            <div class="mb-3 px-3 py-2 rounded bg-green-50 text-green-700 text-sm">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-3 px-3 py-2 rounded bg-red-50 text-red-700 text-sm">{{ session('error') }}</div>
        @endif
        <div class="grid grid-cols-6 gap-3 mb-6">
            <div class="p-3 rounded border border-gray-200"><div class="text-xs text-gray-500">Businesses</div><div class="text-xl font-semibold">{{ number_format($rawData['totals']['businesses'] ?? 0) }}</div></div>
            <div class="p-3 rounded border border-gray-200"><div class="text-xs text-gray-500">Ad Accounts</div><div class="text-xl font-semibold">{{ number_format($rawData['totals']['accounts'] ?? 0) }}</div></div>
            <div class="p-3 rounded border border-gray-200"><div class="text-xs text-gray-500">Campaigns</div><div class="text-xl font-semibold">{{ number_format($rawData['totals']['campaigns'] ?? 0) }}</div></div>
            <div class="p-3 rounded border border-gray-200"><div class="text-xs text-gray-500">Ad Sets</div><div class="text-xl font-semibold">{{ number_format($rawData['totals']['adsets'] ?? 0) }}</div></div>
            <div class="p-3 rounded border border-gray-200"><div class="text-xs text-gray-500">Ads</div><div class="text-xl font-semibold">{{ number_format($rawData['totals']['ads'] ?? 0) }}</div></div>
            <div class="p-3 rounded border border-gray-200"><div class="text-xs text-gray-500">Insights rows</div><div class="text-xl font-semibold">{{ number_format($rawData['totals']['insights'] ?? 0) }}</div></div>
        </div>

        <div class="grid grid-cols-3 gap-3 mb-6">
            <div class="p-4 rounded border border-gray-200">
                <div class="text-sm font-semibold mb-2">Yesterday – Account</div>
                @php($acc = $rawData['yesterday']['account'] ?? null)
                <div class="text-xs text-gray-600">Rows: <span class="font-semibold">{{ number_format($acc->total_rows ?? 0) }}</span></div>
                <div class="text-xs text-gray-600">Spend: <span class="font-semibold">{{ number_format($acc->spend ?? 0, 2) }}</span></div>
                <div class="text-xs text-gray-600">Impressions: <span class="font-semibold">{{ number_format($acc->impressions ?? 0) }}</span></div>
                <div class="text-xs text-gray-600">Clicks: <span class="font-semibold">{{ number_format($acc->clicks ?? 0) }}</span></div>
            </div>
            <div class="p-4 rounded border border-gray-200">
                <div class="text-sm font-semibold mb-2">Yesterday – Ad Set</div>
                @php($as = $rawData['yesterday']['adset'] ?? null)
                <div class="text-xs text-gray-600">Rows: <span class="font-semibold">{{ number_format($as->total_rows ?? 0) }}</span></div>
                <div class="text-xs text-gray-600">Spend: <span class="font-semibold">{{ number_format($as->spend ?? 0, 2) }}</span></div>
                <div class="text-xs text-gray-600">Impressions: <span class="font-semibold">{{ number_format($as->impressions ?? 0) }}</span></div>
                <div class="text-xs text-gray-600">Clicks: <span class="font-semibold">{{ number_format($as->clicks ?? 0) }}</span></div>
            </div>
            <div class="p-4 rounded border border-gray-200">
                <div class="text-sm font-semibold mb-2">Yesterday – Ad</div>
                @php($ad = $rawData['yesterday']['ad'] ?? null)
                <div class="text-xs text-gray-600">Rows: <span class="font-semibold">{{ number_format($ad->total_rows ?? 0) }}</span></div>
                <div class="text-xs text-gray-600">Spend: <span class="font-semibold">{{ number_format($ad->spend ?? 0, 2) }}</span></div>
                <div class="text-xs text-gray-600">Impressions: <span class="font-semibold">{{ number_format($ad->impressions ?? 0) }}</span></div>
                <div class="text-xs text-gray-600">Clicks: <span class="font-semibold">{{ number_format($ad->clicks ?? 0) }}</span></div>
            </div>
        </div>

        <div class="space-y-8">
            <div>
                <div class="text-sm font-semibold text-gray-800 mb-2">Businesses</div>
                <div class="overflow-auto rounded border border-gray-200">
                    <table class="min-w-full text-xs">
                        <thead class="bg-gray-50 text-gray-600"><tr><th class="px-3 py-2 text-left">ID</th><th class="px-3 py-2 text-left">Name</th><th class="px-3 py-2 text-left">Verification</th><th class="px-3 py-2 text-left">Created</th><th class="px-3 py-2 text-left">#Accounts</th></tr></thead>
                        <tbody>
                            @foreach(($rawData['businesses'] ?? []) as $b)
                                <tr class="border-t">
                                    <td class="px-3 py-2">{{ $b->id }}</td>
                                    <td class="px-3 py-2">{{ $b->name }}</td>
                                    <td class="px-3 py-2">{{ $b->verification_status }}</td>
                                    <td class="px-3 py-2">{{ optional($b->created_time)->toDateTimeString() }}</td>
                                    <td class="px-3 py-2">{{ $b->ad_accounts_count }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <div class="text-sm font-semibold text-gray-800 mb-2">Ad Accounts</div>
                <div class="overflow-auto rounded border border-gray-200">
                    <table class="min-w-full text-xs">
                        <thead class="bg-gray-50 text-gray-600"><tr><th class="px-3 py-2 text-left">ID</th><th class="px-3 py-2 text-left">Account ID</th><th class="px-3 py-2 text-left">Name</th><th class="px-3 py-2 text-left">Status</th><th class="px-3 py-2 text-left">#Campaigns</th></tr></thead>
                        <tbody>
                            @foreach(($rawData['accounts'] ?? []) as $acc)
                                <tr class="border-t">
                                    <td class="px-3 py-2">{{ $acc->id }}</td>
                                    <td class="px-3 py-2">{{ $acc->account_id }}</td>
                                    <td class="px-3 py-2">{{ $acc->name }}</td>
                                    <td class="px-3 py-2">{{ $acc->account_status }}</td>
                                    <td class="px-3 py-2">{{ $acc->campaigns_count }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('btnSyncFacebook');
    if (!btn) return;
    const elProgress = document.getElementById('syncProgress');
    const elBar = document.getElementById('syncBar');
    const elPercent = document.getElementById('syncPercent');
    const elStage = document.getElementById('syncStage');
    const elCounts = document.getElementById('syncCounts');
    const elErrors = document.getElementById('syncErrors');
    let syncId = null;
    let timer = null;

    function renderCounts(counts) {
        const items = [
            ['Businesses', counts?.businesses ?? 0],
            ['Accounts', counts?.accounts ?? 0],
            ['Campaigns', counts?.campaigns ?? 0],
            ['AdSets', counts?.adsets ?? 0],
            ['Ads', counts?.ads ?? 0],
            ['Insights', counts?.insights ?? 0],
        ];
        elCounts.innerHTML = items.map(([label, value]) => `<div class="px-2 py-1 bg-gray-100 rounded"><span class="text-gray-500">${label}:</span> <span class="font-semibold text-gray-800">${value}</span></div>`).join('');
    }

    function renderErrors(errors) {
        if (!errors || errors.length === 0) { elErrors.innerHTML = ''; return; }
        elErrors.innerHTML = `<div class="px-2 py-1 bg-red-50 rounded"><pre class="whitespace-pre-wrap">${JSON.stringify(errors, null, 2)}</pre></div>`;
    }

    async function safeFetchJson(url, options = {}) {
        const merged = options || {};
        merged.headers = Object.assign({
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }, merged.headers || {});
        const res = await fetch(url, merged);
        const contentType = res.headers.get('content-type') || '';
        const text = await res.text();
        let data = null;
        if (contentType.includes('application/json')) {
            try { data = JSON.parse(text); } catch (e) { /* ignore */ }
        }
        return { res, data, text };
    }

    async function poll() {
        if (!syncId) return;
        try {
            const { res, data, text } = await safeFetchJson(`{{ route('api.sync.facebook.status', ['id' => 'SYNC_ID']) }}`.replace('SYNC_ID', syncId));
            if (!data) {
                renderErrors([{ stage: 'status', message: `HTTP ${res.status}: ${text?.slice(0, 200)}` }]);
                return;
            }
            elProgress.classList.remove('hidden');
            const percent = data.percent ?? 0;
            elBar.style.width = `${percent}%`;
            elPercent.textContent = `${percent}%`;
            elStage.textContent = data.stage ?? '-';
            renderCounts(data.counts);
            renderErrors(data.errors);
            if (data.done) {
                clearInterval(timer);
                btn.removeAttribute('disabled');
                btn.textContent = 'Đồng bộ Facebook (AJAX)';
            }
        } catch (e) {
            // ignore
        }
    }

    btn.addEventListener('click', async function() {
        btn.setAttribute('disabled', 'disabled');
        btn.textContent = 'Đang đồng bộ...';
        elProgress.classList.remove('hidden');
        elBar.style.width = '0%';
        elPercent.textContent = '0%';
        elStage.textContent = 'queued';
        renderCounts({});
        renderErrors([]);
        try {
            const { res, data, text } = await safeFetchJson(`{{ route('api.sync.facebook.start') }}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            });
            if (res.ok && data && data.id) {
                syncId = data.id;
                timer = setInterval(poll, 1200);
                poll();
            } else {
                btn.removeAttribute('disabled');
                btn.textContent = 'Đồng bộ Facebook (AJAX)';
                const msg = (data && data.error) ? data.error : `HTTP ${res.status}: ${text?.slice(0, 200)}`;
                renderErrors([msg]);
            }
        } catch (e) {
            btn.removeAttribute('disabled');
            btn.textContent = 'Đồng bộ Facebook (AJAX)';
            renderErrors([{ stage: 'start', message: e?.message || String(e) }]);
        }
    });
});
</script>
@endpush
