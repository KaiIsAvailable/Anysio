<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenants extends Model
{
    use Auditable, HasUlids;

    protected $table = 'tenants';

    public function scopeForManagement(\Illuminate\Database\Eloquent\Builder $query, User $manager): \Illuminate\Database\Eloquent\Builder
    {
        if (\Illuminate\Support\Facades\Gate::allows('super-admin')) {
            return $query;
        }

        return $query->where($manager->role === 'owner' ? 'owner_id' : 'created_by', $manager->id);
    }

    protected $fillable = [
        'user_id',
        'created_by',
        'owner_id',
        'tenant_type',
        'phone',
        'ic_number',
        'passport',
        'nationality',
        'gender',
        'occupation',
        'ic_photo_path',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owners::class, 'owner_id');
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class, 'tenant_id');
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class, 'tenant_id', 'id');
    }

    public function invoice(): HasMany
    {
        return $this->hasMany(Invoice::class, 'user_id');
    }
}
