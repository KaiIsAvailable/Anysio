<?php

namespace App\Services;

use App\Models\{Invoice, Lease, FeeType, User, DocumentTemplate, Tenants, Wallet, LeaseCharge, Property, Unit, Room};
use Carbon\Carbon;
use Illuminate\Support\Facades\{DB, Log, Auth};
use Illuminate\Support\HtmlString;

class InvoiceService
{
    public function __construct(
        private readonly PaymentProcessor $paymentProcessor,
        private readonly WalletService $walletService,
        private readonly DocumentSequenceService $documentSequenceService,
        private readonly SettingService $settingService,
    ) {}

    public static function statementFilterRules(): array
    {
        return [
            'statement_from' => ['nullable', 'date_format:Y-m-d'],
            'statement_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:statement_from'],
            'statement_kind' => ['nullable', 'in:invoice,payment,wallet_credit,wallet_payment,wallet_other'],
            'statement_invoice' => ['nullable', 'string', 'max:100'],
            'statement_page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function getIndexInvoices(?string $actualId, ?string $effectiveId, bool $superAdmin, int $page = 1)
    {
        $invoices = Invoice::indexPage($actualId, $effectiveId, $superAdmin, $page);
        $invoices->setCollection($invoices->getCollection()->map(fn ($invoice) => (object) $this->transformInvoice($invoice)));
        return $invoices;
    }

    public function getStatement(?string $actualId, ?string $effectiveId, bool $superAdmin, array $filters = [], ?string $leaseId = null)
    {
        $page = Invoice::statementPage($actualId, $effectiveId, $superAdmin, $filters, $leaseId);
        [$invoices, $walletUsers] = Invoice::statementDetails(
            $page->getCollection()->pluck('invoice_id')->filter()->unique()->all(),
            $page->getCollection()->pluck('wallet_user_id')->filter()->unique()->all(),
        );
        $page->setCollection($page->getCollection()->map(function ($event) use ($invoices, $walletUsers) {
            $invoice = $invoices->get($event->invoice_id);
            $walletEvent = str_starts_with($event->event_kind, 'wallet_');
            $person = $walletEvent ? $walletUsers->get($event->wallet_user_id) : ($invoice?->lease?->tenant?->user ?? $invoice?->user);
            $asset = $invoice?->lease?->leasable;
            $payee = $asset instanceof Room ? $asset->unit?->owner : $asset?->owner;
            $labels = ['invoice' => 'Invoice issued', 'payment' => 'Invoice payment applied', 'wallet_credit' => 'Wallet credit', 'wallet_payment' => 'Wallet debit', 'wallet_other' => 'Wallet entry'];
            $displayAmount = $event->amount === null ? null : (int) $event->amount;
            // Only normal invoice amounts reverse direction for this presentation.
            if ($event->event_kind === 'invoice' && $displayAmount > 0) $displayAmount = -$displayAmount;
            return [
                'id' => $event->event_kind.':'.$event->event_id,
                'kind' => $event->event_kind,
                'label' => $labels[$event->event_kind],
                'date' => $event->occurred_at ? Carbon::parse($event->occurred_at)->format($event->date_only ? 'd/m/Y' : 'd/m/Y H:i') : 'Not recorded',
                'recorded_at' => $event->recorded_at ? Carbon::parse($event->recorded_at)->format('d/m/Y H:i') : null,
                'date_only' => (bool) $event->date_only,
                'party_label' => $walletEvent ? 'Wallet holder' : 'Billed to',
                'party' => $person?->name ?? 'Not recorded',
                'payee' => $invoice?->lease ? ($payee?->name ?? 'Not recorded') : null,
                'issuer' => $invoice?->documentTemplate?->user?->name,
                'invoice_no' => $event->invoice_no,
                'receipt_no' => $event->receipt_no,
                'reference' => $event->reference,
                'source_type' => $walletEvent
                    ? ucfirst(str_replace('_', ' ', $event->source_type ?? ''))
                    : 'Current invoice status: '.($event->source_type ?? 'Not recorded'),
                'payment_method' => $event->payment_method,
                'amount_cents' => $event->amount === null ? null : (int) $event->amount,
                'amount' => $displayAmount === null ? 'Not recorded' : ($displayAmount > 0 ? '+' : '').number_format($displayAmount / 100, 2),
                'amount_class' => $displayAmount > 0 ? 'text-green-600' : ($displayAmount < 0 ? 'text-red-600' : 'text-slate-600'),
                'description' => filled($event->description) ? $event->description : null,
                // Fee lines are invoice context, not a guessed allocation of partial payments.
                'fees' => $invoice?->items->map(fn ($item) => [
                    'name' => $item->feeType?->name ?? 'Not recorded',
                    'description' => filled($item->description) && trim($item->description) !== trim($item->feeType?->name ?? '') ? $item->description : null,
                ])->all() ?? [],
            ];
        }));
        return $page->appends(array_filter($filters, fn ($v, $k) => $k !== 'statement_page' && $v !== null && $v !== '', ARRAY_FILTER_USE_BOTH));
    }

    /**
     * Owner manually creates an invoice containing multiple items after the lease exists.
     */
    public function createManualInvoice(Lease $lease, array $data): Invoice 
    {
        return DB::transaction(function () use ($lease, $data) {
            if (empty($data['items'])) {
                throw new \InvalidArgumentException('Invoice must contain at least one item.');
            }

            $currentUser = get_effective_user(); 
            if (!$currentUser) {
                throw new \RuntimeException('Authenticated user required to generate invoices.');
            }

            $template = DocumentTemplate::where('category', 'invoice')
                ->where('status', 'active')
                ->where(function($query) use ($currentUser) {
                    $query->where('user_id', $currentUser->id)
                        ->orWhereNull('user_id'); 
                })
                ->first() ?? DocumentTemplate::where('category', 'invoice')->where('status', 'active')->first();

            ['validated_items' => $items, 'total_cents' => $totalCents] = $this->processAndValidateItems($currentUser, $data['items']);

            $invoiceNo = $this->documentSequenceService->generateInvoiceNumber($currentUser);

            $invoice = Invoice::create([
                'parent_id'            => $data['parent_id'] ?? null,  
                'billable_type'        => Tenants::class,  
                'billable_id'          => $lease->tenant_id,
                'lease_id'             => $lease->id,
                'document_template_id' => $template?->id,
                'invoice_no'           => $invoiceNo,
                'type'                 => $data['type'] ?? 'manual', // Allow dynamic type
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

    public function generateRecurringInvoices(Lease $lease): int
    {
        $currentUser = get_effective_user();
        if (!$currentUser) {
            throw new \RuntimeException('Authenticated user required to generate recurring invoices.');
        }

        if (!$lease->is_current) {
            Log::channel('testing')->warning('Lease skipped: Not current.');
            return 0;
        }

        
        $settings = $this->settingService->getEffectiveSettings();
        $dueDateDays = (int) data_get($settings, 'due_date_config.value.days', 7);

        // 1. Check if there is a voided invoice gap that needs to be re-generated first
        $voidedInvoice = $lease->invoices()
            ->where('status', 'void')
            ->orderBy('period', 'asc')
            ->first();

        $voidedInvoice = $lease->invoices()
            ->where('status', 'void')
            ->whereNotIn('period', function ($query) use ($lease) {
                // Exclude periods that already have a valid (non-void) replacement invoice
                $query->select('period')
                    ->from('invoices')
                    ->where('lease_id', $lease->id)
                    ->where('status', '!=', 'void');
            })
            ->orderBy('period', 'asc')
            ->first();

        if ($voidedInvoice) {
            Log::channel('testing')->info('Unresolved voided invoice gap detected for lease:', [
                'lease_id' => $lease->id,
                'voided_invoice_id' => $voidedInvoice->id,
                'target_period' => $voidedInvoice->period
            ]);

            $billingDate = Carbon::parse($voidedInvoice->period);
            $period = $billingDate->startOfMonth()->toDateString();
            $dueDate = $billingDate->copy()->addDays($dueDateDays)->toDateString();

            // Trace back from this specific voided invoice's items to the lease charges
            $feeTypeIds = $voidedInvoice->items()->pluck('fee_type_id')->toArray();

            $dueCharges = $lease->charges()
                ->where('charge_type', 'recurring')
                ->when(!empty($feeTypeIds), function ($query) use ($feeTypeIds) {
                    $query->whereIn('fee_type_id', $feeTypeIds);
                })
                ->get();

            Log::channel('testing')->info('Mapped voided invoice items to lease charges:', [
                'fee_type_ids' => $feeTypeIds,
                'found_charges_count' => $dueCharges->count()
            ]);

            $isFillingGap = true;
        } else {
            // 2. Normal flow: Determine the next sequential period based on the latest valid invoice or lease start date
            $latestInvoice = $lease->invoices()
                ->where('status', '!=', 'void')
                ->latest('period')
                ->first();

            if ($latestInvoice && $latestInvoice->period) {
                $billingDate = Carbon::parse($latestInvoice->period)->addMonth();
            } else {
                // Fallback to lease start date if no invoice has been generated yet
                $billingDate = Carbon::parse($lease->start_date);
            }

            $period = $billingDate->startOfMonth()->toDateString();
            $dueDate = $billingDate->copy()->addDays($dueDateDays)->toDateString();

            // Stop generating if the next period exceeds the lease end date
            if ($lease->end_date && Carbon::parse($period)->greaterThan(Carbon::parse($lease->end_date))) {
                Log::channel('testing')->info('Lease billing reached end date. Stopping generation.', [
                    'lease_id' => $lease->id,
                    'end_date' => $lease->end_date,
                    'attempted_period' => $period
                ]);
                return 0;
            }

            // Fetch active recurring charges for this generation cycle
            $dueCharges = $lease->charges()
                ->where('is_active', true)
                ->where('charge_type', 'recurring')
                ->get();

            if ($dueCharges->isEmpty()) {
                return 0;
            }

            $isFillingGap = false;
        }

        if ($dueCharges->isEmpty()) {
            return 0;
        }

        Log::channel('testing')->info('Proceeding to generate invoice:', [
            'period' => $period,
            'due_date' => $dueDate,
            'is_filling_gap' => $isFillingGap,
            'charges_count' => $dueCharges->count()
        ]);

        $generatedCount = 0;

        DB::transaction(function () use ($lease, $dueCharges, $period, $dueDate, $billingDate, $isFillingGap, &$generatedCount) {
            $items = $dueCharges->map(function ($charge) {
                return [
                    'fee_type_id' => $charge->fee_type_id,
                    'description' => $charge->description,
                    'amount'      => $charge->amount / 100, 
                ];
            })->toArray();

            // Create the invoice
            $this->createManualInvoice($lease, [
                'items'    => $items,
                'period'   => $period,
                'due_date' => $dueDate,
                'remarks'  => 'Auto-generated recurring charges for ' . $billingDate->format('F Y'),
            ]);

            // Only update next billing dates if we are moving forward (not filling a past voided gap)
            if (!$isFillingGap) {
                foreach ($dueCharges as $charge) {
                    $nextDate = match ($charge->frequency) {
                        'daily'   => Carbon::parse($charge->next_billing_date)->addDay(),
                        'weekly'  => Carbon::parse($charge->next_billing_date)->addWeek(),
                        'monthly' => Carbon::parse($charge->next_billing_date)->addMonth(),
                        'yearly'  => Carbon::parse($charge->next_billing_date)->addYear(),
                        default   => Carbon::parse($charge->next_billing_date)->addMonth(),
                    };

                    $nextBillingDate = $nextDate;
                    $isActive = true;

                    if ($lease->end_date && $nextDate->greaterThan(Carbon::parse($lease->end_date))) {
                        $nextBillingDate = null;
                        $isActive = false; 
                    }

                    LeaseCharge::where('id', $charge->id)->update([
                        'next_billing_date' => $nextBillingDate,
                        'is_active'         => $isActive,
                    ]);
                }
            }

            $generatedCount++;
        });

        return $generatedCount;
    }

    /**
     * Record a payment against an invoice.
     */
    public function recordPayment(Invoice $invoice, array $data): void
    {
        DB::transaction(function () use ($invoice, $data) {
            $this->paymentProcessor->process($invoice, $data, $this);
        });
    }

    /**
     * Soft-cancel an invoice while preserving the audit trail.
     */
    public function voidInvoice(Invoice $invoice, string $reason): void
    {
        $allowedStatuses = ['unpaid', 'partial', 'paid'];
        
        abort_if(
            !in_array(strtolower($invoice->status), $allowedStatuses),
            422,
            'This invoice cannot be voided.'
        );

        DB::transaction(function () use ($invoice, $reason) {
            // 1. If the invoice was paid or partial, refund the actual amount paid into the user's wallet
            $amountPaid = $invoice->amount_paid ?? 0;
            $amountInCents = (int) round($amountPaid); // Ensure you are working with cents consistently

            if (in_array(strtolower($invoice->status), ['paid', 'partial']) && $amountInCents > 0) {
                // Get the user ID associated with the tenant/invoice
                $userId = $invoice->lease?->tenant?->user_id;

                if ($userId) {
                    // Find or create the user's wallet
                    $wallet = Wallet::firstOrCreate(
                        ['user_id' => $userId],
                        ['balance' => 0]
                    );

                    // Increment wallet balance by the exact amount paid
                    $wallet->increment('balance', $amountInCents);

                    // Log the transaction
                    $wallet->transactions()->create([
                        'amount'       => $amountInCents, // Positive for credit
                        'type'         => 'refund',
                        'reference_id' => $invoice->id,
                        'remarks'      => "Refund from voided invoice #{$invoice->invoice_no}: {$reason}",
                    ]);
                }
            }

            // 2. Void the invoice
            $remarks = $invoice->remarks ? $invoice->remarks . "\n" : '';
            $remarks .= '[VOIDED ' . now()->format('Y-m-d H:i') . '] ' . $reason;

            $invoice->update([
                'status'  => 'void',
                'remarks' => $remarks,
            ]);
        });
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
                ->where(function ($query) use ($owner) {
                    $query->where('user_id', $owner->id)
                        ->orWhere('is_system', true); // Allow system-wide fee types if applicable
                })
                ->where('is_active', true)
                ->firstOrFail();

            $validatedItems[] = [
                'fee_type'     => $feeType,
                'amount_cents' => $amountCents,
                'description'  => filled($item['description'] ?? null) ? $item['description'] : '',
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
    public function createInitialInvoiceForLease(Lease $lease, User $currentUser): \Illuminate\Support\Collection
    {
        return DB::transaction(function () use ($lease, $currentUser) {
            $charges = $lease->charges()->with('feeType')->get();
            
            if ($charges->isEmpty()) {
                return collect();
            }

            $ownerId = $currentUser->id;
            if ($lease->leasable) {
                if ($lease->leasable instanceof Room) {
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
                ->first() ?? DocumentTemplate::where('category', 'invoice')
                    ->where('status', 'active')
                    ->first();

            $settings = $this->settingService->getEffectiveSettings();
            $dueDateDays = (int) data_get($settings, 'due_date_config.value.days', 7);

            $invoices = collect();

            // Safely parse start_date into a Carbon instance
            $startDate = Carbon::parse($lease->start_date);

            $dueDate = $startDate->copy()->addDays($dueDateDays)->toDateString();
            $periodDate = $startDate->copy()->startOfMonth()->toDateString();

            // Log the due date to the 'testing' channel
            Log::channel('testing')->info('Calculated lease due date', [
                'lease_id' => $lease->id ?? null,
                'start_date' => $startDate->toDateString(),
                'due_date_days' => $dueDateDays,
                'calculated_due_date' => $dueDate,
            ]);

            foreach ($charges as $charge) {
                $amountCents = $charge->amount;

                if ($amountCents <= 0) {
                    continue;
                }

                // Generate a unique invoice number for each individual invoice item
                $invoiceNo = $this->documentSequenceService->generateInvoiceNumber($currentUser);

                $invoice = Invoice::create([
                    'billable_type'        => User::class,
                    'billable_id'          => $currentUser->id,
                    'lease_id'             => $lease->id,
                    'document_template_id' => $template?->id,
                    'invoice_no'           => $invoiceNo,
                    'type'                 => 'rent',
                    'period'               => $periodDate,
                    'due_date'             => $dueDate,
                    'total_amount'         => $amountCents,
                    'amount_paid'          => 0,
                    'amount_balance'       => $amountCents,
                    'status'               => 'unpaid',
                    'remarks'              => $charge->description ? "Invoice for: {$charge->description}" : 'Initial Lease Charge Invoice',
                ]);

                // Save the single charge item to this specific invoice
                $this->saveInvoiceItems($invoice, [
                    [
                        'fee_type'     => $charge->feeType,
                        'amount_cents' => $amountCents,
                        'description'  => $charge->description,
                        'lease_charge_id' => $charge->id,
                    ]
                ]);

                $invoices->push($invoice->load('items.feeType', 'documentTemplate'));
            }

            return $invoices;
        });
    }

    private function saveInvoiceItems(Invoice $invoice, array $items): void
    {
        foreach ($items as $item) {
            $feeType = $item['fee_type'];

            $invoice->items()->create([
                'fee_type_id' => $feeType->id,
                'lease_charge_id' => $item['lease_charge_id'] ?? null,
                'description' => filled($item['description'] ?? null) ? $item['description'] : '',
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
                    Room::class => ['unit.owner', 'owner'],
                    Unit::class => ['owner'],
                    Property::class => ['owner'],
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
        } elseif ($invoice->billable instanceof User) {
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

    public function transformInvoice($invoice)
    {
        $rawPeriod = $invoice->period_display ?? $invoice->period;

        $formattedPeriod = '—';
        if ($rawPeriod) {
            try {
                $formattedPeriod = \Carbon\Carbon::parse($rawPeriod)->format('m/Y');
            } catch (\Exception $e) {
                $formattedPeriod = $rawPeriod;
            }
        }

        $invoiceItems = $invoice->items->map(function ($subItem) {
            return [
                'name' => $subItem->feeType?->name ?? 'Item',
                'description' => $subItem->description,
                'amount' => number_format(($subItem->amount ?? 0) / 100, 2),
                'category' => $subItem->feeType?->category ?? '—',
            ];
        });

        if ($invoiceItems->isEmpty() && $invoice->description) {
            $invoiceItems->push([
                'description' => $invoice->feeType?->name ?? 'Invoice Charge',
                'amount' => number_format(($invoice->total_amount ?? 0) / 100, 2),
                'category' => '—',
            ]);
        }

        $latestPayment = $invoice->payments?->first();

        $receipts = $invoice->transactions ? $invoice->transactions->map(function ($transaction) {
            return [
                'id' => $transaction->id,
                'receipt_no' => $transaction->receipt_no ?? 'Receipt-' . $transaction->id,
                'amount' => $transaction->amount_paid,
                'created_at' => $transaction->payment_date ?? $transaction->created_at,
                'variables' => array_merge(
                    method_exists($transaction, 'variables') ? ($transaction->variables ?? []) : [],
                    [
                        'issued_by_name'  => $transaction->approver?->name ?? 'N/A',
                        'issued_by_phone' => $transaction->approver?->phone ?? $transaction->approver?->phone_number ?? 'N/A',
                        'issued_by_email' => $transaction->approver?->email ?? 'N/A',
                    ]
                ),
                'documentTemplate' => $transaction->documentTemplate ? [
                    'title' => $transaction->documentTemplate->title,
                    'html_template' => $transaction->documentTemplate->html_template,
                    'html_content' => $transaction->documentTemplate->html_content,
                ] : null,
            ];
        })->toArray() : [];

        $tenantUserId = $invoice->isTenantInvoice() ? $invoice->lease?->tenant?->user_id : null;
        $walletBalanceCents = $tenantUserId ? $this->walletService->getBalance((string) $tenantUserId) : 0;
        $walletBalanceFormatted = number_format($walletBalanceCents / 100, 2, '.', '');

        return [
            'id' => $invoice->id,
            'invoice_no' => $invoice->invoice_no ?? $invoice->serial_number,
            'total_amount' => $invoice->total_amount,
            'amount_due' => $invoice->amount_due ?? $invoice->total_amount,
            'amount_balance' => $invoice->amount_balance,
            'amount_paid' => $invoice->amount_paid,
            'status' => $invoice->status,
            'invoice_items' => $invoiceItems,
            'actionUrl' => route('admin.invoices.payment', $invoice->id),
            'context' => $invoice->context,
            'created_at' => $invoice->created_at,
            'period' => $formattedPeriod,
            'due_date' => $invoice->due_date?->format('Y-m-d') ?? '',
            'due_date_formatted' => $invoice->due_date?->format('d/m/Y') ?? '—',
            'remarks' => $invoice->remarks,
            'document_template_id' => $invoice->document_template_id ?? '—',
            'documentTemplate' => $invoice->documentTemplate,
            'receipt_path' => $invoice->receipt_path ?? $latestPayment?->receipt_path,
            'latestPayment' => $latestPayment,
            'user' => $invoice->lease?->user ?? null,
            'template_title' => $invoice->documentTemplate?->title,
            'template_html' => $invoice->documentTemplate?->html_content ?? $invoice->documentTemplate?->html_template,
            'wallet_balance' => $walletBalanceFormatted,
            'variables' => $this->getInvoiceVariables($invoice),
            'receipts' => $receipts,
            'recipient_name' => $invoice->isTenantInvoice()
                ? ($invoice->lease?->tenant?->user?->name ?? 'N/A')
                : ($invoice->user?->name ?? 'N/A'),
            'tenant_id' => $invoice->lease?->tenant?->id,
            'payments' => $invoice->payments?->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'status' => $payment->status,
                    'receipt_path' => $payment->receipt_path,
                    'receipt_url' => $payment->receipt_path ? route('admin.payments.view-receipt', $payment->id) : null,
                ];
            }),
            'context_label' => (function () use ($invoice) {
                if (!$invoice->isTenantInvoice()) {
                    return new HtmlString('<span class="text-gray-500">Subscription</span>');
                }

                $lease = $invoice->lease;
                if (!$lease || !$lease->leasable) {
                    return new HtmlString('<span class="text-gray-500">Tenant Lease</span>');
                }

                $model = $lease->leasable;
                $linkClass = 'font-semibold text-indigo-600 hover:text-indigo-900 hover:underline';

                if ($model instanceof Property) {
                    $url = route('admin.properties.show', $model->id);
                    return new HtmlString("Property: <a href=\"{$url}\" class=\"{$linkClass}\">{$model->name}</a>");
                }

                if ($model instanceof Unit) {
                    $url = route('admin.units.show', $model->id);
                    $unitName = e($model->name ?? $model->unit_no);
                    
                    $propertyText = '';
                    if ($model->property) {
                        $propertyUrl = route('admin.properties.show', $model->property->id);
                        $propertyName = e($model->property->name);
                        $propertyText = " <span class=\"text-gray-400\">(<a href=\"{$propertyUrl}\" class=\"{$linkClass}\">{$propertyName}</a>)</span>";
                    }

                    return new HtmlString("Unit: <a href=\"{$url}\" class=\"{$linkClass}\">{$unitName}</a>{$propertyText}");
                }

                if ($model instanceof Room) {
                    $url = route('admin.rooms.show', $model->id);
                    $roomNo = e($model->room_no);
                    return new HtmlString("Room: <a href=\"{$url}\" class=\"{$linkClass}\">{$roomNo}</a>");
                }

                return new HtmlString('<span class="text-gray-500">Tenant Lease</span>');
            })(),
        ];
    }
}
