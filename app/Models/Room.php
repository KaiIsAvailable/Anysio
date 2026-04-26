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
        'unit_id',
        'room_no',
        'room_type',
        'status',
        'created_by',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function assets(): BelongsToMany
    {
        // 关联 Asset 字典表，指定中间表为 asset_room
        return $this->belongsToMany(Asset::class, 'asset_room')
                    ->withPivot('id', 'condition', 'last_maintenance', 'remark') // 允许访问中间表字段
                    ->withTimestamps();
    }

    public function leases()
{
    // 使用 morphMany 而不是 hasMany
    return $this->morphMany(Lease::class, 'leasable');
}
}