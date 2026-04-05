<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_pack_output_mappings', function (Blueprint $table): void {
            $table->string('evidence_policy', 40)->default('always')->after('on_fail_policy');
        });
    }

    public function down(): void
    {
        Schema::table('automation_pack_output_mappings', function (Blueprint $table): void {
            $table->dropColumn('evidence_policy');
        });
    }
};
