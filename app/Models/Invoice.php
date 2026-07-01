<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;

class Invoice extends Model
{
    use HasUlids, Auditable;

    protected $table = 'invoices';

    protected $fillable = [
        'lease_id',
        'invoice_no',
        'total_amount',
        'due_date',
        'status',
    ];

    // --- 关联关系 ---

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /**
     * 获取账单明细 (Invoice Items)
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * 获取支付记录 (Transactions)
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    // --- 财务逻辑辅助方法 ---

    /**
     * 获取已付金额 (通过 Transactions 计算)
     */
    public function getPaidAmountAttribute(): int
    {
        return $this->transactions()->sum('amount_paid');
    }

    /**
     * 获取未付余额 (Remaining Balance)
     */
    public function getBalanceAttribute(): int
    {
        return max(0, $this->total_amount - $this->getPaidAmountAttribute());
    }

    /**
     * 格式化金额 (用于 UI 显示，将 Cents 转为 Decimal)
     */
    public function getFormattedTotalAttribute(): string
    {
        return number_format($this->total_amount / 100, 2);
    }

    /**
     * 是否逾期
     */
    public function isOverdue(): bool
    {
        return $this->due_date < now() && in_array($this->status, ['unpaid', 'partial']);
    }
}