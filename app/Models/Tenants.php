<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenants extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'phone',
        'ic_number',
        'passport',
        'nationality',
        'gender',
        'occupation',
        'ic_photo_path',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class);
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}