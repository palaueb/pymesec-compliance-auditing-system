<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_review_questionnaire_items', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('review_id')->index();
            $table->string('organization_id')->index();
            $table->string('scope_id')->nullable()->index();
            $table->unsignedInteger('position')->default(1);
            $table->string('prompt');
            $table->string('response_type')->default('long-text');
            $table->string('response_status')->default('draft');
            $table->text('answer_text')->nullable();
            $table->text('follow_up_notes')->nullable();
            $table->timestamps();

            $table->index(['review_id', 'position'], 'vendor_review_questionnaire_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_review_questionnaire_items');
    }
};
