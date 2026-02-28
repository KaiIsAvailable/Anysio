<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Room extends Model
{
    use HasUlids;

    protected $fillable = [
        'owner_id',
        'room_no',
        'room_type',
        'status',
        'address',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owners::class, 'owner_id');
    }

    public function assets(): BelongsToMany
    {
        // 关联 Asset 字典表，指定中间表为 asset_room
        return $this->belongsToMany(Asset::class, 'asset_room')
                    ->withPivot('id', 'condition', 'last_maintenance', 'remark') // 允许访问中间表字段
                    ->withTimestamps();
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }
}