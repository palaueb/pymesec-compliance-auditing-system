<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('type', 120);
            $table->string('title', 160);
            $table->text('body');
            $table->string('status', 32);
            $table->string('principal_id', 64)->nullable();
            $table->string('functional_actor_id', 120)->nullable();
            $table->string('organization_id', 64)->nullable();
            $table->string('scope_id', 64)->nullable();
            $table->string('source_event_name', 160)->nullable();
            $table->timestamp('deliver_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['status', 'deliver_at']);
            $table->index(['principal_id', 'organization_id']);
            $table->index(['functional_actor_id', 'organization_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
