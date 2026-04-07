<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_access_tokens', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->string('label', 120);
            $table->string('principal_id', 64)->index();
            $table->string('organization_id', 64)->nullable()->index();
            $table->string('scope_id', 64)->nullable()->index();
            $table->string('token_prefix', 24)->index();
            $table->string('token_hash', 64)->unique();
            $table->json('abilities')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->string('created_by_principal_id', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_access_tokens');
    }
};
