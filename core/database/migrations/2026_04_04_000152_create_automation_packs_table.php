<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_packs', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('organization_id', 64)->index();
            $table->string('scope_id', 64)->nullable()->index();
            $table->string('pack_key', 160)->index();
            $table->string('name', 180);
            $table->string('summary', 400)->nullable();
            $table->string('version', 64)->nullable();
            $table->string('provider_type', 32)->default('community');
            $table->string('source_ref', 512)->nullable();
            $table->string('provenance_type', 32)->default('plugin');
            $table->string('owner_principal_id')->nullable();
            $table->string('lifecycle_state', 32)->default('discovered')->index();
            $table->boolean('is_installed')->default(false)->index();
            $table->boolean('is_enabled')->default(false)->index();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->string('health_state', 32)->default('unknown')->index();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->text('last_failure_reason')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'scope_id', 'pack_key'], 'automation_packs_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_packs');
    }
};
