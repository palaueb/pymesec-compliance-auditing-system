<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_events', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('origin_component', 80);
            $table->string('organization_id', 64)->nullable();
            $table->string('scope_id', 64)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->index(['name', 'published_at']);
            $table->index(['origin_component', 'published_at']);
            $table->index(['organization_id', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_events');
    }
};
