<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Maintenance extends Model
{
    use HasUlids;

    // 指定表名
    protected $table = 'maintenance';

    protected $fillable = [
        'lease_id',
        'asset_id',
        'title',
        'desc',
        'photo_path',
        'status',
        'cost',
        'paid_by',
    ];

    protected $casts = [
        'cost' => 'integer',
    ];

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(RoomAsset::class, 'asset_id');
    }
}