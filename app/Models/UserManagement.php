<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserManagement extends Model
{
    use HasFactory, HasUlids, SoftDeletes;
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'package_id',
        'subscription_status',
        'start_date',
        'end_date',
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

    public function package() {
        return $this->belongsTo(RefCodePackage::class, 'package_id');
    }

    public function staff() {
        return $this->hasOne(Staff::class, 'user_mgnt_id');
    }

    public function payments()
    {
        return $this->hasMany(UserPayment::class, 'user_management_id');
    }
}
