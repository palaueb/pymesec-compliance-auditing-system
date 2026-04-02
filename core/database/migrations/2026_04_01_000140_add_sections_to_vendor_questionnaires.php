<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_questionnaire_template_items', function (Blueprint $table): void {
            $table->string('section_title')->nullable()->after('position');
            $table->index(['template_id', 'section_title'], 'vendor_questionnaire_template_items_section_idx');
        });

        Schema::table('vendor_review_questionnaire_items', function (Blueprint $table): void {
            $table->string('section_title')->nullable()->after('position');
            $table->index(['review_id', 'section_title'], 'vendor_review_questionnaire_items_section_idx');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_review_questionnaire_items', function (Blueprint $table): void {
            $table->dropIndex('vendor_review_questionnaire_items_section_idx');
            $table->dropColumn('section_title');
        });

        Schema::table('vendor_questionnaire_template_items', function (Blueprint $table): void {
            $table->dropIndex('vendor_questionnaire_template_items_section_idx');
            $table->dropColumn('section_title');
        });
    }
};
