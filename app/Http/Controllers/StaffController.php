<?php

namespace App\Http\Controllers;

use App\Models\{Staff, UserManagement, User, Role};
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Auth, Gate};
use Spatie\Permission\PermissionRegistrar;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        if (Gate::denies('owner-admin') && Gate::denies('staff.tab')) {
            return view('errors.403');
        }

        $user = get_effective_user();

        $staff = Staff::query()
            ->with(['user_management.user'])
            // If the user is NOT a super admin, restrict to their specific management ID
            ->when(!$user->hasRole('admin') && !Gate::allows('super-admin'), function ($q) use ($user) {
                $currentMgntId = optional($user->user_management)->id;
                abort_unless($currentMgntId, 403);
                $q->where('staff.user_mgnt_id', $currentMgntId);
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->get('search');
                $q->where(function ($sub) use ($s) {
                    $sub->whereHas('user_management.user', function ($uq) use ($s) {
                        $uq->where('name', 'like', "%{$s}%")
                        ->orWhere('email', 'like', "%{$s}%");
                    })->orWhere('staff.role', 'like', "%{$s}%");
                });
            })
            ->orderByDesc('staff.created_at')
            ->paginate(5)
            ->onEachSide(1);

        return view('adminSide.userManagement.staff.index', compact('staff'));
    }

    public function create(Request $request)
    {
        if (Gate::denies('owner-admin') && Gate::denies('staff.create')) {
            return view('errors.403');
        }

        $currentUser = get_effective_user();
        $managementList = [];
        $roles = collect();

        if ($currentUser->role === 'admin') {
            $managementList = UserManagement::with('user')->get();
            // Admin gets all custom roles (with user_id) so Alpine.js can filter them based on the selected management account
            $roles = Role::pluck('name', 'name')->toArray();
        } else {
            // Non-admin only sees their own custom roles
            $roles = Role::where('team_id', $currentUser->id)->get();
        }

        return view('adminSide.userManagement.staff.create', compact('managementList', 'roles'));
    }

    public function store(Request $request)
    {
        if (Gate::denies('owner-admin') && Gate::denies('staff.create')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }

        // 1. Determine the management ID based on user role (Admin can choose, others use their own)
        $currentUser = $request->user();
        
        if ($currentUser->role === 'admin') {
            $request->validate([
                'user_mgnt_id' => 'required|exists:user_management,id',
            ]);
            $currentMgntId = $request->user_mgnt_id;
        } else {
            $currentMgntId = optional($currentUser->user_management)->id;
            abort_unless($currentMgntId, 403, 'Management profile not found.');
        }

        // 2. Validation Rules (Added verify_email_now boolean validation)
        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'email'            => 'required|string|email|max:255|unique:users,email',
            'password'         => 'required|string|min:8|confirmed',
            'role'             => 'required|string', 
            'verify_email_now' => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $plainPassword = $request->password;

            // 3. Create User record (for login) with verification check
            $newUser = User::create([
                'id'                => (string) Str::ulid(),
                'name'              => $data['name'],
                'email'             => $data['email'],
                'password'          => Hash::make($plainPassword),
                'role'              => 'staff', 
                'status'            => 'active',
                'is_agree'          => true,
                'email_verified_at' => $request->boolean('verify_email_now') ? now() : null, // <--- Handles manual verification flag
            ]);

            // 4. Create Staff record (operational role linkage)
            Staff::create([
                'id'           => (string) Str::ulid(),
                'user_id'      => $newUser->id,
                'user_mgnt_id' => $currentMgntId, 
                'role'         => $data['role'], 
                'is_active'    => true,
            ]);

            // 5. 👇 ASSIGN SPATIE ROLE & TEAM CONTEXT
            // Find the user ID of the owner/agency that owns this management account
            $ownerUserId = ($currentUser->role === 'admin') 
                ? UserManagement::where('id', $currentMgntId)->value('user_id') 
                : $currentUser->id;

            // Set Spatie's team context if you use teams (or leave as needed)
            // app(PermissionRegistrar::class)->...

            // Find the specific role created by this owner user
            $role = Role::where('name', $data['role'])
                ->where('team_id', $ownerUserId)
                ->first();

            // Fallback to a global role (where user_id is null) if a custom one isn't found
            if (!$role) {
                $role = Role::where('name', $data['role'])
                    ->whereNull('team_id')
                    ->firstOrFail();
            }

            // Assign the found role instance directly to the new user
            $newUser->assignRole($role);

            DB::commit();

            // 5. Return and flash success session data
            return redirect()->route('admin.staff.index')->with('success', 'Staff created successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withErrors(['error' => 'Failed to create staff: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Display staff details.
     */
    public function show(string $id)
    {
        if (Gate::denies('owner-admin') && Gate::denies('staff.show')) {
            return view('errors.403');
        }

        $user = get_effective_user();
        $currentMgntId = $user->user_management->id ?? null;

        $staff = Staff::with(['user', 'user_management.user'])
            ->where('user_mgnt_id', $currentMgntId)
            ->findOrFail($id);

        return view('adminSide.userManagement.staff.details', compact('staff'));
    }

    /**
     * Show edit form.
     */
    public function edit(string $id)
    {
        if (Gate::denies('owner-admin') && Gate::denies('staff.edit')) {
            return view('errors.403');
        }

        $user = get_effective_user();

        $staff = Staff::with(['user', 'user_management'])
            ->when($user->role !== 'admin' && !Gate::allows('super-admin'), function ($q) use ($user) {
                $currentMgntId = optional($user->user_management)->id;
                abort_unless($currentMgntId, 403, 'Management profile not found.');
                $q->where('user_mgnt_id', $currentMgntId);
            })
            ->where(function ($query) use ($id) {
                $query->where('id', $id)->orWhere('user_id', $id);
            })
            ->firstOrFail();

        $managementList = [];
        if ($user->role === 'admin' || Gate::allows('super-admin')) {
            $managementList = UserManagement::with('user')->get();
        }

        // Fetch all roles globally
        $roles = Role::where('team_id', $staff->user_management->user_id)->get();

        return view('adminSide.userManagement.staff.edit', compact('staff', 'managementList', 'roles'));
    }

    /**
     * Update staff & user data.
     */
    public function update(Request $request, string $id)
    {
        if (Gate::denies('owner-admin') && Gate::denies('staff.edit')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }

        $currentUser = get_effective_user();

        // Find staff record with admin override capability
        $staff = Staff::with(['user', 'user_management'])
            ->when($currentUser->role !== 'admin' && !Gate::allows('super-admin'), function ($q) use ($currentUser) {
                $currentMgntId = optional($currentUser->user_management)->id;
                abort_unless($currentMgntId, 403, 'Management profile not found.');
                $q->where('user_mgnt_id', $currentMgntId);
            })
            ->where(function ($query) use ($id) {
                $query->where('id', $id)->orWhere('user_id', $id);
            })
            ->firstOrFail();

        $user = $staff->user;

        // Validate request inputs (including role validation scoped to the team)
        $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email,' . $user->id,
            'role'         => 'required|string',
            'is_active'    => 'required|in:0,1',
            'verify_email' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($request, $user, $staff) {
            // 1. Prepare User update data
            $userData = [
                'name'  => $request->name,
                'email' => $request->email,
            ];

            // Handle Email Verification update
            if ($request->has('verify_email')) {
                $userData['email_verified_at'] = $user->email_verified_at ?? now();
            } else {
                $userData['email_verified_at'] = null;
            }

            if ($user->email !== $request->email && !$request->has('verify_email')) {
                $userData['email_verified_at'] = null;
            }

            $user->update($userData);

            // 2. Determine the correct Team ID based on the staff's management profile (works for both Admin and Owners)
            $teamId = optional($staff->user_management)->user_id ?? $staff->user_mgnt_id;

            if ($teamId) {
                // Set Spatie's active team context to the staff's boss team ID
                app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);

                // Find the role by NAME instead of ID, scoped to this team
                $role = Role::where('name', $request->role)
                    ->where('team_id', $teamId)
                    ->firstOrFail();

                // Sync the Spatie Role model instance safely
                $user->syncRoles([$role]);
            }

            // 3. Update Staff record with the role name and status
            $staff->update([
                'role'      => $request->role, // Stores the role name
                'is_active' => $request->is_active,
            ]);
        });

        return redirect()->route('admin.staff.index')->with('success', 'Staff updated successfully.');
    }

    /**
     * Remove staff and user.
     */
    public function destroy($id)
    {
        if (Gate::denies('owner-admin') && Gate::denies('staff.delete')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }

        $user = get_effective_user();
        $currentMgntId = $user->user_management->id ?? null;

        try {
            DB::transaction(function () use ($id, $currentMgntId) {
                $staff = Staff::where('user_mgnt_id', $currentMgntId)->findOrFail($id);
                $user = $staff->user;

                // 先删 staff 记录
                $staff->delete();
                // 再删 user 账号
                if ($user) {
                    $user->delete();
                }
            });

            return redirect()->route('admin.staff.index')
                             ->with('success', 'Staff deleted successfully.');

        } catch (\Exception $e) {
            return redirect()->back()
                             ->with('error', 'Error: ' . $e->getMessage());
        }
    }
}
