<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('principal_functional_actor_links', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('principal_id', 64);
            $table->string('functional_actor_id', 120);
            $table->string('organization_id', 64);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(
                ['principal_id', 'functional_actor_id', 'organization_id'],
                'principal_actor_link_unique',
            );
            $table->index(['principal_id', 'organization_id'], 'principal_actor_org_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('principal_functional_actor_links');
    }
};
