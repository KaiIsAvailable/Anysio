<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Agreements extends Model
{
    use HasUlids;

    protected $fillable = [
        'owner_id',
        'type',
        'title',
        'version',
        'content',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owners::class);
    }
}