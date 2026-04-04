<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collaboration_external_links', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('owner_component', 64)->index();
            $table->string('subject_type', 64)->index();
            $table->string('subject_id', 120)->index();
            $table->string('organization_id', 64)->index();
            $table->string('scope_id', 64)->nullable()->index();
            $table->string('contact_name')->nullable();
            $table->string('contact_email');
            $table->string('token_hash', 64)->unique();
            $table->boolean('can_answer_questionnaire')->default(true);
            $table->boolean('can_upload_artifacts')->default(true);
            $table->string('issued_by_principal_id')->nullable();
            $table->string('revoked_by_principal_id')->nullable();
            $table->string('email_delivery_status', 32)->default('manual-only');
            $table->text('email_delivery_error')->nullable();
            $table->timestamp('email_last_attempted_at')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        if (Schema::hasTable('vendor_review_external_links')) {
            DB::table('vendor_review_external_links')
                ->orderBy('id')
                ->get()
                ->each(static function (object $row): void {
                    DB::table('collaboration_external_links')->insertOrIgnore([
                        'id' => (string) $row->id,
                        'owner_component' => 'third-party-risk',
                        'subject_type' => 'vendor-review',
                        'subject_id' => (string) $row->review_id,
                        'organization_id' => (string) $row->organization_id,
                        'scope_id' => is_string($row->scope_id) ? $row->scope_id : null,
                        'contact_name' => is_string($row->contact_name) ? $row->contact_name : null,
                        'contact_email' => (string) $row->contact_email,
                        'token_hash' => (string) $row->token_hash,
                        'can_answer_questionnaire' => (bool) $row->can_answer_questionnaire,
                        'can_upload_artifacts' => (bool) $row->can_upload_artifacts,
                        'issued_by_principal_id' => is_string($row->issued_by_principal_id) ? $row->issued_by_principal_id : null,
                        'revoked_by_principal_id' => is_string($row->revoked_by_principal_id) ? $row->revoked_by_principal_id : null,
                        'email_delivery_status' => is_string($row->email_delivery_status ?? null) ? $row->email_delivery_status : 'manual-only',
                        'email_delivery_error' => is_string($row->email_delivery_error ?? null) ? $row->email_delivery_error : null,
                        'email_last_attempted_at' => $row->email_last_attempted_at,
                        'email_sent_at' => $row->email_sent_at,
                        'expires_at' => $row->expires_at,
                        'last_accessed_at' => $row->last_accessed_at,
                        'revoked_at' => $row->revoked_at,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('collaboration_external_links');
    }
};
