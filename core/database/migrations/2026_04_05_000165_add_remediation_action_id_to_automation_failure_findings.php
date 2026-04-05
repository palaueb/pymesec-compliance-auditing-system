<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_failure_findings', function (Blueprint $table): void {
            $table->string('remediation_action_id')->nullable()->after('finding_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('automation_failure_findings', function (Blueprint $table): void {
            $table->dropColumn('remediation_action_id');
        });
    }
};
