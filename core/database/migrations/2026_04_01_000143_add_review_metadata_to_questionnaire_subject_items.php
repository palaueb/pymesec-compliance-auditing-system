<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questionnaire_subject_items', function (Blueprint $table): void {
            $table->text('review_notes')->nullable()->after('follow_up_notes');
            $table->string('reviewed_by_principal_id')->nullable()->after('review_notes');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_principal_id');
            $table->index('reviewed_by_principal_id', 'questionnaire_subject_items_reviewed_by_idx');
            $table->index('reviewed_at', 'questionnaire_subject_items_reviewed_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('questionnaire_subject_items', function (Blueprint $table): void {
            $table->dropIndex('questionnaire_subject_items_reviewed_by_idx');
            $table->dropIndex('questionnaire_subject_items_reviewed_at_idx');
            $table->dropColumn(['review_notes', 'reviewed_by_principal_id', 'reviewed_at']);
        });
    }
};
