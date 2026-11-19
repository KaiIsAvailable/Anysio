<?php

use App\Services\FeeTypeService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $names = FeeTypeService::categoryPermissions();
            // Existing accounts retain their catalogs until an administrator
            // explicitly closes a category. Do not overwrite partial selections.
            DB::table('users')->whereIn('role', ['agentAdmin', 'ownerAdmin'])
                ->select(['id', 'account_permissions'])->chunkById(200, function ($users) use ($names) {
                    foreach ($users as $user) {
                        $grants = json_decode($user->account_permissions ?? '[]', true, 512, JSON_THROW_ON_ERROR) ?? [];
                        if (array_intersect($grants, $names)) continue;
                        DB::table('users')->where('id', $user->id)->update([
                            'account_permissions' => json_encode(array_values(array_unique(array_merge($grants, $names))), JSON_THROW_ON_ERROR),
                        ]);
                    }
                });

            $permissions = config('permission.table_names.permissions');
            $roles = config('permission.table_names.roles');
            $pivot = config('permission.table_names.role_has_permissions');
            if (!Schema::hasTable($permissions) || !Schema::hasTable($roles) || !Schema::hasTable($pivot)) return;
            $ids = [];
            foreach ($names as $name) {
                $ids[] = DB::table($permissions)->where('name', $name)->where('guard_name', 'web')->value('id')
                    ?? DB::table($permissions)->insertGetId(['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
            }
            $permissionKey = config('permission.column_names.permission_pivot_key') ?: 'permission_id';
            $roleKey = config('permission.column_names.role_pivot_key') ?: 'role_id';
            DB::table($roles)->where('guard_name', 'web')->whereNotExists(function ($query) use ($pivot, $roles, $roleKey, $permissionKey, $ids) {
                $query->selectRaw('1')->from($pivot)->whereColumn($pivot.'.'.$roleKey, $roles.'.id')->whereIn($permissionKey, $ids);
            })->select('id')->chunkById(100, function ($batch) use ($pivot, $ids, $permissionKey, $roleKey) {
                $grants = [];
                foreach ($batch as $role) foreach ($ids as $id) $grants[] = [$roleKey => $role->id, $permissionKey => $id];
                DB::table($pivot)->insertOrIgnore($grants);
            });
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Data-only provisioning: retain grants and subsequent administrator
        // choices on rollback. No fee records or financial history were changed.
    }
};
