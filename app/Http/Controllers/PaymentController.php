<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payment\UploadPaymentReceiptRequest;
use Illuminate\Http\RedirectResponse;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\PaymentService;

class PaymentController extends Controller
{
    public function __construct(protected PaymentService $paymentService) 
    {}

    public function store(UploadPaymentReceiptRequest $request, Invoice $invoice)
    {
        $this->paymentService->submitReceipt($invoice, Auth::user(), $request->validated());
        return back()->with('success','Payment receipt submitted successfully. Waiting for verification.');
    }

    public function show(Payment $payment)
    {

    }

    public function approve(Payment $payment): RedirectResponse
    {
        $this->paymentService->approve($payment);

        return back()->with('success', 'Payment confirmed successfully.');
    }

    public function reject(Payment $payment): RedirectResponse
    {
        $this->paymentService->reject($payment);

        return back()->with('success', 'Payment rejected successfully.');
    }

    public function destroy(Payment $payment)
    {

    }

    public function viewReceipt(Payment $payment)
    {
        abort_unless($payment->receipt_path, 404);

        return response()->file(storage_path('app/private/' . $payment->receipt_path));
    }
}