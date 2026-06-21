<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class NotificationRecipient extends Model
{
    use HasUlids, Auditable;

    protected $table = 'notification_recipients';
    protected $fillable = [
        'notification_id',
        'tenant_id',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenants::class);
    }
}