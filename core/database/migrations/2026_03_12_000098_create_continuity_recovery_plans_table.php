<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('continuity_recovery_plans', function (Blueprint $table): void {
            $table->string('id', 120)->primary();
            $table->string('service_id', 120);
            $table->string('organization_id', 64);
            $table->string('scope_id', 64)->nullable();
            $table->string('title', 160);
            $table->string('strategy_summary', 255);
            $table->date('test_due_on')->nullable();
            $table->string('linked_policy_id', 120)->nullable();
            $table->string('linked_finding_id', 120)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'scope_id']);
            $table->index(['service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('continuity_recovery_plans');
    }
};
