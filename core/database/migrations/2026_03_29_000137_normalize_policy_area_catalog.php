<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('policies')) {
            return;
        }

        $defaults = [
            'Identity' => 'identity',
            'Resilience' => 'resilience',
            'Operations' => 'operations',
            'Third Parties' => 'third-parties',
            'Third parties' => 'third-parties',
            'Governance' => 'governance',
            'Privacy' => 'privacy',
        ];

        $rows = DB::table('policies')
            ->select('id', 'organization_id', 'area')
            ->get();

        $sortOrderByOrganization = [];

        foreach ($rows as $row) {
            $area = is_string($row->area ?? null) ? trim((string) $row->area) : '';

            if ($area === '') {
                continue;
            }

            $normalized = $defaults[$area] ?? null;

            if ($normalized === null && in_array($area, array_values($defaults), true)) {
                continue;
            }

            if ($normalized === null) {
                $normalized = Str::slug($area);
            }

            if ($normalized === '') {
                continue;
            }

            if (! in_array($normalized, array_values($defaults), true) && Schema::hasTable('reference_catalog_entries')) {
                $organizationId = (string) $row->organization_id;
                $sortOrderByOrganization[$organizationId] = ($sortOrderByOrganization[$organizationId] ?? 1000) + 10;
                $existingEntryId = DB::table('reference_catalog_entries')
                    ->where('organization_id', $organizationId)
                    ->where('catalog_key', 'policies.areas')
                    ->where('option_key', $normalized)
                    ->value('id');

                if ($existingEntryId === null) {
                    DB::table('reference_catalog_entries')->insert([
                        'id' => (string) Str::ulid(),
                        'organization_id' => $organizationId,
                        'catalog_key' => 'policies.areas',
                        'option_key' => $normalized,
                        'label' => $area,
                        'description' => 'Migrated from legacy policy area free text.',
                        'sort_order' => $sortOrderByOrganization[$organizationId],
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('reference_catalog_entries')
                        ->where('id', $existingEntryId)
                        ->update([
                            'label' => $area,
                            'description' => 'Migrated from legacy policy area free text.',
                            'is_active' => true,
                            'updated_at' => now(),
                        ]);
                }
            }

            DB::table('policies')
                ->where('id', (string) $row->id)
                ->update([
                    'area' => $normalized,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('policies')) {
            return;
        }

        $reverse = [
            'identity' => 'Identity',
            'resilience' => 'Resilience',
            'operations' => 'Operations',
            'third-parties' => 'Third parties',
            'governance' => 'Governance',
            'privacy' => 'Privacy',
        ];

        foreach ($reverse as $key => $label) {
            DB::table('policies')
                ->where('area', $key)
                ->update([
                    'area' => $label,
                    'updated_at' => now(),
                ]);
        }
    }
};
