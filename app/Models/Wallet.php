<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;

class Wallet extends Model
{
    use HasUlids, Auditable;

    protected $table = 'wallets';

    protected $fillable = [
        'user_id',
        'balance',
    ];

    /**
     * 钱包属于租客 (User)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 钱包的交易流水 (Ledger)
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    // --- 财务辅助方法 ---

    /**
     * 格式化余额 (Cents -> Decimal)
     */
    public function getFormattedBalanceAttribute(): string
    {
        return number_format($this->balance / 100, 2);
    }
}