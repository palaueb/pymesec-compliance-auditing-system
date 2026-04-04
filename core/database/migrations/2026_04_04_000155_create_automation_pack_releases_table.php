<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_pack_releases', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('repository_id')->index();
            $table->string('organization_id')->index();
            $table->string('scope_id')->nullable()->index();
            $table->string('pack_key', 180)->index();
            $table->string('pack_name', 180);
            $table->text('pack_description')->nullable();
            $table->string('version', 80);
            $table->boolean('is_latest')->default(false);
            $table->string('artifact_url', 1024);
            $table->string('artifact_signature_url', 1024)->nullable();
            $table->string('artifact_sha256', 128)->nullable();
            $table->string('pack_manifest_url', 1024)->nullable();
            $table->text('capabilities_json')->nullable();
            $table->text('permissions_requested_json')->nullable();
            $table->text('raw_metadata_json')->nullable();
            $table->timestamp('discovered_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['repository_id', 'pack_key', 'version'],
                'automation_pack_releases_unique_repo_pack_version'
            );
            $table->index(['organization_id', 'scope_id', 'pack_key'], 'automation_pack_releases_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_pack_releases');
    }
};
