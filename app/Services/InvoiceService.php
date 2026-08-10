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

                $template = DocumentTemplate::where('created_by', $currentUser->id)
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

    // =========================================================================
    // 🌟 預覽/PDF 渲染專用的「變數打包機」(暴力穿透全欄位版)
    // =========================================================================
    public function getInvoiceVariables(Invoice $invoice): array
    {
        // 確保載入更深層的關聯，包含 tenant 的 user 以及 owner 的 user
        $invoice->loadMissing([
            'user', 
            'lease.tenant.user', 
            'lease.leasable'
        ]);

        // 全欄位電話號碼安全檢測器
        $getPhone = function ($model) {
            if (!$model) return null;
            return $model->phone 
                ?? $model->phone_number 
                ?? $model->contact_no 
                ?? $model->contact_number 
                ?? $model->mobile 
                ?? null;
        };

        // 安全 Email 檢測器
        $getEmail = function ($model) {
            if (!$model) return null;
            return $model->email ?? null;
        };

        // 全欄位公司註冊號 / 名稱檢測器
        $getCompanyNo = function ($model) {
            if (!$model) return null;
            return $model->company_no 
                ?? $model->company_registration_no 
                ?? $model->company_reg_no 
                ?? $model->company_number 
                ?? $model->ssm_no
                ?? null;
        };

        // 1. 處理 Due Date 格式 (改為 Y-m-d)
        $dueDate = $invoice->due_date ? Carbon::parse($invoice->due_date)->format('Y-m-d') : 'N/A';

        // 2. 處理 Property 詳情 (針對不同類型獲取對應的號碼)
        $propertyDetails = 'N/A';
        $leasable = $invoice->lease?->leasable;
        if ($leasable) {
            if ($leasable instanceof \App\Models\Property) {
                $propertyDetails = $leasable->name;
            } elseif ($leasable instanceof \App\Models\Unit) {
                $propertyDetails = $leasable->unit_no;
            } elseif ($leasable instanceof \App\Models\Room) {
                // 如果是房間，顯示 Room No (Unit No)
                $propertyDetails = $leasable->room_no;
                if ($leasable->unit) {
                    $propertyDetails .= ' (' . $leasable->unit->unit_no . ')';
                }
            }
        }

        // 3. 處理 Billed To (租客資訊) - 暴力穿透
        $tenantModel = $invoice->lease?->tenant;
        $tenantUser = $tenantModel?->user;

        $tenantName  = $tenantUser?->name ?? $tenantModel?->name ?? 'N/A';
        $tenantPhone = $getPhone($tenantUser) ?? $getPhone($tenantModel) ?? 'N/A';
        $tenantEmail = $getEmail($tenantUser) ?? $getEmail($tenantModel) ?? 'N/A';

        // 4. 處理 Pay To (真正的 Owner 資訊) - 暴力穿透
        $ownerModel = null;
        if ($leasable) {
            if ($leasable instanceof \App\Models\Room) {
                $ownerModel = $leasable->unit?->owner; // Room的Owner在Unit上
            } else {
                $ownerModel = $leasable->owner; // Unit或Property直接有Owner
            }
        }

        // 找到真正綁定的 User 帳號
        $actualOwnerUser = $ownerModel?->owner ?? $ownerModel?->user;

        // 如果真的連 Owner/User 都找不到，最後才用發票建立者 (Invoice User) 兜底
        $fallbackUser = $actualOwnerUser ?? $ownerModel ?? $invoice->user;

        $ownerName  = $actualOwnerUser?->name ?? $ownerModel?->name ?? $fallbackUser?->name ?? 'N/A';
        $ownerPhone = $getPhone($actualOwnerUser) ?? $getPhone($ownerModel) ?? $getPhone($fallbackUser) ?? 'N/A';
        $ownerEmail = $getEmail($actualOwnerUser) ?? $getEmail($ownerModel) ?? $getEmail($fallbackUser) ?? 'N/A';
        $companyNo  = $getCompanyNo($actualOwnerUser) ?? $getCompanyNo($ownerModel) ?? $getCompanyNo($fallbackUser) ?? 'N/A';

        return [
            // --- 發票基本資訊 ---
            'invoice_no'      => $invoice->invoice_no,
            'invoice_type'    => ucfirst($invoice->type), 
            'billing_period'  => Carbon::parse($invoice->period)->format('F Y'),
            'invoice_date'    => $invoice->created_at ? $invoice->created_at->format('Y-m-d') : 'N/A',
            'invoice_duedate' => $dueDate,
            'invoice_status'  => strtoupper($invoice->status),
            'remarks'         => $invoice->remarks ?? '—',

            // --- 金額資訊 ---
            'total_amount'    => number_format($invoice->total_amount / 100, 2),
            'amount_paid'     => number_format($invoice->amount_paid / 100, 2),
            'amount_balance'  => number_format($invoice->amount_balance / 100, 2),

            // --- 租客與物業資訊 (Billed To) ---
            'tenant_name'           => $tenantName,
            'tenant_phone'          => $tenantPhone,
            'tenant_email'          => $tenantEmail,
            'property_unit_details' => $propertyDetails,

            // --- 房東/收款方資訊 (Pay To) ---
            'owner_name'              => $ownerName,
            'owner_phone'             => $ownerPhone,
            'owner_email'             => $ownerEmail,
            'company_registration_no' => $companyNo, 
        ];
    }
}