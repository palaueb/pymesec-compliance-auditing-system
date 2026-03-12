<?php

namespace PymeSec\Plugins\ControlsCatalog;

class ControlsCatalogRepository
{
    /**
     * @return array<int, array<string, string>>
     */
    public function all(string $organizationId, ?string $scopeId = null): array
    {
        return array_values(array_filter($this->controls(), static function (array $control) use ($organizationId, $scopeId): bool {
            if ($control['organization_id'] !== $organizationId) {
                return false;
            }

            if ($scopeId === null || $scopeId === '') {
                return true;
            }

            return ($control['scope_id'] ?? null) === $scopeId;
        }));
    }

    /**
     * @return array<string, string> | null
     */
    public function find(string $controlId): ?array
    {
        foreach ($this->controls() as $control) {
            if ($control['id'] === $controlId) {
                return $control;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function controls(): array
    {
        return [
            [
                'id' => 'control-access-review',
                'organization_id' => 'org-a',
                'scope_id' => 'scope-eu',
                'name' => 'Quarterly Access Review',
                'framework' => 'ISO 27001',
                'domain' => 'Identity',
                'evidence' => 'Access certification pack',
            ],
            [
                'id' => 'control-backup-governance',
                'organization_id' => 'org-a',
                'scope_id' => 'scope-it',
                'name' => 'Backup Governance',
                'framework' => 'NIS2',
                'domain' => 'Resilience',
                'evidence' => 'Backup policy and restore tests',
            ],
            [
                'id' => 'control-route-integrity',
                'organization_id' => 'org-b',
                'scope_id' => 'scope-ops',
                'name' => 'Route Integrity Monitoring',
                'framework' => 'SOC 2',
                'domain' => 'Operations',
                'evidence' => 'Telemetry reviews',
            ],
        ];
    }
}
