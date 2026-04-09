<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Header --}}
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Leases</h1>
                    <p class="mt-2 text-sm text-gray-500">Manage and review tenant leases.</p>
                </div>
                <div class="flex-shrink-0">
                    <a href="{{ route('admin.leases.create') }}"
                       class="inline-flex items-center justify-center px-5 py-2.5 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm transition-all duration-200">
                        Lease Controller
                    </a>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                {{-- Table Section --}}
                <div class="overflow-x-auto">
                    @if($leases && $leases->count() > 0)
                        <table class="w-full min-w-[1100px] divide-y divide-gray-200 text-left">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Property / Unit / Room</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Tenant</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Duration</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Rent & Term</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Deposits</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach($leases as $lease)
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
                                    <tr class="hover:!bg-indigo-50 transition-colors cursor-pointer group duration-150"
                                        onclick="window.location='{{ route('admin.leases.show', $lease->id) }}'">
                                        {{-- leasable_type & leasable_id --}}
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-semibold text-slate-900">
                                                @php
                                                    // 获取类名，例如 "App\Models\Room" 变成 "Room"
                                                    $type = basename(str_replace('\\', '/', $lease->leasable_type));
                                                @endphp

                                                @if($type === 'Unit')
                                                    Unit: {{ $lease->leasable?->unit_no ?? 'N/A' }}
                                                @elseif($type === 'Property')
                                                    Property: {{ $lease->leasable?->name ?? 'N/A' }}
                                                @elseif($type === 'Room')
                                                    Room: {{ $lease->leasable?->room_no ?? 'N/A' }}
                                                @else
                                                    {{ $type }}: {{ $lease->leasable_id }}
                                                @endif
                                            </div>
                                            <div class="text-xs text-gray-400 italic">
                                                {{ $type }} 
                                            </div>
                                        </td>

                                        {{-- tenant_id --}}
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-slate-900">{{ $lease->tenant?->user?->name ?? 'N/A' }}</div>   
                                        </td>

                                        {{-- start_date & end_date --}}
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-slate-900">
                                                {{ $lease->start_date_formatted }} - {{ $lease->end_date_formatted }}
                                                @if ($lease->agreement_ended_at)
                                                    <span class="text-xs text-gray-500 block">End Date:</span>
                                                    <div class="text-sm text-slate-900">{{ $lease->agreement_ended_at_formatted }}</div>
                                                @elseif ($lease->checked_out_at)
                                                    <span class="text-xs text-gray-500 block">Check Out Date:</span>
                                                    <div class="text-sm text-slate-900">{{ $lease->checked_out_at_formatted }}</div>
                                                @endif
                                            </div>
                                        </td>

                                        {{-- term_type & rent_price --}}
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-semibold text-indigo-600">RM {{ number_format($lease->rent_price, 2) }}</div>
                                            <div class="text-xs text-gray-500 capitalize">{{ $lease->term_type ?? 'N/A' }}</div>
                                        </td>

                                        {{-- deposit_mode, security, utilities --}}
                                        <td class="px-6 py-4">
                                            <div class="text-xs font-medium text-slate-700">Sec: {{ number_format($lease->security_deposit, 2) }}</div>
                                            <div class="text-xs font-medium text-slate-700">Util: {{ number_format($lease->utilities_deposit, 2) }}</div>
                                            <div class="text-[10px] text-gray-400 mt-1 uppercase tracking-wider">{{ $lease->deposit_mode ?? 'Manual' }}</div>
                                        </td>

                                        {{-- status --}}
                                        <td class="px-6 py-4">
                                            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                                                {{ $lease->status ?? 'N/A' }}
                                            </span>
                                        </td>
                                        {{-- action --}}
                                        <td class="px-6 py-4" x-data="{ openUpload: false, shake: false }" @click.stop>
    
                                            {{-- 情况 A: 已经上传了证书 --}}
                                            @if($lease->stamping_status)
                                                <div class="flex items-center gap-2">
                                                    <span class="p-1 bg-emerald-100 text-emerald-600 rounded-full">
                                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                                    </span>
                                                    <a href="{{ route('admin.leases.view-cert', $lease->id) }}" 
                                                    target="_blank" 
                                                    class="text-xs font-bold text-indigo-600 hover:underline">
                                                        View Cert
                                                    </a>
                                                </div>

                                            {{-- 情况 B: 还没上传，且租约还在进行中 (不是 Check Out 或 End) --}}
                                            @elseif(!in_array(strtolower($lease->status), ['check out', 'end agreement']))
                                                <button @click="openUpload = true" 
                                                        class="px-3 py-1.5 bg-indigo-50 text-indigo-600 text-xs font-black rounded-lg border border-indigo-100 hover:bg-indigo-600 hover:text-white transition-all shadow-sm">
                                                    UPLOAD STAMPING
                                                </button>

                                            {{-- 情况 C: 租约已结束且没上传证书 (显示一个淡淡的提示或留空) --}}
                                            @else
                                                <span class="text-[10px] text-gray-400 font-medium uppercase tracking-tighter">
                                                    NO STAMPING NEEDED
                                                </span>
                                            @endif

                                            {{-- 只有在需要上传的情况下才渲染 Modal，节省 DOM 资源 --}}
                                            @if(!$lease->stamping_status && !in_array(strtolower($lease->status), ['check out', 'end agreement']))
                                                <x-lease-stamping-modal :lease="$lease" />
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="text-center py-20 bg-white">
                            <h3 class="text-lg font-medium text-slate-900">No leases found</h3>
                        </div>
                    @endif
                </div>

                {{-- Pagination --}}
                @if($leases && method_exists($leases, 'hasPages') && $leases->hasPages())
                    <div class="bg-white px-6 py-4 border-t border-gray-100">
                        {{ $leases->appends(request()->query())->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>