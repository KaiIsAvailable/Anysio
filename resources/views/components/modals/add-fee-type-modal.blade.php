<template x-teleport="body">
    <div x-data="{ 
            openFeeModal: false,
            shake: false,
            form: { name: '', category: 'invoice', original_price: '', sell_price: '' },
            loading: false,
            openModal(category = 'invoice') {
                this.form.category = category;
                this.form.name = '';
                this.form.original_price = '';
                this.form.sell_price = '';
                this.shake = false;
                this.openFeeModal = true;
            },
            submitForm() {
                this.loading = true;

                let payload = {
                    ...this.form,
                    original_price: (this.form.category === 'item' && this.form.original_price) ? Math.round(parseFloat(this.form.original_price) * 100) : null,
                    sell_price: (this.form.category === 'item' && this.form.sell_price) ? Math.round(parseFloat(this.form.sell_price) * 100) : null
                };

                fetch('{{ route('admin.fee-types.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success || data.id) {
                        window.dispatchEvent(new CustomEvent('fee-type-added', { 
                            detail: { 
                                id: data.id || data.feeType.id, 
                                name: data.name || data.feeType.name,
                                category: data.category || data.feeType.category,
                                sell_price: data.sell_price !== undefined ? data.sell_price : payload.sell_price
                            } 
                        }));
                        
                        this.openFeeModal = false;
                    }
                })
                .catch(error => console.error('Error:', error))
                .finally(() => this.loading = false);
            }
         }"
         @open-fee-type-modal.window="openModal($event.detail?.category || 'invoice')"
         x-show="openFeeModal" 
         x-cloak
         class="fixed inset-0 z-[120] overflow-y-auto">
        
        <div class="flex items-center justify-center min-h-screen px-4 py-6 text-center sm:block sm:p-0">
            
            {{-- Backdrop (Triggers shake instead of closing) --}}
            <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-sm" 
                 x-show="openFeeModal"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 @click="shake = true; setTimeout(() => shake = false, 400)">
            </div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

            {{-- Modal Box with shake animation class --}}
            <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-2xl sm:my-8 sm:align-middle sm:max-w-md sm:w-full"
                 :class="{ 'animate-shake': shake }"
                 x-show="openFeeModal"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">
                
                <x-form.form @submit.prevent="submitForm()">
                    @csrf
                    <input type="hidden" name="category" x-model="form.category">
                    
                    {{-- Header --}}
                    <div class="px-6 pt-6 pb-4 bg-white border-b border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-bold text-slate-900" x-text="form.category === 'item' ? 'Add New Item / Fee Type' : 'Add New Fee Type'"></h3>
                                <p class="text-xs text-gray-500 mt-0.5" x-text="form.category === 'item' ? 'Create a damage item or inventory with default pricing.' : 'Create a custom charge type for your invoices.'"></p>
                            </div>
                            <button type="button" @click="openFeeModal = false" class="text-gray-400 hover:text-gray-600 text-2xl font-light">&times;</button>
                        </div>
                    </div>

                    {{-- Body --}}
                    <div class="px-6 py-5 bg-white space-y-4">
                        {{-- Fee Name --}}
                        <div>
                            <x-form.input-label value="Fee Type Name" required class="text-xs text-gray-500 mb-1" />
                            <x-form.text-input x-model="form.name" name="name" placeholder="e.g. Light Bulb / Key Card" class="w-full text-xs" required />
                        </div>

                        {{-- Prices Grid (ONLY shows when category is 'item') --}}
                        <div class="grid grid-cols-2 gap-3" x-show="form.category === 'item'" x-transition>
                            <div>
                                <x-form.input-label value="Original Price (Cost)" class="text-xs text-gray-500 mb-1" />
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-gray-400 text-xs">RM</div>
                                    <input type="number" step="0.01" x-model="form.original_price" placeholder="0.00"
                                           class="block w-full pl-9 pr-3 py-2 bg-white border border-gray-200 rounded-lg text-xs font-semibold text-right focus:ring-2 focus:ring-indigo-500">
                                </div>
                            </div>

                            <div>
                                <x-form.input-label value="Sell Price (Default)" class="text-xs text-gray-500 mb-1" />
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-gray-400 text-xs">RM</div>
                                    <input type="number" step="0.01" x-model="form.sell_price" placeholder="0.00"
                                           class="block w-full pl-9 pr-3 py-2 bg-white border border-gray-200 rounded-lg text-xs font-semibold text-right focus:ring-2 focus:ring-indigo-500">
                                </div>
                            </div>
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