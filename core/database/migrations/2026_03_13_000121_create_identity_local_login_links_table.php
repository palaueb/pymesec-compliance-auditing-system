<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_local_login_links', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('principal_id', 64);
            $table->string('email', 190);
            $table->string('token_hash', 64)->unique();
            $table->string('requested_ip', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['principal_id', 'expires_at'], 'identity_login_links_principal_exp_idx');
            $table->index(['email', 'expires_at'], 'identity_login_links_email_exp_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_local_login_links');
    }
};
