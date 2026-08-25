<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Traits\Auditable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasUlids, Auditable;

    const ROLE_ADMIN = 'admin';
    const ROLE_AGENT_ADMIN = 'agentAdmin';
    const ROLE_OWNER_ADMIN = 'ownerAdmin';
    const ROLE_OWNER = 'owner';
    const ROLE_TENANT = 'tenant';
    const ROLE_STAFF = 'staff';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $table = 'users';
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
        'email_verified_at',
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

    public function getRoleLevel(): int
    {
        // If the user is a staff member, check who owns/manages their workspace
        if ($this->role === self::ROLE_STAFF) {
            // 1. Get the staff record associated with this user
            // (Assuming a User hasOne Staff or you query the staff table directly)
            $staffRecord = $this->staff ?? Staff::where('user_id', $this->id)->first();
            
            if ($staffRecord && $staffRecord->user_mgnt_id) {
                // 2. Get the UserManagement record
                $userManagement = UserManagement::find($staffRecord->user_mgnt_id);
                
                // 3. Find the user who owns/manages this user_management record.
                // Check how your UserManagement model relates to the admin user (e.g., user_id or similar column)
                $parentUser = $userManagement?->user ?? (User::find($userManagement?->user_id ?? null));

                // 4. If the parent manager is an agent-admin, staff gets Level 4
                if ($parentUser && $parentUser->role === self::ROLE_AGENT_ADMIN) {
                    return 4;
                }
            }

            // Default staff level matching owner-admin
            return 3;
        }

        // Standard levels for non-staff roles
        $levels = [
            self::ROLE_ADMIN       => 5,
            self::ROLE_AGENT_ADMIN => 4,
            self::ROLE_OWNER_ADMIN => 3,
            self::ROLE_OWNER       => 2,
            self::ROLE_TENANT      => 1,
        ];

        return $levels[$this->role] ?? 0;
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

    public function notificationRecipients()
    {
        return $this->hasMany(NotificationRecipient::class);
    }

    public function unreadNotifications()
    {
        return $this->hasManyThrough(
            Notification::class,  
            NotificationRecipient::class, 
            'user_id',    
            'id',        
            'id',         
            'notification_id' 
        )->whereNull('notification_recipients.read_at');
    }
}