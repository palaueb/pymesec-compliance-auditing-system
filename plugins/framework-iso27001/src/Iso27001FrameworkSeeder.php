<?php

namespace PymeSec\Plugins\FrameworkIso27001;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the ISO/IEC 27001:2022 Annex A framework catalog as global data.
 *
 * This seeder is idempotent (insertOrIgnore) and is called by SystemBootstrapSeeder
 * on every fresh installation. It does NOT create any organization-level data.
 *
 * Translation keys follow the convention plugin.framework-iso27001.elements.<id>.title/description.
 * Actual text lives in resources/lang/en.json (and future locale files).
 *
 * Structure seeded:
 *   1 framework:  iso27001 (global, organization_id = null)
 *   4 themes:     iso27001-theme-5, -6, -7, -8 (element_type = domain)
 *  93 controls:   iso27001-5-1 through iso27001-8-34 (element_type = control)
 *
 * Control IDs follow the pattern iso27001-<clause> (dots replaced by dashes).
 * Example: A.5.18 → iso27001-5-18
 */
class Iso27001FrameworkSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('frameworks')->insertOrIgnore([
            'id' => 'framework-iso-27001',
            'organization_id' => null,
            'code' => 'ISO 27001',
            'name' => 'plugin.framework-iso27001.framework.name',
            'version' => '2022',
            'description' => 'plugin.framework-iso27001.framework.description',
            'kind' => 'audit',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seedTheme('iso27001-theme-5', 'framework-iso-27001', null, '5', 1);
        $this->seedTheme('iso27001-theme-6', 'framework-iso-27001', null, '6', 2);
        $this->seedTheme('iso27001-theme-7', 'framework-iso-27001', null, '7', 3);
        $this->seedTheme('iso27001-theme-8', 'framework-iso-27001', null, '8', 4);

        // Theme 5 — Organizational controls (A.5.1 – A.5.37)
        $this->seedControl('iso27001-5-1',  'framework-iso-27001', 'iso27001-theme-5', 'A.5.1',  1);
        $this->seedControl('iso27001-5-2',  'framework-iso-27001', 'iso27001-theme-5', 'A.5.2',  2);
        $this->seedControl('iso27001-5-3',  'framework-iso-27001', 'iso27001-theme-5', 'A.5.3',  3);
        $this->seedControl('iso27001-5-4',  'framework-iso-27001', 'iso27001-theme-5', 'A.5.4',  4);
        $this->seedControl('iso27001-5-5',  'framework-iso-27001', 'iso27001-theme-5', 'A.5.5',  5);
        $this->seedControl('iso27001-5-6',  'framework-iso-27001', 'iso27001-theme-5', 'A.5.6',  6);
        $this->seedControl('iso27001-5-7',  'framework-iso-27001', 'iso27001-theme-5', 'A.5.7',  7);
        $this->seedControl('iso27001-5-8',  'framework-iso-27001', 'iso27001-theme-5', 'A.5.8',  8);
        $this->seedControl('iso27001-5-9',  'framework-iso-27001', 'iso27001-theme-5', 'A.5.9',  9);
        $this->seedControl('iso27001-5-10', 'framework-iso-27001', 'iso27001-theme-5', 'A.5.10', 10);
        $this->seedControl('iso27001-5-11', 'framework-iso-27001', 'iso27001-theme-5', 'A.5.11', 11);
        $this->seedControl('iso27001-5-12', 'framework-iso-27001', 'iso27001-theme-5', 'A.5.12', 12);
        $this->seedControl('iso27001-5-13', 'framework-iso-27001', 'iso27001-theme-5', 'A.5.13', 13);
        $this->seedControl('iso27001-5-14', 'framework-iso-27001', 'iso27001-theme-5', 'A.5.14', 14);
        $this->seedControl('iso27001-5-15', 'framework-iso-27001', 'iso27001-theme-5', 'A.5.15', 15);
        $this->seedControl('iso27001-5-16', 'framework-iso-27001', 'iso27001-theme-5', 'A.5.16', 16);
        $this->seedControl('iso27001-5-17', 'framework-iso-27001', 'iso27001-theme-5', 'A.5.17', 17);
        $this->seedControl('iso27001-5-18', 'framework-iso-27001', 'iso27001-theme-5', 'A.5.18', 18);
        $this->seedControl('iso27001-5-19', 'framework-iso-27001', 'iso27001-theme-5', 'A.5.19', 19);
        $this->seedControl('iso27001-5-20', 'framework-iso-27001', 'iso27001-theme-5', 'A.5.20', 20);
        $this->seedControl('iso27001-5-21', 'framework-iso-27001', 'iso27001-theme-5', 'A.5.21', 21);
        $this->seedControl('iso27001-5-22', 'framework-iso-27001', 'iso27001-theme-5', 'A.5.22', 22);
        $this->seedControl('iso27001-5-23', 'framework-iso-27001', 'iso27001-theme-5', 'A.5.23', 23);
        $this->seedControl('iso27001-5-24', 'framework-iso-27001', 'iso27001-theme-5', 'A.5.24', 24);
        $this->seedControl('iso27001-5-25', 'framework-iso-27001', 'iso27001-theme-5', 'A.5.25', 25);
        $this->seedControl('iso27001-5-26', 'framework-iso-27001', 'iso27001-theme-5', 'A.5.26', 26);
        $this->seedControl('iso27001-5-27', 'framework-iso-27001', 'iso27001-theme-5', 'A.5.27', 27);
        $this->seedControl('iso27001-5-28', 'framework-iso-27001', 'iso27001-theme-5', 'A.5.28', 28);
        $this->seedControl('iso27001-5-29', 'framework-iso-27001', 'iso27001-theme-5', 'A.5.29', 29);
        $this->seedControl('iso27001-5-30', 'framework-iso-27001', 'iso27001-theme-5', 'A.5.30', 30);
        $this->seedControl('iso27001-5-31', 'framework-iso-27001', 'iso27001-theme-5', 'A.5.31', 31);
        $this->seedControl('iso27001-5-32', 'framework-iso-27001', 'iso27001-theme-5', 'A.5.32', 32);
        $this->seedControl('iso27001-5-33', 'framework-iso-27001', 'iso27001-theme-5', 'A.5.33', 33);
        $this->seedControl('iso27001-5-34', 'framework-iso-27001', 'iso27001-theme-5', 'A.5.34', 34);
        $this->seedControl('iso27001-5-35', 'framework-iso-27001', 'iso27001-theme-5', 'A.5.35', 35);
        $this->seedControl('iso27001-5-36', 'framework-iso-27001', 'iso27001-theme-5', 'A.5.36', 36);
        $this->seedControl('iso27001-5-37', 'framework-iso-27001', 'iso27001-theme-5', 'A.5.37', 37);

        // Theme 6 — People controls (A.6.1 – A.6.8)
        $this->seedControl('iso27001-6-1', 'framework-iso-27001', 'iso27001-theme-6', 'A.6.1', 1);
        $this->seedControl('iso27001-6-2', 'framework-iso-27001', 'iso27001-theme-6', 'A.6.2', 2);
        $this->seedControl('iso27001-6-3', 'framework-iso-27001', 'iso27001-theme-6', 'A.6.3', 3);
        $this->seedControl('iso27001-6-4', 'framework-iso-27001', 'iso27001-theme-6', 'A.6.4', 4);
        $this->seedControl('iso27001-6-5', 'framework-iso-27001', 'iso27001-theme-6', 'A.6.5', 5);
        $this->seedControl('iso27001-6-6', 'framework-iso-27001', 'iso27001-theme-6', 'A.6.6', 6);
        $this->seedControl('iso27001-6-7', 'framework-iso-27001', 'iso27001-theme-6', 'A.6.7', 7);
        $this->seedControl('iso27001-6-8', 'framework-iso-27001', 'iso27001-theme-6', 'A.6.8', 8);

        // Theme 7 — Physical controls (A.7.1 – A.7.14)
        $this->seedControl('iso27001-7-1',  'framework-iso-27001', 'iso27001-theme-7', 'A.7.1',  1);
        $this->seedControl('iso27001-7-2',  'framework-iso-27001', 'iso27001-theme-7', 'A.7.2',  2);
        $this->seedControl('iso27001-7-3',  'framework-iso-27001', 'iso27001-theme-7', 'A.7.3',  3);
        $this->seedControl('iso27001-7-4',  'framework-iso-27001', 'iso27001-theme-7', 'A.7.4',  4);
        $this->seedControl('iso27001-7-5',  'framework-iso-27001', 'iso27001-theme-7', 'A.7.5',  5);
        $this->seedControl('iso27001-7-6',  'framework-iso-27001', 'iso27001-theme-7', 'A.7.6',  6);
        $this->seedControl('iso27001-7-7',  'framework-iso-27001', 'iso27001-theme-7', 'A.7.7',  7);
        $this->seedControl('iso27001-7-8',  'framework-iso-27001', 'iso27001-theme-7', 'A.7.8',  8);
        $this->seedControl('iso27001-7-9',  'framework-iso-27001', 'iso27001-theme-7', 'A.7.9',  9);
        $this->seedControl('iso27001-7-10', 'framework-iso-27001', 'iso27001-theme-7', 'A.7.10', 10);
        $this->seedControl('iso27001-7-11', 'framework-iso-27001', 'iso27001-theme-7', 'A.7.11', 11);
        $this->seedControl('iso27001-7-12', 'framework-iso-27001', 'iso27001-theme-7', 'A.7.12', 12);
        $this->seedControl('iso27001-7-13', 'framework-iso-27001', 'iso27001-theme-7', 'A.7.13', 13);
        $this->seedControl('iso27001-7-14', 'framework-iso-27001', 'iso27001-theme-7', 'A.7.14', 14);

        // Theme 8 — Technological controls (A.8.1 – A.8.34)
        $this->seedControl('iso27001-8-1',  'framework-iso-27001', 'iso27001-theme-8', 'A.8.1',  1);
        $this->seedControl('iso27001-8-2',  'framework-iso-27001', 'iso27001-theme-8', 'A.8.2',  2);
        $this->seedControl('iso27001-8-3',  'framework-iso-27001', 'iso27001-theme-8', 'A.8.3',  3);
        $this->seedControl('iso27001-8-4',  'framework-iso-27001', 'iso27001-theme-8', 'A.8.4',  4);
        $this->seedControl('iso27001-8-5',  'framework-iso-27001', 'iso27001-theme-8', 'A.8.5',  5);
        $this->seedControl('iso27001-8-6',  'framework-iso-27001', 'iso27001-theme-8', 'A.8.6',  6);
        $this->seedControl('iso27001-8-7',  'framework-iso-27001', 'iso27001-theme-8', 'A.8.7',  7);
        $this->seedControl('iso27001-8-8',  'framework-iso-27001', 'iso27001-theme-8', 'A.8.8',  8);
        $this->seedControl('iso27001-8-9',  'framework-iso-27001', 'iso27001-theme-8', 'A.8.9',  9);
        $this->seedControl('iso27001-8-10', 'framework-iso-27001', 'iso27001-theme-8', 'A.8.10', 10);
        $this->seedControl('iso27001-8-11', 'framework-iso-27001', 'iso27001-theme-8', 'A.8.11', 11);
        $this->seedControl('iso27001-8-12', 'framework-iso-27001', 'iso27001-theme-8', 'A.8.12', 12);
        $this->seedControl('iso27001-8-13', 'framework-iso-27001', 'iso27001-theme-8', 'A.8.13', 13);
        $this->seedControl('iso27001-8-14', 'framework-iso-27001', 'iso27001-theme-8', 'A.8.14', 14);
        $this->seedControl('iso27001-8-15', 'framework-iso-27001', 'iso27001-theme-8', 'A.8.15', 15);
        $this->seedControl('iso27001-8-16', 'framework-iso-27001', 'iso27001-theme-8', 'A.8.16', 16);
        $this->seedControl('iso27001-8-17', 'framework-iso-27001', 'iso27001-theme-8', 'A.8.17', 17);
        $this->seedControl('iso27001-8-18', 'framework-iso-27001', 'iso27001-theme-8', 'A.8.18', 18);
        $this->seedControl('iso27001-8-19', 'framework-iso-27001', 'iso27001-theme-8', 'A.8.19', 19);
        $this->seedControl('iso27001-8-20', 'framework-iso-27001', 'iso27001-theme-8', 'A.8.20', 20);
        $this->seedControl('iso27001-8-21', 'framework-iso-27001', 'iso27001-theme-8', 'A.8.21', 21);
        $this->seedControl('iso27001-8-22', 'framework-iso-27001', 'iso27001-theme-8', 'A.8.22', 22);
        $this->seedControl('iso27001-8-23', 'framework-iso-27001', 'iso27001-theme-8', 'A.8.23', 23);
        $this->seedControl('iso27001-8-24', 'framework-iso-27001', 'iso27001-theme-8', 'A.8.24', 24);
        $this->seedControl('iso27001-8-25', 'framework-iso-27001', 'iso27001-theme-8', 'A.8.25', 25);
        $this->seedControl('iso27001-8-26', 'framework-iso-27001', 'iso27001-theme-8', 'A.8.26', 26);
        $this->seedControl('iso27001-8-27', 'framework-iso-27001', 'iso27001-theme-8', 'A.8.27', 27);
        $this->seedControl('iso27001-8-28', 'framework-iso-27001', 'iso27001-theme-8', 'A.8.28', 28);
        $this->seedControl('iso27001-8-29', 'framework-iso-27001', 'iso27001-theme-8', 'A.8.29', 29);
        $this->seedControl('iso27001-8-30', 'framework-iso-27001', 'iso27001-theme-8', 'A.8.30', 30);
        $this->seedControl('iso27001-8-31', 'framework-iso-27001', 'iso27001-theme-8', 'A.8.31', 31);
        $this->seedControl('iso27001-8-32', 'framework-iso-27001', 'iso27001-theme-8', 'A.8.32', 32);
        $this->seedControl('iso27001-8-33', 'framework-iso-27001', 'iso27001-theme-8', 'A.8.33', 33);
        $this->seedControl('iso27001-8-34', 'framework-iso-27001', 'iso27001-theme-8', 'A.8.34', 34);
    }

    private function seedTheme(string $id, string $frameworkId, ?string $parentId, string $code, int $sortOrder): void
    {
        DB::table('framework_elements')->insertOrIgnore([
            'id' => $id,
            'framework_id' => $frameworkId,
            'parent_id' => $parentId,
            'code' => $code,
            'title' => "plugin.framework-iso27001.elements.{$id}.title",
            'description' => "plugin.framework-iso27001.elements.{$id}.description",
            'element_type' => 'domain',
            'applicability_level' => null,
            'sort_order' => $sortOrder,
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedControl(string $id, string $frameworkId, string $parentId, string $code, int $sortOrder): void
    {
        DB::table('framework_elements')->insertOrIgnore([
            'id' => $id,
            'framework_id' => $frameworkId,
            'parent_id' => $parentId,
            'code' => $code,
            'title' => "plugin.framework-iso27001.elements.{$id}.title",
            'description' => "plugin.framework-iso27001.elements.{$id}.description",
            'element_type' => 'control',
            'applicability_level' => null,
            'sort_order' => $sortOrder,
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
