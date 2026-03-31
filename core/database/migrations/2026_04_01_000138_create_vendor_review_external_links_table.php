<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_review_external_links', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('review_id')->index();
            $table->string('organization_id')->index();
            $table->string('scope_id')->nullable()->index();
            $table->string('contact_name')->nullable();
            $table->string('contact_email');
            $table->string('token_hash', 64)->unique();
            $table->boolean('can_answer_questionnaire')->default(true);
            $table->boolean('can_upload_artifacts')->default(true);
            $table->string('issued_by_principal_id')->nullable();
            $table->string('revoked_by_principal_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_review_external_links');
    }
};
