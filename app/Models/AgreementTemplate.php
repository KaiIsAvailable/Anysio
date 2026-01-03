<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgreementTemplate extends Model
{
    use HasUlids;

    protected $fillable = [
        'owner_id',
        'template_path',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owners::class);
    }
}