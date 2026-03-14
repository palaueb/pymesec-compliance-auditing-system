<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [];
        $rolePermissions = [];

        foreach (config('authorization.roles', []) as $key => $role) {
            if (! is_string($key) || ! is_array($role)) {
                continue;
            }

            $roles[] = [
                'key' => $key,
                'label' => (string) ($role['label'] ?? $key),
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            foreach (array_values(array_filter(
                $role['permissions'] ?? [],
                static fn (mixed $permission): bool => is_string($permission) && $permission !== '',
            )) as $permission) {
                $rolePermissions[] = [
                    'role_key' => $key,
                    'permission_key' => $permission,
                ];
            }
        }

        DB::table('authorization_roles')->insertOrIgnore($roles);
        DB::table('authorization_role_permissions')->insertOrIgnore($rolePermissions);
    }
}
