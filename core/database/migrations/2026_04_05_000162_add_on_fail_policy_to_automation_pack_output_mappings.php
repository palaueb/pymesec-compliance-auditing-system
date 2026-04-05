<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_pack_output_mappings', function (Blueprint $table): void {
            $table->string('on_fail_policy', 40)->default('no-op')->after('execution_mode');
        });
    }

    public function down(): void
    {
        Schema::table('automation_pack_output_mappings', function (Blueprint $table): void {
            $table->dropColumn('on_fail_policy');
        });
    }
};
