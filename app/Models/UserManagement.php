<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserManagement extends Model
{
    use HasFactory, HasUlids;
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'referred_by',
        'subscription_status',
        'discount_rate',
        'usage_count',
        'role',
    ];

    protected $casts = [
        'email_verify_at' => 'datetime',
    ];

    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function referred_person() {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function staff() {
        return $this->hasOne(Staff::class, 'user_mgnt_id');
    }
}
