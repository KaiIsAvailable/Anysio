<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;

class FeeType extends Model
{
    use HasUlids, Auditable;

    protected $table = 'fee_types';

    protected $fillable = [
        'user_id',
        'name',
        'is_active',
    ];

    /**
     * 该费用类型属于哪个管理公司/用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取使用此类型的所有账单明细
     * 这对于生成“某项费用收入报表”至关重要
     */
    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * 作用域：只获取当前启用的费用类型
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}