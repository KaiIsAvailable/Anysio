<?php

namespace App\Models;

use App\Enums\DisbursementStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class RefundDisbursement extends Model {
    use HasUlids, Auditable;

    protected $guarded = [];

    protected $casts = [
        'status' => DisbursementStatus::class,
        'amount_cents' => 'integer',
        'processed_at' => 'datetime',
    ];

    public function refund(): BelongsTo {
        return $this->belongsTo(Refund::class);
    }
}