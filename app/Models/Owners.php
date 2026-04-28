<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Owners extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'agent_id',
        'company_name',
        'ic_number',
        'phone',
        'gender',
    ];

    protected $casts = [
        'email_verify_at' => 'datetime',
    ];

    public function owner()
    {
        return $this->unit()->with('owner'); 
    }

    public function leases()
    {
        // 通过 rooms 找到所有的 leases
        return $this->hasManyThrough(Lease::class, Room::class, 'owner_id', 'leasable_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(Agreements::class);
    }
}