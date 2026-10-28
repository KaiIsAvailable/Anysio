<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Traits\Auditable;
use Illuminate\Support\Facades\DB;

class Invoice extends Model
{
    use HasUlids, SoftDeletes, Auditable;

    protected $table = 'invoices';

    // -------------------------------------------------------------------------
    // Constants — single source of truth, no magic strings anywhere in codebase
    // -------------------------------------------------------------------------

    const CONTEXT_TENANT       = 'tenant';
    const CONTEXT_SUBSCRIPTION = 'subscription';

    const TYPE_RENT          = 'rent';
    const TYPE_MANUAL        = 'manual';
    const TYPE_DEPOSIT       = 'deposit';
    const TYPE_DEPOSIT_TOPUP = 'deposit_topup';
    const TYPE_SUBSCRIPTION  = 'subscription';
    const TYPE_RENEWAL       = 'renewal';

    const STATUS_UNPAID  = 'unpaid';
    const STATUS_PARTIAL = 'partial';
    const STATUS_PAID    = 'paid';
    const STATUS_OVERDUE = 'overdue';
    const STATUS_VOID    = 'void';

    // -------------------------------------------------------------------------
    // Fillable
    // -------------------------------------------------------------------------

    protected $fillable = [
        'parent_id',
        'context',
        'billable_type',
        'billable_id',
        'lease_id',
        'user_id',
        'invoice_no',
        'document_template_id',
        'type',
        'period',
        'due_date',
        'total_amount',
        'amount_paid',
        'amount_balance',
        'status',
        'remarks',
    ];

    // -------------------------------------------------------------------------
    // Casts — modern Attribute casting, no manual /100 scattered in views
    // -------------------------------------------------------------------------

    protected $casts = [
        'period'         => 'date',
        'due_date'       => 'date',
        'total_amount'   => 'integer',
        'amount_paid'    => 'integer',
        'amount_balance' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * Polymorphic — resolves to Lease (tenant) or UserManagement (subscription)
     */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment()
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    /**
     * Shortcut to tenant — only valid for tenant context invoices.
     * Always eager load lease when using this to avoid N+1.
     */
    public function tenant(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
    {
        return $this->hasOneThrough(
            Tenants::class,
            Lease::class,
            'id',        // leases.id
            'id',        // tenants.id
            'lease_id',  // invoices.lease_id
            'tenant_id'  // leases.tenant_id
        );
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeTenant($q)
    {
        return $q->where('context', self::CONTEXT_TENANT);
    }

    /** Existing invoice-index visibility, shared by the list and statement. */
    public function scopeVisibleTo($query, ?string $actualId, ?string $effectiveId, bool $superAdmin = false)
    {
        if ($superAdmin) return $query;
        if (!$actualId || !$effectiveId) return $query->whereRaw('1 = 0');

        return $query->whereIn('lease_id', static::statementLeases($actualId, $effectiveId, $superAdmin)->select('leases.id'));
    }

    public static function statementLeases(?string $actualId, ?string $effectiveId, bool $superAdmin = false)
    {
        $query = Lease::query();
        if ($superAdmin) return $query;
        if (!$actualId || !$effectiveId) return $query->whereRaw('1 = 0');

        return $query->where(function ($lease) use ($actualId, $effectiveId) {
            $lease->where(function ($scope) use ($actualId, $effectiveId) {
                $scope->whereHasMorph('leasable', [Room::class, Unit::class, Property::class], function ($asset, $type) use ($actualId, $effectiveId) {
                    $asset->whereHas($type === Room::class ? 'unit.owner' : 'owner', function ($owner) use ($actualId, $effectiveId) {
                        $owner->where(function ($q) use ($actualId, $effectiveId) {
                            $q->where('created_by', $effectiveId)->orWhere('created_by', $actualId)->orWhere('owner_id', $effectiveId);
                        });
                    });
                })->orWhereHas('tenant', fn ($tenant) => $tenant->whereIn('created_by', [$actualId, $effectiveId]));
            });
        });
    }

    /** One row per source event. Never join items to payments or wallet movements. */
    public static function statementPage(?string $actualId, ?string $effectiveId, bool $superAdmin, array $filters, ?string $leaseId = null)
    {
        $visible = static::query()->visibleTo($actualId, $effectiveId, $superAdmin)
            ->when($leaseId !== null, fn ($q) => $q->forLease($leaseId))->select('invoices.id');

        $invoices = DB::table('invoices as i')->whereIn('i.id', clone $visible)
            ->selectRaw("i.id as event_id, 'invoice' as event_kind, i.id as invoice_id, i.invoice_no,
                i.created_at as occurred_at, i.created_at as recorded_at, i.total_amount as amount,
                NULL as wallet_user_id, NULL as receipt_no, NULL as payment_method, NULL as reference,
                i.remarks as description, i.status as source_type, 0 as date_only, NULL as linked_transaction_id");
        $payments = DB::table('transactions as t')->join('invoices as i', 'i.id', '=', 't.invoice_id')
            ->whereNull('t.deleted_at')->whereIn('i.id', clone $visible)
            ->selectRaw("t.id as event_id, 'payment' as event_kind, i.id as invoice_id, i.invoice_no,
                t.created_at as occurred_at, t.created_at as recorded_at,
                t.amount_applied as amount, NULL as wallet_user_id, t.receipt_no, t.payment_method,
                t.transaction_ref as reference, t.remarks as description, i.status as source_type,
                CASE WHEN t.payment_date IS NULL THEN 0 ELSE 1 END as date_only, NULL as linked_transaction_id");

        // Explicit children are removed before pagination, never by invoice/date/amount matching.
        $wallet = DB::query()->fromSub(static::statementWalletEvents($visible, $leaseId, $superAdmin, $actualId, $effectiveId), 'wallet_events')
            ->whereNull('linked_transaction_id');

        $events = DB::query()->fromSub($invoices->unionAll($payments)->unionAll($wallet), 'statement_events');
        if (!empty($filters['statement_from'])) $events->where('occurred_at', '>=', $filters['statement_from']);
        if (!empty($filters['statement_to'])) $events->where('occurred_at', '<', \Carbon\Carbon::parse($filters['statement_to'])->addDay()->toDateString());
        if (!empty($filters['statement_kind'])) $events->where('event_kind', $filters['statement_kind']);
        if (!empty($filters['statement_invoice'])) $events->where('invoice_no', 'like', '%'.$filters['statement_invoice'].'%');
        // The receipt is the parent event for grouped movements. Unknown recording times sort last.
        return $events->orderByRaw('CASE WHEN recorded_at IS NULL THEN 1 ELSE 0 END')->orderByDesc('recorded_at')->orderBy('event_kind')->orderByDesc('event_id')
            ->paginate(20, ['*'], 'statement_page', $filters['statement_page'] ?? 1);
    }

    private static function statementWalletEvents($visible, ?string $leaseId, bool $superAdmin, ?string $actualId, ?string $effectiveId)
    {
        // reference_id is historically either an invoice ID or a transaction ID.
        // All joins below are primary-key joins, so a movement can appear only once.
        return DB::table('wallet_transactions as wt')->join('wallets as w', 'w.id', '=', 'wt.wallet_id')
            ->whereRaw("LOWER(COALESCE(wt.type, '')) NOT LIKE '%refund%'")
            ->leftJoin('invoices as direct', 'direct.id', '=', 'wt.reference_id')
            ->leftJoin('transactions as receipt', 'receipt.id', '=', 'wt.reference_id')
            ->leftJoin('invoices as via_receipt', 'via_receipt.id', '=', 'receipt.invoice_id')
            ->where(function ($q) use ($visible, $leaseId, $superAdmin, $actualId, $effectiveId) {
                $q->where(function ($linked) use ($visible) {
                    $linked->whereIn(DB::raw('COALESCE(direct.id, via_receipt.id)'), clone $visible)
                        ->where(function ($unambiguous) {
                            $unambiguous->whereNull('direct.id')->orWhereNull('receipt.id')->orWhereColumn('direct.id', 'via_receipt.id');
                        })
                        ->where(function ($r) {
                            $r->whereNotNull('direct.id')->orWhereNull('receipt.deleted_at');
                        })->whereExists(function ($tenant) {
                            $tenant->selectRaw('1')->from('leases as wl')->join('tenants as wt_owner', 'wt_owner.id', '=', 'wl.tenant_id')
                                ->whereColumn('wl.id', DB::raw('COALESCE(direct.lease_id, via_receipt.lease_id)'))
                                ->whereColumn('wt_owner.user_id', 'w.user_id');
                        });
                });
                // An unresolved tenant-level reference cannot be assigned to a lease.
                if ($leaseId === null) {
                    $q->orWhere(function ($unlinked) use ($superAdmin, $actualId, $effectiveId) {
                        $unlinked->whereNull('direct.id')->whereNull('receipt.id');
                        if (!$superAdmin) {
                            $ids = $actualId && $effectiveId ? [$actualId, $effectiveId] : [];
                            $unlinked->whereExists(fn ($t) => $t->selectRaw('1')->from('tenants as managed_tenant')
                                ->whereColumn('managed_tenant.user_id', 'w.user_id')->whereIn('managed_tenant.created_by', $ids));
                        }
                    });
                }
            })
            ->selectRaw("wt.id as event_id,
                CASE WHEN wt.amount > 0 THEN 'wallet_credit' WHEN wt.amount < 0 THEN 'wallet_payment' ELSE 'wallet_other' END as event_kind,
                COALESCE(direct.id, via_receipt.id) as invoice_id, COALESCE(direct.invoice_no, via_receipt.invoice_no) as invoice_no,
                wt.created_at as occurred_at, wt.created_at as recorded_at, wt.amount, w.user_id as wallet_user_id,
                NULL as receipt_no, NULL as payment_method, wt.reference_id as reference,
                wt.remarks as description, wt.type as source_type, 0 as date_only,
                CASE WHEN direct.id IS NULL AND receipt.id IS NOT NULL
                    AND ((wt.type = 'invoice_payment' AND wt.amount < 0) OR (wt.type = 'overpayment_credit' AND wt.amount > 0))
                    THEN receipt.id ELSE NULL END as linked_transaction_id");

    }

    public static function statementPaymentDetails(array $ids, ?string $actualId, ?string $effectiveId, bool $superAdmin, ?string $leaseId): array
    {
        $visible = static::query()->visibleTo($actualId, $effectiveId, $superAdmin)
            ->when($leaseId !== null, fn ($q) => $q->forLease($leaseId))->select('invoices.id');
        return [
            Transaction::whereIn('invoice_id', clone $visible)->whereKey($ids)->get()->keyBy('id'),
            DB::query()->fromSub(static::statementWalletEvents($visible, $leaseId, $superAdmin, $actualId, $effectiveId), 'movements')
                ->whereIn('linked_transaction_id', $ids)->orderBy('recorded_at')->orderBy('event_id')->get()->groupBy('linked_transaction_id'),
        ];
    }

    public static function statementSummary(?string $actualId, ?string $effectiveId, bool $superAdmin, ?string $leaseId): array
    {
        // Persisted balances using existing unpaid rules, including penalties. No refund adjustment.
        $outstanding = static::query()->visibleTo($actualId, $effectiveId, $superAdmin)
            ->when($leaseId !== null, fn ($q) => $q->forLease($leaseId))->unpaid()->sum('amount_balance');
        $wallet = null;
        if ($leaseId !== null) {
            $lease = static::statementLeases($actualId, $effectiveId, $superAdmin)->with('tenant')->find($leaseId);
            if ($lease?->tenant?->user_id) $wallet = Wallet::where('user_id', $lease->tenant->user_id)->value('balance');
        }
        return ['outstanding' => $outstanding, 'wallet' => $wallet];
    }

    public static function statementDetails(array $invoiceIds, array $userIds): array
    {
        return [
            static::with(['items.feeType', 'lease.tenant.user', 'user', 'documentTemplate.user',
                'lease.leasable' => fn (MorphTo $asset) => $asset->morphWith([
                    Room::class => ['unit.owner'], Unit::class => ['owner'], Property::class => ['owner'],
                ]),
            ])
                ->whereKey($invoiceIds)->get()->keyBy('id'),
            User::whereIn('id', $userIds)->get()->keyBy('id'),
        ];
    }

    public static function indexPage(?string $actualId, ?string $effectiveId, bool $superAdmin, int $page = 1)
    {
        return static::with([
            'documentTemplate', 'user', 'billable', 'lease.leasable.owner', 'lease.tenant.user',
            'items.feeType', 'transactions.documentTemplate', 'transactions.approver', 'payments',
        ])->visibleTo($actualId, $effectiveId, $superAdmin)->latest()
            ->paginate(10, ['*'], 'page', $page)->onEachSide(1);
    }

    public function scopeSubscription($q)
    {
        return $q->where('context', self::CONTEXT_SUBSCRIPTION);
    }

    public function scopeUnpaid($q)
    {
        return $q->whereIn('status', [self::STATUS_UNPAID, self::STATUS_PARTIAL, self::STATUS_OVERDUE]);
    }

    public function scopeOverdue($q)
    {
        return $q->where('status', self::STATUS_OVERDUE);
    }

    public function scopePaid($q)
    {
        return $q->where('status', self::STATUS_PAID);
    }

    public function scopeForLease($q, string $leaseId)
    {
        return $q->where('lease_id', $leaseId);
    }

    public function scopeForUser($q, int|string $userId)
    {
        return $q->where('user_id', $userId);
    }

    public function scopeByPeriodAsc($q)
    {
        return $q->orderBy('period');
    }

    public function scopeByPeriodDesc($q)
    {
        return $q->orderByDesc('period');
    }

    /**
     * 關聯到發票所使用的 Document Template
     */
    public function documentTemplate()
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }
    
    // -------------------------------------------------------------------------
    // Computed Attributes — modern Attribute::make() style
    // -------------------------------------------------------------------------

    /**
     * Formatted total for UI display — reads cents, returns decimal string.
     * Usage: $invoice->formatted_total  →  "1,200.00"
     */
    protected function formattedTotal(): Attribute
    {
        return Attribute::make(
            get: fn () => number_format($this->total_amount / 100, 2),
        );
    }

    protected function formattedPaid(): Attribute
    {
        return Attribute::make(
            get: fn () => number_format($this->amount_paid / 100, 2),
        );
    }

    protected function formattedBalance(): Attribute
    {
        return Attribute::make(
            get: fn () => number_format($this->amount_balance / 100, 2),
        );
    }

    // -------------------------------------------------------------------------
    // Status helpers — use constants, no magic strings
    // -------------------------------------------------------------------------

    public function isVoidable(): bool
    {
        return in_array($this->status, [
            self::STATUS_UNPAID,
            self::STATUS_PARTIAL,
            self::STATUS_OVERDUE,
        ]);
    }

    public function isPaid(): bool    { return $this->status === self::STATUS_PAID; }
    public function isVoid(): bool    { return $this->status === self::STATUS_VOID; }
    public function isPartial(): bool { return $this->status === self::STATUS_PARTIAL; }

    public function isOverdue(): bool
    {
        return $this->due_date->lt(now())
            && in_array($this->status, [self::STATUS_UNPAID, self::STATUS_PARTIAL]);
    }

    // -------------------------------------------------------------------------
    // Context helpers
    // -------------------------------------------------------------------------

    public function isTenantInvoice(): bool
    {
        return $this->context === self::CONTEXT_TENANT;
    }

    public function isSubscriptionInvoice(): bool
    {
        return $this->context === self::CONTEXT_SUBSCRIPTION;
    }
}
