<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomAsset extends Model
{
    use HasUlids;

    protected $table = 'asset_room';

    protected $fillable = [
        'asset_id',
        'room_id',
        'unit_id',
        'status',
        'condition',
        'last_maintenance',
        'remark',
        'quantity',
    ];

    protected $casts = [
        'last_maintenance' => 'date',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * 关联此资产的维修记录
     */
    public function maintenanceHistory(): HasMany
    {
        return $this->hasMany(Maintenance::class, 'asset_id');
    }
}