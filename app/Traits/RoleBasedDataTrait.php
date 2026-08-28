<?php
namespace App\Traits;

use App\Models\{User, Owners, Property, Unit, Room,Staff};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

trait RoleBasedDataTrait
{
    /**
     * 统一的权限过滤逻辑，返回 Builder 对象以支持链式调用
     */
    protected function applyOwnershipFilter($query, $user, $column = 'created_by')
    {
        if (Gate::allows('super-admin')) {
            return $query;
        }

        // 获取有效用户（如果是 staff，会解析出其背后的主管理员/owner 账号）
        $effectiveUser = get_effective_user() ?? $user;

        return $query->where(function ($q) use ($effectiveUser, $column) {
            if ($effectiveUser->role === 'ownerAdmin') {
                $q->where($column, $effectiveUser->id);
            } elseif ($effectiveUser->role === 'agentAdmin') {
                $managedOwnerIds = Owners::where('agent_id', $effectiveUser->id)
                    ->select('user_id');

                $q->where(function ($sub) use (
                    $effectiveUser,
                    $column,
                    $managedOwnerIds
                ) {
                    $sub->where($column, $effectiveUser->id)
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
        $query = User::whereIn('role', ['owner', 'ownerAdmin']);
        
        return $this->applyOwnershipFilter($query, $user, 'id')->get();
    }

    protected function getAuthorizedOwnersOnly()
    {
        $user = Auth::user();
        $query = User::where('role', 'owner');
        
        return $this->applyOwnershipFilter($query, $user, 'id')->get();
    }

    protected function applyLeaseOwnershipFilter($query, $user)
    {
        if (Gate::allows('super-admin')) return $query;

        return $query->whereHas('tenant', function ($q) use ($user) {
            $this->applyOwnershipFilter($q, $user);
        });
    }
}