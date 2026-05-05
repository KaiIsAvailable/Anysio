<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Property extends Model
{
    use HasUlids;

    /**
     * 可批量赋值的字段
     * 按照你之前定稿的 Migration 字段
     */
    protected $fillable = [
        'name',
        'address',
        'city',
        'postcode',
        'state',
        'type',
        'owner_id',
        'created_by',
    ];

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class, 'property_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owners::class, 'owner_id');
    }

    public function getFullAddressAttribute()
    {
        // 使用 PHP 8.0+ 的方式，确保字段为空时不会有奇怪的逗号
        return implode(', ', array_filter([
            $this->address,
            $this->postcode . ' ' . $this->city,
            $this->state
        ]));
    }
}