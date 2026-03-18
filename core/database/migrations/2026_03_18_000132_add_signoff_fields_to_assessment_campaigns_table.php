<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_campaigns', function (Blueprint $table): void {
            $table->text('signoff_notes')->nullable()->after('status');
            $table->date('signed_off_on')->nullable()->after('signoff_notes');
            $table->string('signed_off_by_principal_id', 120)->nullable()->after('signed_off_on');
            $table->text('closure_summary')->nullable()->after('signed_off_by_principal_id');
            $table->date('closed_on')->nullable()->after('closure_summary');
            $table->string('closed_by_principal_id', 120)->nullable()->after('closed_on');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_campaigns', function (Blueprint $table): void {
            $table->dropColumn([
                'signoff_notes',
                'signed_off_on',
                'signed_off_by_principal_id',
                'closure_summary',
                'closed_on',
                'closed_by_principal_id',
            ]);
        });
    }
};
