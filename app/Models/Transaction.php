<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Auditable;

class Transaction extends Model
{
    use HasUlids, SoftDeletes, Auditable;

    protected $table = 'transactions';

    protected $fillable = [
        'invoice_id',
        'amount_paid',
        'payment_method',
        'transaction_ref',
        'receipt_no',
        'payment_date',
        'approved_by',
    ];

    /**
     * 关联所属的账单
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * 关联审批人 (通常是管理员或房东账号)
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by', 'id');
    }

    // --- 财务辅助方法 ---

    /**
     * 格式化金额 (Cents -> Decimal)
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount_paid / 100, 2);
    }
}