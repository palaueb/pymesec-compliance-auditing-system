<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_evidence_delivery_states', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('organization_id')->index();
            $table->string('scope_id')->nullable()->index();
            $table->string('automation_output_mapping_id')->index();
            $table->string('target_subject_type', 80);
            $table->string('target_subject_id');
            $table->string('last_payload_fingerprint', 190)->nullable();
            $table->string('last_check_outcome', 40)->nullable();
            $table->string('last_artifact_id')->nullable();
            $table->timestamp('last_delivered_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['automation_output_mapping_id', 'target_subject_type', 'target_subject_id'],
                'automation_evidence_delivery_states_target_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_evidence_delivery_states');
    }
};
