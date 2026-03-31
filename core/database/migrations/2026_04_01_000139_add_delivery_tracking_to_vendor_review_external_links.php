<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_review_external_links', function (Blueprint $table): void {
            $table->string('email_delivery_status')->default('manual-only')->after('revoked_by_principal_id');
            $table->text('email_delivery_error')->nullable()->after('email_delivery_status');
            $table->timestamp('email_last_attempted_at')->nullable()->after('email_delivery_error');
            $table->timestamp('email_sent_at')->nullable()->after('email_last_attempted_at');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_review_external_links', function (Blueprint $table): void {
            $table->dropColumn([
                'email_delivery_status',
                'email_delivery_error',
                'email_last_attempted_at',
                'email_sent_at',
            ]);
        });
    }
};
