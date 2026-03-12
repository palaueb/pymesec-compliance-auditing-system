<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('functional_assignments', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('functional_actor_id', 120);
            $table->string('domain_object_type', 80);
            $table->string('domain_object_id', 120);
            $table->string('assignment_type', 80);
            $table->string('organization_id', 64);
            $table->string('scope_id', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique([
                'functional_actor_id',
                'domain_object_type',
                'domain_object_id',
                'assignment_type',
            ], 'functional_assignment_unique');
            $table->index(['organization_id', 'scope_id']);
            $table->index(['domain_object_type', 'domain_object_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('functional_assignments');
    }
};
