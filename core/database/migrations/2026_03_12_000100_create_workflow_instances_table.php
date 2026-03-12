<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_instances', function (Blueprint $table): void {
            $table->id();
            $table->string('workflow_key', 120);
            $table->string('subject_type', 60);
            $table->string('subject_id', 120);
            $table->string('organization_id', 60);
            $table->string('scope_id', 60)->nullable();
            $table->string('current_state', 60);
            $table->timestamps();

            $table->unique(
                ['workflow_key', 'subject_type', 'subject_id', 'organization_id'],
                'workflow_instances_subject_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_instances');
    }
};
