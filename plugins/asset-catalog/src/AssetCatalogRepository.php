<?php

namespace PymeSec\Plugins\AssetCatalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssetCatalogRepository
{
    /**
     * @return array<int, array<string, string>>
     */
    public function all(string $organizationId, ?string $scopeId = null): array
    {
        $query = DB::table('assets')
            ->where('organization_id', $organizationId)
            ->orderBy('criticality')
            ->orderBy('name');

        if ($scopeId !== null && $scopeId !== '') {
            $query->where('scope_id', $scopeId);
        }

        return $query->get()
            ->map(fn ($asset): array => $this->mapAsset($asset))
            ->all();
    }

    /**
     * @return array<string, string>|null
     */
    public function find(string $assetId): ?array
    {
        $asset = DB::table('assets')->where('id', $assetId)->first();

        return $asset !== null ? $this->mapAsset($asset) : null;
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, string>
     */
    public function create(array $data): array
    {
        $id = $this->nextId('asset', (string) ($data['name'] ?? 'asset'));

        DB::table('assets')->insert([
            'id' => $id,
            'organization_id' => $data['organization_id'],
            'scope_id' => ($data['scope_id'] ?? null) ?: null,
            'name' => $data['name'],
            'type' => $data['type'],
            'criticality' => $data['criticality'],
            'classification' => $data['classification'],
            'owner_label' => ($data['owner_label'] ?? null) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var array<string, string> $asset */
        $asset = $this->find($id);

        return $asset;
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, string>|null
     */
    public function update(string $assetId, array $data): ?array
    {
        $update = [
            'scope_id' => ($data['scope_id'] ?? null) ?: null,
            'name' => $data['name'],
            'type' => $data['type'],
            'criticality' => $data['criticality'],
            'classification' => $data['classification'],
            'updated_at' => now(),
        ];

        if (array_key_exists('owner_label', $data)) {
            $update['owner_label'] = ($data['owner_label'] ?? null) ?: null;
        }

        $updated = DB::table('assets')
            ->where('id', $assetId)
            ->update($update);

        if ($updated === 0) {
            return $this->find($assetId);
        }

        return $this->find($assetId);
    }

    private function nextId(string $prefix, string $value): string
    {
        $base = $prefix.'-'.Str::slug($value);
        $candidate = $base !== $prefix.'-' ? $base : $prefix.'-'.Str::lower(Str::ulid());

        if (! DB::table('assets')->where('id', $candidate)->exists()) {
            return $candidate;
        }

        return $candidate.'-'.Str::lower(Str::random(4));
    }

    /**
     * @return array<string, string>
     */
    private function mapAsset(object $asset): array
    {
        return [
            'id' => (string) $asset->id,
            'organization_id' => (string) $asset->organization_id,
            'scope_id' => is_string($asset->scope_id) ? $asset->scope_id : '',
            'name' => (string) $asset->name,
            'type' => (string) $asset->type,
            'criticality' => (string) $asset->criticality,
            'classification' => (string) $asset->classification,
            'owner_label' => is_string($asset->owner_label) ? $asset->owner_label : '',
        ];
    }
}
