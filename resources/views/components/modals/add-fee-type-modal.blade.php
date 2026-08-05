<template x-teleport="body">
    <div x-data="{ 
            openFeeModal: false,
            form: { name: '', category: 'invoice' },
            loading: false,
            submitForm() {
                this.loading = true;
                fetch('{{ route('admin.fee-types.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(this.form)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success || data.id) {
                        // Dispatch event to update the invoice modal dropdowns
                        window.dispatchEvent(new CustomEvent('fee-type-added', { 
                            detail: { id: data.id || data.feeType.id, name: data.name || data.feeType.name } 
                        }));
                        
                        // Reset and close only this modal
                        this.form.name = '';
                        this.openFeeModal = false;
                    }
                })
                .catch(error => console.error('Error:', error))
                .finally(() => this.loading = false);
            }
         }" 
         @open-fee-type-modal.window="openFeeModal = true"
         x-show="openFeeModal" 
         x-cloak
         class="fixed inset-0 z-[120] overflow-y-auto">
        
        <div class="flex items-center justify-center min-h-screen px-4 py-6 text-center sm:block sm:p-0">
            
            {{-- Backdrop --}}
            <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-sm" 
                 x-show="openFeeModal"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 @click="openFeeModal = false">
            </div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

            {{-- Modal Box --}}
            <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-2xl sm:my-8 sm:align-middle sm:max-w-md sm:w-full"
                 x-show="openFeeModal"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">
                
                {{-- Changed to @submit.prevent to prevent regular page reload --}}
                <x-form.form @submit.prevent="submitForm()">
                    @csrf
                    <input type="hidden" name="category" value="invoice">
                    
                    {{-- Header --}}
                    <div class="px-6 pt-6 pb-4 bg-white border-b border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-bold text-slate-900">Add New Fee Type</h3>
                                <p class="text-xs text-gray-500 mt-0.5">Create a custom charge type for your invoices.</p>
                            </div>
                            <button type="button" @click="openFeeModal = false" class="text-gray-400 hover:text-gray-600 text-2xl font-light">&times;</button>
                        </div>
                    </div>

                    {{-- Body --}}
                    <div class="px-6 py-5 bg-white space-y-4">
                        <div>
                            <x-form.input-label value="Fee Type Name" required class="text-xs text-gray-500 mb-1" />
                            <x-form.text-input x-model="form.name" name="name" class="w-full" required />
                        </div>
                    </div>

                    {{-- Footer Actions --}}
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-3">
                        <button type="button" @click="openFeeModal = false" class="px-5 py-2.5 border border-gray-200 text-gray-600 rounded-xl font-bold text-xs hover:bg-white transition-colors uppercase tracking-wider">
                            Cancel
                        </button>
                        <x-form.primary-button loading="loading" class="px-6 py-2.5 rounded-xl text-xs shadow-md shadow-indigo-100 uppercase tracking-wider">
                            <span>Save</span>
                        </x-form.primary-button>
                    </div>
                </x-form.form>
            </div>
        </div>
    </div>
</template>