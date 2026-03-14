<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_ldap_connections', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('organization_id', 64)->unique();
            $table->string('name', 120);
            $table->string('host', 190);
            $table->unsignedInteger('port')->default(389);
            $table->string('base_dn', 190);
            $table->string('bind_dn', 190)->nullable();
            $table->string('bind_password', 255)->nullable();
            $table->string('user_dn_attribute', 64)->default('uid');
            $table->string('mail_attribute', 64)->default('mail');
            $table->string('display_name_attribute', 64)->default('cn');
            $table->string('job_title_attribute', 64)->default('title');
            $table->string('group_attribute', 64)->default('memberOf');
            $table->string('login_mode', 16)->default('username');
            $table->unsignedInteger('sync_interval_minutes')->default(60);
            $table->text('user_filter')->nullable();
            $table->boolean('fallback_email_enabled')->default(true);
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('last_sync_started_at')->nullable();
            $table->timestamp('last_sync_completed_at')->nullable();
            $table->string('last_sync_status', 32)->nullable();
            $table->text('last_sync_message')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('identity_ldap_group_mappings', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('connection_id', 64);
            $table->string('ldap_group', 190);
            $table->text('role_keys')->nullable();
            $table->text('scope_ids')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['connection_id', 'ldap_group'], 'identity_ldap_group_mapping_unique');
            $table->foreign('connection_id')->references('id')->on('identity_ldap_connections')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_ldap_group_mappings');
        Schema::dropIfExists('identity_ldap_connections');
    }
};
