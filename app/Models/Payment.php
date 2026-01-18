<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'payment_type',
        'amount_type',
        'amount_due',
        'amount_paid',
        'receipt_path',
        'status',
        'approved_by',
    ];

    protected $casts = [
        'amount_due' => 'integer',
        'amount_paid' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenants::class, 'tenant_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}