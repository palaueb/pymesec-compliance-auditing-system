<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the global framework pack tables.
 *
 * These tables hold compliance framework skeleton data (ISO 27001, NIS2, ENS, etc.)
 * that ships with the application via framework pack plugins. They are NOT per-organization
 * — a single global copy serves all tenants.
 *
 * See ADR-020 for the full architecture rationale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frameworks', function (Blueprint $table): void {
            $table->string('id')->primary();
            // null = global system pack (seeded by a plugin, available to all orgs)
            // non-null = custom org-level framework created by that organization
            $table->string('organization_id')->nullable()->index();
            $table->string('code');
            $table->string('name');
            $table->string('version')->nullable();
            $table->text('description')->nullable();
            // audit | compliance | directive | custom
            $table->string('kind')->default('compliance');
            $table->timestamps();
        });

        Schema::create('framework_elements', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('framework_id')->index();
            // Self-referential: null = top-level domain/theme; non-null = child element
            $table->string('parent_id')->nullable()->index();
            $table->string('code')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            // domain | control | clause | article | obligation | criterion | item
            $table->string('element_type')->default('control');
            // Used by ENS: basic | medium | high — element applies from this level up
            $table->string('applicability_level')->nullable();
            $table->integer('sort_order')->default(0);
            // Framework-specific attributes that don't warrant a dedicated column
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('framework_id')->references('id')->on('frameworks')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('framework_elements')->nullOnDelete();
        });

        Schema::create('org_framework_adoptions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('organization_id')->index();
            $table->string('framework_id')->index();
            $table->string('scope_id')->nullable()->index();
            // For ENS: the security level the org is targeting (basic | medium | high)
            $table->string('target_level')->nullable();
            $table->timestamp('adopted_at')->nullable();
            // active | inactive | in-progress
            $table->string('status')->default('active');
            $table->timestamps();

            $table->foreign('framework_id')->references('id')->on('frameworks')->cascadeOnDelete();
            $table->unique(['organization_id', 'framework_id', 'scope_id'], 'org_framework_adoption_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_framework_adoptions');
        Schema::dropIfExists('framework_elements');
        Schema::dropIfExists('frameworks');
    }
};
