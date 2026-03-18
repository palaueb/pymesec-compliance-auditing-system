<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_catalog_entries', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('organization_id')->index();
            $table->string('catalog_key', 120)->index();
            $table->string('option_key', 120);
            $table->string('label', 160);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(100);
            $table->boolean('is_active')->default(true)->index();
            $table->string('created_by_principal_id')->nullable()->index();
            $table->string('updated_by_principal_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['organization_id', 'catalog_key', 'option_key'], 'reference_catalog_entries_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_catalog_entries');
    }
};
