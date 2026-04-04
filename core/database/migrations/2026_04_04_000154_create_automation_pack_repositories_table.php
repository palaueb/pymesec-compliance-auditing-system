<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_pack_repositories', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('organization_id')->index();
            $table->string('scope_id')->nullable()->index();
            $table->string('label', 180);
            $table->string('repository_url', 1024);
            $table->string('repository_sign_url', 1024)->nullable();
            $table->text('public_key_pem');
            $table->string('trust_tier', 40)->default('trusted-partner');
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('last_refreshed_at')->nullable();
            $table->string('last_status', 40)->default('never');
            $table->text('last_error')->nullable();
            $table->string('created_by_principal_id')->nullable()->index();
            $table->string('updated_by_principal_id')->nullable()->index();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'scope_id', 'repository_url'],
                'automation_pack_repositories_unique_repo_per_scope'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_pack_repositories');
    }
};
