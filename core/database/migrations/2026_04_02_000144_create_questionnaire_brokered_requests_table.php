<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questionnaire_brokered_requests', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('owner_component', 80);
            $table->string('subject_type', 80);
            $table->string('subject_id', 120);
            $table->string('organization_id', 64);
            $table->string('scope_id', 64)->nullable();
            $table->string('contact_name', 120);
            $table->string('contact_email', 160)->nullable();
            $table->string('collection_channel', 40);
            $table->string('collection_status', 40)->default('queued');
            $table->text('instructions')->nullable();
            $table->text('broker_notes')->nullable();
            $table->string('broker_principal_id', 64)->nullable();
            $table->string('issued_by_principal_id', 64)->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(
                ['owner_component', 'subject_type', 'subject_id'],
                'questionnaire_brokered_requests_subject_idx'
            );
            $table->index(
                ['organization_id', 'scope_id', 'collection_status'],
                'questionnaire_brokered_requests_context_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaire_brokered_requests');
    }
};
