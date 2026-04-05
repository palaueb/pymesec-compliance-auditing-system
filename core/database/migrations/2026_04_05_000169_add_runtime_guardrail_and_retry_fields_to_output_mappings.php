<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_pack_output_mappings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('runtime_retry_max_attempts')->default(0)->after('evidence_policy');
            $table->unsignedInteger('runtime_retry_backoff_ms')->default(0)->after('runtime_retry_max_attempts');
            $table->unsignedInteger('runtime_max_targets')->default(200)->after('runtime_retry_backoff_ms');
            $table->unsignedInteger('runtime_payload_max_kb')->default(512)->after('runtime_max_targets');
        });
    }

    public function down(): void
    {
        Schema::table('automation_pack_output_mappings', function (Blueprint $table): void {
            $table->dropColumn([
                'runtime_retry_max_attempts',
                'runtime_retry_backoff_ms',
                'runtime_max_targets',
                'runtime_payload_max_kb',
            ]);
        });
    }
};
