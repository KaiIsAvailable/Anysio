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

        $currentUser = get_effective_user();

        // 💡 1. Determine the target team_id: 
        // If the current user is an admin (and provided a target user_id in the request), use that. Otherwise, use their own ID.
        $userId = ($currentUser->role === 'admin' || Gate::allows('super-admin')) 
            ? $request->input('user_id', $currentUser->id) 
            : $currentUser->id;

        // 💡 2. Validate request inputs, including an optional validation for admin user selection
        $validationRules = [
            'name' => [
                'required',
                'string',
                'max:255',
                // Ensure role name is unique for this specific target team/user
                Rule::unique('roles')->where(function ($query) use ($userId) {
                    return $query->where('team_id', $userId);
                }),
            ],
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ];

        // If admin, validate that the selected user_id actually exists
        if ($currentUser->role === 'admin' || Gate::allows('super-admin')) {
            $validationRules['user_id'] = 'nullable|exists:users,id';
        }

        $request->validate($validationRules);

        // 3. Set Spatie's permission team context
        app(PermissionRegistrar::class)->setPermissionsTeamId($userId);

        // 4. Create the role using the resolved team_id
        $role = Role::create([
            'team_id' => $userId, 
            'name' => $request->input('name'),
            'guard_name' => 'web',
        ]);

        // 5. Sync permissions safely
        $role->syncPermissions($request->input('permissions', []));

        // If requested via AJAX/fetch
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