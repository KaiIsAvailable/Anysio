<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class Utility extends Model
{
    use HasUlids, Auditable;

    protected $table = 'utilities';
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
