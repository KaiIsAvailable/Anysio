<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $this->renamePermissions('maintenance', 'service request');
    }

    public function down(): void
    {
        $this->renamePermissions('service request', 'maintenance');
    }

    private function renamePermissions(string $from, string $to): void
    {
        DB::transaction(function () use ($from, $to) {
            $table = config('permission.table_names.permissions');
            $permissionKey = config('permission.column_names.permission_pivot_key') ?: 'permission_id';
            if (Schema::hasTable($table)) {
                foreach (DB::table($table)->where('name', 'like', $from.'.%')->orderBy('id')->get() as $permission) {
                    $name = $to.substr($permission->name, strlen($from));
                    $existing = DB::table($table)->where('name', $name)->where('guard_name', $permission->guard_name)->first();
                    if (!$existing) {
                        // Keep the original ID, so existing assignments stay intact.
                        DB::table($table)->where('id', $permission->id)->update(['name' => $name]);
                        continue;
                    }

                    // Application bootstrap may already have registered the new
                    // names. Merge both sets of grants without duplicate pivots.
                    foreach (['role_has_permissions', 'model_has_permissions'] as $pivotName) {
                        $pivot = config('permission.table_names.'.$pivotName);
                        if (!Schema::hasTable($pivot)) continue;
                        $grants = DB::table($pivot)->where($permissionKey, $permission->id)->get();
                        foreach ($grants->chunk(200) as $chunk) {
                            DB::table($pivot)->insertOrIgnore($chunk->map(fn ($grant) => array_replace(
                                (array) $grant, [$permissionKey => $existing->id]
                            ))->all());
                        }
                        DB::table($pivot)->where($permissionKey, $permission->id)->delete();
                    }
                    DB::table($table)->where('id', $permission->id)->delete();
                }
            }

            if (Schema::hasColumn('users', 'account_permissions')) {
                DB::table('users')->whereNotNull('account_permissions')->select(['id', 'account_permissions'])
                    ->chunkById(200, function ($users) use ($from, $to) {
                        foreach ($users as $user) {
                            $original = json_decode($user->account_permissions, true, 512, JSON_THROW_ON_ERROR);
                            $renamed = array_values(array_unique(array_map(
                                fn ($name) => str_starts_with($name, $from.'.') ? $to.substr($name, strlen($from)) : $name,
                                $original
                            )));
                            if ($renamed !== $original) {
                                DB::table('users')->where('id', $user->id)->update([
                                    'account_permissions' => json_encode($renamed, JSON_THROW_ON_ERROR),
                                ]);
                            }
                        }
                    });
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
