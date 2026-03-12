<?php

namespace PymeSec\Plugins\AssetCatalog;

class AssetCatalogRepository
{
    /**
     * @return array<int, array<string, string>>
     */
    public function all(string $organizationId, ?string $scopeId = null): array
    {
        return array_values(array_filter($this->assets(), static function (array $asset) use ($organizationId, $scopeId): bool {
            if ($asset['organization_id'] !== $organizationId) {
                return false;
            }

            if ($scopeId === null || $scopeId === '') {
                return true;
            }

            return ($asset['scope_id'] ?? null) === $scopeId;
        }));
    }

    /**
     * @return array<string, string> | null
     */
    public function find(string $assetId): ?array
    {
        foreach ($this->assets() as $asset) {
            if ($asset['id'] === $assetId) {
                return $asset;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function assets(): array
    {
        return [
            [
                'id' => 'asset-erp-prod',
                'organization_id' => 'org-a',
                'scope_id' => 'scope-eu',
                'name' => 'ERP Production',
                'type' => 'application',
                'criticality' => 'high',
                'classification' => 'confidential',
                'owner' => 'Finance Operations',
            ],
            [
                'id' => 'asset-vault-docs',
                'organization_id' => 'org-a',
                'scope_id' => '',
                'name' => 'Document Vault',
                'type' => 'storage',
                'criticality' => 'medium',
                'classification' => 'restricted',
                'owner' => 'Compliance Office',
            ],
            [
                'id' => 'asset-laptop-fleet',
                'organization_id' => 'org-a',
                'scope_id' => 'scope-it',
                'name' => 'Managed Laptop Fleet',
                'type' => 'endpoint',
                'criticality' => 'medium',
                'classification' => 'internal',
                'owner' => 'IT Services',
            ],
            [
                'id' => 'asset-warehouse-mesh',
                'organization_id' => 'org-b',
                'scope_id' => 'scope-ops',
                'name' => 'Warehouse Mesh',
                'type' => 'network',
                'criticality' => 'high',
                'classification' => 'restricted',
                'owner' => 'Operations Control',
            ],
            [
                'id' => 'asset-route-planner',
                'organization_id' => 'org-b',
                'scope_id' => 'scope-ops',
                'name' => 'Route Planner',
                'type' => 'application',
                'criticality' => 'medium',
                'classification' => 'internal',
                'owner' => 'Logistics Team',
            ],
        ];
    }
}
