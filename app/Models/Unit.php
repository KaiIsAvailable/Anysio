<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasUlids;

    protected $fillable = [
        'property_id',
        'owner_id',
        'unit_no',
        'block',
        'floor',
        'sqft',
        'management_fee',
        'electricity_acc_no',
        'water_acc_no',
        'status',
        'has_rooms',
        'total_rooms',
        'created_by',
    ];

    /**
     * 关联到所属的大楼/小区 (Property)
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * 关联到业主 (Owner)
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owners::class, 'owner_id');
    }

    /**
     * 关联下属的所有房间 (Rooms)
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function roomAssets(): HasMany
    {
        // 假设你的中间表模型是 RoomAsset
        return $this->hasMany(RoomAsset::class, 'unit_id');
    }

    public function getFullAddressAttribute() {
        return $this->property ? $this->property->full_address : 'N/A';
    }
}