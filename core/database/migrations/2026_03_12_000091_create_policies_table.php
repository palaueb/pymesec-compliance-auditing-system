<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policies', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('organization_id');
            $table->string('scope_id')->nullable();
            $table->string('title');
            $table->string('area', 80);
            $table->string('version_label', 40);
            $table->text('statement');
            $table->string('linked_control_id')->nullable();
            $table->date('review_due_on')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'scope_id']);
            $table->index(['linked_control_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policies');
    }
};
