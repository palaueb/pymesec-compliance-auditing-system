<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_local_users', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('principal_id', 64)->unique();
            $table->string('organization_id', 64);
            $table->string('display_name', 120);
            $table->string('email', 190)->unique();
            $table->string('job_title', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'is_active'], 'identity_local_users_org_active_idx');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_local_users');
    }
};
