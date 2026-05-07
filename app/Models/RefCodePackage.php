<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefCodePackage extends Model
{
    use HasUlids;

    protected $fillable = [
        'ref_code',
        'name',
        'price_mode',       
        'price',            
        'commission_rate',
        'base_lease',  
        'max_lease_limit',  
        'allow_extra_lease',
        'extra_lease_price',
        'user_mgnt_id',     
        'is_official',
    ];

    protected $casts = [
        'is_official'       => 'boolean',
        'allow_extra_lease' => 'boolean',
        'price'             => 'integer',
        'commission_rate'   => 'integer',
        'max_lease_limit'   => 'integer',
        'extra_lease_price' => 'integer',
    ];

    /**
     * 关联创建或管理此 Package 的用户 (User Management)
     */
    public function userManagement(): BelongsTo
    {
        return $this->belongsTo(UserManagement::class, 'user_mgnt_id');
    }

    // --- 业务逻辑辅助方法 (Accessors) ---

    /**
     * 获取显示用的价格 (RM)
     */
    public function getFormattedPriceAttribute(): string
    {
        return 'RM ' . number_format($this->price / 100, 2);
    }

    /**
     * 获取显示用的抽成比例 (%)
     */
    public function getFormattedCommissionAttribute(): string
    {
        return ($this->commission_rate / 100) . '%';
    }

    /**
     * 获取超额单价 (RM)
     */
    public function getFormattedExtraPriceAttribute(): string
    {
        return 'RM ' . number_format($this->extra_lease_price / 100, 2);
    }
}