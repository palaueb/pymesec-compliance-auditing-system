<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('findings', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('organization_id');
            $table->string('scope_id')->nullable();
            $table->string('title');
            $table->string('severity', 40);
            $table->text('description');
            $table->string('linked_control_id')->nullable();
            $table->string('linked_risk_id')->nullable();
            $table->date('due_on')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'scope_id']);
            $table->index(['linked_control_id']);
            $table->index(['linked_risk_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('findings');
    }
};
