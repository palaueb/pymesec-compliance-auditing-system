<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('identity_local_users', function (Blueprint $table): void {
            $table->string('username', 120)->nullable()->after('organization_id');
            $table->string('password_hash', 255)->nullable()->after('email');
            $table->boolean('password_enabled')->default(false)->after('password_hash');
            $table->boolean('magic_link_enabled')->default(true)->after('password_enabled');
        });

        $usedUsernames = [];

        foreach (DB::table('identity_local_users')
            ->orderBy('created_at')
            ->get(['id', 'email', 'display_name']) as $user) {
            $base = Str::slug(Str::before((string) $user->email, '@'));

            if ($base === '') {
                $base = Str::slug((string) $user->display_name);
            }

            if ($base === '') {
                $base = 'user';
            }

            $candidate = $base;
            $suffix = 1;

            while (in_array($candidate, $usedUsernames, true) || DB::table('identity_local_users')->where('username', $candidate)->exists()) {
                $candidate = $base.'-'.$suffix;
                $suffix++;
            }

            $usedUsernames[] = $candidate;

            DB::table('identity_local_users')
                ->where('id', $user->id)
                ->update([
                    'username' => $candidate,
                    'password_enabled' => false,
                    'magic_link_enabled' => true,
                    'updated_at' => now(),
                ]);
        }

        Schema::table('identity_local_users', function (Blueprint $table): void {
            $table->unique('username', 'identity_local_users_username_unique');
        });
    }

    public function down(): void
    {
        Schema::table('identity_local_users', function (Blueprint $table): void {
            $table->dropUnique('identity_local_users_username_unique');
            $table->dropColumn(['username', 'password_hash', 'password_enabled', 'magic_link_enabled']);
        });
    }
};
