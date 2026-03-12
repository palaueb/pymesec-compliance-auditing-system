<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artifacts', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('owner_component');
            $table->string('subject_type');
            $table->string('subject_id');
            $table->string('artifact_type');
            $table->string('label');
            $table->string('original_filename');
            $table->string('media_type');
            $table->string('extension', 32);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64);
            $table->string('disk');
            $table->text('storage_path');
            $table->string('principal_id')->nullable();
            $table->string('membership_id')->nullable();
            $table->string('organization_id')->nullable()->index();
            $table->string('scope_id')->nullable()->index();
            $table->json('metadata');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['owner_component', 'artifact_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artifacts');
    }
};
