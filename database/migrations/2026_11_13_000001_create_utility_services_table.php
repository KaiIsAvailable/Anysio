<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_services', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('management_user_id')->constrained('users')->restrictOnDelete();
            $table->ulidMorphs('location');
            $table->string('type', 30);
            $table->string('billing_method', 20)->default('metered');
            $table->string('label');
            $table->string('meter_number')->nullable();
            $table->foreignUlid('parent_service_id')->nullable()->constrained('utility_services')->restrictOnDelete();
            $table->decimal('opening_reading', 15, 3)->nullable();
            $table->date('opening_reading_date')->nullable();
            $table->string('status', 20)->default('unconfirmed');
            $table->timestamps();
            // A location's service must not be recreated under another account.
            $table->unique(['location_type', 'location_id', 'type'], 'utility_services_location_type_unique');
            $table->index(['management_user_id', 'type', 'status'], 'utility_services_management_type_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_services');
    }
};
