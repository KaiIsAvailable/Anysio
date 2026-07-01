<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class InvoiceItem extends Model
{
    use HasUlids, Auditable;

    protected $table = 'invoice_items';

    protected $fillable = [
        'invoice_id',
        'fee_type_id',
        'description',
        'amount',
    ];

    /**
     * 关联所属账单
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * 关联费用类型
     */
    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    // --- 财务辅助方法 ---

    /**
     * 格式化金额 (Cents -> Decimal)
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount / 100, 2);
    }
}