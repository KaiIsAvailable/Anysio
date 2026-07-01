<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('parent_id')->nullable()->index();
            $table->foreign('parent_id')->references('id')->on('document_templates')->onDelete('set null');
            $table->ulid('user_id')->nullable()->index(); 
            $table->ulid('created_by')->nullable()->index(); 
            $table->string('category')->index(); // 'agreement', 'invoice', 'receipt'
            $table->string('title');
            $table->string('version')->default('1.0.0');
            $table->longText('details'); 
            $table->longText('html_template');
            $table->string('status')->default('active')->index();
            $table->boolean('is_system_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};