<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('continuity_service_dependencies', function (Blueprint $table): void {
            $table->string('id', 120)->primary();
            $table->string('organization_id', 64);
            $table->string('source_service_id', 120);
            $table->string('depends_on_service_id', 120);
            $table->string('dependency_kind', 80);
            $table->string('recovery_notes', 255)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'source_service_id']);
            $table->unique(['organization_id', 'source_service_id', 'depends_on_service_id'], 'continuity_service_dependency_unique');
        });

        Schema::create('continuity_plan_exercises', function (Blueprint $table): void {
            $table->string('id', 120)->primary();
            $table->string('organization_id', 64);
            $table->string('plan_id', 120);
            $table->date('exercise_date');
            $table->string('exercise_type', 80);
            $table->string('scenario_summary', 255);
            $table->string('outcome', 40);
            $table->string('follow_up_summary', 255)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'plan_id']);
            $table->index(['plan_id', 'exercise_date']);
        });

        Schema::create('continuity_plan_test_executions', function (Blueprint $table): void {
            $table->string('id', 120)->primary();
            $table->string('organization_id', 64);
            $table->string('plan_id', 120);
            $table->date('executed_on');
            $table->string('execution_type', 80);
            $table->string('status', 40);
            $table->string('participants', 255)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'plan_id']);
            $table->index(['plan_id', 'executed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('continuity_plan_test_executions');
        Schema::dropIfExists('continuity_plan_exercises');
        Schema::dropIfExists('continuity_service_dependencies');
    }
};
