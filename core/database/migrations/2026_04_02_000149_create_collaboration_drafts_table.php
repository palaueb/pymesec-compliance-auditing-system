<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collaboration_drafts', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('owner_component', 64);
            $table->string('subject_type', 64);
            $table->string('subject_id', 120);
            $table->string('organization_id', 64);
            $table->string('scope_id', 64)->nullable();
            $table->string('draft_type', 32)->default('comment');
            $table->string('title', 200)->nullable();
            $table->text('body')->nullable();
            $table->text('details')->nullable();
            $table->string('priority', 32)->default('normal');
            $table->string('handoff_state', 32)->default('review');
            $table->text('mentioned_actor_ids')->nullable();
            $table->string('assigned_actor_id', 64)->nullable();
            $table->date('due_on')->nullable();
            $table->string('edited_by_principal_id', 64)->nullable();
            $table->timestamps();

            $table->index(['owner_component', 'subject_type', 'subject_id'], 'collaboration_drafts_subject_idx');
            $table->index(['organization_id', 'scope_id'], 'collaboration_drafts_context_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collaboration_drafts');
    }
};
