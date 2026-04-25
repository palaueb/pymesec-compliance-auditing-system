<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_framework_adoptions', function (Blueprint $table): void {
            $table->string('requested_by_principal_id')->nullable()->after('status');
            $table->string('approved_by_principal_id')->nullable()->after('requested_by_principal_id');
            $table->text('change_reason')->nullable()->after('approved_by_principal_id');
            $table->timestamp('approved_at')->nullable()->after('change_reason');
            $table->timestamp('retired_at')->nullable()->after('approved_at');
            $table->string('starter_pack_version')->nullable()->after('retired_at');
            $table->string('starter_pack_applied_by_principal_id')->nullable()->after('starter_pack_version');
            $table->timestamp('starter_pack_applied_at')->nullable()->after('starter_pack_applied_by_principal_id');
        });
    }

    public function down(): void
    {
        Schema::table('org_framework_adoptions', function (Blueprint $table): void {
            $table->dropColumn([
                'requested_by_principal_id',
                'approved_by_principal_id',
                'change_reason',
                'approved_at',
                'retired_at',
                'starter_pack_version',
                'starter_pack_applied_by_principal_id',
                'starter_pack_applied_at',
            ]);
        });
    }
};
