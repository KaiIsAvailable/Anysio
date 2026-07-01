<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans"
         x-data="{ 
            openPayment: {{ $errors->any() ? 'true' : 'false' }}, 
            shakePayment: {{ $errors->any() ? 'true' : 'false' }},
            loading: false,
            paymentData: {
                invoiceNo: '{{ old('invoice_no', '') }}',
                amountDue: '{{ old('amount_paid', '0.00') }}',
                actionUrl: '{{ old('action_url', '') }}',
                transactionRef: '{{ old('transaction_ref', '') }}', // Add this
                method: '{{ old('received_via', 'Cash') }}' // Add this to remember the select dropdown
            }
        }"
         @click.stop
         @open-payment-record.window="
            paymentData = $event.detail; 
            openPayment = true;
         ">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <x-modals.approve-payment :payments="$pendingPayments" />
            <x-modals.payment-modal /> 

            {{-- Header Section --}}
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">User Management</h1>
                    <p class="mt-2 text-sm text-gray-500">Managing subscriptions, usage, and roles for system users.</p>
                </div>
                <div class="flex-shrink-0" x-data="{loading: false}">
                    <x-form.primary-button
                        type="button"
                        loading="loading"
                        @click="loading = true; window.location.href = '{{ route('admin.userManagement.create') }}'"
                        >
                        <svg x-show="!loading" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Add New Manager
                    </x-form.primary-button>
                </div>
            </div>

            {{-- DataTable Component Apply --}}
            <x-data-table 
                :collection="$userManagement" 
                :columns="[
                    ['name' => 'User Details', 'sortField' => 'u'],
                    ['name' => 'Role & Referral', 'sortField' => 'r'],
                    ['name' => 'Subscription', 'sortField' => 's'],
                    ['name' => 'Usage/Discount', 'sortField' => 'ud'],
                    ['name' => 'Joined Date', 'sortField' => 'jd']
                ]"
            >
                <x-slot name="header">
                    <div class="flex justify-end">
                        <x-form.form method="GET" action="{{ route('admin.userManagement.index') }}" class="flex flex-wrap items-center gap-4">
                            <div class="flex items-stretch justify-between">
                                <x-table.search placeholder="Search by user name..." />
                            </div>
                        </x-form.form>
                    </div>
                </x-slot>

                {{-- Table Body Row Content --}}
                @foreach($userManagement as $user)
                    <tr class="hover:!bg-indigo-50 transition-colors cursor-pointer group duration-150" 
                        onclick="window.location='{{ route('admin.userManagement.show', $user->id) }}'">
                        
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-700 font-bold shadow-sm">
                                    {{ strtoupper(substr($user->user_name ?? $user->name ?? 'U', 0, 1)) }}
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-semibold text-slate-900">{{ $user->user_name ?? $user->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $user->user_email ?? $user->email }}</div>
                                </div>
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="inline-flex items-center px-2.5 py-0.5 rounded text-[10px] font-bold uppercase bg-blue-50 text-blue-700 border border-blue-100 mb-1">
                                {{ $user->role ?? 'N/A' }}
                            </div>
                            <div class="text-xs text-gray-400">
                                Ref: <span class="font-medium text-indigo-600">{{ $user->applied_ref_code ?? 'None' }}</span>
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            @php
                                $status = strtolower($user->subscription_status ?? 'unknown');
                                $color = match($status) {
                                    'active' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                    'pending' => 'bg-amber-100 text-amber-800 border-amber-200',
                                    'expired' => 'bg-rose-100 text-rose-800 border-rose-200',
                                    default => 'bg-gray-100 text-gray-800 border-gray-200',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $color }}">
                                {{ ucfirst($status) }}
                            </span>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-slate-700 font-medium">Count: {{ $user->usage_count ?? 0 }}</div>
                            <div class="text-xs text-indigo-600">Disc: {{ $user->discount_rate ?? 0 }}%</div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm text-slate-600">
                                {{ $user->created_at ? \Carbon\Carbon::parse($user->created_at)->format('d M, Y') : 'N/A' }}
                            </span>
                        </td>

                        {{-- 操作列 --}}
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium" onclick="event.stopPropagation()">
                            <div class="flex items-center justify-center space-x-2">
                                <a href="{{ route('admin.userManagement.edit', $user->id) }}" class="p-2 text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors" title="Edit">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </a>
                                <form action="{{ route('admin.userManagement.destroy', $user->id) }}" method="POST" onsubmit="return confirm('Delete this manager?');" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-2 text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 rounded-lg transition-colors" title="Delete">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        </div>
    </div>
</x-app-layout>