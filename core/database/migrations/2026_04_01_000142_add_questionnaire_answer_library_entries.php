<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questionnaire_answer_library_entries', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('owner_component')->index();
            $table->string('subject_type')->index();
            $table->string('organization_id')->index();
            $table->string('scope_id')->nullable()->index();
            $table->string('response_type')->index();
            $table->string('prompt_fingerprint')->index();
            $table->string('prompt_text');
            $table->text('answer_text');
            $table->text('notes')->nullable();
            $table->unsignedInteger('usage_count')->default(1);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(
                ['owner_component', 'subject_type', 'organization_id', 'response_type'],
                'questionnaire_answer_library_lookup_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaire_answer_library_entries');
    }
};
