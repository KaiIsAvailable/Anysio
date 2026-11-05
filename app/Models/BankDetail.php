<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany};

class BankDetail extends Model
{
    use HasFactory, HasUlids, Auditable;

    protected $table = 'bank_details';

    protected $fillable = [
        'bank_name',
        'bank_code',
        'account_holder_name',
        'account_number',
        'verified_at',
        'verified_by',
    ];

    protected $hidden = [
        'account_number',
    ];

    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
            'verified_at' => 'datetime',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'bank_user')
            ->using(BankUser::class)
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function bankUsers(): HasMany
    {
        return $this->hasMany(BankUser::class, 'bank_detail_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
