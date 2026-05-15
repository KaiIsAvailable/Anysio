<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPayment extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * 允许批量赋值的字段
     */
    protected $fillable = [
        'user_id',
        'ref_code',
        'invoice_no',
        'payment_type',
        'amount_due',
        'amount_paid',
        'amount_over_paid',
        'amount_under_paid',
        'status',
        'payment_date',
        'payment_method',
        'transaction_ref',
        'approve_transaction_ref',
        'received_via',
        'attachment',
        'receipt_path',
        'approved_by',
        'approved_at',
        'remarks',
    ];

    /**
     * 字段格式转换
     */
    protected $casts = [
        'payment_date' => 'date',
        'approved_at' => 'timestamp',
        'amount_due' => 'integer',
        'amount_paid' => 'integer',
    ];

    // --- 关系映射 (Relationships) ---

    /**
     * 获取支付该订单的用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取所购买的套餐
     */
    //public function package(): BelongsTo
    //{
    //    return $this->belongsTo(Package::class);
    //}

    /**
     * 获取审核人 (管理员)
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // --- 访问器 (Accessors) ---

    /**
     * 将存储的“分”转换为“元”显示 (例如: 18000 -> 180.00)
     * 使用方法: $payment->amount_due_formatted
     */
    public function getAmountDueFormattedAttribute(): string
    {
        return number_format($this->amount_due / 100, 2);
    }

    public function getAmountPaidFormattedAttribute(): string
    {
        return number_format($this->amount_paid / 100, 2);
    }

    // --- 逻辑判断 (Helper Methods) ---

    /**
     * 判断是否已支付成功
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * 判断是否正在审核中
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}