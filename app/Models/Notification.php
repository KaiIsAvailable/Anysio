<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notification extends Model
{
    use HasUlids;

    protected $fillable = [
        'owner_id',
        'type',
        'title',
        'content',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owners::class);
    }

    /**
     * 关联已读/接收记录
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class);
    }
}