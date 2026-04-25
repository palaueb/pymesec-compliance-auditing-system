<?php

namespace PymeSec\Plugins\FrameworkPlatform;

use PymeSec\Plugins\FrameworkPlatform\Contracts\FrameworkPlatformRegistryInterface;

class FrameworkPlatformRegistry implements FrameworkPlatformRegistryInterface
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $definitions = [];

    public function register(string $frameworkId, array $definition): void
    {
        $this->definitions[$frameworkId] = $this->normalize($frameworkId, $definition);
        ksort($this->definitions);
    }

    public function has(string $frameworkId): bool
    {
        return isset($this->definitions[$frameworkId]);
    }

    public function definition(string $frameworkId): ?array
    {
        return $this->definitions[$frameworkId] ?? null;
    }

    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function normalize(string $frameworkId, array $definition): array
    {
        $onboarding = is_array($definition['onboarding'] ?? null) ? $definition['onboarding'] : [];
        $reporting = is_array($definition['reporting'] ?? null) ? $definition['reporting'] : [];
        $updates = is_array($definition['updates'] ?? null) ? $definition['updates'] : [];

        return [
            'framework_id' => $frameworkId,
            'onboarding' => [
                'version' => is_string($onboarding['version'] ?? null) ? $onboarding['version'] : '1',
                'summary' => is_string($onboarding['summary'] ?? null) ? $onboarding['summary'] : '',
                'controls' => $this->normalizeRows($onboarding['controls'] ?? []),
                'policies' => $this->normalizeRows($onboarding['policies'] ?? []),
                'evidence_requests' => $this->normalizeRows($onboarding['evidence_requests'] ?? []),
            ],
            'reporting' => [
                'management_views' => $this->normalizeRows($reporting['management_views'] ?? []),
                'export_bundles' => $this->normalizeRows($reporting['export_bundles'] ?? []),
            ],
            'updates' => [
                'channel' => is_string($updates['channel'] ?? null) ? $updates['channel'] : '',
                'summary' => is_string($updates['summary'] ?? null) ? $updates['summary'] : '',
                'guidance' => is_string($updates['guidance'] ?? null) ? $updates['guidance'] : '',
            ],
        ];
    }

    /**
     * @param  mixed  $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter(array_map(static function (mixed $row): ?array {
            return is_array($row) ? $row : null;
        }, $rows)));
    }
}
