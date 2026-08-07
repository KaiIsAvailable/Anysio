<?php

namespace App\Services;

use App\Models\{Invoice, Lease, FeeType, User, DocumentTemplate};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class InvoiceService
{
    public function __construct(
        private readonly PaymentProcessor $paymentProcessor,
        private readonly WalletService $walletService,
        private readonly DocumentSequenceService $documentSequenceService,
    ) {}

    /**
     * Owner manually creates an invoice containing multiple items after the lease exists.
     */
    public function createManualInvoice(
            Lease $lease,
            array $data
        ): Invoice {
            return DB::transaction(function () use ($lease, $data) {
                if (empty($data['items'])) {
                    throw new \InvalidArgumentException('Invoice must contain at least one item.');
                }

                // Use the currently authenticated user instead of the lease owner
                $currentUser = Auth::user(); 

                if (!$currentUser) {
                    throw new \RuntimeException('Authenticated user required to generate invoices.');
                }

                $template = DocumentTemplate::where('user_id', $currentUser->id)
                    ->where('category', 'invoice')
                    ->where('status', 'active')
                    ->first();

                // Pass the current user to sequence generation and item validation
                ['validated_items' => $items, 'total_cents' => $totalCents] = $this->processAndValidateItems($currentUser, $data['items']);

                $invoiceNo = $this->documentSequenceService->generateInvoiceNumber($currentUser);

                $invoice = Invoice::create([
                    'user_id'              => $currentUser->id,
                    'billable_type'        => User::class,
                    'billable_id'          => $currentUser->id,
                    'lease_id'             => $lease->id,
                    'document_template_id' => $template?->id, // Assign template if found
                    'invoice_no'           => $invoiceNo,
                    'type'                 => 'manual',
                    'period'               => Carbon::parse($data['period'])->startOfMonth()->toDateString(),
                    'due_date'             => $data['due_date'],
                    'total_amount'         => $totalCents,
                    'amount_paid'          => 0,
                    'amount_balance'       => $totalCents,
                    'status'               => 'unpaid',
                    'remarks'              => $data['remarks'] ?? null,
                ]);

                $this->saveInvoiceItems($invoice, $items);

                return $invoice->load('items.feeType');
            });
        }

    /**
     * Record a payment against an invoice.
     */
    public function recordPayment(Invoice $invoice, array $data): void
    {
        DB::transaction(function () use ($invoice, $data) {
            $this->paymentProcessor->process($invoice, $data);
        });
    }

    /**
     * Soft-cancel an invoice while preserving the audit trail.
     */
    public function voidInvoice(Invoice $invoice, string $reason): void
    {
        abort_if(
            !$invoice->isVoidable(),
            422,
            'Only unpaid or partial invoices can be voided.'
        );

        $remarks = $invoice->remarks ? $invoice->remarks . "\n" : '';
        $remarks .= '[VOIDED ' . now()->format('Y-m-d H:i') . '] ' . $reason;

        $invoice->update([
            'status'  => 'void',
            'remarks' => $remarks,
        ]);
    }

    /**
     * Mark all overdue invoices.
     */
    public function markOverdueInvoices(): int
    {
        return Invoice::query()
            ->whereIn('status', ['unpaid', 'partial'])
            ->where('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);
    }

    // =========================================================================
    // Private Helper Methods
    // =========================================================================
    /**
     * Validate raw charge/item arrays and map them into cents and active fee types.
     */
    private function processAndValidateItems(User $owner, array $rawItems): array
    {
        $totalCents = 0;
        $validatedItems = [];

        foreach ($rawItems as $item) {
            if (empty($item['fee_type_id']) || !isset($item['amount'])) {
                throw new \InvalidArgumentException('Each invoice item must contain fee_type_id and amount.');
            }

            $amountCents = (int) round(((float) $item['amount']) * 100);

            if ($amountCents <= 0) {
                throw new \InvalidArgumentException('Invoice item amount must be greater than zero.');
            }

            $feeType = FeeType::query()
                ->where('id', $item['fee_type_id'])
                ->where('user_id', $owner->id)
                ->where('is_active', true)
                ->firstOrFail();

            $validatedItems[] = [
                'fee_type'     => $feeType,
                'amount_cents' => $amountCents,
                'description'  => $item['description'] ?? $feeType->name,
            ];

            $totalCents += $amountCents;
        }

        if ($totalCents <= 0) {
            throw new \InvalidArgumentException('Invoice total must be greater than zero.');
        }

        return [
            'validated_items' => $validatedItems,
            'total_cents'     => $totalCents,
        ];
    }

    /**
     * 🌟 自動為新租約 (New/Renew) 產生第一期帳單，並自動關聯 Active 的 Invoice Template
     */
    /**
     * 🌟 自動為新租約 (New/Renew) 產生第一期帳單，並自動關聯 Active 的 Invoice Template
     */
    public function createInitialInvoiceForLease(Lease $lease, User $currentUser): ?Invoice
    {
        return DB::transaction(function () use ($lease, $currentUser) {
            // 1. 抓取這張租約建立好的所有 Charges
            $charges = $lease->charges()->with('feeType')->get();
            
            if ($charges->isEmpty()) {
                return null;
            }

            // 🌟 2. 找出真正的房东 ID
            $ownerId = $currentUser->id;
            if ($lease->leasable) {
                if ($lease->leasable instanceof \App\Models\Room) {
                    $ownerId = $lease->leasable->unit->owner_id ?? $currentUser->id;
                } else {
                    $ownerId = $lease->leasable->owner_id ?? $currentUser->id;
                }
            }

            // 🌟 3. 防弹版搜寻 (Bulletproof Query)
            // 第一顺位：尝试找专属房东、Agent 或系统(NULL)的 Active 模板
            $template = DocumentTemplate::where('category', 'invoice')
                ->where('status', 'active')
                ->where(function($query) use ($ownerId, $currentUser) {
                    $query->whereIn('user_id', [$ownerId, $currentUser->id])
                          ->orWhereNull('user_id'); 
                })
                ->first();

            // 🌟 终极兜底：如果上面的严格条件找不到（例如是 SuperAdmin 建的模板）
            // 既然系统现在保证了只有一份 Active 模板，我们就直接抓全系统唯一的那一份！
            if (!$template) {
                $template = DocumentTemplate::where('category', 'invoice')
                    ->where('status', 'active')
                    ->first();
                
                // (可选) 如果你想要在后台日志里看到是不是触发了兜底，可以解除这行注释
                // \Illuminate\Support\Facades\Log::info('Invoice Fallback Template Used: ' . $template?->id);
            }

            // 4. 整理明細與計算總額
            $totalCents = 0;
            $items = [];

            foreach ($charges as $charge) {
                $items[] = [
                    'fee_type'     => $charge->feeType,
                    'amount_cents' => $charge->amount,
                    'description'  => $charge->description,
                ];
                $totalCents += $charge->amount;
            }

            if ($totalCents <= 0) {
                return null;
            }

            // 5. 產生編號與日期
            $invoiceNo = $this->documentSequenceService->generateInvoiceNumber($currentUser);
            $dueDate = $lease->start_date ?? now()->toDateString();
            $periodDate = Carbon::parse($lease->start_date ?? now())->startOfMonth()->toDateString();

            // 6. 建立 Invoice，自動填入 document_template_id
            $invoice = Invoice::create([
                'user_id'              => $currentUser->id,
                'billable_type'        => User::class,
                'billable_id'          => $currentUser->id,
                'lease_id'             => $lease->id,
                'document_template_id' => $template?->id, // 🌟 這裡絕對能抓到正確的 ID 了！
                'invoice_no'           => $invoiceNo,
                'type'                 => 'rent',
                'period'               => $periodDate,
                'due_date'             => $dueDate,
                'total_amount'         => $totalCents,
                'amount_paid'          => 0,
                'amount_balance'       => $totalCents,
                'status'               => 'unpaid',
                'remarks'              => 'Initial Invoice for Lease (Includes Deposits & First Rent)',
            ]);

            // 7. 寫入明細
            $this->saveInvoiceItems($invoice, $items);

            return $invoice->load('items.feeType', 'documentTemplate');
        });
    }

    /**
     * Persist validated items to the invoice relationship.
     */
    private function saveInvoiceItems(Invoice $invoice, array $items): void
    {
        foreach ($items as $item) {
            /** @var FeeType $feeType */
            $feeType = $item['fee_type'];

            $invoice->items()->create([
                'fee_type_id' => $feeType->id,
                'description' => $item['description'],
                'amount'      => $item['amount_cents'],
            ]);
        }
    }
}