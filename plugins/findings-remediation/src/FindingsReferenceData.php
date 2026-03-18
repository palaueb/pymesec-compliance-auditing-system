<?php

namespace PymeSec\Plugins\FindingsRemediation;

use PymeSec\Core\ReferenceData\ReferenceCatalogService;

class FindingsReferenceData
{
    /**
     * @return array<string, string>
     */
    public static function severityLevels(): array
    {
        return self::group('severity');
    }

    /**
     * @return array<string, string>
     */
    public static function remediationStatuses(): array
    {
        return self::group('remediation_status');
    }

    public static function severityLabel(string $value): string
    {
        return self::label('severity', $value);
    }

    public static function remediationStatusLabel(string $value): string
    {
        return self::label('remediation_status', $value);
    }

    /**
     * @return array<int, string>
     */
    public static function severityKeys(): array
    {
        return array_keys(self::severityLevels());
    }

    /**
     * @return array<int, string>
     */
    public static function remediationStatusKeys(): array
    {
        return array_keys(self::remediationStatuses());
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    public static function optionsFor(string $group): array
    {
        $options = [];

        foreach (self::group($group) as $id => $label) {
            $options[] = [
                'id' => $id,
                'label' => $label,
            ];
        }

        return $options;
    }

    private static function label(string $group, string $value): string
    {
        return self::group($group)[$value] ?? $value;
    }

    /**
     * @return array<string, string>
     */
    private static function group(string $group): array
    {
        /** @var ReferenceCatalogService $catalogs */
        $catalogs = app(ReferenceCatalogService::class);

        return $catalogs->options('findings.'.$group, $catalogs->currentOrganizationId());
    }
}
