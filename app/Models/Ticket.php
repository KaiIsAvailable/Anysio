<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasUlids;

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
}