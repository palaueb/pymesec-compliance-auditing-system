<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_pack_output_mappings', function (Blueprint $table): void {
            $table->string('posture_propagation_policy', 40)->default('disabled')->after('target_selector_json');
        });

        Schema::table('assets', function (Blueprint $table): void {
            $table->string('automation_posture', 40)->default('unknown')->after('owner_label');
            $table->timestamp('automation_posture_updated_at')->nullable()->after('automation_posture');
            $table->text('automation_posture_message')->nullable()->after('automation_posture_updated_at');
        });

        Schema::table('risks', function (Blueprint $table): void {
            $table->string('automation_posture', 40)->default('unknown')->after('treatment');
            $table->timestamp('automation_posture_updated_at')->nullable()->after('automation_posture');
            $table->text('automation_posture_message')->nullable()->after('automation_posture_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('risks', function (Blueprint $table): void {
            $table->dropColumn([
                'automation_posture',
                'automation_posture_updated_at',
                'automation_posture_message',
            ]);
        });

        Schema::table('assets', function (Blueprint $table): void {
            $table->dropColumn([
                'automation_posture',
                'automation_posture_updated_at',
                'automation_posture_message',
            ]);
        });

        Schema::table('automation_pack_output_mappings', function (Blueprint $table): void {
            $table->dropColumn('posture_propagation_policy');
        });
    }
};
