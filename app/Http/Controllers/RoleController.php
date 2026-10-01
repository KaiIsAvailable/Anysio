<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Role;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Facades\{Log, Auth, Gate};

class RoleController extends Controller
{
    public function store(Request $request)
    {
        if (Gate::denies('owner-admin') && Gate::denies('settings.create new role')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }

        $user = get_effective_user();
        $userId = $user->id;

        // 💡 1. Add unique validation scoped to this team/user if needed, 
        // or keep your standard validation and add unique check with team_id constraint
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                // Optional but recommended: ensure role name is unique for this specific team/user
                Rule::unique('roles')->where(function ($query) use ($userId) {
                    return $query->where('team_id', $userId);
                }),
            ],
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name', // 💡 Optional security check to ensure valid permission names
        ]);

        // 1. Get the effective user model, then extract their ULID string
        app(PermissionRegistrar::class)->setPermissionsTeamId($userId);

        // 2. Use 'team_id' so it matches your database column
        $role = Role::create([
            'team_id' => $userId, 
            'name' => $request->input('name'),
            'guard_name' => 'web',
        ]);

        // 💡 3. Sync permissions safely (fall back to empty array if null)
        $role->syncPermissions($request->input('permissions', []));

        // If requested via AJAX/fetch, flash to session and return success json
        if ($request->expectsJson()) {
            session()->flash('success', "Staff role ({$role->name}) created successfully.");

            return response()->json([
                'message' => "Staff role ({$role->name}) created successfully.",
                'role' => $role
            ], 201);
        }

        return redirect()->back()->with('success', 'Staff role ' . $role->name . ' created successfully.');
    }

    public function destroy(Request $request, Role $role)
    {
        if (Gate::denies('owner-admin') && Gate::denies('settings.delete user role')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }

        $user = get_effective_user();

        // Ensure the role belongs to the current effective user/team
        if ($role->team_id !== $user->id) {
            return redirect()->back()->with('error', 'You have no permission.');
        }

        // Delete the role
        $role->delete();

        $activeTab = $request->input('active_tab') ?? $request->input('tab') ?? $request->query('tab');

        // 3. Redirect back while preserving the tab
        if ($activeTab) {
            $previousUrl = strtok(url()->previous(), '?');
            return redirect()->to($previousUrl . '?tab=' . $activeTab)->with('success', 'Role deleted successfully.');
        }
    }
}