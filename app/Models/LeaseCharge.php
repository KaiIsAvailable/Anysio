<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaseCharge extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'lease_charges';

    // Charge type constants — single source of truth
    const TYPE_RECURRING  = 'recurring';
    const TYPE_ONE_TIME   = 'one_time';
    const TYPE_REFUNDABLE = 'refundable';   // deposit-like charges

    protected $fillable = [
        'lease_id',
        'fee_type_id',
        'description',
        'amount',
        'charge_type',
        'frequency',
        'next_billing_date',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'amount'     => 'integer',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
        'next_billing_date' => 'date',
    ];

    // --- Relationships ---

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    // --- Scopes ---

    public function scopeRecurring($q)
    {
        return $q->where('charge_type', self::TYPE_RECURRING)
                 ->where('is_active', true);
    }

    public function scopeOneTime($q)
    {
        return $q->where('charge_type', self::TYPE_ONE_TIME)
                 ->where('is_active', true);
    }

    public function scopeRefundable($q)
    {
        return $q->where('charge_type', self::TYPE_REFUNDABLE)
                 ->where('is_active', true);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)->orderBy('sort_order');
    }

    // --- Helpers ---

    public function isRecurring(): bool  { return $this->charge_type === self::TYPE_RECURRING; }
    public function isOneTime(): bool    { return $this->charge_type === self::TYPE_ONE_TIME; }
    public function isRefundable(): bool { return $this->charge_type === self::TYPE_REFUNDABLE; }

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount / 100, 2);
    }
}