<style>
    [x-cloak] {
        display: none !important;
    }
</style>
<x-app-layout>
    <div x-data="{ 
                openPayment: false, 
                shakePayment: false,
                {{-- 1. 初始化结构，保持驼峰命名 --}}
                paymentData: { id: '', invoiceNo: '', amountDue: 0, actionUrl: '' },
                
                {{-- 2. 如果你在其他地方需要手动调用这个方法 --}}
                openMakePayment(data) {
                    this.paymentData = {
                        id: data.id,
                        invoiceNo: data.invoiceNo,
                        amountDue: data.amountDue,
                        actionUrl: data.actionUrl
                    };
                    this.openPayment = true;
                }
            }"
        {{-- 3. 核心修改：直接从 $event.detail 中读取驼峰命名的 Key --}}
        @open-payment.window="
                paymentData.id = $event.detail.id;
                paymentData.invoiceNo = $event.detail.invoiceNo;
                paymentData.amountDue = $event.detail.amountDue;
                paymentData.actionUrl = $event.detail.actionUrl;
                openPayment = true;
            ">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <nav class="flex mb-2" aria-label="Breadcrumb">
                        <a href="{{ route('admin.tenants.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-700 flex items-center transition-colors">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            Back to Tenants List
                        </a>
                    </nav>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Tenant Details</h1>
                </div>

                <div class="flex items-center gap-3">
                    @canany(['super-admin', 'tenant.edit'])
                    <a href="{{ route('admin.tenants.edit', [$tenant->id, 'from' => 'show']) }}"
                        class="inline-flex items-center px-4 py-2.5 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-all">
                        <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        Edit Tenant
                    </a>
                    @endcanany

                    @canany(['super-admin', 'tenant.delete'])
                    <form action="{{ route('admin.tenants.destroy', $tenant->id) }}" method="POST" class="contents" onsubmit="return confirm('Are you sure you want to delete this tenant?');">
                        @csrf @method('DELETE')
                        <button type="submit" class="inline-flex items-center px-4 py-2.5 border border-transparent text-sm font-medium rounded-lg text-white bg-red-600 hover:bg-red-700 shadow-sm transition-all">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            Delete
                        </button>
                    </form>
                    @endcanany
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                <div class="px-8 py-8 border-b border-gray-100 bg-white">
                    <div class="flex items-center">
                        <div class="h-16 w-16 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-700 text-2xl font-bold uppercase">
                            {{ strtoupper(mb_substr($tenant->user->name ?? 'T', 0, 1, 'UTF-8')) }}
                        </div>
                        <div class="ml-6">
                            <h2 class="text-2xl font-bold text-slate-900 tracking-tight">{{ $tenant->user->name }}</h2>
                            @if(filled($tenant->user->email))
                                <p class="text-sm text-gray-500 font-medium">{{ $tenant->user->email }}</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="px-8 py-10 bg-white">
                    <div class="space-y-8">
                        <section>
                            <h3 class="mb-4 text-xs font-semibold text-gray-400 uppercase tracking-widest border-l-4 border-indigo-500 pl-3">Tenant Details</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                @foreach(['tenant_type' => 'Tenant Type', 'phone' => 'Tenant Phone', 'occupation' => 'Occupation', 'gender' => 'Gender', 'nationality' => 'Nationality', 'ic_number' => 'IC Number', 'passport' => 'Passport'] as $field => $label)
                                    @if(filled($tenant->{$field}))
                                        <div class="pl-4">
                                            <p class="text-xs font-medium text-gray-400">{{ $label }}</p>
                                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ $field === 'tenant_type' ? ucfirst($tenant->{$field}) : $tenant->{$field} }}</p>
                                        </div>
                                    @endif
                                @endforeach
                                @if($tenant->created_at)
                                    <div class="pl-4">
                                        <p class="text-xs font-medium text-gray-400">Joined Date</p>
                                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ $tenant->created_at->format('d M Y, H:i') }}</p>
                                    </div>
                                @endif
                                @if($tenant->tenant_type !== 'corporate' && filled($tenant->ic_photo_path))
                                    <div class="pl-4">
                                        <a href="{{ route('admin.tenants.view-ic', $tenant->id) }}" class="text-sm font-semibold text-indigo-600 hover:underline">Preview IC / Passport Document</a>
                                    </div>
                                @endif
                            </div>
                        </section>

                        @if($tenant->emergencyContacts->isNotEmpty())
                            <section>
                                <h3 class="mb-4 text-xs font-semibold text-gray-400 uppercase tracking-widest border-l-4 border-indigo-500 pl-3">Emergency Contacts</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    @foreach($tenant->emergencyContacts as $contact)
                                        <div class="pl-4">
                                            @if(filled($contact->name))
                                                <p class="text-sm font-semibold text-slate-900">{{ $contact->name }}</p>
                                            @endif
                                            @if(filled($contact->relationship))
                                                <p class="text-xs text-gray-500">{{ $contact->relationship }}</p>
                                            @endif
                                            @if(filled($contact->phone))
                                                <p class="text-sm text-slate-700">{{ $contact->phone }}</p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif

                        @if($tenant->user->companies->isNotEmpty())
                            <section class="space-y-4 border-t border-gray-100 pt-6">
                                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-widest border-l-4 border-indigo-500 pl-3">Company Details</h3>
                                @foreach($tenant->user->companies as $company)
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 rounded-xl border border-gray-200 bg-slate-50 p-5">
                                        @if(filled($company->logo_path))
                                            <div class="md:col-span-2 lg:col-span-3">
                                                <img src="{{ asset('storage/' . $company->logo_path) }}" alt="{{ $company->name }} logo" class="h-20 w-20 object-contain" />
                                            </div>
                                        @endif
                                        @foreach(['name' => 'Company Name', 'ssm_old_number' => 'SSM Old Number', 'ssm_new_number' => 'SSM New Number', 'phone' => 'Company Phone', 'email' => 'Company Email', 'address' => 'Company Address'] as $field => $label)
                                            @if(filled($company->{$field}))
                                                <div class="min-w-0">
                                                    <p class="text-xs font-medium text-gray-400">{{ $label }}</p>
                                                    <p class="mt-1 text-sm font-semibold text-slate-900 break-words">{{ $company->{$field} }}</p>
                                                </div>
                                            @endif
                                        @endforeach
                                        @if(filled($company->ssm_cert_path))
                                            <div class="md:col-span-2 lg:col-span-3">
                                                <a href="{{ route('admin.tenants.ic_photo', basename($company->ssm_cert_path)) }}" target="_blank" rel="noopener" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-indigo-600 hover:bg-indigo-50">Preview SSM Certificate</a>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </section>
                        @endif

                        @if($bankDetail)
                            <section class="space-y-4 border-t border-gray-100 pt-6">
                                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-widest border-l-4 border-indigo-500 pl-3">Bank Details</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 rounded-xl border border-gray-200 bg-slate-50 p-5">
                                    @foreach(['bank_name' => 'Bank Name', 'bank_code' => 'Bank Code', 'account_holder_name' => 'Account Holder'] as $field => $label)
                                        @if(filled($bankDetail->{$field}))
                                            <div>
                                                <p class="text-xs font-medium text-gray-400">{{ $label }}</p>
                                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ $bankDetail->{$field} }}</p>
                                            </div>
                                        @endif
                                    @endforeach
                                    @if(filled($bankDetail->account_number))
                                        <div>
                                            <p class="text-xs font-medium text-gray-400">Account Number</p>
                                            <p class="mt-1 text-sm font-semibold text-slate-900">******{{ substr($bankDetail->account_number, -4) }}</p>
                                        </div>
                                    @endif
                                </div>
                            </section>
                        @endif
                    </div>

                    <!-- Lease Section -->
                    <div class="mt-12 pt-8 border-t border-gray-100">

                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                                <svg class="w-5 h-5 text-indigo-600"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                                    </path>
                                </svg>

                                Lease Agreements
                            </h3>
                        </div>

                        <div class="border border-gray-200 rounded-xl shadow-sm">

                            <div>
                                @if($leases && $leases->count() > 0)

                                <x-table.lease-table :leases="$leases" :showOwner="false" :showTenant="false" :showAction="true" />

                                @else

                                <div class="text-center py-12 bg-white">
                                    <p class="text-sm text-gray-500">
                                        No leases found.
                                    </p>
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
            </div>
        </div>

        <x-modals.lease-create-modal 
            :tenants="$tenants" 
            :leases="$modalLeases" {{-- Use unfiltered modal leases here --}}
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
    </div>
</x-app-layout>