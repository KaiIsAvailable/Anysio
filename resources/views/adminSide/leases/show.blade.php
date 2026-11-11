<x-app-layout>
    @php
    $defaultActiveId = $leaseHistory->firstWhere('is_current', true)?->id ?? $leaseHistory->first()?->id ?? $lease->id;
    $stampingErrors = $errors->hasAny(['stamping_reference_no', 'stamping_cert']);
    @endphp
    <div class="py-12 bg-gray-50 min-h-screen font-sans"
        x-data="{
            activeId: '{{ old('active_id', $defaultActiveId) }}',
            source: {{ $historyJson->isNotEmpty() ? $historyJson->toJson() : '{}' }},
            openUpload: @js($stampingErrors), shake: @js($stampingErrors),
            openManual: false, manualActionUrl: '',
            get activeLease() { return this.source[this.activeId] || {}; },
            getManualInvoiceUrl() {
                return '{{ route('admin.invoices.store-manual', '__LEASE__') }}'.replace('__LEASE__', this.activeId);
            }
        }"
        @click.stop
        @open-manual-modal.window="openManual = true; manualActionUrl = $event.detail.action"
        @invoice-table-updated.window="
            const data = $event.detail;
            if (data.lease_id && source[data.lease_id]) {
                source[data.lease_id].refund_status = data.refund_status;
                source[data.lease_id].refund_id = data.refund_id;
                Object.values(source).forEach(lease => lease.wallet_balance = data.wallet_balance);
            }
        ">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <a href="{{ route('admin.leases.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-700 flex items-center transition-colors mb-4">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Back to Lease List
            </a>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-3">
                <div class="mb-8 w-full">
                    <div class="p-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                        <h2 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Lease Progression</h2>
                        
                        <div class="flex items-center gap-2">
                            {{-- 1. Renew Lease Button --}}
                            @canany(['super-admin', 'leases.renew lease'])
                                <button type="button" 
                                    x-show="activeLease && activeLease.is_current && !['cancelled', 'check out', 'end', 'refunded'].includes((activeLease.status || '').toLowerCase())"
                                    x-cloak
                                    @click.stop="window.dispatchEvent(new CustomEvent('open-lease-modal', { detail: { status: 'Renew', leaseId: activeId } }))"
                                    class="px-3 py-1.5 bg-emerald-50 text-emerald-600 text-xs font-black rounded-lg border border-emerald-100 hover:bg-emerald-600 hover:text-white transition-all shadow-sm flex items-center justify-center">
                                    RENEW LEASE
                                </button>
                            @endcanany

                            {{-- 2. Check Out Lease Button --}}
                            @canany(['super-admin', 'leases.check out lease'])
                                        <button type="button"
                                            x-show="activeId && !['cancelled', 'end'].includes((activeLease.status || '').toLowerCase())"
                                            x-cloak
                                            x-text="activeLease.refund_status === 'paid' ? 'VIEW REFUND' : (activeLease.refund_id ? 'CONTINUE REFUND' : 'CHECK OUT')"
                                            @click.stop="window.dispatchEvent(new CustomEvent('open-lease-modal', { detail: { status: 'Check Out', leaseId: activeId } }))"
                                            class="px-3 py-1.5 bg-amber-50 text-amber-600 text-xs font-black rounded-lg border border-amber-100 hover:bg-amber-600 hover:text-white transition-all shadow-sm flex items-center justify-center">
                                        </button>
                            @endcanany

                            {{-- 3. Cancel Lease Button --}}
                            @canany(['super-admin', 'leases.cancel lease'])
                                @if(!in_array(strtolower($lease->status), ['cancelled', 'check out', 'end']))
                                    <button type="button" 
                                        @click="$dispatch('open-lease-confirm-modal', { 
                                            actionUrl: '{{ route('admin.leases.cancel', ':id') }}'.replace(':id', activeId) 
                                        })"
                                        class="px-3 py-1.5 bg-rose-50 text-rose-600 text-xs font-black rounded-lg border border-rose-100 hover:bg-rose-600 hover:text-white transition-all shadow-sm flex items-center justify-center">
                                        CANCEL LEASE
                                    </button>
                                @endif
                            @endcanany
                        </div>
                    </div>

                    <div class="p-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 w-full z-[101]">
                            @foreach($leaseHistory as $history)
                            @php
                                $hStatus = strtolower((string)$history->status);
                                $hIsCheckout = in_array($hStatus, ['check out', 'refunded'], true);
                                $hRefundStatus = $history->latestRefund?->status?->value;
                                $hRefundLabel = match ($hRefundStatus) {
                                    'draft' => 'REFUND: DRAFT',
                                    'pending' => 'REFUND: PENDING APPROVE',
                                    'approved' => 'REFUND: APPROVED, AWAITING PAYMENT',
                                    'processing' => 'REFUND: PROCESSING PAYMENT',
                                    'paid' => 'REFUNDED',
                                    'rejected' => 'REFUND: REJECTED',
                                    'cancelled' => 'REFUND: CANCELLED',
                                    default => $hStatus === 'refunded' ? 'REFUNDED' : 'REFUND: NOT STARTED',
                                };
                                $statusColor = match($hStatus) {
                                    'new' => 'indigo', 'renew' => 'emerald', 'check out' => 'amber', 'end' => 'gray', default => 'slate'
                                };
                            @endphp
                            <div @click="activeId = '{{ $history->id }}'"
                                class="relative p-4 rounded-xl border-2 transition-all duration-200 cursor-pointer group"
                                :class="activeId == '{{ $history->id }}' ? 'border-{{ $statusColor }}-500 bg-{{ $statusColor }}-50 ring-4 ring-{{ $statusColor }}-100 z-10' : 'border-gray-100 bg-white hover:border-indigo-300 hover:shadow-md hover-translate-y-1'">
                                <div class="flex flex-col items-start gap-2 mb-3">
                                    <span class="text-[10px] font-black uppercase tracking-widest px-2 py-0.5 rounded"
                                        :class="activeId == '{{ $history->id }}' ? 'bg-{{ $statusColor }}-200 text-{{ $statusColor }}-800' : 'bg-gray-100 text-gray-500'">
                                        @if($hIsCheckout)
                                            CHECK OUT
                                        @elseif($history->is_current)
                                            {{ in_array($hStatus, ['new', 'renew']) ? 'CURRENT' : strtoupper($history->status) }}
                                        @endif
                                        @if($history->is_pending_renewal) (PENDING ACTION) @endif
                                    </span>
                                    @if($hIsCheckout)
                                        <span class="text-xs font-medium leading-relaxed text-black">{{ $hRefundLabel }}</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <p class="text-xs font-bold text-slate-900">
                                        {{ $history->start_date_formatted ?? '-' }} - {{ $history->end_date_formatted ?? '-' }}
                                    </p>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm border border-gray-200 rounded-xl p-6 mb-6" x-show="activeLease && Object.keys(activeLease).length > 0">
                <!-- Header Summary Bar -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center border-b border-gray-100 pb-4 mb-6 gap-3">
                    <div class="space-y-1">
                        <span class="block text-xs font-semibold tracking-wider text-gray-400 uppercase">Lease Profile</span>

                        <template x-if="activeLease.leasable_url">
                            <a :href="activeLease.leasable_url"
                            class="block text-base font-bold text-indigo-600 hover:text-indigo-900 hover:underline"
                            x-text="activeLease.property_name"></a>
                        </template>

                        <template x-if="!activeLease.leasable_url">
                            <h3 class="block text-base font-bold text-gray-900" x-text="activeLease.property_name"></h3>
                        </template>
                    </div>
                    <div class="flex items-center gap-2" x-data="{ 
                        openUpload: @js($stampingErrors),
                        shake: @js($stampingErrors)
                    }">
                        {{-- 左侧按钮：View Agreement --}}
                        @canany(['super-admin', 'leases.view agreement'])
                        @if (!empty($lease->document_id))
                            <x-form.primary-button type="button"
                                data-base-content="{{ $lease->documentTemplate?->html_template }}"
                                data-rental-breakdown="{{ !$lease->stamping_status && !$lease->stamping_cert_path ? 'true' : 'false' }}"
                                data-rental-basis="{{ json_encode(app(\App\Services\LeaseService::class)->getAgreementRentalBasis($lease)) }}"
                                data-title="{{ $lease->documentTemplate?->title }}"
                                data-replacements="{{ json_encode(app(\App\Services\LeaseService::class)->getAgreementVariables($lease), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) }}"
                                @click="viewAgreement($el)"
                                class="ml-auto !text-indigo-700 !bg-indigo-50 hover:!bg-indigo-100 !border-indigo-200 focus:!bg-indigo-50 active:!bg-indigo-50">
                                VIEW AGREEMENT
                            </x-form.primary-button>
                        @endif
                        @endcanany

                        @canany(['super-admin', 'leases.view agreement'])
                        @if (!empty($lease->refund_document_id))
                            <x-form.primary-button type="button"
                                data-template-id="{{ $lease->refund_document_id }}"
                                data-lease-id="{{ $lease->id }}"
                                data-check-out-date="{{ $lease->checked_out_at?->format('Y-m-d') }}"
                                @click="viewRefund($el)"
                                class="ml-auto !text-indigo-700 !bg-indigo-50 hover:!bg-indigo-100 !border-indigo-200 focus:!bg-indigo-50 active:!bg-indigo-50">
                                VIEW REFUND STATEMENT
                            </x-form.primary-button>
                        @endif
                        @endcanany

                        {{-- 右侧按钮：Upload Stamping (条件渲染) --}}
                        @canany(['super-admin', 'leases.upload stamping'])
                        @if(!$lease->stamping_status && !in_array(strtolower($lease->status), ['check out', 'end agreement']))
                            <button @click="openUpload = true"
                                class="flex-1 px-4 py-2.5 bg-indigo-600 text-white text-xs font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-sm text-center">
                                UPLOAD STAMPING
                            </button>
                        @endif

                        {{-- Modal 渲染 --}}
                        @if(!$lease->stamping_status && !in_array(strtolower($lease->status), ['check out', 'end agreement']))
                            <x-modals.lease-stamping-modal :lease="$lease" />
                        @endif
                        @endcanany
                    </div>
                </div>

                <!-- Compact Structured Grid Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">

                    <!-- Block 1: Terms & Financials -->
                    <div class="bg-gray-50/60 rounded-md p-3 border border-gray-100 space-y-1.5">
                        <h4 class="font-bold tracking-wider text-gray-400 uppercase text-xs">Financials & Term</h4>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500">Term Type</span>
                            <span class="font-semibold text-gray-900" x-text="activeLease.term_type"></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500">Total Rent</span>
                            <span class="font-bold text-emerald-600">RM <span x-text="activeLease.total_rent_price"></span></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500">Duration</span>
                            <span class="font-medium text-gray-900"><span x-text="activeLease.start_date"></span> → <span x-text="activeLease.end_date"></span></span>
                        </div>
                    </div>

                    <!-- Block 2: Tenant Information -->
                    <div class="bg-gray-50/60 rounded-md p-3 border border-gray-100 space-y-1.5">
                        <h4 class="font-bold tracking-wider text-gray-400 uppercase text-xs">Tenant Details</h4>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500">Name</span>
                            <span class="font-semibold text-gray-900 truncate max-w-[160px]" :title="activeLease.tenant_name" x-text="activeLease.tenant_name"></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500">IC / ID</span>
                            <span class="font-mono text-gray-700" x-text="activeLease.tenant_ic"></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500">Wallet Amount</span>
                            <span class="font-mono text-gray-700" x-text="'RM ' + activeLease.wallet_balance"></span>
                        </div>
                    </div>

                    <!-- Block 3: Owner & Compliance -->
                    <div class="bg-gray-50/60 rounded-md p-3 border border-gray-100 space-y-1.5">
                        <h4 class="font-bold tracking-wider text-gray-400 uppercase text-xs">Owner & Compliance</h4>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500">Owner</span>
                            <span class="font-semibold text-gray-900 truncate max-w-[160px]" :title="activeLease.owner_name" x-text="activeLease.owner_name"></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500">Owner ID</span>
                            <span class="font-mono text-gray-700" x-text="activeLease.owner_ic"></span>
                        </div>
                        <div class="flex justify-between items-center pt-0.5">
                            <span class="text-gray-500">Stamping</span>
                            <span class="inline-flex items-center gap-1 font-medium">
                                <span class="w-1.5 h-1.5 rounded-full" :class="activeLease.stamping_status ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                                <span :class="activeLease.stamping_status ? 'text-emerald-700 font-semibold' : 'text-amber-700'"
                                    x-text="activeLease.stamping_status ? 'Stamped (' + (activeLease.stamping_reference_no ?? 'N/A') + ')' : 'Unstamped'"></span>
                            </span>
                        </div>
                    </div>

                    <!-- Block 4: Property Address (Compact Full Width) -->
                    <div class="md:col-span-2 lg:col-span-3 bg-gray-50/60 rounded-md p-3 border border-gray-100 flex flex-col justify-center">
                        <h4 class="font-bold tracking-wider text-gray-400 uppercase text-[10px] mb-0.5">Property Address</h4>
                        <span class="font-semibold text-gray-900 line-clamp-1" :title="activeLease.property_address" x-text="activeLease.property_address"></span>
                    </div>

                </div>

                <!-- Additional Charges Section (Compact Horizontal Wrap) -->
                <template x-if="activeLease.charges && activeLease.charges.length > 0">
                    <div class="border-t border-gray-100 pt-3 mt-4 text-sm">
                        <h4 class="font-bold tracking-wider text-gray-400 uppercase text-[10px] mb-2">Additional Charges Breakdown</h4>
                        <div class="bg-gray-50/50 rounded-md p-2.5 border border-gray-100 flex flex-wrap gap-x-6 gap-y-1.5">
                            <template x-for="charge in activeLease.charges" :key="charge.id">
                                <div class="py-2 border-b border-gray-100">
                                    <div class="flex items-center justify-between">
                                        <span x-text="charge.name" class="text-gray-600 font-medium"></span>
                                        <span class="font-semibold text-gray-900">RM <span x-text="charge.amount"></span></span>
                                    </div>
                                    <div class="text-sm text-gray-500" x-show="charge.description" x-text="charge.description"></div>
                                    <div class="text-sm text-gray-500 mt-1" x-show="charge.next_billing_date">
                                        Next Billing Date <span class="font-medium text-gray-900" x-text="charge.next_billing_date"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <x-invoice-statement-modal :endpoint="route('admin.leases.invoice-statement', $lease)" />

            <div class="mt-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden p-6 space-y-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold text-slate-800">Payment Overview</h3>
                        <div class="flex items-center gap-2" x-data="{
                            generating: false,
                            generateCharges() {
                                if (!this.activeId) {
                                    window.dispatchEvent(new CustomEvent('notify', { detail: { type: 'error', message: 'Please select a lease first.' } }));
                                    return;
                                }

                                this.generating = true;
                                // Dynamically append the selected activeId to the route URL
                                axios.post('{{ url('admin/invoices/generate-auto-invoice') }}/' + this.activeId)
                                    .then(response => {
                                        this.generating = false;
                                        window.dispatchEvent(new CustomEvent('notify', { 
                                            detail: { 
                                                type: 'success', 
                                                message: response.data.message 
                                            } 
                                        }));

                                        console.log('[Debug] Auto-generate invoice response:', response.data);
                                        //this.refreshTable();
                                        window.location.reload();
                                    })
                                    .catch(error => {
                                        this.generating = false;
                                        const errorMessage = error.response?.data?.message || 'Failed to generate invoice.';
                                        
                                        window.dispatchEvent(new CustomEvent('notify', { 
                                            detail: { 
                                                type: 'error', 
                                                message: errorMessage 
                                            } 
                                        }));
                                        window.location.reload();
                                    });
                            }
                        }">
                            @canany(['super-admin', 'leases.auto generate invoice'])
                            @if($allowManualRecurringInvoice)
                            <x-form.primary-button type="button" loading="generating" @click="generateCharges()">
                                Auto Generate Invoice
                            </x-form.primary-button>
                            @endif
                            @endcanany

                            <!-- Add Manual Invoice Button -->
                            @canany(['super-admin', 'leases.add manual invoice'])
                            <x-form.primary-button type="button"
                                x-show="activeId"
                                @click="$dispatch('open-manual-modal', { 
                                    action: getManualInvoiceUrl(),
                                    leaseStatus: activeLease.status,
                                    refundStatus: activeLease.refund_status,
                                    defaultDueDate: '{{ now()->addDays($dueDateDays)->format('Y-m-d') }}'
                                })"
                                class="!text-indigo-700 !bg-indigo-50 hover:!bg-indigo-100 !border-indigo-200 focus:!bg-indigo-50 active:!bg-indigo-50 shadow-sm">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Add Manual Invoice
                            </x-form.primary-button>
                            
                            <x-modals.manual-invoice-modal :feeTypes="$feeTypes" />
                            @endcanany
                        </div>
                    </div>

                    <div>
                        <div>
                            <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider mb-3 flex items-center gap-2">
                                <span class="w-1.5 h-4 bg-emerald-500 rounded-full"></span>
                                Invoices
                                <x-form.primary-button type="button"
                                    @click="$dispatch('open-modal', 'invoice-statement')"
                                    class="ml-auto !text-indigo-700 !bg-indigo-50 hover:!bg-indigo-100 !border-indigo-200 focus:!bg-indigo-50 active:!bg-indigo-50">View Statement</x-form.primary-button>
                            </h4>
                            <x-table.invoice-table
                                class="-mx-6"
                                :endpoint="route('admin.invoices.lease-rows', $defaultActiveId)"
                                :lease-id="$defaultActiveId"
                                x-effect="setEndpoint('{{ route('admin.invoices.lease-rows', '__LEASE__') }}'.replace('__LEASE__', activeId), activeId)" />
                        </div>

                            {{-- Cancel Confirmation Modal --}}
                            <x-modals.confirmation-modal id="lease-confirm-modal" title="Cancel Lease">
                                <div x-data="{ targetAction: '', reason: '', loading: false }"
                                    @open-lease-confirm-modal.window="targetAction = $event.detail.actionUrl; reason = ''">
                                <x-form.form
                                    x-bind:action="targetAction" 
                                    method="POST" 
                                    class="p-6">
                                    @csrf
                                    @method('PATCH')
                                    
                                    <div class="flex items-center gap-3 text-amber-600 bg-amber-50 p-4 rounded-xl border border-amber-100 mb-4">
                                        <p class="text-sm font-semibold text-gray-700">Are you sure you want to cancel this lease? This action cannot be undone.</p>
                                    </div>

                                    <div class="mb-4">
                                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Cancellation Reason <span class="text-red-500">*</span></label>
                                        <textarea @keydown.enter.stop="if ($event.ctrlKey &amp;&amp; !$event.isComposing &amp;&amp; $event.keyCode !== 229) { $event.preventDefault(); if (!loading) $el.form.requestSubmit(); }" name="cancellation_reason" required x-model="reason" rows="3" class="w-full text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl shadow-sm"></textarea>
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
                                </div>
                            </x-modals.confirmation-modal>



                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <x-modals.lease-create-modal 
        :tenants="$tenants" 
        :leases="$modalLeases"
        :properties="$properties" 
        :units="$units" 
        :rooms="$rooms" 
        :templates="$templates" 
        :refundTemplates="$refundTemplates" 
        :rentFeeTypes="$rentFeeTypes" 
        :serviceFeeTypes="$serviceFeeTypes" 
        :depositFeeTypes="$depositFeeTypes" 
        :managementFeeTypes="$managementFeeTypes" 
        :itemFeeTypes="$itemFeeTypes" 
        :leasePreviewData="$leasePreviewData" 
    />
</x-app-layout>

<x-lease-preview-actions />
