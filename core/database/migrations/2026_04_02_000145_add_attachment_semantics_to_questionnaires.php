<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questionnaire_template_items', function (Blueprint $table): void {
            $table->string('attachment_mode', 40)->default('none')->after('response_type');
            $table->string('attachment_upload_profile', 80)->nullable()->after('attachment_mode');
            $table->boolean('promote_attachments_to_evidence')->default(false)->after('attachment_upload_profile');
        });

        Schema::table('questionnaire_subject_items', function (Blueprint $table): void {
            $table->string('attachment_mode', 40)->default('none')->after('response_type');
            $table->string('attachment_upload_profile', 80)->nullable()->after('attachment_mode');
            $table->boolean('promote_attachments_to_evidence')->default(false)->after('attachment_upload_profile');
        });
    }

    public function down(): void
    {
        Schema::table('questionnaire_subject_items', function (Blueprint $table): void {
            $table->dropColumn([
                'attachment_mode',
                'attachment_upload_profile',
                'promote_attachments_to_evidence',
            ]);
        });

        Schema::table('questionnaire_template_items', function (Blueprint $table): void {
            $table->dropColumn([
                'attachment_mode',
                'attachment_upload_profile',
                'promote_attachments_to_evidence',
            ]);
        });
    }
};
