<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('utility_tariffs')) Schema::create('utility_tariffs', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('management_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('kind', 20); $t->string('reference_key')->nullable()->index();
            $t->string('name'); $t->string('provider')->nullable(); $t->string('state')->nullable();
            $t->string('customer_category')->nullable(); $t->string('utility_type', 30)->default('water');
            $t->boolean('is_active')->default(true); $t->timestamps();
            $t->index(['management_user_id', 'kind', 'is_active']);
        });
        if (!Schema::hasTable('utility_tariff_versions')) Schema::create('utility_tariff_versions', function (Blueprint $t) {
            $t->ulid('id')->primary(); $t->foreignUlid('utility_tariff_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('version'); $t->date('effective_from')->nullable(); $t->json('definition');
            $t->text('source_url')->nullable(); $t->dateTime('verified_at')->nullable();
            $t->foreignUlid('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignUlid('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('reason'); $t->timestamps(); $t->unique(['utility_tariff_id', 'version']);
            $t->index(['utility_tariff_id', 'effective_from']);
        });
        if (!Schema::hasTable('utility_tariff_assignments')) Schema::create('utility_tariff_assignments', function (Blueprint $t) {
            $t->ulid('id')->primary(); $t->foreignUlid('management_user_id')->constrained('users')->restrictOnDelete();
            $t->foreignUlid('utility_service_id')->constrained()->restrictOnDelete();
            $t->foreignUlid('utility_tariff_id')->constrained()->restrictOnDelete();
            $t->date('effective_from'); $t->foreignUlid('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('reason'); $t->timestamps(); $t->unique(['utility_service_id', 'effective_from'], 'utility_assignment_service_date_unique');
        });
        // MySQL DDL commits independently. Resume a partially created assignment table
        // without dropping any existing data when an earlier schema operation failed.
        if (!Schema::hasIndex('utility_tariff_assignments', 'utility_assignment_service_date_unique')) {
            Schema::table('utility_tariff_assignments', fn (Blueprint $t) => $t->unique(['utility_service_id', 'effective_from'], 'utility_assignment_service_date_unique'));
        }
        $assignmentForeigns = array_column(Schema::getForeignKeys('utility_tariff_assignments'), 'columns');
        foreach (['management_user_id' => 'users', 'utility_service_id' => 'utility_services', 'utility_tariff_id' => 'utility_tariffs', 'created_by' => 'users'] as $column => $target) {
            if (!in_array([$column], $assignmentForeigns, true)) {
                Schema::table('utility_tariff_assignments', fn (Blueprint $t) => $t->foreign($column)->references('id')->on($target)->restrictOnDelete());
            }
        }
        Schema::create('utility_meter_events', function (Blueprint $t) {
            $t->ulid('id')->primary(); $t->foreignUlid('management_user_id')->constrained('users')->restrictOnDelete();
            $t->foreignUlid('utility_service_id')->constrained()->restrictOnDelete();
            $t->string('type', 30); $t->date('event_date'); $t->decimal('old_closing', 15, 3)->nullable();
            $t->decimal('new_opening', 15, 3)->nullable(); $t->decimal('capacity', 15, 3)->nullable();
            $t->string('old_meter_number')->nullable(); $t->string('new_meter_number')->nullable();
            $t->text('reason'); $t->foreignUlid('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignUlid('consumed_by_utility_id')->nullable()->constrained('utilities')->restrictOnDelete();
            $t->timestamps(); $t->index(['utility_service_id', 'event_date']);
        });
        Schema::table('utility_services', function (Blueprint $t) { $t->string('billing_scope', 20)->default('individual'); });
        Schema::table('utilities', function (Blueprint $t) {
            $t->string('entry_key', 64)->nullable()->unique(); $t->decimal('consumption', 15, 3)->nullable();
            $t->foreignUlid('utility_tariff_version_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignUlid('utility_meter_event_id')->nullable()->constrained()->restrictOnDelete();
            $t->unsignedInteger('allocation_revision')->default(0);
        });
        Schema::create('utility_allocations', function (Blueprint $t) {
            $t->ulid('id')->primary(); $t->foreignUlid('management_user_id')->constrained('users')->restrictOnDelete();
            $t->foreignUlid('utility_id')->constrained('utilities')->restrictOnDelete();
            $t->unsignedInteger('revision'); $t->string('beneficiary_key', 30);
            $t->foreignUlid('lease_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('method', 30); $t->bigInteger('weight')->nullable(); $t->unsignedInteger('amount');
            $t->date('from_date'); $t->date('to_date'); $t->json('snapshot'); $t->text('reason');
            $t->foreignUlid('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignUlid('invoice_item_id')->nullable()->constrained()->restrictOnDelete();
            $t->dateTime('superseded_at')->nullable(); $t->text('superseded_reason')->nullable(); $t->timestamps();
            $t->unique(['utility_id', 'revision', 'beneficiary_key'], 'utility_allocation_beneficiary_unique');
        });
        Schema::create('utility_corrections', function (Blueprint $t) {
            $t->ulid('id')->primary(); $t->foreignUlid('management_user_id')->constrained('users')->restrictOnDelete();
            $t->foreignUlid('utility_id')->constrained('utilities')->restrictOnDelete();
            $t->foreignUlid('utility_allocation_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignUlid('original_invoice_item_id')->nullable()->constrained('invoice_items')->restrictOnDelete();
            $t->foreignUlid('replacement_invoice_item_id')->nullable()->constrained('invoice_items')->restrictOnDelete();
            $t->foreignUlid('created_by')->constrained('users')->restrictOnDelete();
            $t->json('original_snapshot'); $t->json('replacement_snapshot'); $t->bigInteger('difference');
            $t->text('reason'); $t->string('status', 20)->default('proposed'); $t->timestamps();
        });

        // Import references as unverified. No scraping, verification, invoice update or repricing.
        $now = now();
        foreach (config('utility_tariffs.profiles', []) as $key => $profile) {
            $parts = explode(' — ', $profile['label']);
            $id = (string) Str::ulid();
            DB::table('utility_tariffs')->insert(['id' => $id, 'kind' => 'official', 'reference_key' => $key,
                'name' => $profile['label'], 'state' => $parts[0] ?? null, 'provider' => $parts[1] ?? null,
                'customer_category' => $parts[2] ?? null, 'utility_type' => $profile['kind'],
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('utility_tariff_versions')->insert(['id' => (string) Str::ulid(), 'utility_tariff_id' => $id,
                'version' => 1, 'effective_from' => $profile['effective_date'] ?? null,
                'definition' => json_encode($profile), 'source_url' => $profile['source'] ?? null,
                'reason' => 'Imported legacy reference. Unverified until a Super Admin records documentary verification.',
                'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('settings')->where('key', 'utility_tariff_config')->orderBy('id')->chunk(100, function ($settings) use ($now) {
            foreach ($settings as $setting) {
                if (!DB::table('users')->where('id', $setting->user_id)->exists()) continue;
                $saved = json_decode($setting->value, true);
                foreach ($saved['profiles'] ?? [] as $key => $override) {
                    $reference = config('utility_tariffs.profiles.'.$key);
                    if (!$reference || !is_array($override)) continue;
                    $profile = array_replace($reference, $override); $id = (string) Str::ulid();
                    DB::table('utility_tariffs')->insert(['id' => $id, 'management_user_id' => $setting->user_id,
                        'kind' => 'custom', 'reference_key' => $key, 'name' => 'Landlord configured: '.$reference['label'],
                        'utility_type' => $reference['kind'], 'is_active' => (bool) $setting->is_active, 'created_at' => $now, 'updated_at' => $now]);
                    DB::table('utility_tariff_versions')->insert(['id' => (string) Str::ulid(), 'utility_tariff_id' => $id,
                        'version' => 1, 'effective_from' => $profile['effective_date'] ?? '1970-01-01',
                        'definition' => json_encode($profile + ['minimum_policy' => 'monthly']),
                        'created_by' => $setting->user_id, 'reason' => 'Preserved existing account configured rates; not an official tariff.',
                        'created_at' => $now, 'updated_at' => $now]);
                }
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Utility billing provenance is retained. Use a forward migration rather than deleting tariff versions or financial history.');
    }
};
