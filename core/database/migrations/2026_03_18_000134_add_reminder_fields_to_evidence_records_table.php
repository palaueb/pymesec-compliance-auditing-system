<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evidence_records', function (Blueprint $table): void {
            $table->timestamp('review_reminder_sent_at')->nullable()->after('review_due_on');
            $table->timestamp('expiry_reminder_sent_at')->nullable()->after('review_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('evidence_records', function (Blueprint $table): void {
            $table->dropColumn([
                'review_reminder_sent_at',
                'expiry_reminder_sent_at',
            ]);
        });
    }
};
