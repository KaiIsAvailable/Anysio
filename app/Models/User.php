<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasUlids;

    const ROLE_ADMIN = 'admin';
    const ROLE_AGENT_ADMIN = 'agentAdmin';
    const ROLE_OWNER_ADMIN = 'ownerAdmin';
    const ROLE_OWNER = 'owner';
    const ROLE_TENANT = 'tenant';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_agree',
        'tos_id',
        'privacy_id',
        'agreed_at',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isAgentAdmin(): bool
    {
        return $this->role === self::ROLE_AGENT_ADMIN;
    }

    public function isOwerAdmin(): bool
    {
        return $this->role === self::ROLE_OWNER_ADMIN;
    }

    public function isOWNER(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function isTenant(): bool
    {
        return $this->role === self::ROLE_TENANT;
    }

    // --- 关联关系 ---

    public function owner()
    {
        return $this->hasOne(Owners::class);
    }

    public function tenant()
    {
        return $this->hasOne(Tenants::class);
    }

    public function user_management() {
        return $this->hasOne(UserManagement::class, 'user_id');
    }

    public function hasRole($role) 
    {
        return $this->role === $role;
    }
}