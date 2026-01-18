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
                    <a href="{{ route('admin.tenants.edit', $tenant->id) }}" 
                       class="inline-flex items-center px-4 py-2.5 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-all">
                        <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        Edit Tenant
                    </a>
                    
                    <form action="{{ route('admin.tenants.destroy', $tenant->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this tenant?');">
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
                            {{ substr($tenant->user->name ?? 'T', 0, 1) }}
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
                                    <a href="{{ route('admin.tenants.ic_photo', basename($tenant->ic_photo_path)) }}" target="_blank">
                                        <img src="{{ route('admin.tenants.ic_photo', basename($tenant->ic_photo_path)) }}" alt="Document" class="h-40 w-40 object-cover rounded-lg border border-gray-200" style="width: 150px; height: 150px;">
                                    </a>
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

                    <!-- Payment Section -->
                    <div class="mt-12 pt-8 border-t border-gray-100">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                Payments
                            </h3>
                            <button onclick="document.getElementById('addPaymentModal').classList.remove('hidden')" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 shadow-sm transition-all">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                Add Payment
                            </button>
                        </div>

                        <div class="overflow-x-auto bg-white border border-gray-200 rounded-lg shadow-sm">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Amount For</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Amount (RM)</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Receipt</th>
                                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($tenant->payments as $payment)
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $payment->created_at->format('d M Y, H:i') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 capitalize">
                                                {{ $payment->payment_type }}
                                            </td>
                                             <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 capitalize">
                                                {{ $payment->amount_type }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">
                                                RM {{ number_format($payment->amount_paid / 100, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @php
                                                    $statusClasses = match($payment->status) {
                                                        'paid' => 'bg-green-100 text-green-800',
                                                        'approved' => 'bg-blue-100 text-blue-800',
                                                        'rejected' => 'bg-red-100 text-red-800',
                                                        'void' => 'bg-gray-100 text-gray-800 line-through',
                                                        default => 'bg-gray-100 text-gray-800',
                                                    };
                                                @endphp
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusClasses }}">
                                                    {{ ucfirst($payment->status) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                @if($payment->status !== 'void' && $payment->status !== 'rejected')
                                                     <a href="{{ route('admin.payments.receipt', $payment->id) }}" target="_blank" class="text-indigo-600 hover:text-indigo-900 hover:underline flex items-center gap-1">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                                        View
                                                     </a>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                                @if($payment->status !== 'void')
                                                    <div class="flex justify-center gap-2">
                                                        @if($payment->status !== 'approved' && $payment->status !== 'rejected')
                                                            <form action="{{ route('admin.payments.updateStatus', $payment->id) }}" method="POST" class="inline">
                                                                @csrf @method('PATCH')
                                                                <input type="hidden" name="status" value="approve">
                                                                <button type="submit" class="text-blue-600 hover:text-blue-900" title="Approve">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                                </button>
                                                            </form>
                                                            <form action="{{ route('admin.payments.updateStatus', $payment->id) }}" method="POST" class="inline">
                                                                @csrf @method('PATCH')
                                                                <input type="hidden" name="status" value="reject">
                                                                <button type="submit" class="text-red-600 hover:text-red-900" title="Reject">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                                </button>
                                                            </form>
                                                        @endif
                                                        
                                                        <form action="{{ route('admin.payments.void', $payment->id) }}" method="POST" class="inline" onsubmit="return confirm('Void this payment?');">
                                                            @csrf @method('DELETE')
                                                            <button type="submit" class="text-gray-400 hover:text-gray-600" title="Void">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                            </button>
                                                        </form>
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">No payments recorded.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Payment Modal -->
    <div id="addPaymentModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="document.getElementById('addPaymentModal').classList.add('hidden')"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">Add New Payment</h3>
                            <div class="mt-4">
                                <form action="{{ route('admin.tenants.payments.store', $tenant->id) }}" method="POST" enctype="multipart/form-data">
                                    @csrf
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Payment Type</label>
                                            <select name="payment_type" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md" required>
                                                <option value="cash">Cash</option>
                                                <option value="debit">Debit Card</option>
                                                <option value="credit">Credit Card</option>
                                                <option value="tng">Touch 'n Go</option>
                                                <option value="grab">GrabPay</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Amount For</label>
                                            <select name="amount_type" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md" required>
                                                <option value="rent">Rent</option>
                                                <option value="maintenance">Maintenance</option>
                                                <option value="water">Water Bill</option>
                                                <option value="electricity">Electricity Bill</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Amount (RM)</label>
                                            <div class="mt-1 flex rounded-md shadow-sm">
                                                <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 sm:text-sm">
                                                    RM
                                                </span>
                                                <input type="number" name="amount" min="0.01" step="0.01" class="flex-1 min-w-0 block w-full px-3 py-2 rounded-none rounded-r-md focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm border-gray-300" placeholder="0.00" required>
                                            </div>
                                        </div>
                                    <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3">
                                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:col-start-2 sm:text-sm">
                                            Confirm Payment
                                        </button>
                                        <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:col-start-1 sm:text-sm" onclick="document.getElementById('addPaymentModal').classList.add('hidden')">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
