<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('questionnaire_templates')) {
            Schema::create('questionnaire_templates', function (Blueprint $table): void {
                $table->string('id')->primary();
                // Keep indexed enum-like fields short to avoid oversized composite keys on utf8mb4 MySQL.
                $table->string('owner_component', 100)->index();
                $table->string('subject_type', 100)->index();
                $table->string('profile_id')->nullable()->index();
                $table->string('organization_id')->index();
                $table->string('scope_id')->nullable()->index();
                $table->string('name');
                $table->text('summary')->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->index(['owner_component', 'subject_type', 'organization_id'], 'questionnaire_templates_owner_subject_org_idx');
            });
        }

        if (! Schema::hasTable('questionnaire_template_items')) {
            Schema::create('questionnaire_template_items', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('template_id')->index();
                $table->string('organization_id')->index();
                $table->string('scope_id')->nullable()->index();
                $table->unsignedInteger('position')->default(1);
                $table->string('section_title')->nullable();
                $table->string('prompt');
                $table->string('response_type')->default('long-text');
                $table->text('guidance_text')->nullable();
                $table->timestamps();

                $table->index(['template_id', 'position'], 'questionnaire_template_items_order_idx');
                $table->index(['template_id', 'section_title'], 'questionnaire_template_items_section_idx');
            });
        }

        if (! Schema::hasTable('questionnaire_subject_items')) {
            Schema::create('questionnaire_subject_items', function (Blueprint $table): void {
                $table->string('id')->primary();
                // Keep indexed enum-like fields short to avoid oversized composite keys on utf8mb4 MySQL.
                $table->string('owner_component', 100)->index();
                $table->string('subject_type', 100)->index();
                $table->string('subject_id')->index();
                $table->string('organization_id')->index();
                $table->string('scope_id')->nullable()->index();
                $table->string('source_template_item_id')->nullable()->index();
                $table->unsignedInteger('position')->default(1);
                $table->string('section_title')->nullable();
                $table->string('prompt');
                $table->string('response_type')->default('long-text');
                $table->string('response_status')->default('draft');
                $table->text('answer_text')->nullable();
                $table->text('follow_up_notes')->nullable();
                $table->timestamps();

                $table->index(['owner_component', 'subject_type', 'subject_id', 'position'], 'questionnaire_subject_items_order_idx');
                $table->index(['owner_component', 'subject_type', 'subject_id', 'section_title'], 'questionnaire_subject_items_section_idx');
            });
        }

        if (Schema::hasTable('vendor_questionnaire_templates')) {
            DB::table('vendor_questionnaire_templates')
                ->orderBy('id')
                ->get()
                ->each(function (object $template): void {
                    DB::table('questionnaire_templates')->updateOrInsert(
                        ['id' => (string) $template->id],
                        [
                            'owner_component' => 'third-party-risk',
                            'subject_type' => 'vendor-review',
                            'profile_id' => is_string($template->profile_id ?? null) ? $template->profile_id : null,
                            'organization_id' => (string) $template->organization_id,
                            'scope_id' => $template->scope_id,
                            'name' => (string) $template->name,
                            'summary' => $template->summary,
                            'is_default' => (bool) $template->is_default,
                            'created_at' => $template->created_at,
                            'updated_at' => $template->updated_at,
                        ],
                    );
                });
        }

        if (Schema::hasTable('vendor_questionnaire_template_items')) {
            DB::table('vendor_questionnaire_template_items')
                ->orderBy('id')
                ->get()
                ->each(function (object $item): void {
                    DB::table('questionnaire_template_items')->updateOrInsert(
                        ['id' => (string) $item->id],
                        [
                            'template_id' => (string) $item->template_id,
                            'organization_id' => (string) $item->organization_id,
                            'scope_id' => $item->scope_id,
                            'position' => (int) $item->position,
                            'section_title' => $item->section_title,
                            'prompt' => (string) $item->prompt,
                            'response_type' => (string) $item->response_type,
                            'guidance_text' => $item->guidance_text,
                            'created_at' => $item->created_at,
                            'updated_at' => $item->updated_at,
                        ],
                    );
                });
        }

        if (Schema::hasTable('vendor_review_questionnaire_items')) {
            DB::table('vendor_review_questionnaire_items')
                ->orderBy('id')
                ->get()
                ->each(function (object $item): void {
                    DB::table('questionnaire_subject_items')->updateOrInsert(
                        ['id' => (string) $item->id],
                        [
                            'owner_component' => 'third-party-risk',
                            'subject_type' => 'vendor-review',
                            'subject_id' => (string) $item->review_id,
                            'organization_id' => (string) $item->organization_id,
                            'scope_id' => $item->scope_id,
                            'source_template_item_id' => $item->source_template_item_id,
                            'position' => (int) $item->position,
                            'section_title' => $item->section_title,
                            'prompt' => (string) $item->prompt,
                            'response_type' => (string) $item->response_type,
                            'response_status' => (string) $item->response_status,
                            'answer_text' => $item->answer_text,
                            'follow_up_notes' => $item->follow_up_notes,
                            'created_at' => $item->created_at,
                            'updated_at' => $item->updated_at,
                        ],
                    );
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaire_subject_items');
        Schema::dropIfExists('questionnaire_template_items');
        Schema::dropIfExists('questionnaire_templates');
    }
};
