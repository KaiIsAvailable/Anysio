<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class WalletTransaction extends Model
{
    use HasUlids, Auditable;

    protected $table = 'wallet_transactions';

    protected $fillable = [
        'wallet_id',
        'amount',
        'type',
        'reference_id',
        'remarks',
    ];

    /**
     * 所属钱包
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    // --- 财务辅助 ---

    /**
     * 格式化金额 (Cents -> Decimal)
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount / 100, 2);
    }

    /**
     * 判断是否为收入 (充值/超额存入)
     */
    public function isCredit(): bool
    {
        return $this->amount > 0;
    }
}