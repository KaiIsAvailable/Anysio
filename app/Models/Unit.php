<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\SyncableStatus;
use Illuminate\Support\Facades\Log;
use App\Traits\Auditable;

class Unit extends Model
{
    use HasUlids, SyncableStatus, Auditable;
    protected $table = 'units';
    protected $fillable = [
        'property_id',
        'owner_id',
        'unit_no',
        'type',
        'block',
        'floor',
        'sqft',
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
    public function property() 
    {
        // 确保这里返回的是 Property 类
        return $this->belongsTo(Property::class, 'property_id'); 
    }

    /**
     * 关联下属的所有房间 (Rooms)
     */
    public function rooms()
    {
        return $this->hasMany(Room::class, 'unit_id', 'id');
    }

    /**
     * 关联到业主 (Owner)
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim($search ?? '');
        if ($search === '') {
            return $query;
        }

        $pattern = "%{$search}%";

        return $query->where(function (Builder $q) use ($pattern) {
            $q->where('units.unit_no', 'like', $pattern);

            foreach (['type', 'block', 'floor', 'sqft', 'electricity_acc_no', 'water_acc_no', 'indah_water_acc_no', 'status'] as $column) {
                $q->orWhere("units.$column", 'like', $pattern);
            }

            $q->orWhereHas('owner', fn ($owner) => $owner->where(function ($owner) use ($pattern) {
                $owner->where('users.name', 'like', $pattern)
                    ->orWhere('users.email', 'like', $pattern);
            }))
                ->orWhereHas('rooms', fn ($rooms) => $rooms->where('rooms.room_no', 'like', $pattern));
        });
    }

    public function scopeWithSearchMatches(Builder $query, ?string $search): Builder
    {
        $search = trim($search ?? '');
        if ($search === '') {
            return $query;
        }

        return $query->with([
            'rooms' => fn ($rooms) => $rooms->where('rooms.room_no', 'like', "%{$search}%")
                ->with('unit:id,unit_no')->orderBy('rooms.room_no'),
        ]);
    }

    //public function owners(): BelongsTo
    //{
    //    return $this->belongsTo(Owners::class, 'owner_id');
    //}

    public function roomAssets(): HasMany
    {
        // 假设你的中间表模型是 RoomAsset
        return $this->hasMany(RoomAsset::class, 'unit_id');
    }

    public function getFullAddressAttribute() {
        return $this->property ? $this->property->full_address : 'N/A';
    }

    public function childrenItems() { return $this->hasMany(Room::class); }
    public function parentItem() { return $this->belongsTo(Property::class, 'property_id'); }
}
