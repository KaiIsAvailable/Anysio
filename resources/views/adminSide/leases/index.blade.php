<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Leases</h1>
                    <p class="mt-2 text-sm text-gray-500">Manage and review tenant leases.</p>
                </div>
                <div class="flex-shrink-0">
                    <a href="{{ route('admin.leases.create') }}"
                       class="inline-flex items-center justify-center px-5 py-2.5 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm transition-all duration-200">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Add New Lease
                    </a>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                <div class="p-5 border-b border-gray-100 bg-white">
                    <div class="flex justify-end">
                        <form method="GET" action="{{ route('admin.leases.index') }}" class="flex flex-wrap items-stretch gap-2">
                            <div class="flex items-stretch">
                                <div class="relative flex-1">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </div>
                                    <input type="text" name="search" value="{{ request('search') }}"
                                           style="padding-left: 45px;"
                                           class="block w-72 sm:w-80 pr-4 py-2.5 bg-gray-50 border border-gray-300 text-slate-900 text-sm rounded-l-lg focus:ring-indigo-500 focus:border-indigo-500 transition-colors placeholder-gray-400"
                                           placeholder="Search tenant / room / status...">
                                </div>
                                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-r-lg text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">
                                    Search
                                </button>
                            </div>
                            <select name="status" class="block w-40 rounded-lg border-gray-300 bg-white text-sm">
                                <option value="">All Status</option>
                                @foreach($statusOptions as $status)
                                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                            @if(request('search') || request('status'))
                                <a href="{{ route('admin.leases.index') }}"
                                   class="inline-flex items-center px-3 py-2 rounded-lg bg-gray-50 hover:bg-gray-100 text-slate-900 border border-gray-200 text-sm">
                                    Clear
                                </a>
                            @endif
                        </form>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    @if($leases && $leases->count() > 0)
                        @php
                            $formatMoney = function ($cents) {
                                if ($cents === null) {
                                    return 'N/A';
                                }
                                return number_format(((int) $cents) / 100, 2);
                            };
                        @endphp
                        <table class="table-fixed w-full min-w-[1200px] divide-y divide-gray-200 text-left">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Lease</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Room</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Tenant</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Dates</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Amounts (RM)</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase text-center">Actions</th>
                                </tr>
                            </thead>
                            @foreach($groups as $group)
                                @php
                                    $latest = $group->first();
                                    $latestStatus = strtolower((string) ($latest->status ?? ''));
                                    $latestBadge = match ($latestStatus) {
                                        'new' => 'bg-blue-100 text-blue-800',
                                        'renew' => 'bg-indigo-100 text-indigo-800',
                                        'check out' => 'bg-amber-100 text-amber-800',
                                        'end' => 'bg-gray-100 text-gray-800',
                                        default => 'bg-slate-100 text-slate-800',
                                    };
                                    $groupKey = $latest->tenant_id . '-' . $latest->room_id;
                                @endphp
                                <tbody data-lease-group="{{ $groupKey }}">
                                    <tr class="bg-gray-50/60">
                                        <td class="px-6 py-4" colspan="7">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <div class="text-sm font-semibold text-slate-900">
                                                        {{ $latest->tenant?->user?->name ?? 'N/A' }} - Room {{ $latest->room?->room_no ?? 'N/A' }}
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        Records: {{ $group->count() }} • Hover to expand
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $latestBadge }}">
                                                        {{ $latest->status ?? 'N/A' }}
                                                    </span>
                                                    @if(strtolower((string) ($latest->status ?? '')) !== 'end')
                                                        <a href="{{ route('admin.leases.create', ['room_id' => $latest->room_id, 'tenant_id' => $latest->tenant_id, 'renew' => 1]) }}"
                                                           class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg text-white bg-emerald-600 hover:bg-emerald-700">
                                                            Renew
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    @foreach($group as $lease)
                                        @php
                                            $status = strtolower((string) ($lease->status ?? ''));
                                            $badge = match ($status) {
                                                'new' => 'bg-blue-100 text-blue-800',
                                                'renew' => 'bg-indigo-100 text-indigo-800',
                                                'check out' => 'bg-amber-100 text-amber-800',
                                                'end' => 'bg-gray-100 text-gray-800',
                                                default => 'bg-slate-100 text-slate-800',
                                            };
                                        @endphp
                                        <tr class="lease-child hidden hover:!bg-indigo-50 transition-colors cursor-pointer duration-150"
                                            data-href="{{ route('admin.leases.show', $lease->id) }}"
                                            onclick="window.location=this.dataset.href">
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-slate-900">Lease Record</div>
                                                <div class="text-xs text-gray-500">{{ optional($lease->created_at)->format('d M Y') }}</div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-slate-900">{{ $lease->room?->room_no ?? 'N/A' }}</div>
                                                <div class="text-xs text-gray-500">{{ $lease->room?->room_type ?? 'N/A' }}</div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-slate-900">{{ $lease->tenant?->user?->name ?? 'N/A' }}</div>
                                                <div class="text-xs text-gray-500">{{ $lease->tenant?->user?->email ?? 'N/A' }}</div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-slate-900">{{ $lease->start_date ?? 'N/A' }}</div>
                                                <div class="text-xs text-gray-500">{{ $lease->end_date ?? 'N/A' }}</div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-slate-900">Rent: {{ $formatMoney($lease->monthly_rent) }}</div>
                                                <div class="text-xs text-gray-500">SD: {{ $formatMoney($lease->security_deposit) }}</div>
                                                <div class="text-xs text-gray-500">Util: {{ $formatMoney($lease->utilities_depost) }}</div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                                                    {{ $lease->status ?? 'N/A' }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-center text-sm text-gray-400">N/A</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            @endforeach
                        </table>
                    @else
                        <div class="text-center py-20 bg-white">
                            <h3 class="text-lg font-medium text-slate-900">No leases found</h3>
                            <p class="mt-1 text-gray-500">Create a lease to get started.</p>
                        </div>
                    @endif
                </div>

                @if($leases && method_exists($leases, 'hasPages') && $leases->hasPages())
                    <div class="bg-white px-6 py-4 border-t border-gray-100">
                        {{ $leases->appends(request()->query())->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <style>
        .lease-child {
            opacity: 0;
            transform: translateY(-8px);
            transition: opacity 520ms cubic-bezier(0.22, 0.61, 0.36, 1), transform 520ms cubic-bezier(0.22, 0.61, 0.36, 1);
        }
        .lease-child.is-visible {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
    <script>
        (function () {
            const groups = document.querySelectorAll('[data-lease-group]');
            groups.forEach(function (group) {
                const rows = group.querySelectorAll('.lease-child');
                if (!rows.length) {
                    return;
                }
                let hideTimer = null;
                group.addEventListener('mouseenter', function () {
                    if (hideTimer) {
                        clearTimeout(hideTimer);
                        hideTimer = null;
                    }
                    rows.forEach(function (row) {
                        row.classList.remove('hidden');
                    });
                    requestAnimationFrame(function () {
                        rows.forEach(function (row) {
                            row.classList.add('is-visible');
                        });
                    });
                });
                group.addEventListener('mouseleave', function () {
                    rows.forEach(function (row) {
                        row.classList.remove('is-visible');
                    });
                    hideTimer = setTimeout(function () {
                        rows.forEach(function (row) {
                            row.classList.add('hidden');
                        });
                    }, 520);
                });
            });
        })();
    </script>
</x-app-layout>
