<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_records', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('organization_id')->index();
            $table->string('scope_id')->nullable()->index();
            $table->string('artifact_id')->index();
            $table->string('title', 160);
            $table->text('summary')->nullable();
            $table->string('evidence_kind', 60);
            $table->string('status', 40);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->date('review_due_on')->nullable();
            $table->date('validated_at')->nullable();
            $table->string('validated_by_principal_id')->nullable()->index();
            $table->text('validation_notes')->nullable();
            $table->string('created_by_principal_id')->nullable()->index();
            $table->string('updated_by_principal_id')->nullable()->index();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('evidence_record_links', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('evidence_id')->index();
            $table->string('domain_type', 80);
            $table->string('domain_id', 80);
            $table->string('domain_label', 200);
            $table->string('organization_id')->index();
            $table->string('scope_id')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['evidence_id', 'domain_type', 'domain_id'], 'evidence_record_links_unique');
            $table->index(['domain_type', 'domain_id'], 'evidence_record_links_domain_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_record_links');
        Schema::dropIfExists('evidence_records');
    }
};
