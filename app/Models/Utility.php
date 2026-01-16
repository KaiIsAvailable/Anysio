<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Utility extends Model
{
    use HasUlids;

    protected $fillable = [
        'lease_id',
        'type',
        'prev_reading',
        'curr_reading',
        'amount',
    ];

    protected $casts = [
        'prev_reading' => 'integer',
        'curr_reading' => 'integer',
        'amount' => 'integer',
    ];

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }
}