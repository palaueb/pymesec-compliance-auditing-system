<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->string('automation_posture_check_result_id')->nullable()->after('automation_posture_message')->index();
            $table->string('automation_posture_run_id')->nullable()->after('automation_posture_check_result_id')->index();
        });

        Schema::table('risks', function (Blueprint $table): void {
            $table->string('automation_posture_check_result_id')->nullable()->after('automation_posture_message')->index();
            $table->string('automation_posture_run_id')->nullable()->after('automation_posture_check_result_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('risks', function (Blueprint $table): void {
            $table->dropColumn([
                'automation_posture_check_result_id',
                'automation_posture_run_id',
            ]);
        });

        Schema::table('assets', function (Blueprint $table): void {
            $table->dropColumn([
                'automation_posture_check_result_id',
                'automation_posture_run_id',
            ]);
        });
    }
};
