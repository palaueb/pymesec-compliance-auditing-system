<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_check_results', function (Blueprint $table): void {
            $table->string('idempotency_key', 190)->nullable()->after('remediation_action_id')->index();
            $table->unsignedSmallInteger('attempt_count')->default(1)->after('idempotency_key');
            $table->unsignedSmallInteger('retry_count')->default(0)->after('attempt_count');
        });
    }

    public function down(): void
    {
        Schema::table('automation_check_results', function (Blueprint $table): void {
            $table->dropColumn([
                'idempotency_key',
                'attempt_count',
                'retry_count',
            ]);
        });
    }
};
