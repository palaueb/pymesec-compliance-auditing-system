<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_exceptions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('policy_id');
            $table->string('organization_id');
            $table->string('scope_id')->nullable();
            $table->string('title');
            $table->text('rationale');
            $table->text('compensating_control')->nullable();
            $table->string('linked_finding_id')->nullable();
            $table->date('expires_on')->nullable();
            $table->timestamps();

            $table->index(['policy_id']);
            $table->index(['organization_id', 'scope_id']);
            $table->index(['linked_finding_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_exceptions');
    }
};
