<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_data_flows', function (Blueprint $table): void {
            $table->string('id', 120)->primary();
            $table->string('organization_id', 64);
            $table->string('scope_id', 64)->nullable();
            $table->string('title', 160);
            $table->string('source', 160);
            $table->string('destination', 160);
            $table->string('data_category_summary', 200);
            $table->string('transfer_type', 80);
            $table->date('review_due_on')->nullable();
            $table->string('linked_asset_id', 120)->nullable();
            $table->string('linked_risk_id', 120)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_data_flows');
    }
};
