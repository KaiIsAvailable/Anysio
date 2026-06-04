<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\User;
use App\Traits\SyncableStatus;

class Room extends Model
{
    use HasUlids, SyncableStatus;

    protected $fillable = [
        'unit_id',
        'owner_id',
        'room_no',
        'room_type',
        'status',
        'created_by',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id');
    }

    public function assets(): BelongsToMany
    {
        // 关联 Asset 字典表，指定中间表为 asset_room；只返回当前 Active 的房间资产
        return $this->belongsToMany(Asset::class, 'asset_room')
                    ->withPivot(['id', 'status', 'condition', 'last_maintenance', 'remark'])
                    ->wherePivot('status', 'Active')
                    ->withTimestamps();
    }

    public function allAssets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'asset_room')
                    ->withPivot(['id', 'status', 'condition', 'last_maintenance', 'remark'])
                    ->withTimestamps();
    }

    public function leases()
    {
        // 使用 morphMany 而不是 hasMany
        return $this->morphMany(Lease::class, 'leasable');
    }

    public function getFullAddressAttribute() {
        return $this->unit && $this->unit->property 
            ? $this->unit->property->full_address 
            : 'N/A';
    }

    public function childrenItems() { return null; }
    public function parentItem() { return $this->unit; }
}