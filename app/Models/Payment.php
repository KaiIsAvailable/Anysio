<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasUlids;

    protected $fillable = [
        'lease_id',        // 关联哪份合约
        'tenant_id',       // 关联哪个学生/租客
        'invoice_no',
        'payment_type',    // 'rent', 'deposit_security', 'agreement', etc.
        'period',          // 哪个月的账单 (2023-10-01)
        'amount_due',      // 应付 (cents)
        'amount_paid',     // 实付 (cents)
        'amount_over_paid',
        'amount_under_paid',
        'payment_date',
        'payment_method',  // 'cash', 'bank_transfer'
        'transaction_ref', // 银行 Reference No
        'received_via',    // 我们的哪个银行 (Maybank/Public Bank)
        'receipt_path',
        'status',          // 'unpaid', 'pending', 'paid', 'rejected'
        'attachment',
        'approved_by',
        'approved_at',
        'remarks',       
    ];

    protected $casts = [
        'amount_due' => 'integer',
        'amount_paid' => 'integer',
        'period' => 'date',         // 自动转成 Carbon 对象，方便格式化
        'approved_at' => 'datetime',
    ];

    // --- Relationships ---

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class, 'lease_id');
    }

    public function tenant(): BelongsTo
    {
        // 建议类名用单数 Tenant，如果你数据库表是 tenants
        return $this->belongsTo(Tenants::class, 'tenant_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // --- Accessors (方便前端显示) ---

    /**
     * 获取显示用的金额 (RM)
     * 在 Blade 中可以使用 $payment->amount_due_formatted
     */
    public function getAmountDueFormattedAttribute()
    {
        return number_format($this->amount_due / 100, 2);
    }

    public function getAmountPaidFormattedAttribute()
    {
        return number_format($this->amount_paid / 100, 2);
    }
}