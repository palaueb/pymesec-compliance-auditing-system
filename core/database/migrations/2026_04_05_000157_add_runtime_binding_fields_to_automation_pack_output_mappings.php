<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_pack_output_mappings', function (Blueprint $table): void {
            $table->string('target_binding_mode', 40)->default('explicit')->after('transition_key');
            $table->string('target_scope_id')->nullable()->after('target_binding_mode');
            $table->text('target_selector_json')->nullable()->after('target_scope_id');
        });
    }

    public function down(): void
    {
        Schema::table('automation_pack_output_mappings', function (Blueprint $table): void {
            $table->dropColumn([
                'target_binding_mode',
                'target_scope_id',
                'target_selector_json',
            ]);
        });
    }
};
