<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

class LandingPageService
{
    public function routeFor(User $user): string
    {
        if ($user->role === User::ROLE_TENANT) {
            return 'tenants.dashboard';
        }
        if ($user->role === User::ROLE_OWNER) {
            return 'admin.owners.dashboard';
        }
        if (!in_array($user->role, [User::ROLE_ADMIN, User::ROLE_AGENT_ADMIN, User::ROLE_OWNER_ADMIN, User::ROLE_STAFF], true)) {
            return 'welcome';
        }

        // Use the actual actor, so staff must pass their own role permissions as
        // well as their manager's grants. Order follows the main navigation.
        $gate = Gate::forUser($user);
        foreach ([
            'dashboard.tab' => 'dashboard',
            'owner.tab' => 'admin.owners.index',
            'tenant.tab' => 'admin.tenants.index',
            'property.tab' => 'admin.properties.index',
            'leases.tab' => 'admin.leases.index',
            'invoice.tab' => 'admin.invoices.index',
            'staff.tab' => 'admin.staff.index',
            'maintenance-tickets' => 'admin.maintenance.index',
            'leases.agreement template' => 'admin.document-templates.index',
            'document template.create' => 'admin.document-templates.create',
            'profile menu.setting' => 'admin.settings.index',
        ] as $permission => $route) {
            if ($gate->allows($permission)) {
                return $route;
            }
        }

        // Profile is available without module grants and cannot redirect back
        // into a denied dashboard when every main page has been disabled.
        return 'profile.edit';
    }
}
