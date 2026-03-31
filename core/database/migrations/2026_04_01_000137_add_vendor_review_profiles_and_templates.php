<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_review_profiles', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('organization_id')->index();
            $table->string('scope_id')->nullable()->index();
            $table->string('name');
            $table->string('tier')->default('medium');
            $table->string('default_inherent_risk')->default('medium');
            $table->unsignedInteger('review_interval_days')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('vendor_questionnaire_templates', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('profile_id')->index();
            $table->string('organization_id')->index();
            $table->string('scope_id')->nullable()->index();
            $table->string('name');
            $table->text('summary')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('vendor_questionnaire_template_items', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('template_id')->index();
            $table->string('organization_id')->index();
            $table->string('scope_id')->nullable()->index();
            $table->unsignedInteger('position')->default(1);
            $table->string('prompt');
            $table->string('response_type')->default('long-text');
            $table->text('guidance_text')->nullable();
            $table->timestamps();

            $table->index(['template_id', 'position'], 'vendor_questionnaire_template_items_order_idx');
        });

        Schema::table('vendor_reviews', function (Blueprint $table): void {
            $table->string('review_profile_id')->nullable()->after('scope_id');
            $table->string('questionnaire_template_id')->nullable()->after('review_profile_id');
            $table->index('review_profile_id', 'vendor_reviews_profile_idx');
            $table->index('questionnaire_template_id', 'vendor_reviews_template_idx');
        });

        Schema::table('vendor_review_questionnaire_items', function (Blueprint $table): void {
            $table->string('source_template_item_id')->nullable()->after('scope_id');
            $table->index('source_template_item_id', 'vendor_review_questionnaire_source_template_idx');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_review_questionnaire_items', function (Blueprint $table): void {
            $table->dropIndex('vendor_review_questionnaire_source_template_idx');
            $table->dropColumn('source_template_item_id');
        });

        Schema::table('vendor_reviews', function (Blueprint $table): void {
            $table->dropIndex('vendor_reviews_profile_idx');
            $table->dropIndex('vendor_reviews_template_idx');
            $table->dropColumn(['review_profile_id', 'questionnaire_template_id']);
        });

        Schema::dropIfExists('vendor_questionnaire_template_items');
        Schema::dropIfExists('vendor_questionnaire_templates');
        Schema::dropIfExists('vendor_review_profiles');
    }
};
