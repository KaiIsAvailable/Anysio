<?php
namespace App\Traits;

use App\Models\{User, Owners, Property, Unit, Room,Staff};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

trait RoleBasedDataTrait
{
    /**
     * Helper to resolve the effective user for data scoping.
     * If the user is staff, it returns the parent admin/agent-admin user. Otherwise, returns the user themselves.
     */
    protected function getEffectiveUser($user)
    {
        if ($user->role === User::ROLE_STAFF) {
            // Find the staff record and get the UserManagement record
            $staff = Staff::where('user_id', $user->id)->first();
            if ($staff && $staff->user_mgnt_id) {
                // Find the user who owns this user_management profile
                $managementUser = User::whereHas('user_management', function ($q) use ($staff) {
                    $q->where('id', $staff->user_mgnt_id);
                })->first();

                if ($managementUser) {
                    return $managementUser;
                }
            }
        }

        return $user;
    }
    /**
     * 统一的权限过滤逻辑，返回 Builder 对象以支持链式调用
     */
    protected function applyOwnershipFilter($query, $user, $column = 'created_by')
    {
        if (Gate::allows('super-admin')) {
            return $query;
        }

        return $query->where(function ($q) use ($user, $column) {
            if ($user->role === 'ownerAdmin') {
                $q->where($column, $user->id);
            } elseif ($user->role === 'agentAdmin') {
                $managedOwnerIds = Owners::where('agent_id', $user->id)
                    ->select('user_id');

                $q->where(function ($sub) use (
                    $user,
                    $column,
                    $managedOwnerIds
                ) {
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