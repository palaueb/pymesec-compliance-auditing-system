<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_pack_runs', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('automation_pack_id')->index();
            $table->string('organization_id')->index();
            $table->string('scope_id')->nullable()->index();
            $table->string('trigger_mode', 40)->default('manual');
            $table->string('status', 40)->default('running');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->unsignedInteger('total_mappings')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->text('summary')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('initiated_by_principal_id')->nullable()->index();
            $table->string('initiated_by_membership_id')->nullable()->index();
            $table->timestamps();

            $table->index(['organization_id', 'scope_id', 'started_at'], 'automation_pack_runs_tenancy_started_idx');
            $table->index(['automation_pack_id', 'started_at'], 'automation_pack_runs_pack_started_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_pack_runs');
    }
};
