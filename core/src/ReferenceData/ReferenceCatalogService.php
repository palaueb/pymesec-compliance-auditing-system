<?php

namespace PymeSec\Core\ReferenceData;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReferenceCatalogService
{
    /**
     * @return array<int, array{key: string, label: string, description: string}>
     */
    public function manageableCatalogs(): array
    {
        return array_values($this->catalogDefinitions());
    }

    /**
     * @return array<string, string>
     */
    public function options(string $catalogKey, ?string $organizationId = null): array
    {
        $rows = $this->effectiveRows($catalogKey, $organizationId);
        $options = [];

        foreach ($rows as $row) {
            $options[$row['option_key']] = $row['label'];
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public function keys(string $catalogKey, ?string $organizationId = null): array
    {
        return array_keys($this->options($catalogKey, $organizationId));
    }

    public function label(string $catalogKey, string $value, ?string $organizationId = null): string
    {
        return $this->options($catalogKey, $organizationId)[$value] ?? $value;
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    public function optionRows(string $catalogKey, ?string $organizationId = null): array
    {
        return array_map(static fn (array $row): array => [
            'id' => $row['option_key'],
            'label' => $row['label'],
        ], $this->effectiveRows($catalogKey, $organizationId));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function effectiveRows(string $catalogKey, ?string $organizationId = null): array
    {
        $rows = [];
        $position = 0;

        foreach ($this->defaultOptions($catalogKey) as $optionKey => $label) {
            $position += 10;
            $rows[$optionKey] = [
                'option_key' => $optionKey,
                'label' => $label,
                'description' => '',
                'sort_order' => $position,
                'source' => 'default',
                'managed_entry_id' => null,
            ];
        }

        foreach ($this->managedEntries($catalogKey, $organizationId) as $entry) {
            if (! ($entry['is_active'] ?? false)) {
                unset($rows[$entry['option_key']]);

                continue;
            }

            $rows[$entry['option_key']] = [
                'option_key' => $entry['option_key'],
                'label' => $entry['label'],
                'description' => $entry['description'],
                'sort_order' => (int) $entry['sort_order'],
                'source' => 'managed',
                'managed_entry_id' => $entry['id'],
            ];
        }

        $rows = array_values($rows);

        usort($rows, static fn (array $left, array $right): int => [$left['sort_order'], $left['label']] <=> [$right['sort_order'], $right['label']]);

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function managedEntries(string $catalogKey, ?string $organizationId): array
    {
        if (! is_string($organizationId) || $organizationId === '') {
            return [];
        }

        return DB::table('reference_catalog_entries')
            ->where('organization_id', $organizationId)
            ->where('catalog_key', $catalogKey)
            ->orderBy('sort_order')
            ->orderBy('option_key')
            ->get()
            ->map(static fn ($entry): array => [
                'id' => (string) $entry->id,
                'organization_id' => (string) $entry->organization_id,
                'catalog_key' => (string) $entry->catalog_key,
                'option_key' => (string) $entry->option_key,
                'label' => (string) $entry->label,
                'description' => is_string($entry->description ?? null) ? $entry->description : '',
                'sort_order' => (int) $entry->sort_order,
                'is_active' => (bool) $entry->is_active,
            ])->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findManagedEntry(string $entryId): ?array
    {
        $entry = DB::table('reference_catalog_entries')->where('id', $entryId)->first();

        if ($entry === null) {
            return null;
        }

        return [
            'id' => (string) $entry->id,
            'organization_id' => (string) $entry->organization_id,
            'catalog_key' => (string) $entry->catalog_key,
            'option_key' => (string) $entry->option_key,
            'label' => (string) $entry->label,
            'description' => is_string($entry->description ?? null) ? $entry->description : '',
            'sort_order' => (int) $entry->sort_order,
            'is_active' => (bool) $entry->is_active,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createManagedEntry(array $data, ?string $principalId): array
    {
        $id = (string) Str::ulid();

        DB::table('reference_catalog_entries')->insert([
            'id' => $id,
            'organization_id' => (string) $data['organization_id'],
            'catalog_key' => (string) $data['catalog_key'],
            'option_key' => (string) $data['option_key'],
            'label' => (string) $data['label'],
            'description' => is_string($data['description'] ?? null) && $data['description'] !== '' ? (string) $data['description'] : null,
            'sort_order' => (int) ($data['sort_order'] ?? 100),
            'is_active' => true,
            'created_by_principal_id' => $principalId,
            'updated_by_principal_id' => $principalId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var array<string, mixed> $entry */
        $entry = $this->findManagedEntry($id);

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public function updateManagedEntry(string $entryId, array $data, ?string $principalId): ?array
    {
        $updated = DB::table('reference_catalog_entries')
            ->where('id', $entryId)
            ->update([
                'option_key' => (string) $data['option_key'],
                'label' => (string) $data['label'],
                'description' => is_string($data['description'] ?? null) && $data['description'] !== '' ? (string) $data['description'] : null,
                'sort_order' => (int) ($data['sort_order'] ?? 100),
                'updated_by_principal_id' => $principalId,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            return $this->findManagedEntry($entryId);
        }

        return $this->findManagedEntry($entryId);
    }

    public function archiveManagedEntry(string $entryId, ?string $principalId): bool
    {
        return DB::table('reference_catalog_entries')
            ->where('id', $entryId)
            ->update([
                'is_active' => false,
                'updated_by_principal_id' => $principalId,
                'updated_at' => now(),
            ]) > 0;
    }

    public function activateManagedEntry(string $entryId, ?string $principalId): bool
    {
        return DB::table('reference_catalog_entries')
            ->where('id', $entryId)
            ->update([
                'is_active' => true,
                'updated_by_principal_id' => $principalId,
                'updated_at' => now(),
            ]) > 0;
    }

    public function currentOrganizationId(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = request();

        $value = $request->input('organization_id', $request->query('organization_id'));

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<string, array{key: string, label: string, description: string}>
     */
    private function catalogDefinitions(): array
    {
        return [
            'assets.types' => [
                'key' => 'assets.types',
                'label' => 'Asset types',
                'description' => 'Govern the kinds of assets people can register in the workspace.',
            ],
            'assets.criticality' => [
                'key' => 'assets.criticality',
                'label' => 'Asset criticality',
                'description' => 'Define the business criticality scale used in the asset catalog.',
            ],
            'assets.classification' => [
                'key' => 'assets.classification',
                'label' => 'Asset classification',
                'description' => 'Define the information classification scheme used for assets.',
            ],
            'continuity.impact_tier' => [
                'key' => 'continuity.impact_tier',
                'label' => 'Continuity impact tiers',
                'description' => 'Control the impact scale used for continuity services.',
            ],
            'continuity.dependency_kind' => [
                'key' => 'continuity.dependency_kind',
                'label' => 'Dependency kinds',
                'description' => 'Define the kinds of operational dependencies used in continuity services.',
            ],
            'privacy.transfer_type' => [
                'key' => 'privacy.transfer_type',
                'label' => 'Privacy transfer types',
                'description' => 'Define the controlled transfer types for privacy data flows.',
            ],
            'privacy.lawful_basis' => [
                'key' => 'privacy.lawful_basis',
                'label' => 'Lawful bases',
                'description' => 'Define the lawful bases used in privacy processing activities.',
            ],
            'findings.severity' => [
                'key' => 'findings.severity',
                'label' => 'Finding severity',
                'description' => 'Define the severity scale used across findings and assessments.',
            ],
            'risks.categories' => [
                'key' => 'risks.categories',
                'label' => 'Risk categories',
                'description' => 'Define the controlled categories used in the risk register.',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function defaultOptions(string $catalogKey): array
    {
        $values = config('reference_data.'.$catalogKey, []);

        if (! is_array($values)) {
            return [];
        }

        return array_filter($values, static fn ($label, $key): bool => is_string($key) && $key !== '' && is_string($label) && $label !== '', ARRAY_FILTER_USE_BOTH);
    }
}
