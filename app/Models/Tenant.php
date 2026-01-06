<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'phone',
        'ic_number',
        'passport',
        'nationality',
        'gender',
        'occupation',
        'ic_photo_path',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
