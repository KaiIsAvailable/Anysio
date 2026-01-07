<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    protected $fillable = [
        'user_mgnt_id',
        'role',
        'is_active',
    ];

    protected $casts = [
        'email_verify_at' => 'datetime',
    ];

    public function user_management() {
        return $this->belongsTo(UserManagement::class, 'user_mgnt_id');
    }
}
