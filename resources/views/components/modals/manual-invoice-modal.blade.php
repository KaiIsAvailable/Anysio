@props([
    'tenantId' => null,
    'feeTypes' => []
])

<template x-teleport="body">
    <div x-data="{ 
        openManual: false, 
        actionUrl: '',
        shake: false,
        loading: false,
        currentBillingPeriod: (() => {
            let d = new Date();
            let year = d.getFullYear();
            let month = String(d.getMonth() + 1).padStart(2, '0');
            return `${year}-${month}-01`;
        })(),
        availableFeeTypes: @js($feeTypes),
        items: [
            { fee_type_id: '', amount: '', description: '' }
        ],
        addItem() {
            this.items.push({ fee_type_id: '', amount: '', description: '' });
        },
        removeItem(index) {
            if (this.items.length > 1) {
                this.items.splice(index, 1);
            }
        },
        calculateTotal() {
            let total = this.items.reduce((sum, item) => sum + (parseFloat(item.amount) || 0), 0);
            return total.toFixed(2);
        },
        handleFeeTypeAdded(detail) {
            this.availableFeeTypes.push(detail);
            let lastItem = this.items[this.items.length - 1];
            if (lastItem && !lastItem.fee_type_id) {
                lastItem.fee_type_id = detail.id;
            }
        },
        resetForm() {
            this.items = [{ fee_type_id: '', amount: '', description: '' }];
            let d = new Date();
            let month = String(d.getMonth() + 1).padStart(2, '0');
            let year = d.getFullYear();
            this.currentBillingPeriod = `${year}-${month}-01`;
            this.shake = false;
            this.loading = false;
        },
        submitInvoice(event) {
            let form = event.target;
            let formData = new FormData(form);
            this.loading = true;

            fetch(this.actionUrl, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']').getAttribute('content')
                },
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    return response.json(); 
                }
                return response.json().then(errData => { throw errData; });
            })
            .then(data => {
                this.loading = false;
                if (data.success && data.invoice) {
                    this.openManual = false;
                    this.resetForm();
                    form.reset();

                    // --- 🌟 调整：规范化传输到主页面的 invoice 数据结构 ---
                    const formattedInvoice = {
                        id: data.invoice.id,
                        invoice_no: data.invoice.invoice_no,
                        document_template_id: data.invoice.document_template_id ?? '—',
                        template_title: data.invoice.template_title ?? 'Manual Invoice',
                        template_html: data.invoice.template_html ?? '',
                        period: data.invoice.period,
                        due_date: data.invoice.due_date,
                        total_amount: data.invoice.total_amount,
                        amount_paid: data.invoice.amount_paid || '0.00',
                        amount_balance: data.invoice.amount_balance,
                        status: data.invoice.status,
                        remarks: data.invoice.remarks,
                        invoice_items: (data.invoice.items || []).map(item => ({
                            description: item.description || item.fee_type?.name || 'Item',
                            amount: item.amount
                        }))
                    };

                    window.dispatchEvent(new CustomEvent('invoice-generated', { 
                        detail: { 
                            success: true, 
                            invoice: formattedInvoice 
                        } 
                    }));
                }
            })
            .catch(error => {
                console.error('Fetch failed, falling back to traditional form.submit():', error);
                this.loading = false;
                form.submit();
            });
        }
     }" 
     @open-manual-modal.window="
        actionUrl = $event.detail.action; 
        openManual = true;
     "
     @fee-type-added.window="handleFeeTypeAdded($event.detail)"
     x-show="openManual" 
     x-cloak
     class="fixed inset-0 z-[110] overflow-y-auto">
         
        <div class="flex items-center justify-center min-h-screen px-4 py-6 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-sm" 
                 x-show="openManual"
                 @click="shake = true; setTimeout(() => shake = false, 400)">
            </div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

            <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-2xl sm:my-8 sm:align-middle sm:max-w-xl sm:w-full"
                 :class="{ 'animate-shake': shake }"
                 x-show="openManual">
                
                <form @submit.prevent="submitInvoice($event)" :action="actionUrl" method="POST">
                    @csrf
                    
                    {{-- Header --}}
                    <div class="px-6 pt-6 pb-4 bg-white border-b border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-bold text-slate-900">Create Invoice</h3>
                            </div>
                            <button type="button" @click="openManual = false" class="text-gray-400 hover:text-gray-600 text-2xl font-light">&times;</button>
                        </div>
                    </div>

                    {{-- Body --}}
                    <div class="px-6 py-5 bg-white space-y-5 max-h-[70vh] overflow-y-auto">
                        
                        {{-- Billing Month & Due Date --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-form.input-label value="Billing Month" required class="text-xs text-gray-500 mb-1" />
                                <x-form.date-input 
                                    id="billing_period" 
                                    name="period" 
                                    mode="month"
                                    x-model="currentBillingPeriod"
                                    x-bind:value="currentBillingPeriod"
                                    class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:bg-white text-sm transition-all shadow-none" 
                                />
                            </div>
                            <div>
                                <x-form.input-label value="Due Date" required class="text-xs text-gray-500 mb-1" />
                                <x-form.date-input 
                                    id="due_date_modal" 
                                    name="due_date" 
                                    value="{{ now()->addDays(7)->format('Y-m-d') }}"
                                    class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:bg-white text-sm transition-all shadow-none" 
                                />
                            </div>
                        </div>

                        <hr class="border-gray-100">

                        {{-- Dynamic Invoice Items --}}
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-4">
                                    <x-form.input-label value="Invoice Items" class="text-xs text-gray-500" />
                                    <span class="text-xs font-semibold text-indigo-600">Total: RM <span x-text="calculateTotal()"></span></span>
                                </div>
                                <button type="button" @click="addItem()" class="inline-flex items-center text-xs font-bold text-indigo-600 hover:text-indigo-700 transition-colors">
                                    Add Charge Item
                                </button>
                            </div>

                            <div class="space-y-3">
                                <template x-for="(item, index) in items" :key="index">
                                    <div class="p-3.5 bg-gray-50 rounded-xl border border-gray-200/60 relative group space-y-3">
                                        <div class="flex items-center justify-between">
                                            <span class="text-[11px] font-bold text-slate-600 uppercase" x-text="'Charge Item #' + (index + 1)"></span>
                                            <button type="button" @click="removeItem(index)" x-show="items.length > 1" class="text-red-500 hover:text-red-700 text-xs font-semibold transition-colors">
                                                Remove
                                            </button>
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                                            {{-- Fee Type Selector Loop updated to use Alpine array --}}
                                            <div class="sm:col-span-7">
                                                <div class="flex items-center gap-1.5">
                                                    <select :name="'items['+index+'][fee_type_id]'" x-model="item.fee_type_id" class="block w-full px-3 py-2 bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-500 text-xs transition-all" required>
                                                        <option value="">Select Fee Type...</option>
                                                        <template x-for="feeType in availableFeeTypes" :key="feeType.id">
                                                            <option :value="feeType.id" x-text="feeType.name"></option>
                                                        </template>
                                                    </select>
                                                    <button type="button" 
                                                            @click="$dispatch('open-fee-type-modal')" 
                                                            title="Add New Fee Type" 
                                                            class="shrink-0 p-2 bg-white border border-gray-200 hover:border-indigo-500 hover:text-indigo-600 text-gray-500 rounded-lg transition-all text-xs font-bold flex items-center justify-center">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>

                                            {{-- Amount --}}
                                            <div class="sm:col-span-5">
                                                <div class="relative rounded-lg shadow-sm">
                                                    <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-gray-400 text-xs">RM</div>
                                                    <input type="number" step="0.01" :name="'items['+index+'][amount]'" x-model.number="item.amount" placeholder="0.00"
                                                           class="block w-full pl-9 pr-3 py-2 bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-500 font-semibold text-xs transition-all" required>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Optional Description --}}
                                        <div>
                                            <input type="text" :name="'items['+index+'][description]'" x-model="item.description" placeholder="Description or meter reading notes (optional)..."
                                                class="block w-full px-3 py-1.5 bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-500 text-xs text-gray-600 transition-all">
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <x-modals.add-fee-type-modal />

                        <hr class="border-gray-100">

                        {{-- Remarks --}}
                        <div>
                            <x-form.input-label value="Remarks / Notes" class="text-xs text-gray-500 mb-1" />
                            <textarea name="remarks" rows="2"
                                      class="block w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:bg-white text-sm transition-all"></textarea>
                        </div>
                    </div>

                    {{-- Footer Actions --}}
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-3">
                        <button type="button" @click="openManual = false; resetForm()" class="px-5 py-2.5 border border-gray-200 text-gray-600 rounded-xl font-bold text-xs hover:bg-white transition-colors uppercase tracking-wider">
                            Cancel
                        </button>
                        <x-form.primary-button loading="loading" class="px-6 py-2.5 rounded-xl text-xs shadow-md shadow-indigo-100 uppercase tracking-wider">
                            <span>Generate Invoice</span>
                        </x-form.primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>