<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authorization_grants', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('target_type');
            $table->string('target_id');
            $table->string('grant_type');
            $table->string('value');
            $table->string('context_type');
            $table->string('organization_id')->nullable();
            $table->string('scope_id')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->index(['target_type', 'target_id']);
            $table->index(['context_type', 'organization_id', 'scope_id']);
            $table->index(['grant_type', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authorization_grants');
    }
};
