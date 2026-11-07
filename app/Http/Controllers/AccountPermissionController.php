<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccountPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AccountPermissionController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('super-admin');

        $accounts = User::whereIn('role', [User::ROLE_AGENT_ADMIN, User::ROLE_OWNER_ADMIN])
            ->orderBy('name')->get(['id', 'name', 'email', 'role']);
        $selectedId = $request->query('account_id', $accounts->first()?->id);
        $account = $selectedId
            ? User::whereIn('role', [User::ROLE_AGENT_ADMIN, User::ROLE_OWNER_ADMIN])->findOrFail($selectedId)
            : null;
        $modules = config('permissions.modules', []);

        return view('adminSide.setting.account-permissions', compact('accounts', 'account', 'modules'));
    }

    public function update(Request $request, User $user)
    {
        Gate::authorize('super-admin');
        abort_unless(in_array($user->role, [User::ROLE_AGENT_ADMIN, User::ROLE_OWNER_ADMIN], true), 404);

        $data = $request->validate([
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['required', 'string', 'distinct', Rule::in(AccountPermissionService::names())],
        ]);

        // This attribute is deliberately excluded from User::$fillable. Only this
        // system-admin endpoint may update it, independently of staff role grants.
        $user->forceFill(['account_permissions' => array_values($data['permissions'] ?? [])])->save();

        return redirect()->route('admin.settings.index', ['account_id' => $user->id])
            ->with('success', 'Permissions updated for '.$user->name.'.');
    }
}
