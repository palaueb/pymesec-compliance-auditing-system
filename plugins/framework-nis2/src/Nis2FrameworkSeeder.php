<?php

namespace PymeSec\Plugins\FrameworkNis2;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the NIS2 Directive (EU 2022/2555) framework catalog as global data.
 *
 * This seeder is idempotent (insertOrIgnore) and is called by SystemBootstrapSeeder.
 * It does NOT create any organization-level data.
 *
 * Translation keys follow the convention plugin.framework-nis2.elements.<id>.title/description.
 * Actual text lives in resources/lang/en.json (and future locale files).
 *
 * Structure seeded:
 *   1 framework:  framework-nis2 (global, organization_id = null)
 *   3 groups:     nis2-chapter-2, nis2-chapter-4, nis2-chapter-5 (element_type = domain)
 *   Key articles: Article 21 (risk measures), Article 23 (incident reporting)
 *
 * Note: NIS2 is structured as Articles and Paragraphs, not controls. The element_type
 * 'article' is used for top-level obligations and 'obligation' for specific requirements.
 */
class Nis2FrameworkSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('frameworks')->insertOrIgnore([
            'id' => 'framework-nis2',
            'organization_id' => null,
            'code' => 'NIS2',
            'name' => 'plugin.framework-nis2.framework.name',
            'version' => '2022',
            'description' => 'plugin.framework-nis2.framework.description',
            'kind' => 'directive',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seedGroup('nis2-chapter-2', 'framework-nis2', null, 'Chapter II', 1);
        $this->seedGroup('nis2-chapter-4', 'framework-nis2', null, 'Chapter IV', 2);
        $this->seedGroup('nis2-chapter-5', 'framework-nis2', null, 'Chapter V', 3);

        $this->seedArticle('nis2-article-21', 'framework-nis2', 'nis2-chapter-4', 'Article 21', 1);
        $this->seedObligation('nis2-21-a', 'framework-nis2', 'nis2-article-21', '21(a)', 1);
        $this->seedObligation('nis2-21-b', 'framework-nis2', 'nis2-article-21', '21(b)', 2);
        $this->seedObligation('nis2-21-c', 'framework-nis2', 'nis2-article-21', '21(c)', 3);
        $this->seedObligation('nis2-21-d', 'framework-nis2', 'nis2-article-21', '21(d)', 4);
        $this->seedObligation('nis2-21-e', 'framework-nis2', 'nis2-article-21', '21(e)', 5);
        $this->seedObligation('nis2-21-f', 'framework-nis2', 'nis2-article-21', '21(f)', 6);
        $this->seedObligation('nis2-21-g', 'framework-nis2', 'nis2-article-21', '21(g)', 7);
        $this->seedObligation('nis2-21-h', 'framework-nis2', 'nis2-article-21', '21(h)', 8);
        $this->seedObligation('nis2-21-i', 'framework-nis2', 'nis2-article-21', '21(i)', 9);
        $this->seedObligation('nis2-21-j', 'framework-nis2', 'nis2-article-21', '21(j)', 10);

        $this->seedArticle('nis2-article-23', 'framework-nis2', 'nis2-chapter-4', 'Article 23', 2);
        $this->seedObligation('nis2-23-1', 'framework-nis2', 'nis2-article-23', '23(1)', 1);
        $this->seedObligation('nis2-23-2', 'framework-nis2', 'nis2-article-23', '23(2)', 2);
        $this->seedObligation('nis2-23-3', 'framework-nis2', 'nis2-article-23', '23(3)', 3);
    }

    private function seedGroup(string $id, string $frameworkId, ?string $parentId, string $code, int $sortOrder): void
    {
        DB::table('framework_elements')->insertOrIgnore([
            'id' => $id,
            'framework_id' => $frameworkId,
            'parent_id' => $parentId,
            'code' => $code,
            'title' => "plugin.framework-nis2.elements.{$id}.title",
            'description' => null,
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
            'title' => "plugin.framework-nis2.elements.{$id}.title",
            'description' => "plugin.framework-nis2.elements.{$id}.description",
            'element_type' => 'article',
            'applicability_level' => null,
            'sort_order' => $sortOrder,
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedObligation(string $id, string $frameworkId, string $parentId, string $code, int $sortOrder): void
    {
        DB::table('framework_elements')->insertOrIgnore([
            'id' => $id,
            'framework_id' => $frameworkId,
            'parent_id' => $parentId,
            'code' => $code,
            'title' => "plugin.framework-nis2.elements.{$id}.title",
            'description' => "plugin.framework-nis2.elements.{$id}.description",
            'element_type' => 'obligation',
            'applicability_level' => null,
            'sort_order' => $sortOrder,
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
