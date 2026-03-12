<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authorization_roles', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->string('label');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('authorization_role_permissions', function (Blueprint $table): void {
            $table->string('role_key');
            $table->string('permission_key');

            $table->primary(['role_key', 'permission_key']);
            $table->index(['permission_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authorization_role_permissions');
        Schema::dropIfExists('authorization_roles');
    }
};
