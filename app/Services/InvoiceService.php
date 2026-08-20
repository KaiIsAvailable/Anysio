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
    public function createManualInvoice(Lease $lease, array $data): Invoice 
    {
        return DB::transaction(function () use ($lease, $data) {
            if (empty($data['items'])) {
                throw new \InvalidArgumentException('Invoice must contain at least one item.');
            }

            // Use the currently authenticated user instead of the lease owner
            $currentUser = Auth::user(); 

            if (!$currentUser) {
                throw new \RuntimeException('Authenticated user required to generate invoices.');
            }

            // 🌟 修復點 1：將 created_by 改為 user_id，並加上系統預設範本的兜底邏輯
            $template = DocumentTemplate::where('category', 'invoice')
                ->where('status', 'active')
                ->where(function($query) use ($currentUser) {
                    $query->where('user_id', $currentUser->id)
                          ->orWhereNull('user_id'); 
                })
                ->first();

            if (!$template) {
                $template = DocumentTemplate::where('category', 'invoice')
                    ->where('status', 'active')
                    ->first();
            }

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
     * 自動為新租約 (New/Renew) 產生第一期帳單，並自動關聯 Active 的 Invoice Template
     */
    public function createInitialInvoiceForLease(Lease $lease, User $currentUser): ?Invoice
    {
        return DB::transaction(function () use ($lease, $currentUser) {
            $charges = $lease->charges()->with('feeType')->get();
            
            if ($charges->isEmpty()) {
                return null;
            }

            $ownerId = $currentUser->id;
            if ($lease->leasable) {
                if ($lease->leasable instanceof \App\Models\Room) {
                    $ownerId = $lease->leasable->unit->owner_id ?? $currentUser->id;
                } else {
                    $ownerId = $lease->leasable->owner_id ?? $currentUser->id;
                }
            }

            $template = DocumentTemplate::where('category', 'invoice')
                ->where('status', 'active')
                ->where(function($query) use ($ownerId, $currentUser) {
                    $query->whereIn('user_id', [$ownerId, $currentUser->id])
                          ->orWhereNull('user_id'); 
                })
                ->first();

            if (!$template) {
                $template = DocumentTemplate::where('category', 'invoice')
                    ->where('status', 'active')
                    ->first();
            }

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

            $invoiceNo = $this->documentSequenceService->generateInvoiceNumber($currentUser);
            $dueDate = $lease->start_date ?? now()->toDateString();
            $periodDate = Carbon::parse($lease->start_date ?? now())->startOfMonth()->toDateString();

            $invoice = Invoice::create([
                'user_id'              => $currentUser->id,
                'billable_type'        => User::class,
                'billable_id'          => $currentUser->id,
                'lease_id'             => $lease->id,
                'document_template_id' => $template?->id,
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

            $this->saveInvoiceItems($invoice, $items);

            return $invoice->load('items.feeType', 'documentTemplate');
        });
    }

    private function saveInvoiceItems(Invoice $invoice, array $items): void
    {
        foreach ($items as $item) {
            $feeType = $item['fee_type'];

            $invoice->items()->create([
                'fee_type_id' => $feeType->id,
                'description' => $item['description'],
                'amount'      => $item['amount_cents'],
            ]);
        }
    }

    // =========================================================================
    // 🌟 修復點 2：補回被遺漏的「通用相容版」變數打包機
    // =========================================================================
    // =========================================================================
    // 🌟 通用相容版預覽/PDF 渲染專用的「變數打包機」 
    // =========================================================================
    // =========================================================================
    // 🌟 通用相容版預覽/PDF 渲染專用的「變數打包機」 
    // =========================================================================
    public function getInvoiceVariables(Invoice $invoice): array
    {
        // 1. 安全載入關聯
        $invoice->loadMissing([
            'user',
            'billable',
            'lease.tenant.user',
            'items',
            'lease.leasable' => function ($morphTo) {
                $morphTo->morphWith([
                    \App\Models\Room::class => ['unit.owner', 'owner'],
                    \App\Models\Unit::class => ['owner'],
                    \App\Models\Property::class => ['owner'],
                ]);
            }
        ]);

        $getPhone = fn($m) => $m?->phone ?? $m?->phone_number ?? $m?->contact_no ?? 'N/A';
        $getEmail = fn($m) => $m?->email ?? 'N/A';
        $getCompanyNo = fn($m) => $m?->company_no ?? $m?->ssm_no ?? 'N/A';

        $invoiceDate = $invoice->created_at ? $invoice->created_at->format('Y-m-d') : 'N/A';
        $dueDate     = $invoice->due_date ? Carbon::parse($invoice->due_date)->format('Y-m-d') : 'N/A';

        $isLeaseInvoice = !empty($invoice->lease_id);

        // 2. 處理 Billed To (付款人/租客/客戶)
        // 🌟 修正點 1：如果是租約發票，絕對優先抓取 Lease 裡面的真實 Tenant！
        $billedUser = null;
        if ($isLeaseInvoice && $invoice->lease?->tenant?->user) {
            $billedUser = $invoice->lease->tenant->user;
        } elseif ($invoice->billable instanceof \App\Models\User) {
            $billedUser = $invoice->billable;
        } else {
            $billedUser = $invoice->user;
        }

        $tenantName  = $billedUser?->name ?? 'N/A';
        $tenantPhone = $getPhone($billedUser);
        if ($tenantPhone === 'N/A' && $isLeaseInvoice) {
            $tenantPhone = $getPhone($invoice->lease?->tenant);
        }
        $tenantEmail = $getEmail($billedUser);
        if ($tenantEmail === 'N/A' && $isLeaseInvoice) {
            $tenantEmail = $getEmail($invoice->lease?->tenant);
        }

        // 3. 處理 Pay To (收款方/房東/平台)
        if ($isLeaseInvoice) {
            $leasable = $invoice->lease?->leasable;
            $ownerModel = null;
            if ($leasable instanceof \App\Models\Room) {
                $ownerModel = $leasable->unit?->owner;
            } elseif ($leasable) {
                $ownerModel = $leasable->owner;
            }
            
            // 🌟 修正點 2：拔除 $invoice->user 頂替房東的退路！
            $payeeUser = $ownerModel?->user ?? $ownerModel;

            if ($payeeUser) {
                $ownerName  = $payeeUser->name ?? 'N/A';
                $ownerPhone = $getPhone($payeeUser);
                $ownerEmail = $getEmail($payeeUser);
                $companyNo  = $getCompanyNo($payeeUser);
            } else {
                // 如果找不到 Owner (No Owner 狀態)，就顯示空值，絕不讓 Agent 頂替
                $ownerName  = 'N/A (No Owner)';
                $ownerPhone = 'N/A';
                $ownerEmail = 'N/A';
                $companyNo  = 'N/A';
            }
        } else {
            // 平台發票 (買 Package 等無租約的情境)
            $ownerName  = 'Anysio Technologies';
            $ownerPhone = '01110880912';         
            $ownerEmail = 'kaifengchoong@gmai.com';    
            $companyNo  = '202603205756';           
        }

        // 4. 處理 Property 詳情
        $propertyDetails = 'N/A';
        if ($isLeaseInvoice && $invoice->lease?->leasable) {
            $leasable = $invoice->lease->leasable;
            if ($leasable instanceof \App\Models\Property) {
                $propertyDetails = $leasable->name;
            } elseif ($leasable instanceof \App\Models\Unit) {
                $propertyDetails = $leasable->unit_no;
            } elseif ($leasable instanceof \App\Models\Room) {
                $propertyDetails = "Room {$leasable->room_no}" . ($leasable->unit ? " ({$leasable->unit->unit_no})" : "");
            }
        }

        return [
            'invoice_no'      => $invoice->invoice_no,
            'invoice_type'    => ucfirst($invoice->type), 
            'billing_period'  => $invoice->period ? Carbon::parse($invoice->period)->format('F Y') : 'N/A',
            'invoice_date'    => $invoiceDate,
            'invoice_duedate' => $dueDate,
            'invoice_status'  => strtoupper($invoice->status),
            'remarks'         => $invoice->remarks ?? '—',
            'total_amount'    => number_format($invoice->total_amount / 100, 2),
            'amount_paid'     => number_format($invoice->amount_paid / 100, 2),
            'amount_balance'  => number_format($invoice->amount_balance / 100, 2),

            'tenant_name'           => $tenantName,
            'user_name'             => $tenantName,
            'tenant_phone'          => $tenantPhone,
            'user_phone'            => $tenantPhone,
            'tenant_email'          => $tenantEmail,
            'user_email'            => $tenantEmail,
            'property_unit_details' => $propertyDetails,
            'package_name'          => $invoice->context ?? $propertyDetails ?? 'N/A',

            'owner_name'              => $ownerName,
            'owner_phone'             => $ownerPhone,
            'company_phone'           => $ownerPhone,
            'owner_email'             => $ownerEmail,
            'company_email'           => $ownerEmail,
            'company_registration_no' => $companyNo, 
        ];
    }
}