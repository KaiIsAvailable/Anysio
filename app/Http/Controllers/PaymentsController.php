<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Tenants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentsController extends Controller
{
    /**
     * Store a newly created payment (default status: paid).
     */
    public function store(Request $request, Tenants $tenant)
    {
        $request->validate([
            'payment_type' => 'required|in:cash,debit,credit,tng,grab',
            'amount_type' => 'required|in:maintenance,water,electricity,rent',
            'amount' => 'required|numeric|min:0.01',
            'receipt' => 'nullable|image|max:2048', // Optional receipt
        ]);

        // Convert RM to Cents (RM1 = 100 cents)
        $amountInCents = intval(round($request->amount * 100));

        $data = [
            'tenant_id' => $tenant->id,
            'payment_type' => $request->payment_type,
            'amount_type' => $request->amount_type,
            'amount_due' => $amountInCents, // Amount due is what they are paying
            'amount_paid' => $amountInCents, // Assuming full payment
            'status' => 'paid', // Default status as per requirement
            'approved_by' => null, // Pending approval
        ];

        if ($request->hasFile('receipt')) {
            // Receipt upload removed as per new requirement: "receipt should generate after payment"
            // We keep the column nullable or use it for something else if needed.
            // For now, we just don't populate receipt_path from file upload.
        }
        
        // Let's generate a receipt number or path based on ID?
        // Actually, we can generate it on the fly. We don't need to store a path if we generate it.
        // But the previous code used receipt_path to check if receipt exists.
        // We can just check if status is 'paid' or 'approved' to show receipt.
        
        $payment = Payment::create($data);

        return redirect()->back()->with('success', 'Payment recorded successfully.');
    }

    /**
     * Show Generated Receipt.
     */
    public function showReceipt(Payment $payment)
    {
        // Ensure user can view this receipt (Authorization check logic here if needed)
        // For now assuming admin access as this is adminSide.
        
        $payment->load('tenant.user'); // Load tenant details
        return view('adminSide.payments.receipt', compact('payment'));
    }

    /**
     * Update payment status (Approve/Reject).
     */
    public function updateStatus(Request $request, Payment $payment)
    {
        $request->validate([
            'status' => 'required|in:approve,reject',
        ]);

        $status = $request->status;
        $dbStatus = $status === 'approve' ? 'approve' : 'reject';
        // Wait, requirements say "Approve", "Reject", "Paid". 
        // Let's stick to lowercase: 'paid', 'approved', 'rejected', 'void' ?
        // User req: "Status - Paid, Approve, Reject"
        // Existing migration had default 'pending'.
        // I set default to 'paid' in migration.
        // Let's use 'paid', 'approved', 'rejected', 'void'.

        if ($status === 'approve') {
             $payment->update([
                 'status' => 'approved',
                 'approved_by' => Auth::id(),
             ]);
             $msg = 'Payment approved.';
        } else {
             $payment->update([
                 'status' => 'rejected',
                 'approved_by' => Auth::id(), // Rejected by (using same col)
             ]);
             $msg = 'Payment rejected.';
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Delete payment (Void).
     */
    public function destroy(Payment $payment)
    {
        // Change status to "Void" instead of hard delete
        $payment->update(['status' => 'void']);

        return redirect()->back()->with('success', 'Payment status updated to Void.');
    }
}
