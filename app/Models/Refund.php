<?php
namespace App\Models;

use App\Enums\RefundType;
use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;

class Refund extends Model {
    use HasUlids, Auditable;

    protected $guarded = [];

    protected $casts = [
        'type' => RefundType::class,
        'status' => RefundStatus::class,
        'gross_amount_cents' => 'integer',
        'deduction_amount_cents' => 'integer',
        'net_amount_cents' => 'integer',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function lease(): BelongsTo {
        return $this->belongsTo(Lease::class);
    }

    public function tenant(): BelongsTo {
        return $this->belongsTo(Tenants::class);
    }

    public function items(): HasMany {
        return $this->hasMany(RefundItem::class);
    }

    public function disbursements(): HasMany {
        return $this->hasMany(RefundDisbursement::class);
    }

    public function requester(): BelongsTo {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payer(): BelongsTo {
        return $this->belongsTo(User::class, 'paid_by');
    }
}