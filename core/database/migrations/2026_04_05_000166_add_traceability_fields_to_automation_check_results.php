<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_check_results', function (Blueprint $table): void {
            $table->string('artifact_id')->nullable()->after('message')->index();
            $table->string('evidence_id')->nullable()->after('artifact_id')->index();
            $table->string('finding_id')->nullable()->after('evidence_id')->index();
            $table->string('remediation_action_id')->nullable()->after('finding_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('automation_check_results', function (Blueprint $table): void {
            $table->dropColumn([
                'artifact_id',
                'evidence_id',
                'finding_id',
                'remediation_action_id',
            ]);
        });
    }
};
