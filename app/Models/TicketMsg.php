<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketMsg extends Model
{
    use HasUlids;

    // 指定表名（因为你给的名称是 ticket_msgs，模型名是 TicketMsg，Laravel 默认会找 ticket_msgs，这里手动指定更安全）
    protected $table = 'ticket_msgs';

    protected $fillable = [
        'ticket_id',
        'sender_type',
        'message',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}