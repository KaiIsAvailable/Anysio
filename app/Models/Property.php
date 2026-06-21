<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use App\Traits\SyncableStatus;
use App\Traits\Auditable;

class Property extends Model
{
    use HasUlids, SyncableStatus, Auditable;
    protected $table = 'payments';
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
        'status',
    ];

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class, 'property_id');
    }

    public function rooms(): HasManyThrough
    {
        // Property 通过 Unit 关联到 Room
        return $this->hasManyThrough(Room::class, Unit::class, 'property_id', 'unit_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeWithSortedRelations($query)
    {
        return $query->leftJoin('users as owners', 'properties.owner_id', '=', 'owners.id')
                    ->leftJoin('users as creators', 'properties.created_by', '=', 'creators.id')
                    ->select('properties.*', 'owners.name as owner_name', 'creators.name as creator_name');
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

    public function childrenItems() { return $this->hasMany(Unit::class); }
}