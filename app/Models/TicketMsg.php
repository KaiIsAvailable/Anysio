<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class TicketMsg extends Model
{
    use HasUlids, Auditable;

    // 指定表名（因为你给的名称是 ticket_msgs，模型名是 TicketMsg，Laravel 默认会找 ticket_msgs，这里手动指定更安全）
    protected $table = 'ticket_msgs';

    // Application default only; legacy NULL values remain unknown.
    protected $attributes = ['status' => 'unread'];

    protected $casts = ['read_at' => 'datetime'];

    protected $fillable = [
        'ticket_id',
        'sender_type',
        'message',
    ];

    public function creationAudits(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AuditLog::class, 'auditable_id')
            ->where('auditable_type', self::class)->where('event', 'created');
    }

    public function authorId(): ?string
    {
        // Legacy tenant messages have a unique recorded participant on their ticket.
        if ($this->creationAudits->isEmpty() && $this->sender_type === User::ROLE_TENANT && $this->ticket?->sender?->isTenant()) {
            return $this->ticket->sender_id;
        }
        $ids = $this->creationAudits->pluck('user_id')->unique();
        return $ids->count() === 1 && $ids->first() ? $ids->first() : null;
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
