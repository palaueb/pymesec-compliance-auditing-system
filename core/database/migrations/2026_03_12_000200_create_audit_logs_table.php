<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->string('event_type', 191);
            $table->string('outcome', 32);
            $table->string('origin_component', 128);
            $table->string('principal_id', 64)->nullable();
            $table->string('membership_id', 64)->nullable();
            $table->string('organization_id', 64)->nullable();
            $table->string('scope_id', 64)->nullable();
            $table->string('target_type', 128)->nullable();
            $table->string('target_id', 191)->nullable();
            $table->json('summary')->nullable();
            $table->json('correlation')->nullable();
            $table->string('execution_origin', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('event_type');
            $table->index('outcome');
            $table->index('origin_component');
            $table->index('principal_id');
            $table->index('organization_id');
            $table->index('scope_id');
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
