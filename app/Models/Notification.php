<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;

class Notification extends Model
{
    use HasUlids, Auditable;

    protected $table = 'notifications';
    protected $fillable = [
        'created_by',
        'type',
        'title',
        'data',
    ];

    protected $casts = [
        'data' => 'array', // 必须加这个！
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