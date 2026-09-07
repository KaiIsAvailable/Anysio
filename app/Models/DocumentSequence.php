<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class DocumentSequence extends Model
{
    use HasUlids, Auditable;

    protected $fillable = [
        'user_id',
        'category',
        'sequence',
    ];

    protected $casts = [
        'sequence' => 'array',
    ];

    /**
     * Get the owner of this document sequence.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}