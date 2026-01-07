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
        'company_name',
        'ic_number',
        'phone',
        'referred_by',
        'gender',
        'subscription_status',
        'discount_rate',
        'usage_count',
    ];

    protected $casts = [
        'email_verify_at' => 'datetime',
        'discount_rate' => 'decimal:2',
        'usage_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function agreementTemplates(): HasMany
    {
        return $this->hasMany(AgreementTemplate::class);
    }
}