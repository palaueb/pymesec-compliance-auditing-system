<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remediation_actions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('finding_id');
            $table->string('organization_id');
            $table->string('scope_id')->nullable();
            $table->string('title');
            $table->string('status', 40);
            $table->text('notes')->nullable();
            $table->date('due_on')->nullable();
            $table->timestamps();

            $table->index(['finding_id']);
            $table->index(['organization_id', 'scope_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remediation_actions');
    }
};
