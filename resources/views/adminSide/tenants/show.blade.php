<!--把makePament的page叫出来-->
@include('adminSide.tenants.payments.makePayment')
@include('adminSide.tenants.payments.generateInvoice')

<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
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
                    <a href="{{ route('admin.tenants.edit', [$tenant->id, 'from' => 'show']) }}" 
                    class="inline-flex items-center px-4 py-2.5 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-all">
                        <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        Edit Tenant
                    </a>
                    
                    <form action="{{ route('admin.tenants.destroy', $tenant->id) }}" method="POST" class="contents" onsubmit="return confirm('Are you sure you want to delete this tenant?');">
                        @csrf @method('DELETE')
                        <button type="submit" class="inline-flex items-center px-4 py-2.5 border border-transparent text-sm font-medium rounded-lg text-white bg-red-600 hover:bg-red-700 shadow-sm transition-all">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            Delete
                        </button>
                    </form>
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
                            <p class="text-sm text-gray-500 font-medium">{{ $tenant->user->email }}</p>
                        </div>
                    </div>
                </div>

                <div class="px-8 py-10 bg-white">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-y-10 gap-x-12">
                        
                        <div class="space-y-4">
                            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-widest border-l-4 border-indigo-500 pl-3">Personal Info</h3>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">Occupation</label>
                                <p class="text-sm font-bold text-slate-900 mt-0.5 tracking-tight">{{ $tenant->occupation ?? '—' }}</p>
                            </div>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">Gender</label>
                                <p class="text-sm font-bold text-slate-900 mt-0.5">{{ $tenant->gender ?? '—' }}</p>
                            </div>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">Nationality</label>
                                <p class="text-sm font-bold text-slate-900 mt-0.5">{{ $tenant->nationality ?? '—' }}</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-widest border-l-4 border-indigo-500 pl-3">Contact & Identity</h3>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">Phone Number</label>
                                <p class="text-sm font-bold text-slate-900 mt-0.5">{{ $tenant->phone ?? '—' }}</p>
                            </div>
                            <div class="pl-4">
                                <label class="text-xs font-medium text-gray-400">Identity Document</label>
                                <p class="text-sm font-semibold text-slate-700 mt-0.5">
                                    @if($tenant->ic_number)
                                        IC: {{ $tenant->ic_number }}
                                    @elseif($tenant->passport)
                                        Passport: {{ $tenant->passport }}
                                    @else
                                        —
                                    @endif
                                </p>
                            </div>
                            <div class="mt-2">
                                @if($tenant->ic_photo_path)
                                    {{-- 统一使用精简版附件样式 --}}
                                    <a href="{{ route('admin.tenants.ic_photo', basename($tenant->ic_photo_path)) }}" 
                                    target="_blank" 
                                    class="group inline-flex items-center gap-3 px-4 py-2 bg-white border border-slate-200 rounded-xl shadow-sm hover:shadow-md hover:border-indigo-300 hover:bg-indigo-50 transition-all duration-200">
                                        
                                        <div class="flex-shrink-0 w-8 h-8 bg-slate-100 group-hover:bg-white rounded-full flex items-center justify-center transition-colors">
                                            <svg class="w-4 h-4 text-slate-500 group-hover:text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm5 3a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                            </svg>
                                        </div>

                                        <div class="flex flex-col">
                                            <span class="text-[12px] font-bold text-slate-700 group-hover:text-indigo-800 uppercase tracking-tight">Identity Document (IC)</span>
                                            <span class="text-[10px] text-slate-400 group-hover:text-indigo-500 font-medium">Click to Preview</span>
                                        </div>

                                        <svg class="w-4 h-4 text-slate-300 group-hover:text-indigo-400 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                        </svg>
                                    </a>
                                @else
                                    <div class="inline-flex items-center gap-2 px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-gray-400 italic text-sm">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                        No document uploaded
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-widest border-l-4 border-indigo-500 pl-3">Emergency Contacts</h3>
                            @forelse($tenant->emergencyContacts as $contact)
                                <div class="pl-4 mb-3 last:mb-0">
                                    <p class="text-sm font-bold text-slate-900">{{ $contact->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $contact->relationship }} • {{ $contact->phone }}</p>
                                </div>
                            @empty
                                <div class="pl-4">
                                    <p class="text-sm text-gray-400 italic">No emergency contacts listed.</p>
                                </div>
                            @endforelse
                            
                            <div class="pt-4 mt-2">
                                <label class="text-xs font-medium text-gray-400 border-l-4 border-indigo-500 pl-3 block mb-2 uppercase tracking-widest">Joined Date</label>
                                <p class="text-sm font-semibold text-slate-700 pl-4 mt-0.5">
                                    {{ $tenant->created_at ? $tenant->created_at->format('d M Y, H:i') : 'N/A' }}
                                </p>
                            </div>
                        </div>

                    </div>

                    <!-- Lease Section -->
                    <div class="mt-12 pt-8 border-t border-gray-100">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                                </svg>
                                Lease Agreements
                            </h3>
                        </div>

                        <div class="grid grid-cols-1 gap-4">
                            @forelse($tenant->leases as $lease)
                                @php
                                    // 逻辑判断：如果结束日期早于今天，标记为已过期
                                    $isExpired = $lease->end_date->isPast();
                                @endphp

                                <div onclick="window.location='{{ route('admin.leases.show', $lease->id) }}'" 
                                    class="group relative bg-white border {{ $isExpired ? 'border-gray-100 opacity-75' : 'border-gray-200' }} rounded-xl p-5 hover:border-indigo-400 hover:shadow-md transition-all cursor-pointer mb-4">
                                    
                                    @if($isExpired)
                                        <div class="absolute top-0 right-0 mt-2 mr-2">
                                            <span class="text-[10px] font-bold text-gray-400 uppercase">Expired</span>
                                        </div>
                                    @endif

                                    <div class="flex flex-col md:flex-row justify-between gap-4">
                                        <div class="flex-1 grid grid-cols-2 md:grid-cols-4 gap-4 text-left">
                                            <div>
                                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Room</label>
                                                <p class="text-sm font-bold {{ $isExpired ? 'text-gray-500' : 'text-slate-900 group-hover:text-indigo-600' }} transition-colors">
                                                    {{ $lease->room->room_no ?? 'N/A' }}
                                                </p>
                                            </div>
                                            <div>
                                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Monthly Rent</label>
                                                <p class="text-sm font-bold {{ $isExpired ? 'text-gray-500' : 'text-slate-900' }}">RM {{ number_format($lease->monthly_rent / 100, 2) }}</p>
                                            </div>
                                            <div>
                                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Duration</label>
                                                <p class="text-sm {{ $isExpired ? 'text-gray-400' : 'text-slate-700' }} font-medium">
                                                    {{ $lease->start_date->format('d M Y') }} - {{ $lease->end_date->format('d M Y') }}
                                                </p>
                                            </div>
                                            <div>
                                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Status</label>
                                                <div>
                                                    @if($isExpired)
                                                        <span class="px-2.5 py-0.5 inline-flex text-xs font-bold rounded-full bg-gray-100 text-gray-500">History</span>
                                                    @else
                                                        <span class="px-2.5 py-0.5 inline-flex text-xs font-bold rounded-full {{ $lease->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                                            {{ ucfirst($lease->status) }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        @if ($lease->status === 'New' || $lease->status === 'Renew')
                                            <form action="{{ route('admin.payments.generateMonthlyInvoice', $lease->id) }}" method="POST" class="contents">
                                                @csrf
                                                {{-- 为了防止卡片点击跳转冲突，建议在按钮上加 stopPropagation --}}
                                                <button type="submit" 
                                                        onclick="event.stopPropagation();"
                                                        class="inline-flex items-center px-4 py-2 h-10 text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm transition-all">
                                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                                    </svg>
                                                    Generate Invoice
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                                @empty
                            @endforelse
                        </div>
                    </div>

                    <!-- Payment Section -->
                    <div class="mt-12 pt-8 border-t border-gray-100">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Invoices & Payments
                            </h3>
                            <div class="flex items-center gap-2">
                                {{-- 次按钮 --}}
                                <button type="button" 
                                        onclick="openManualInvoiceModal(this)"
                                        data-url="{{ route('admin.payments.storeManualInvoice', $tenant->id) }}"
                                        class="inline-flex items-center px-4 py-2 h-10 text-sm font-medium rounded-lg text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 shadow-sm transition-all">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                    Add Manual Invoice
                                </button>
                            </div>
                        </div>

                        <h4 class="text-sm font-bold text-gray-700 mb-2">Rent Outstanding</h4>
                        <div class="overflow-x-auto bg-white border border-gray-200 rounded-xl shadow-sm">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr></tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @include('adminSide.tenants.payments.paymentTable', [
                                        'payments' => $rentPayments,
                                        'emptyMessage' => 'No outstanding rent found.'
                                    ])
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">
                            {{ $rentPayments->appends(['other_page' => request('other_page')])->links() }}
                        </div>

                        <br>

                        <h4 class="text-sm font-bold text-gray-700 mb-2">Other Bills & Miscellaneous</h4>
                        <div class="overflow-x-auto bg-white border border-gray-200 rounded-xl shadow-sm">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr></tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @include('adminSide.tenants.payments.paymentTable', [
                                        'payments' => $otherPayments,
                                        'emptyMessage' => 'No miscellaneous records found.'
                                    ])
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">
                            {{ $otherPayments->appends(['rent_page' => request('rent_page')])->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
