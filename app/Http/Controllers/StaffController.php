<?php

namespace App\Http\Controllers;

use App\Models\{Staff, UserManagement};
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

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

    public function create()
    {
        $currentUser = Auth::user();
        $managementList = [];

        // If the logged-in user is a super admin, fetch the list of management accounts for the dropdown
        if ($currentUser->role === 'admin' || Gate::allows('super-admin')) {
            $managementList = UserManagement::with('user')->get();
        }

        return view('adminSide.userManagement.staff.create', compact('managementList'));
    }

    public function store(Request $request)
    {
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
        $user = Auth::user();
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
        $user = Auth::user();

        $staff = Staff::with(['user', 'user_management'])
            ->when($user->role !== 'admin' && !Gate::allows('super-admin'), function ($q) use ($user) {
                $currentMgntId = optional($user->user_management)->id;
                abort_unless($currentMgntId, 403, 'Management profile not found.');
                $q->where('user_mgnt_id', $currentMgntId);
            })
            // Use user_id if your route passes the User ID, or change to 'id' if it passes the Staff table ID
            ->where(function ($query) use ($id) {
                $query->where('id', $id)->orWhere('user_id', $id);
            })
            ->firstOrFail();

        // If an admin needs to select a management account during edit, fetch the list too:
        $managementList = [];
        if ($user->role === 'admin' || Gate::allows('super-admin')) {
            $managementList = UserManagement::with('user')->get();
        }

        return view('adminSide.userManagement.staff.edit', compact('staff', 'managementList'));
    }

    /**
     * Update staff & user data.
     */
    public function update(Request $request, string $id)
    {
        $currentUser = Auth::user();

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

        // Validate request inputs (including verify_email checkbox and active boolean values)
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
                // If checked, verify email now (keep existing timestamp if already verified, or set to now)
                $userData['email_verified_at'] = $user->email_verified_at ?? now();
            } else {
                // If unchecked, unverify email
                $userData['email_verified_at'] = null;
            }

            // If email was explicitly changed and the verification checkbox wasn't ticked, reset verification
            if ($user->email !== $request->email && !$request->has('verify_email')) {
                $userData['email_verified_at'] = null;
            }

            $user->update($userData);

            // 2. Update Staff record (operational role and status)
            $staff->update([
                'role'      => $request->role,
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
        $user = Auth::user();
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
