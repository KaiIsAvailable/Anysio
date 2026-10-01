<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;
use App\Models\Staff;
use App\Models\UserManagement;

class SetPermissionTeamContext
{
    public function handle(Request $request, Closure $next)
    {
        // 1. Get the actual logged-in user (Auth::user()) for checking permissions
        $user = Auth::user();

        if ($user) {
            $teamId = $user->id; // Default for owners/admins

            // 2. If the user is staff, find their boss's user_id 
            // This is ONLY used to set Spatie's team scope so they inherit the boss's permissions!
            if ($user->role === 'staff') {
                $staff = Staff::where('user_id', $user->id)->first();

                if ($staff && $staff->user_mgnt_id) {
                    $userMgnt = UserManagement::find($staff->user_mgnt_id);

                    if ($userMgnt && $userMgnt->user_id) {
                        $teamId = $userMgnt->user_id;
                    }
                }
            }

            // 3. Lock in the team scope for Spatie
            app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        }

        return $next($request);
    }
}