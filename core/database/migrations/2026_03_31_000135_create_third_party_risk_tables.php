<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('organization_id')->index();
            $table->string('scope_id')->nullable()->index();
            $table->string('legal_name');
            $table->string('vendor_status')->default('prospective');
            $table->string('tier')->default('medium');
            $table->string('service_summary');
            $table->string('website')->nullable();
            $table->string('primary_contact_name')->nullable();
            $table->string('primary_contact_email')->nullable();
            $table->timestamps();
        });

        Schema::create('vendor_reviews', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('vendor_id')->index();
            $table->string('organization_id')->index();
            $table->string('scope_id')->nullable()->index();
            $table->string('title');
            $table->string('inherent_risk')->default('medium');
            $table->text('review_summary');
            $table->text('decision_notes')->nullable();
            $table->string('linked_asset_id')->nullable();
            $table->string('linked_control_id')->nullable();
            $table->string('linked_risk_id')->nullable();
            $table->string('linked_finding_id')->nullable();
            $table->date('next_review_due_on')->nullable();
            $table->string('created_by_principal_id')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'created_at'], 'vendor_reviews_vendor_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_reviews');
        Schema::dropIfExists('vendors');
    }
};
