<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collaboration_comments', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('owner_component', 120);
            $table->string('subject_type', 120);
            $table->string('subject_id', 120);
            $table->string('organization_id', 64);
            $table->string('scope_id', 64)->nullable();
            $table->string('author_principal_id', 120)->nullable();
            $table->text('body');
            $table->timestamps();

            $table->index(['owner_component', 'subject_type', 'subject_id'], 'collab_comments_subject_idx');
            $table->index(['organization_id', 'scope_id'], 'collab_comments_context_idx');
        });

        Schema::create('collaboration_requests', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('owner_component', 120);
            $table->string('subject_type', 120);
            $table->string('subject_id', 120);
            $table->string('organization_id', 64);
            $table->string('scope_id', 64)->nullable();
            $table->string('title', 200);
            $table->text('details')->nullable();
            $table->string('status', 40)->default('open');
            $table->string('priority', 40)->default('normal');
            $table->string('assigned_actor_id', 64)->nullable();
            $table->string('requested_by_principal_id', 120)->nullable();
            $table->date('due_on')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['owner_component', 'subject_type', 'subject_id'], 'collab_requests_subject_idx');
            $table->index(['organization_id', 'scope_id'], 'collab_requests_context_idx');
            $table->index(['status', 'priority'], 'collab_requests_status_priority_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collaboration_requests');
        Schema::dropIfExists('collaboration_comments');
    }
};
