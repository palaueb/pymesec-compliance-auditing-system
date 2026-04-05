<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_packs', function (Blueprint $table): void {
            $table->boolean('runtime_schedule_enabled')->default(false)->after('is_enabled');
            $table->string('runtime_schedule_cron', 120)->nullable()->after('runtime_schedule_enabled');
            $table->string('runtime_schedule_timezone', 64)->nullable()->after('runtime_schedule_cron');
            $table->string('runtime_schedule_last_slot', 32)->nullable()->after('runtime_schedule_timezone');
        });
    }

    public function down(): void
    {
        Schema::table('automation_packs', function (Blueprint $table): void {
            $table->dropColumn([
                'runtime_schedule_enabled',
                'runtime_schedule_cron',
                'runtime_schedule_timezone',
                'runtime_schedule_last_slot',
            ]);
        });
    }
};
