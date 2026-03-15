<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_campaigns', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('organization_id', 64);
            $table->string('scope_id', 64)->nullable();
            $table->string('framework_id', 64)->nullable();
            $table->string('title', 160);
            $table->string('summary', 500);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 32)->default('draft');
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'assessment_campaigns_org_status_idx');
            $table->index(['organization_id', 'scope_id'], 'assessment_campaigns_org_scope_idx');
        });

        Schema::create('assessment_campaign_controls', function (Blueprint $table): void {
            $table->string('id', 80)->primary();
            $table->string('assessment_id', 64);
            $table->string('control_id', 64);
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();

            $table->unique(['assessment_id', 'control_id'], 'assessment_campaign_controls_unique');
            $table->index(['assessment_id', 'position'], 'assessment_campaign_controls_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_campaign_controls');
        Schema::dropIfExists('assessment_campaigns');
    }
};
