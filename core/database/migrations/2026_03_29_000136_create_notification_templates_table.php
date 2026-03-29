<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('organization_id', 64);
            $table->string('notification_type', 190);
            $table->boolean('is_active')->default(true);
            $table->text('title_template')->nullable();
            $table->text('body_template')->nullable();
            $table->string('updated_by_principal_id', 120)->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'notification_type'], 'notification_templates_org_type_unique');
            $table->index(['organization_id', 'is_active'], 'notification_templates_org_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
