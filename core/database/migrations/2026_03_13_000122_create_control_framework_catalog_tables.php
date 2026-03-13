<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('control_frameworks', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('organization_id')->index();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('control_requirements', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('organization_id')->index();
            $table->string('framework_id')->index();
            $table->string('code');
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('control_requirement_mappings', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('organization_id')->index();
            $table->string('control_id')->index();
            $table->string('requirement_id')->index();
            $table->string('coverage')->default('supports');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'control_id', 'requirement_id'], 'control_requirement_unique');
        });

        Schema::table('controls', function (Blueprint $table): void {
            $table->string('framework_id')->nullable()->after('scope_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('controls', function (Blueprint $table): void {
            $table->dropColumn('framework_id');
        });

        Schema::dropIfExists('control_requirement_mappings');
        Schema::dropIfExists('control_requirements');
        Schema::dropIfExists('control_frameworks');
    }
};
