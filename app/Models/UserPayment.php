<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPayment extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_management_id',
        'invoice_no',
        'payment_type',
        'amount_due',
        'amount_paid',
        'payment_method',
        'transaction_ref',
        'bank_name',
        'receipt_path',
        'status',
        'approved_by',
        'approved_at',
        'remarks',
    ];

    protected $casts = [
        'amount_due' => 'integer',
        'amount_paid' => 'integer',
        'approved_at' => 'datetime',
    ];

    // --- Relationships ---

    public function userManagement(): BelongsTo
    {
        return $this->belongsTo(UserManagement::class, 'user_management_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // --- Accessors (金额显示转换) ---

    public function getAmountDueRmAttribute()
    {
        return number_format($this->amount_due / 100, 2);
    }

    public function getAmountPaidRmAttribute()
    {
        return number_format($this->amount_paid / 100, 2);
    }

    // 获取还欠多少钱
    public function getBalanceRmAttribute()
    {
        return number_format(($this->amount_due - $this->amount_paid) / 100, 2);
    }
}