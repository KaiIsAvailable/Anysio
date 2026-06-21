<?php
namespace App\Traits;

use App\Models\{User, Owners, Property, Unit, Room};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

trait RoleBasedDataTrait
{
    /**
     * 统一的权限过滤逻辑，返回 Builder 对象以支持链式调用
     */
    protected function applyOwnershipFilter($query, $user, $column = 'created_by')
    {
        if (Gate::allows('super-admin')) return $query;

        return $query->where(function ($q) use ($user, $column) {
            if ($user->role === 'ownerAdmin') {
                $q->where($column, $user->id);
            } elseif ($user->role === 'agentAdmin') {
                $managedOwnerIds = Owners::where('agent_id', $user->id)->select('user_id');
                
                $q->where(function ($sub) use ($user, $column, $managedOwnerIds) {
                    $sub->where($column, $user->id)
                        ->orWhereIn($column, $managedOwnerIds);
                });
            }
        });
    }

    protected function getAuthorizedProperties()
    {
        $query = Property::query();
        return Gate::allows('super-admin') ? $query : $this->applyOwnershipFilter($query, Auth::user());
    }

    protected function getAuthorizedUnits()
    {
        $query = Unit::query();
        return Gate::allows('super-admin') ? $query : $query->whereHas('property', function ($q) {
            $this->applyOwnershipFilter($q, Auth::user());
        });
    }

    protected function getAuthorizedRooms()
    {
        $query = Room::query();
        return Gate::allows('super-admin') ? $query : $query->whereHas('unit.property', function ($q) {
            $this->applyOwnershipFilter($q, Auth::user());
        });
    }

    protected function getAuthorizedOwners()
    {
        $user = Auth::user();
        
        // 1. Super Admin 拥有所有权限
        if (Gate::allows('super-admin')) {
            return User::whereIn('role', ['owner', 'ownerAdmin', 'agentAdmin', 'admin'])->get();
        }

        // 2. 普通逻辑
        return User::where(function ($q) use ($user) {
            if ($user->role === 'ownerAdmin') {
                $q->where('id', $user->id);
            } elseif ($user->role === 'agentAdmin') {
                // 获取该 Agent 管理的所有关联用户ID
                $ownerIds = Owners::where('agent_id', $user->id)->pluck('user_id');
                $q->whereIn('id', $ownerIds);
            }
        })
        // 关键点：这里应该允许 'owner' 和 'ownerAdmin'，除非你只想看 owner
        ->whereIn('role', ['owner', 'ownerAdmin']) 
        ->get();
    }

    protected function getEffectiveOwnerId(): string 
    {
        $user = Auth::user();
        if ($user->role === 'ownerAdmin') {
            return $user->id; // 这里返回的是 ULID 字符串
        }
        
        // AgentAdmin 情况：确保这里获取的也是 ULID 字符串
        $owner = Owners::where('agent_id', $user->id)->first();
        return $owner ? $owner->user_id : $user->id;
    }

    protected function applyLeaseOwnershipFilter($query, $user)
    {
        if (Gate::allows('super-admin')) return $query;

        return $query->whereHas('tenant', function ($q) use ($user) {
            // 直接调用你写好的那个包含 OwnerAdmin/AgentAdmin 逻辑的过滤器
            $this->applyOwnershipFilter($q, $user);
        });
    }
}