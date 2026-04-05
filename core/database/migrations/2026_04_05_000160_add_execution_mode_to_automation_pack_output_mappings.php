<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_pack_output_mappings', function (Blueprint $table): void {
            $table->string('execution_mode', 40)->default('both')->after('posture_propagation_policy');
        });
    }

    public function down(): void
    {
        Schema::table('automation_pack_output_mappings', function (Blueprint $table): void {
            $table->dropColumn('execution_mode');
        });
    }
};
