<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the legacy per-org framework catalog tables with references to the new
 * global framework pack tables introduced in migration 000130.
 *
 * Changes:
 * - Drops control_frameworks and control_requirements (superseded by frameworks + framework_elements)
 * - Drops control_requirement_mappings and recreates it with framework_element_id instead of requirement_id
 * - Adds framework_element_id (nullable) to controls, keeping framework_id for the label FK
 *
 * See ADR-020 for rationale.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop legacy mapping table first (has FKs to control_requirements)
        Schema::dropIfExists('control_requirement_mappings');

        // Drop legacy catalog tables (superseded by frameworks + framework_elements)
        Schema::dropIfExists('control_requirements');
        Schema::dropIfExists('control_frameworks');

        // Recreate the mapping table pointing to framework_elements instead of requirements
        Schema::create('control_requirement_mappings', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('organization_id')->index();
            $table->string('control_id')->index();
            // Points to a framework_elements record (replaces the old requirement_id FK)
            $table->string('framework_element_id')->index();
            $table->string('coverage')->default('supports');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'control_id', 'framework_element_id'], 'control_element_unique');
        });

        // Add a direct FK from controls to framework_elements for the primary implementing element.
        // Nullable: a control may not be linked to any specific framework element (custom/internal controls).
        // The existing framework_id column (string label FK) is kept for display purposes.
        Schema::table('controls', function (Blueprint $table): void {
            $table->string('framework_element_id')->nullable()->after('framework_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('controls', function (Blueprint $table): void {
            $table->dropColumn('framework_element_id');
        });

        Schema::dropIfExists('control_requirement_mappings');

        // Restore legacy tables (structure only — data is not restored)
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
    }
};
