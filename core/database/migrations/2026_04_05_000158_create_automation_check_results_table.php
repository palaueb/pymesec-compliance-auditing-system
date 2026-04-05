<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_check_results', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('automation_pack_run_id')->index();
            $table->string('automation_pack_id')->index();
            $table->string('automation_output_mapping_id')->nullable()->index();
            $table->string('organization_id')->index();
            $table->string('scope_id')->nullable()->index();
            $table->string('trigger_mode', 40)->default('manual');
            $table->string('mapping_kind', 40)->default('evidence-refresh');
            $table->string('target_subject_type', 80)->nullable();
            $table->string('target_subject_id')->nullable();
            $table->string('status', 40)->default('failed');
            $table->string('outcome', 40)->default('error');
            $table->string('severity', 40)->nullable();
            $table->text('message')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index(['automation_pack_id', 'checked_at'], 'automation_check_results_pack_checked_idx');
            $table->index(['organization_id', 'scope_id', 'checked_at'], 'automation_check_results_tenancy_checked_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_check_results');
    }
};
