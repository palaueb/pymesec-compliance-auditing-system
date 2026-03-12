<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('functional_actors', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('provider');
            $table->string('kind');
            $table->string('display_name');
            $table->string('organization_id');
            $table->string('scope_id')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['organization_id', 'scope_id']);
            $table->index(['provider', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('functional_actors');
    }
};
