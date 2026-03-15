<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_control_reviews', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('assessment_id');
            $table->string('control_id');
            $table->string('organization_id');
            $table->string('scope_id')->nullable();
            $table->string('result', 40)->default('not-tested');
            $table->text('test_notes')->nullable();
            $table->text('conclusion')->nullable();
            $table->date('reviewed_on')->nullable();
            $table->string('reviewer_principal_id')->nullable();
            $table->string('linked_finding_id')->nullable();
            $table->timestamps();

            $table->unique(['assessment_id', 'control_id']);
            $table->index(['organization_id', 'scope_id']);
            $table->index('linked_finding_id');
            $table->foreign('assessment_id')->references('id')->on('assessment_campaigns')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_control_reviews');
    }
};
