<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class Ticket extends Model
{
    use HasUlids, Auditable;
    protected $table = 'tickets';
    protected $fillable = [
        'sender_id',
        'receive_id',
        'category',
        'subject',
        'status',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMsg::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receive_id');
    }
}