<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Header --}}
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Leases</h1>
                    <p class="mt-2 text-sm text-gray-500">Manage and review tenant leases.</p>
                </div>
                <div class="flex-shrink-0" x-data="{ loading: false, loadings: false }">
                    <x-form.primary-button
                        type="button"
                        loading="loading"
                        @click="loading = true; window.location.href = '{{ route('admin.document-templates.index') }}'"
                        >
                        <svg x-show="!loading" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Agreement Templates
                    </x-form.primary-button>
                    <x-form.primary-button
                        type="button"
                        loading="loadings"
                        @click="loadings = true; window.location.href = '{{ route('admin.leases.create') }}'"
                        >
                        <svg x-show="!loadings" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Lease Controller
                    </x-form.primary-button>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                <div class="p-5 border-b border-gray-100 bg-white">
                    <div class="flex justify-end">
                        <x-form.form method="GET" action="{{ route('admin.leases.index') }}" class="flex flex-wrap items-center gap-4">
                            <div class="flex items-stretch justify-between">
                                <x-table.search :isAdvanceSearch="true" :withDate="true" placeholder="Search by name, unit, property..." />
                            </div>
                        </x-form.form>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                    {{-- Table Section --}}
                    <div class="overflow-x-auto">
                        @if($leases && $leases->count() > 0)
                            <table class="w-full min-w-[1100px] divide-y divide-gray-200 text-left">
                                <thead class="bg-gray-50">
                                    <tr>
                                        {{-- 移除了复杂的 l 和 o 的排序，避免系统变卡 --}}
                                        <x-table.th name="Property / Unit / Room" />
                                        <x-table.th name="Owner" />
                                        <x-table.th name="Tenant" sortField="t" />
                                        <x-table.th name="Duration" sortField="d"/>
                                        <x-table.th name="Charges"/>
                                        <x-table.th name="Status" sortField="s"/>
                                        <x-table.th name="Action" />
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    @foreach($leases as $lease)
                                        @php
                                            $status = strtolower((string) ($lease->status ?? ''));
                                            $badge = get_status_badge($lease->status ?? null);
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
                                                        {{ $lease->leasable?->unit_no ?? 'N/A' }}
                                                    @elseif($type === 'Property')
                                                        {{ $lease->leasable?->name ?? 'N/A' }}
                                                    @elseif($type === 'Room')
                                                        {{ $lease->leasable?->room_no ?? 'N/A' }}
                                                    @else
                                                        {{ $type }}: {{ $lease->leasable_id }}
                                                    @endif
                                                </div>
                                                <div class="text-xs text-gray-400 italic">
                                                    {{ $type }} 
                                                </div>
                                            </td>

                                            {{-- owner --}}
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-slate-900">
                                                    @php
                                                        // 获取类名，例如 "App\Models\Room" 变成 "Room"
                                                        $type = basename(str_replace('\\', '/', $lease->leasable_type));
                                                    @endphp

                                                    @if($type === 'Unit')
                                                        {{ $lease->leasable?->owner->name ?? 'N/A' }}
                                                    @elseif($type === 'Property')
                                                        {{ $lease->leasable?->owner->name ?? 'N/A' }}
                                                    @elseif($type === 'Room')
                                                        {{ $lease->leasable?->unit->owner->name ?? 'N/A' }}
                                                    @else
                                                        No Owner
                                                    @endif
                                                </div>
                                            </td>

                                            {{-- tenant_id --}}
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-slate-900">{{ $lease->tenant?->user?->name ?? 'N/A' }}</div>   
                                            </td>

                                            {{-- start_date & end_date --}}
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-slate-900">
                                                    {{ $lease->start_date_formatted }} to {{ $lease->end_date_formatted }}
                                                    @if ($lease->agreement_ended_at)
                                                        <span class="text-xs text-gray-500 block">End Date:</span>
                                                        <div class="text-sm text-slate-900">{{ $lease->agreement_ended_at_formatted }}</div>
                                                    @elseif ($lease->checked_out_at)
                                                        <span class="text-xs text-gray-500 block">Check Out Date:</span>
                                                        <div class="text-sm text-slate-900">{{ $lease->checked_out_at_formatted }}</div>
                                                    @endif
                                                </div>
                                            </td>

                                            {{-- term_type & charges breakdown --}}
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-semibold text-indigo-600">
                                                    RM {{ number_format($lease->rent_price + $lease->charges->sum('amount') / 100, 2) }}
                                                </div>

                                                {{-- Loop through individual lease charges --}}
                                                @if($lease->charges && $lease->charges->count() > 0)
                                                    <div class="space-y-0.5 border-t border-gray-100 pt-1 mt-1">
                                                        @foreach($lease->charges as $charge)
                                                            <div class="text-[11px] text-slate-600 flex justify-between gap-2">
                                                                <span class="truncate" title="{{ $charge->description }}">{{ $charge->description }}:</span>
                                                                <span class="font-medium text-slate-900 shrink-0">RM {{ number_format($charge->amount / 100, 2 ) }}</span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </td>

                                            {{-- status --}}
                                            <td class="px-6 py-4">
                                                <span class="whitespace-nowrap px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                                                    {{ $lease->status ?? 'N/A' }}
                                                </span>
                                            </td>
                                            {{-- action --}}
                                            <td class="px-6 py-4" x-data="{ 
                                                openUpload: {{ $errors->any() && !$errors->has('error') ? 'true' : 'false' }}, 
                                                shake: {{ $errors->any() ? 'true' : 'false' }},
                                                errorMessage: '{{ $errors->first('error') }}',
                                                activeLease: JSON.parse(sessionStorage.getItem('lastActiveLease') || '{}')
                                            }" @click.stop>
                                                <div class="flex flex-col gap-3">
                                                    
                                                    {{-- 第一部分：Stamping 状态区 --}}
                                                    <div class="min-h-[32px] flex items-center">
                                                        @if($lease->stamping_status)
                                                            <div class="flex items-center gap-2">
                                                                <span class="p-1 bg-emerald-100 text-emerald-600 rounded-full">
                                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                                                </span>
                                                                <a href="{{ route('admin.leases.view-cert', $lease->id) }}" class="text-xs font-bold text-emerald-600 hover:underline">
                                                                    View Cert
                                                                </a>
                                                            </div>
                                                        @elseif(!in_array(strtolower($lease->status), ['check out', 'end agreement']))
                                                            <button @click="openUpload = true" 
                                                                    class="w-full px-3 py-1.5 bg-indigo-50 text-indigo-600 text-xs font-black rounded-lg border border-indigo-100 hover:bg-indigo-600 hover:text-white transition-all shadow-sm flex items-center justify-center">
                                                                UPLOAD STAMPING
                                                            </button>
                                                        @else
                                                            <span class="text-[10px] text-gray-400 font-medium uppercase tracking-tighter">
                                                                NO STAMPING NEEDED
                                                            </span>
                                                        @endif
                                                    </div>

                                                    {{-- 第二部分：按钮区 --}}
                                                    <div>
                                                        @if (!empty($lease->document_id))
                                                            <button type="button"
                                                                data-base-content="{{ $lease->documentTemplate?->html_template }}"
                                                                data-title="{{ $lease->documentTemplate?->title }}"
                                                                data-replacements="{{ json_encode([
                                                                    '{status}' => $lease->status ?? 'N/A',
                                                                    '{tenant_name}' => $lease->tenant?->user->name ?? 'N/A',
                                                                    '{tenant_ic}'   => $lease->tenant?->ic_number ?? 'N/A',
                                                                    '{owner_name}' => $lease->leasable?->owner?->user->name ?? 'N/A',
                                                                    '{owner_ic}'   => $lease->leasable?->owner?->ic_number ?? 'N/A',
                                                                    '{property_address}'   => $lease->leasable?->full_address ?? 'N/A',
                                                                    '{property_type}'   => $lease->leasableTypeLabel ?? 'N/A',
                                                                    '{property_name}'   => $lease->leasableName ?? 'N/A',
                                                                    '{rent_mode}'   => $lease->term_type ?? 'N/A',
                                                                    '{rent_price}'  => number_format($lease->rent_price, 2),
                                                                    '{deposit_mode}'  => $lease->deposit_mode ?? 'N/A',
                                                                    '{security_deposit}' => number_format($lease->security_deposit, 2),
                                                                    '{utilities_deposit}' => number_format($lease->utilities_deposit, 2),
                                                                    '{start_date}'  => $lease->start_date?->format('d/m/Y') ?? 'N/A',
                                                                    '{end_date}'    => $lease->end_date?->format('d/m/Y') ?? 'N/A',
                                                                    '{check_out_date}'    => $lease->checked_out_at?->format('d/m/Y') ?? 'N/A',
                                                                    '{end_agreement_date}'    => $lease->agreement_ended_at?->format('d/m/Y') ?? 'N/A',
                                                                ]) }}"
                                                                @click="
                                                                    const btn = $el;
                                                                    let content = btn.dataset.baseContent;
                                                                    if (!content) { console.error('Agreement content is empty'); return; }
                                                                    const replacements = JSON.parse(btn.dataset.replacements);
                                                                    Object.keys(replacements).forEach(key => {
                                                                        const val = replacements[key];
                                                                        const regex = new RegExp(key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
                                                                        content = content.replace(regex, `<span class='text-inherit font-semibold'>${val}</span>`);
                                                                    });
                                                                    $dispatch('open-lease-preview', { content: content, title: btn.dataset.title });
                                                                "
                                                                class="w-full px-3 py-1.5 bg-indigo-50 text-indigo-600 text-xs font-black rounded-lg border border-indigo-100 hover:bg-indigo-600 hover:text-white transition-all shadow-sm flex items-center justify-center">
                                                                VIEW AGREEMENT
                                                            </button>
                                                        @endif

                                                        {{-- Cancel Lease Button --}}
                                                        @if($lease->status != 'cancelled')
                                                            <form id="cancel-lease-form-{{ $lease->id }}" action="{{ route('admin.leases.cancel', $lease->id) }}" method="POST" class="mt-3">
                                                                @csrf
                                                                @method('PATCH')
                                                                <input type="hidden" name="cancellation_reason" id="reason-input-{{ $lease->id }}">
                                                                
                                                                <button type="button"
                                                                    @click="
                                                                        $dispatch('open-modal', 'lease-confirm-modal'); 
                                                                        $dispatch('open-lease-confirm-modal', { actionUrl: '{{ route('admin.leases.cancel', $lease->id) }}' })
                                                                    "
                                                                    class="w-full px-3 py-1.5 bg-red-50 text-red-600 text-xs font-black rounded-lg border border-red-100 hover:bg-red-600 hover:text-white transition-all shadow-sm flex items-center justify-center gap-1.5">
                                                                    <span>CANCEL LEASE</span>
                                                                </button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                </div>
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
                </div>

                {{-- Stamping Modal --}}
                @if(!$lease->stamping_status && !in_array(strtolower($lease->status), ['check out', 'end agreement']))
                    <x-modals.lease-stamping-modal :lease="$lease" />
                @endif

                {{-- Cancel Confirmation Modal --}}
                <x-modals.confirmation-modal id="lease-confirm-modal" title="Cancel Lease">
                    <x-form.form x-data="{ targetAction: '', reason: '', loading: false }" 
                        x-bind:action="targetAction" 
                        method="POST" 
                        class="p-6"
                        @open-lease-confirm-modal.window="targetAction = $event.detail.actionUrl; reason = ''"
                        @submit="loading = true">
                        @csrf
                        @method('PATCH')
                        
                        <div class="flex items-center gap-3 text-amber-600 bg-amber-50 p-4 rounded-xl border border-amber-100 mb-4">
                            <p class="text-sm font-semibold text-gray-700">Are you sure you want to cancel this lease? This action cannot be undone.</p>
                        </div>

                        <div class="mb-4">
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Cancellation Reason <span class="text-red-500">*</span></label>
                            <textarea name="cancellation_reason" x-model="reason" rows="3" class="w-full text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl shadow-sm"></textarea>
                        </div>

                        <div class="flex justify-end gap-2">
                            <button type="button" @click="$dispatch('close-lease-confirm-modal')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition-all">
                                Cancel
                            </button>
                            <x-form.primary-button type="submit" x-bind:disabled="!reason.trim()" loading="loading"
                                class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-xl transition-all">
                                Confirm
                            </x-form.primary-button>
                        </div>
                    </x-form.form>
                </x-modals.confirmation-modal>

                <x-preview-agreement-modal />

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