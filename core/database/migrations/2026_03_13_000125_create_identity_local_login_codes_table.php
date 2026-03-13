<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_local_login_codes', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('principal_id', 64);
            $table->string('email', 190);
            $table->string('purpose', 40);
            $table->string('code_hash', 64);
            $table->string('requested_ip', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['principal_id', 'purpose', 'expires_at'], 'identity_login_codes_principal_purpose_exp_idx');
            $table->index(['email', 'purpose', 'expires_at'], 'identity_login_codes_email_purpose_exp_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_local_login_codes');
    }
};
