<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_scope', function (Blueprint $table): void {
            $table->string('membership_id', 64);
            $table->string('scope_id', 64);

            $table->primary(['membership_id', 'scope_id']);
            $table->foreign('membership_id')->references('id')->on('memberships')->cascadeOnDelete();
            $table->foreign('scope_id')->references('id')->on('scopes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_scope');
    }
};
