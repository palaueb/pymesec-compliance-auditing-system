<?php

namespace PymeSec\Plugins\FrameworkEns;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EnsFrameworkSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('frameworks')->insertOrIgnore([
            'id' => 'framework-ens',
            'organization_id' => null,
            'code' => 'ENS',
            'name' => 'plugin.framework-ens.framework.name',
            'version' => '2022',
            'description' => 'plugin.framework-ens.framework.description',
            'kind' => 'compliance',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seedDomain('ens-domain-org', 'framework-ens', 'ORG', 1);
        $this->seedDomain('ens-domain-protect', 'framework-ens', 'PROTECT', 2);
        $this->seedDomain('ens-domain-detect', 'framework-ens', 'DETECT', 3);
        $this->seedDomain('ens-domain-recover', 'framework-ens', 'RECOVER', 4);

        $this->seedMeasure('ens-org-governance', 'framework-ens', 'ens-domain-org', 'org.1', 'basic', 1);
        $this->seedMeasure('ens-protect-access', 'framework-ens', 'ens-domain-protect', 'pr.1', 'basic', 1);
        $this->seedMeasure('ens-protect-backup', 'framework-ens', 'ens-domain-protect', 'pr.2', 'medium', 2);
        $this->seedMeasure('ens-detect-monitoring', 'framework-ens', 'ens-domain-detect', 'de.1', 'medium', 1);
        $this->seedMeasure('ens-recover-continuity', 'framework-ens', 'ens-domain-recover', 'rc.1', 'high', 1);
    }

    private function seedDomain(string $id, string $frameworkId, string $code, int $sortOrder): void
    {
        DB::table('framework_elements')->insertOrIgnore([
            'id' => $id,
            'framework_id' => $frameworkId,
            'parent_id' => null,
            'code' => $code,
            'title' => "plugin.framework-ens.elements.{$id}.title",
            'description' => "plugin.framework-ens.elements.{$id}.description",
            'element_type' => 'domain',
            'applicability_level' => null,
            'sort_order' => $sortOrder,
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedMeasure(
        string $id,
        string $frameworkId,
        string $parentId,
        string $code,
        string $applicabilityLevel,
        int $sortOrder,
    ): void {
        DB::table('framework_elements')->insertOrIgnore([
            'id' => $id,
            'framework_id' => $frameworkId,
            'parent_id' => $parentId,
            'code' => $code,
            'title' => "plugin.framework-ens.elements.{$id}.title",
            'description' => "plugin.framework-ens.elements.{$id}.description",
            'element_type' => 'measure',
            'applicability_level' => $applicabilityLevel,
            'sort_order' => $sortOrder,
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
