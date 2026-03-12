<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CoreTenancySeeder extends Seeder
{
    public function run(): void
    {
        DB::table('organizations')->insertOrIgnore([
            [
                'id' => 'org-a',
                'name' => 'Northwind Manufacturing',
                'slug' => 'northwind-manufacturing',
                'default_locale' => 'en',
                'default_timezone' => 'Europe/Madrid',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 'org-b',
                'name' => 'Bluewave Logistics',
                'slug' => 'bluewave-logistics',
                'default_locale' => 'en',
                'default_timezone' => 'Europe/Berlin',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('scopes')->insertOrIgnore([
            [
                'id' => 'scope-eu',
                'organization_id' => 'org-a',
                'name' => 'Europe Perimeter',
                'slug' => 'europe-perimeter',
                'description' => 'European operational perimeter for shared assets and assessments.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 'scope-it',
                'organization_id' => 'org-a',
                'name' => 'IT Services',
                'slug' => 'it-services',
                'description' => 'Internal IT service perimeter.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 'scope-ops',
                'organization_id' => 'org-b',
                'name' => 'Operations Hub',
                'slug' => 'operations-hub',
                'description' => 'Primary logistics and warehouse perimeter.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('memberships')->insertOrIgnore([
            [
                'id' => 'membership-org-a-hello',
                'principal_id' => 'principal-org-a',
                'organization_id' => 'org-a',
                'roles' => json_encode(['asset-operator', 'hello-viewer'], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 'membership-org-a-viewer',
                'principal_id' => 'principal-org-a',
                'organization_id' => 'org-a',
                'roles' => json_encode(['asset-viewer'], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 'membership-org-b-ops',
                'principal_id' => 'principal-org-a',
                'organization_id' => 'org-b',
                'roles' => json_encode(['asset-viewer'], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('membership_scope')->insertOrIgnore([
            [
                'membership_id' => 'membership-org-b-ops',
                'scope_id' => 'scope-ops',
            ],
        ]);

        DB::table('functional_actors')->insertOrIgnore([
            [
                'id' => 'actor-finance-ops',
                'provider' => 'actor-directory',
                'kind' => 'team',
                'display_name' => 'Finance Operations',
                'organization_id' => 'org-a',
                'scope_id' => 'scope-eu',
                'metadata' => json_encode([
                    'title' => 'Asset owner team',
                    'source' => 'seed',
                ], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 'actor-compliance-office',
                'provider' => 'actor-directory',
                'kind' => 'team',
                'display_name' => 'Compliance Office',
                'organization_id' => 'org-a',
                'scope_id' => null,
                'metadata' => json_encode([
                    'title' => 'Shared governance owner',
                    'source' => 'seed',
                ], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 'actor-it-services',
                'provider' => 'actor-directory',
                'kind' => 'team',
                'display_name' => 'IT Services',
                'organization_id' => 'org-a',
                'scope_id' => 'scope-it',
                'metadata' => json_encode([
                    'title' => 'Endpoint operations owner',
                    'source' => 'seed',
                ], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 'actor-ava-mason',
                'provider' => 'actor-directory',
                'kind' => 'person',
                'display_name' => 'Ava Mason',
                'organization_id' => 'org-a',
                'scope_id' => null,
                'metadata' => json_encode([
                    'title' => 'Compliance manager',
                    'source' => 'seed',
                ], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 'actor-operations-control',
                'provider' => 'actor-directory',
                'kind' => 'team',
                'display_name' => 'Operations Control',
                'organization_id' => 'org-b',
                'scope_id' => 'scope-ops',
                'metadata' => json_encode([
                    'title' => 'Warehouse operations owner',
                    'source' => 'seed',
                ], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 'actor-logistics-team',
                'provider' => 'actor-directory',
                'kind' => 'team',
                'display_name' => 'Logistics Team',
                'organization_id' => 'org-b',
                'scope_id' => 'scope-ops',
                'metadata' => json_encode([
                    'title' => 'Route planning owner',
                    'source' => 'seed',
                ], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('principal_functional_actor_links')->insertOrIgnore([
            [
                'id' => 'link-principal-org-a-ava-mason',
                'principal_id' => 'principal-org-a',
                'functional_actor_id' => 'actor-ava-mason',
                'organization_id' => 'org-a',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('functional_assignments')->insertOrIgnore([
            [
                'id' => 'assignment-asset-erp-owner',
                'functional_actor_id' => 'actor-finance-ops',
                'domain_object_type' => 'asset',
                'domain_object_id' => 'asset-erp-prod',
                'assignment_type' => 'owner',
                'organization_id' => 'org-a',
                'scope_id' => 'scope-eu',
                'metadata' => json_encode([
                    'source' => 'seed',
                ], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 'assignment-asset-vault-owner',
                'functional_actor_id' => 'actor-compliance-office',
                'domain_object_type' => 'asset',
                'domain_object_id' => 'asset-vault-docs',
                'assignment_type' => 'owner',
                'organization_id' => 'org-a',
                'scope_id' => null,
                'metadata' => json_encode([
                    'source' => 'seed',
                ], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 'assignment-asset-laptop-owner',
                'functional_actor_id' => 'actor-it-services',
                'domain_object_type' => 'asset',
                'domain_object_id' => 'asset-laptop-fleet',
                'assignment_type' => 'owner',
                'organization_id' => 'org-a',
                'scope_id' => 'scope-it',
                'metadata' => json_encode([
                    'source' => 'seed',
                ], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 'assignment-asset-warehouse-owner',
                'functional_actor_id' => 'actor-operations-control',
                'domain_object_type' => 'asset',
                'domain_object_id' => 'asset-warehouse-mesh',
                'assignment_type' => 'owner',
                'organization_id' => 'org-b',
                'scope_id' => 'scope-ops',
                'metadata' => json_encode([
                    'source' => 'seed',
                ], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 'assignment-asset-route-owner',
                'functional_actor_id' => 'actor-logistics-team',
                'domain_object_type' => 'asset',
                'domain_object_id' => 'asset-route-planner',
                'assignment_type' => 'owner',
                'organization_id' => 'org-b',
                'scope_id' => 'scope-ops',
                'metadata' => json_encode([
                    'source' => 'seed',
                ], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 'assignment-control-access-owner',
                'functional_actor_id' => 'actor-ava-mason',
                'domain_object_type' => 'control',
                'domain_object_id' => 'control-access-review',
                'assignment_type' => 'owner',
                'organization_id' => 'org-a',
                'scope_id' => 'scope-eu',
                'metadata' => json_encode([
                    'source' => 'seed',
                ], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 'assignment-control-backup-owner',
                'functional_actor_id' => 'actor-it-services',
                'domain_object_type' => 'control',
                'domain_object_id' => 'control-backup-governance',
                'assignment_type' => 'owner',
                'organization_id' => 'org-a',
                'scope_id' => 'scope-it',
                'metadata' => json_encode([
                    'source' => 'seed',
                ], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 'assignment-control-route-owner',
                'functional_actor_id' => 'actor-operations-control',
                'domain_object_type' => 'control',
                'domain_object_id' => 'control-route-integrity',
                'assignment_type' => 'owner',
                'organization_id' => 'org-b',
                'scope_id' => 'scope-ops',
                'metadata' => json_encode([
                    'source' => 'seed',
                ], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
