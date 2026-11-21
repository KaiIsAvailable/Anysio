<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;

class ResolveSettingsAccount
{
    public function handle(Request $request, Closure $next)
    {
        $id = $request->input('account_id');
        if (!$id && $request->routeIs('admin.settings.*', 'admin.utilities.tariffs*')) {
            $id = $request->input('management_user_id');
        }
        if (!$id && $request->routeIs('admin.roles.store') && Gate::allows('super-admin')) {
            $id = $request->input('user_id');
        }
        if (Gate::denies('super-admin')) {
            if ($id && $id !== get_effective_user()?->id) return $this->denied($request);
            return $next($request);
        }

        $accounts = User::whereIn('role', [User::ROLE_OWNER_ADMIN, User::ROLE_AGENT_ADMIN]);
        if ($id) {
            validator(['account_id' => $id], ['account_id' => ['required', 'string']])->validate();
            $account = $accounts->findOrFail($id);
            foreach (['management_user_id', 'user_id'] as $alias) {
                if ($request->filled($alias) && $request->input($alias) !== $account->id) return $this->denied($request);
            }
        } elseif ($request->isMethod('GET') && $request->routeIs('admin.settings.*', 'admin.fee-types.index')) {
            $account = $accounts->orderBy('name')->orderBy('id')->first();
        } else {
            if (!$request->isMethod('GET') && !$request->has('early_payment_config') &&
                $request->routeIs('admin.settings.update', 'admin.settings.user-role-permissions.update', 'admin.fee-types.*', 'admin.roles.*')) {
                throw \Illuminate\Validation\ValidationException::withMessages(['account_id' => 'Select a management account first.']);
            }
            $account = null;
        }

        if (!$account) return $next($request);
        $request->attributes->set('settings_account', $account);
        $registrar = app(PermissionRegistrar::class);
        $previousTeam = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($account->id);
        try {
            return $next($request);
        } finally {
            $request->attributes->remove('settings_account');
            $registrar->setPermissionsTeamId($previousTeam);
        }
    }

    private function denied(Request $request)
    {
        if ($request->isMethod('GET')) return response()->view('errors.403', [], 403);
        session()->flash('error', 'You have no permission.');
        return $request->expectsJson()
            ? response()->json(['message' => session('error')], 403)
            : back()->with('error', session('error'));
    }
}
