<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_failure_findings', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('organization_id')->index();
            $table->string('scope_id')->nullable()->index();
            $table->string('automation_pack_id')->index();
            $table->string('automation_output_mapping_id')->nullable()->index();
            $table->string('fingerprint', 190)->unique();
            $table->string('target_subject_type', 80)->nullable();
            $table->string('target_subject_id')->nullable();
            $table->string('finding_id')->index();
            $table->string('first_check_result_id')->nullable()->index();
            $table->string('last_check_result_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_failure_findings');
    }
};
