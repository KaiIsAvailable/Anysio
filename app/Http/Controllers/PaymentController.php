<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payment\UploadPaymentReceiptRequest;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\PaymentService;

class PaymentController extends Controller
{
    public function __construct(protected PaymentService $paymentService)
    {
        //
    }

    public function store(UploadPaymentReceiptRequest $request, Invoice $invoice)
    {
        $this->paymentService->submitReceipt($invoice, Auth::user(), $request->validated());
        return back()->with('success','Payment receipt submitted successfully. Waiting for verification.');
    }

    public function show(Payment $payment)
    {

    }

    public function approve(Request $request, Payment $payment)
    {

    }

    public function reject(Request $request, Payment $payment)
    {

    }

    public function destroy(Payment $payment)
    {

    }
}