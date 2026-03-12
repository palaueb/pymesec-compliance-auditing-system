<?php

namespace PymeSec\Plugins\RiskManagement;

class RiskRepository
{
    /**
     * @return array<int, array<string, string>>
     */
    public function all(string $organizationId, ?string $scopeId = null): array
    {
        return array_values(array_filter($this->risks(), static function (array $risk) use ($organizationId, $scopeId): bool {
            if ($risk['organization_id'] !== $organizationId) {
                return false;
            }

            if ($scopeId === null || $scopeId === '') {
                return true;
            }

            return ($risk['scope_id'] ?? null) === $scopeId;
        }));
    }

    /**
     * @return array<string, string> | null
     */
    public function find(string $riskId): ?array
    {
        foreach ($this->risks() as $risk) {
            if ($risk['id'] === $riskId) {
                return $risk;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function risks(): array
    {
        return [
            [
                'id' => 'risk-access-drift',
                'organization_id' => 'org-a',
                'scope_id' => 'scope-eu',
                'title' => 'Privileged access drift',
                'category' => 'Identity',
                'inherent_score' => '20',
                'residual_score' => '10',
                'linked_asset_id' => 'asset-erp-prod',
                'linked_control_id' => 'control-access-review',
                'treatment' => 'Quarterly certification and emergency access review.',
            ],
            [
                'id' => 'risk-backup-assurance',
                'organization_id' => 'org-a',
                'scope_id' => 'scope-it',
                'title' => 'Restore assurance gap',
                'category' => 'Resilience',
                'inherent_score' => '16',
                'residual_score' => '8',
                'linked_asset_id' => 'asset-laptop-fleet',
                'linked_control_id' => 'control-backup-governance',
                'treatment' => 'Evidence monthly restore tests and tighten backup ownership.',
            ],
            [
                'id' => 'risk-route-blackout',
                'organization_id' => 'org-b',
                'scope_id' => 'scope-ops',
                'title' => 'Route telemetry blackout',
                'category' => 'Operations',
                'inherent_score' => '18',
                'residual_score' => '9',
                'linked_asset_id' => 'asset-route-planner',
                'linked_control_id' => 'control-route-integrity',
                'treatment' => 'Correlate route planner telemetry with warehouse monitoring.',
            ],
        ];
    }
}
