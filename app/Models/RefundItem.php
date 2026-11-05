<?php

namespace App\Models;

use App\Enums\RefundItemType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;

class RefundItem extends Model {
    use HasUlids, Auditable;

    protected $fillable = [
        'refund_id',
        'origin',
        'fee_type_id',
        'lease_charge_id',
        'invoice_id',
        'invoice_item_id',
        'type',
        'description',
        'qty',
        'unit_amount_cents',
        'amount_cents',
    ];

    protected $casts = [
        'type' => RefundItemType::class,
        'qty' => 'integer',
        'unit_amount_cents' => 'integer',
        'amount_cents' => 'integer',
    ];

    public function refund(): BelongsTo {
        return $this->belongsTo(Refund::class);
    }

    public function leaseCharge(): BelongsTo {
        return $this->belongsTo(LeaseCharge::class);
    }

    public function invoiceItem(): BelongsTo {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function invoice(): BelongsTo {
        return $this->belongsTo(Invoice::class);
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class, 'fee_type_id');
    }

    protected function unitPrice(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => ($attributes['amount_cents'] ?? 0) / 100 / ($attributes['qty'] ?? 1),
            set: fn ($value) => ['amount_cents' => (int) round(((float) $value) * 100)],
        );
    }
}