<?php

namespace PymeSec\Plugins\FrameworkGdpr;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GdprFrameworkSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('frameworks')->insertOrIgnore([
            'id' => 'framework-gdpr',
            'organization_id' => null,
            'code' => 'GDPR',
            'name' => 'plugin.framework-gdpr.framework.name',
            'version' => '2016',
            'description' => 'plugin.framework-gdpr.framework.description',
            'kind' => 'directive',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seedDomain('gdpr-domain-principles', 'framework-gdpr', 'Principles', 1);
        $this->seedDomain('gdpr-domain-governance', 'framework-gdpr', 'Governance', 2);
        $this->seedDomain('gdpr-domain-incidents', 'framework-gdpr', 'Incidents', 3);

        $this->seedArticle('gdpr-article-5', 'framework-gdpr', 'gdpr-domain-principles', 'Article 5', 1);
        $this->seedArticle('gdpr-article-30', 'framework-gdpr', 'gdpr-domain-governance', 'Article 30', 1);
        $this->seedArticle('gdpr-article-32', 'framework-gdpr', 'gdpr-domain-governance', 'Article 32', 2);
        $this->seedArticle('gdpr-article-33', 'framework-gdpr', 'gdpr-domain-incidents', 'Article 33', 1);
        $this->seedArticle('gdpr-article-35', 'framework-gdpr', 'gdpr-domain-governance', 'Article 35', 3);
    }

    private function seedDomain(string $id, string $frameworkId, string $code, int $sortOrder): void
    {
        DB::table('framework_elements')->insertOrIgnore([
            'id' => $id,
            'framework_id' => $frameworkId,
            'parent_id' => null,
            'code' => $code,
            'title' => "plugin.framework-gdpr.elements.{$id}.title",
            'description' => "plugin.framework-gdpr.elements.{$id}.description",
            'element_type' => 'domain',
            'applicability_level' => null,
            'sort_order' => $sortOrder,
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedArticle(string $id, string $frameworkId, string $parentId, string $code, int $sortOrder): void
    {
        DB::table('framework_elements')->insertOrIgnore([
            'id' => $id,
            'framework_id' => $frameworkId,
            'parent_id' => $parentId,
            'code' => $code,
            'title' => "plugin.framework-gdpr.elements.{$id}.title",
            'description' => "plugin.framework-gdpr.elements.{$id}.description",
            'element_type' => 'article',
            'applicability_level' => null,
            'sort_order' => $sortOrder,
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
