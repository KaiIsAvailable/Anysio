<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomAsset extends Model
{
    use HasUlids;

    protected $fillable = [
        'room_id',
        'name',
        'condition',
        'last_maintenance',
        'remark',
    ];

    protected $casts = [
        'last_maintenance' => 'date',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * 关联此资产的维修记录
     */
    public function maintenanceHistory(): HasMany
    {
        return $this->hasMany(Maintenance::class, 'asset_id');
    }
}