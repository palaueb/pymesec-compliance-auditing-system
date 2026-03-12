<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('organization_id', 64);
            $table->string('scope_id', 64)->nullable();
            $table->string('name', 160);
            $table->string('type', 80);
            $table->string('criticality', 40);
            $table->string('classification', 80);
            $table->string('owner_label', 160)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'scope_id']);
            $table->index(['organization_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
