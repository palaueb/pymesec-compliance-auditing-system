<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('principal_id', 64);
            $table->string('organization_id', 64);
            $table->json('roles')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['principal_id', 'organization_id']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
