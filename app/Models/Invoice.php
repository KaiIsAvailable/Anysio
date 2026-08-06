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