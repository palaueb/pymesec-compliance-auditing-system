<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('continuity_services', function (Blueprint $table): void {
            $table->string('id', 120)->primary();
            $table->string('organization_id', 64);
            $table->string('scope_id', 64)->nullable();
            $table->string('title', 160);
            $table->string('impact_tier', 80);
            $table->unsignedInteger('recovery_time_objective_hours');
            $table->unsignedInteger('recovery_point_objective_hours');
            $table->string('linked_asset_id', 120)->nullable();
            $table->string('linked_risk_id', 120)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('continuity_services');
    }
};
