<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_processing_activities', function (Blueprint $table): void {
            $table->string('id', 120)->primary();
            $table->string('organization_id', 64);
            $table->string('scope_id', 64)->nullable();
            $table->string('title', 160);
            $table->string('purpose', 200);
            $table->string('lawful_basis', 120);
            $table->text('linked_data_flow_ids')->nullable();
            $table->text('linked_risk_ids')->nullable();
            $table->string('linked_policy_id', 120)->nullable();
            $table->string('linked_finding_id', 120)->nullable();
            $table->date('review_due_on')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_processing_activities');
    }
};
