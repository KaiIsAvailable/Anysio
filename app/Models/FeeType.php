<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\FeeTypeCategory;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class FeeType extends Model
{
    use HasUlids, Auditable;

    protected $table = 'fee_types';

    protected $fillable = [
        'user_id',
        'name',
        'category',
        'original_price',
        'sell_price',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'category' => FeeTypeCategory::class,
        'is_system' => 'boolean',
        'is_active' => 'boolean',
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

    public function refundItems(): HasMany
    {
        return $this->hasMany(RefundItem::class, 'fee_type_id');
    }

    public function scopeForAccount(Builder $query, string $userId): Builder
    {
        return $query->where(fn (Builder $fees) => $fees->where('is_system', true)->orWhere('user_id', $userId));
    }

    // Apply only to user-facing catalogs. Historical references and automatic
    // billing must continue to resolve existing fee types independently.
    public function scopeVisibleToActor(Builder $query): Builder
    {
        return $query->whereIn('category', \App\Services\FeeTypeService::allowedCategories());
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when(filled($search), fn (Builder $fees) => $fees->where(function (Builder $match) use ($search) {
            $match->where('name', 'like', '%'.$search.'%')->orWhere('category', 'like', '%'.$search.'%');
        }));
    }

    public function settingKey(): string
    {
        return Str::slug(strtolower($this->category->value.'_'.$this->name), '_');
    }

    public function leaseCharges(): HasMany
    {
        return $this->hasMany(LeaseCharge::class)->withoutGlobalScopes();
    }

    public function maintenanceItems(): HasMany
    {
        return $this->hasMany(Maintenance::class)->withoutGlobalScopes();
    }
}
