<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function assets(): HasMany
    {
        return $this->hasMany(RoomAsset::class);
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }
}