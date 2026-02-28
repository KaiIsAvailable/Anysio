<div id="paymentModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closePaymentModal()"></div>

        <div class="relative bg-white rounded-lg shadow-xl max-w-lg w-full p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Record Payment: <span id="modalInvoiceNo"></span></h3>
            
            <form id="paymentForm" method="POST">
                @csrf
                @method('PATCH')
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Amount Paid (RM)</label>
                        <input type="number" 
                            step="0.01" 
                            name="amount_paid" 
                            id="modalAmountPaid" 
                            required
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            placeholder="0.00">
                        <p class="text-xs text-gray-500 mt-1">Due Amount: RM <span id="modalAmountDue"></span></p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Payment Date</label>
                        <input type="date" name="payment_date" value="{{ date('Y-m-d') }}" required
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Received Via</label>
                        <select name="received_via" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            <option value="Maybank">Maybank</option>
                            <option value="Public Bank">Public Bank</option>
                            <option value="Cash">Cash</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Transaction Ref (Optional)</label>
                        <input type="text" name="transaction_ref" placeholder="e.g. MBB123456"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Remarks</label>
                        <textarea name="remarks" rows="2" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"></textarea>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closePaymentModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Confirm Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>