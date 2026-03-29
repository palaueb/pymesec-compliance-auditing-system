<?php

namespace PymeSec\Plugins\PolicyExceptions;

use PymeSec\Core\ReferenceData\ReferenceCatalogService;

class PolicyReferenceData
{
    /**
     * @return array<string, string>
     */
    public static function areas(): array
    {
        /** @var ReferenceCatalogService $catalogs */
        $catalogs = app(ReferenceCatalogService::class);

        return $catalogs->options('policies.areas', $catalogs->currentOrganizationId());
    }

    public static function areaLabel(string $value): string
    {
        return self::areas()[$value] ?? $value;
    }

    /**
     * @return array<int, string>
     */
    public static function areaKeys(): array
    {
        return array_keys(self::areas());
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::areas() as $id => $label) {
            $options[] = [
                'id' => $id,
                'label' => $label,
            ];
        }

        return $options;
    }
}
