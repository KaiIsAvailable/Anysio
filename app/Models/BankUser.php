<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class BankUser extends Pivot
{
    use HasFactory, HasUlids, Auditable;

    protected $table = 'bank_user';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'bank_detail_id',
        'user_id',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function bankDetail(): BelongsTo
    {
        return $this->belongsTo(BankDetail::class, 'bank_detail_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
