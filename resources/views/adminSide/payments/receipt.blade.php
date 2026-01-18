<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #{{ substr($payment->id, -8) }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none; }
            body { background: white; }
            .shadow-lg { box-shadow: none; }
            .border { border: 1px solid #ddd; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center py-10">

    <div class="bg-white p-8 rounded-lg shadow-lg max-w-2xl w-full mx-4 relative overflow-hidden">
        
        <!-- Header -->
        <div class="flex justify-between items-start border-b pb-6 mb-6">
            <div>
                <h1 class="text-3xl font-bold text-indigo-700">Anysio</h1>
                <p class="text-xs text-gray-500 mt-1">Property Management System</p>
                <!-- Add company address if available -->
            </div>
            <div class="text-right">
                <h2 class="text-xl font-semibold text-gray-800">RECEIPT</h2>
                <p class="text-sm text-gray-500 mt-1">#{{ substr($payment->id, -8) }}</p>
                <div class="mt-2 text-xs text-gray-400">
                    <p>Date: {{ $payment->created_at->format('d M Y') }}</p>
                    <p>Time: {{ $payment->created_at->format('H:i') }}</p>
                </div>
            </div>
        </div>

        <!-- Tenant Info -->
        <div class="mb-8">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Received From</h3>
            <div class="bg-gray-50 p-4 rounded-md border border-gray-100">
                <p class="text-lg font-bold text-gray-800">{{ $payment->tenant->user->name ?? 'Tenant' }}</p>
                <p class="text-sm text-gray-600 truncate">{{ $payment->tenant->user->email ?? '' }}</p>
                <p class="text-sm text-gray-600">{{ $payment->tenant->phone ?? '' }}</p>
            </div>
        </div>

        <!-- Payment Details -->
        <div class="mb-8">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Payment Details</h3>
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="py-2 text-sm font-semibold text-gray-600">Description</th>
                        <th class="py-2 text-sm font-semibold text-gray-600 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="py-4 text-gray-800">
                            <p class="font-medium text-lg capitalize">{{ $payment->amount_type }} Payment</p>
                            <p class="text-sm text-gray-500 capitalize">Method: {{ $payment->payment_type }}</p>
                        </td>
                        <td class="py-4 text-right align-top">
                            <span class="text-xl font-bold text-gray-900">RM {{ number_format($payment->amount_paid / 100, 2) }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Total -->
        <div class="flex justify-end border-t border-gray-200 pt-6">
            <div class="text-right">
                <p class="text-sm text-gray-500">Total Paid</p>
                <p class="text-3xl font-bold text-indigo-600">RM {{ number_format($payment->amount_paid / 100, 2) }}</p>
            </div>
        </div>
        
        <!-- Status Stamp -->
        @if($payment->status === 'void')
            <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 text-9xl font-black text-red-200 opacity-50 -rotate-45 pointer-events-none select-none border-4 border-red-200 p-4 rounded-xl uppercase tracking-widest">
                VOID
            </div>
        @elseif($payment->status === 'rejected')
             <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 text-8xl font-black text-red-200 opacity-50 -rotate-45 pointer-events-none select-none border-4 border-red-200 p-4 rounded-xl uppercase tracking-widest">
                REJECTED
            </div>
        @else
             <!-- Paid/Approved Stamp -->
             <div class="absolute bottom-10 left-10 text-6xl font-black text-green-100 opacity-80 -rotate-12 pointer-events-none select-none border-4 border-green-100 p-2 rounded uppercase tracking-widest">
                PAID
            </div>
        @endif

        <!-- Footer -->
        <div class="mt-10 pt-6 border-t border-gray-100 text-center text-xs text-gray-400 no-print">
            <p>This is a computer-generated receipt. No signature is required.</p>
        </div>
        
        <!-- Actions -->
        <div class="mt-6 text-center no-print">
            <button onclick="window.print()" class="bg-indigo-600 text-white px-6 py-2 rounded shadow hover:bg-indigo-700 transition">Print / Download PDF</button>
            <button onclick="window.close()" class="ml-2 text-gray-500 hover:text-gray-700 underline">Close</button>
        </div>

    </div>

</body>
</html>
