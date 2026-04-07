<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('channel', 16)->nullable()->after('origin_component');
            $table->string('author_type', 32)->nullable()->after('channel');
            $table->string('author_id', 128)->nullable()->after('author_type');
            $table->string('request_method', 16)->nullable()->after('execution_origin');
            $table->string('request_path', 512)->nullable()->after('request_method');
            $table->unsignedSmallInteger('status_code')->nullable()->after('request_path');

            $table->index('channel');
            $table->index(['author_type', 'author_id'], 'audit_logs_author_idx');
            $table->index(['request_method', 'status_code'], 'audit_logs_http_method_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex('audit_logs_http_method_status_idx');
            $table->dropIndex('audit_logs_author_idx');
            $table->dropIndex(['channel']);

            $table->dropColumn([
                'channel',
                'author_type',
                'author_id',
                'request_method',
                'request_path',
                'status_code',
            ]);
        });
    }
};
