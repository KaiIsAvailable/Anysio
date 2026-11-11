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

    public function destroy(Payment $payment)
    {

    }

    public function viewReceipt(Payment $payment)
    {
        $invoice = $payment->invoice;
        $ownInvoiceProof = Auth::check() && $invoice && (
            $invoice->lease?->tenant?->user_id === Auth::id()
            || (!$invoice->lease_id && $invoice->user_id === Auth::id())
        );
        if (!$ownInvoiceProof) {
            abort_unless(\Illuminate\Support\Facades\Gate::allows('super-admin')
                || \Illuminate\Support\Facades\Gate::allows('invoice.view payment proof'), 403);
            abort_unless(\App\Models\Invoice::query()->visibleTo(Auth::id(), get_effective_user()?->id,
                \Illuminate\Support\Facades\Gate::allows('super-admin'))->whereKey($payment->invoice_id)->exists(), 403);
        }
        if (\Illuminate\Support\Facades\Gate::allows('owner-admin') || \Illuminate\Support\Facades\Gate::allows('is-staff')) {
            \Illuminate\Support\Facades\Gate::authorize('invoice.view payment proof');
        }

        abort_unless($payment->receipt_path, 404);

        $fullPath = storage_path('app/private/' . $payment->receipt_path);

        // Safeguard: Ensure the file physically exists on disk
        if (!file_exists($fullPath)) {
            abort(404, 'Receipt file not found on server.');
        }

        return response()->file($fullPath);
    }
}
