<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Header --}}
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Leases</h1>
                    <p class="mt-2 text-sm text-gray-500">Manage and review tenant leases.</p>
                </div>
                <div class="flex-shrink-0 flex gap-2">
                    @canany(['owner-admin', 'leases.agreement template'])
                    <div x-data="{ loading: false }">
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
                    </div>
                    @endcanany

                    @canany(['owner-admin', 'leases.lease controller'])
                    <div x-data="{ loading: false }">
                        <x-form.primary-button
                            type="button"
                            loading="loading"
                            @click="loading = true; window.location.href = '{{ route('admin.leases.create') }}'"
                        >
                            <svg x-show="!loading" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Lease Controller
                        </x-form.primary-button>
                    </div>
                    @endcanany
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg border border-gray-100">
                <div class="p-5 border-b border-gray-100 bg-white">
                    <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                        @if(isset($packageLimitInfo))
                            <div class="flex flex-col sm:flex-row items-start sm:items-center rounded-lg bg-gray-50 p-3 border border-gray-100 gap-3 w-full md:w-auto">
                                <div class="flex items-center space-x-3">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $packageLimitInfo['isReached'] ? 'bg-red-100 text-red-800' : 'bg-emerald-100 text-emerald-800' }}">
                                        Active Leases: {{ $packageLimitInfo['current'] }} / {{ $packageLimitInfo['limit'] }}
                                    </span>
                                </div>

                                @if($packageLimitInfo['isReached'])
                                    <span class="text-xs font-medium text-red-600">
                                        ⚠️ Limit reached.
                                    </span>
                                @endif
                            </div>
                        @else
                            <div></div>
                        @endif

                        <!-- Right Side: Search Form -->
                        <div class="w-full md:w-auto flex justify-end">
                            <x-form.form
                                method="GET"
                                action="{{ route('admin.leases.index') }}"
                                class="w-full"
                            >
                                <x-table.search
                                    :isAdvanceSearch="false"
                                    :withDate="true"
                                    :showTenantField="true"
                                    :showOwnerField="true"
                                    :showleaseableField="true"
                                    :showStatusField="true"
                                    :showStampingStatusField="true"
                                    :showChargeField="true"
                                    placeholder="Search by name, unit, property..."
                                />
                            </x-form.form>
                        </div>

                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg border border-gray-100">
                    <x-table.lease-table :leases="$leases" :showOwner="true" :showTenant="true" :showAction="true" />
                </div>

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

                <x-modals.lease-create-modal 
                    :tenants="$tenants" 
                    :leases="$modalLeases" {{-- Use unfiltered modal leases here --}}
                    :properties="$properties" 
                    :units="$units" 
                    :rooms="$rooms" 
                    :templates="$templates" 
                    :rentFeeTypes="$rentFeeTypes" 
                    :serviceFeeTypes="$serviceFeeTypes" 
                    :depositFeeTypes="$depositFeeTypes" 
                    :managementFeeTypes="$managementFeeTypes" 
                    :leasePreviewData="$leasePreviewData" 
                />

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

<script>
    window.viewAgreement = function(button) {
        let content = button.dataset.baseContent;
        if (!content) {
            console.error('Agreement content is empty');
            return;
        }

        const replacements = JSON.parse(button.dataset.replacements);

        Object.keys(replacements).forEach(key => {
            const val = replacements[key] || 'N/A';
            const safeVal = `<strong>${val}</strong>`;

            // Replace GrapesJS data-variable elements
            const dataVarRegex = new RegExp(`<[^>]+data-variable=["']${key}["'][^>]*>[\\s\\S]*?<\\/\\w+>`, 'gi');
            content = content.replace(dataVarRegex, safeVal);

            // Replace standard or encoded curly brackets variants
            const textRegex = new RegExp(`(?:\\{|&#123;|&lcub;){1,2}(?:[\\s\\u200B\\u200C\\u200D\\uFEFF&nbsp;]|<[^>]*>)*${key}(?:[\\s\\u200B\\u200C\\u200D\\uFEFF&nbsp;]|<[^>]*>)*(?:\\}|&#125;|&rcub;){1,2}`, 'gi');
            content = content.replace(textRegex, safeVal);
        });

        window.dispatchEvent(new CustomEvent('open-lease-preview', { 
            detail: { content: content, title: button.dataset.title } 
        }));
    };
</script>