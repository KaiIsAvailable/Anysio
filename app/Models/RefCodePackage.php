<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RefCodePackage extends Model
{
    use HasUlids;

    protected $fillable = [
        'ref_code',
        'owner_id',
        'is_official',
        'ref_installation_price',
        'ref_monthly_price',
    ];

    protected $casts = [
        'is_official' => 'boolean',
        'ref_installation_price' => 'integer',
        'ref_monthly_price' => 'integer',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owners::class);
    }

    /**
     * 关联使用了此推荐码的房东
     */
    public function referredOwners(): HasMany
    {
        return $this->hasMany(Owners::class, 'referred_by');
    }
}