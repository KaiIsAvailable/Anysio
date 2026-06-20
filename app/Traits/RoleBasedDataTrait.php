<?php
namespace App\Traits;

use App\Models\{User, Owners, Property, Room};
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;

trait RoleBasedDataTrait
{
    /**
     * 根据角色获取 Owners 列表
     */
    protected function getAuthorizedOwners()
    {
        $user = Auth::user();

        if (Gate::allows('super-admin')) {
            return User::whereIn('role', ['owner', 'ownerAdmin', 'agentAdmin', 'admin'])->get(['id', 'name']);
        }

        if ($user->role === 'agentAdmin') {
            $ownerIds = Owners::where('agent_id', $user->id)->pluck('user_id');
            return User::whereIn('id', $ownerIds)->where('role', 'owner')->get(['id', 'name']);
        }

        if ($user->role === 'ownerAdmin') {
            return User::where('id', $user->id)->get(['id', 'name']);
        }

        return collect();
    }
}