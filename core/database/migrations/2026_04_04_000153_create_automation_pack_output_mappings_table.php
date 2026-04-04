<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_pack_output_mappings', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('automation_pack_id')->index();
            $table->string('organization_id')->index();
            $table->string('scope_id')->nullable()->index();
            $table->string('mapping_label', 180);
            $table->string('mapping_kind', 40);
            $table->string('target_subject_type', 80)->nullable();
            $table->string('target_subject_id', 80)->nullable();
            $table->string('workflow_key', 180)->nullable();
            $table->string('transition_key', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_applied_at')->nullable();
            $table->string('last_status', 40)->default('never');
            $table->text('last_message')->nullable();
            $table->string('created_by_principal_id')->nullable()->index();
            $table->string('updated_by_principal_id')->nullable()->index();
            $table->timestamps();

            $table->index(['automation_pack_id', 'mapping_kind'], 'automation_pack_output_mappings_pack_kind_idx');
            $table->index(['organization_id', 'scope_id'], 'automation_pack_output_mappings_tenancy_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_pack_output_mappings');
    }
};
