<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_mail_settings', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('organization_id', 64)->unique();
            $table->boolean('email_enabled')->default(false);
            $table->string('smtp_host', 190)->nullable();
            $table->unsignedInteger('smtp_port')->nullable();
            $table->string('smtp_encryption', 16)->nullable();
            $table->string('smtp_username', 190)->nullable();
            $table->text('smtp_password_encrypted')->nullable();
            $table->string('from_address', 190)->nullable();
            $table->string('from_name', 190)->nullable();
            $table->string('reply_to_address', 190)->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('updated_by_principal_id', 120)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'email_enabled'], 'notification_mail_settings_org_enabled_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_mail_settings');
    }
};
