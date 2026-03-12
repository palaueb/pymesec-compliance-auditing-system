<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risks', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('organization_id')->index();
            $table->string('scope_id')->nullable()->index();
            $table->string('title');
            $table->string('category');
            $table->unsignedInteger('inherent_score');
            $table->unsignedInteger('residual_score');
            $table->string('linked_asset_id')->nullable();
            $table->string('linked_control_id')->nullable();
            $table->text('treatment');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risks');
    }
};
