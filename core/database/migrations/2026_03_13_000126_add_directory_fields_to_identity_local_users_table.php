<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('identity_local_users', function (Blueprint $table): void {
            $table->string('auth_provider', 32)->default('local')->after('principal_id');
            $table->string('external_subject', 190)->nullable()->after('auth_provider');
            $table->string('directory_source', 64)->nullable()->after('external_subject');
            $table->text('directory_groups')->nullable()->after('directory_source');
            $table->timestamp('directory_synced_at')->nullable()->after('directory_groups');
            $table->unique(['auth_provider', 'external_subject'], 'identity_local_users_provider_subject_unique');
            $table->index(['organization_id', 'auth_provider'], 'identity_local_users_org_provider_idx');
        });

        DB::table('identity_local_users')
            ->update([
                'auth_provider' => 'local',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('identity_local_users', function (Blueprint $table): void {
            $table->dropUnique('identity_local_users_provider_subject_unique');
            $table->dropIndex('identity_local_users_org_provider_idx');
            $table->dropColumn([
                'auth_provider',
                'external_subject',
                'directory_source',
                'directory_groups',
                'directory_synced_at',
            ]);
        });
    }
};
