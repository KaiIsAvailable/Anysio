<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');

        throw_if(empty($tableNames), Exception::class, 'Error: config/permission.php not loaded.');

        $modelMorphKey = $columnNames['model_morph_key'] ?? 'model_id';

        // 1. Alter model_has_roles table model_id to ulid (char 26)
        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($modelMorphKey) {
            $table->ulid($modelMorphKey)->change();
        });

        // 2. Alter model_has_permissions table model_id to ulid (char 26)
        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($modelMorphKey) {
            $table->ulid($modelMorphKey)->change();
        });
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $modelMorphKey = $columnNames['model_morph_key'] ?? 'model_id';

        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($modelMorphKey) {
            $table->unsignedBigInteger($modelMorphKey)->change();
        });

        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($modelMorphKey) {
            $table->unsignedBigInteger($modelMorphKey)->change();
        });
    }
};