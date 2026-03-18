<?php

namespace PymeSec\Plugins\RiskManagement;

use PymeSec\Core\ReferenceData\ReferenceCatalogService;

class RiskReferenceData
{
    /**
     * @return array<string, string>
     */
    public static function categories(): array
    {
        /** @var ReferenceCatalogService $catalogs */
        $catalogs = app(ReferenceCatalogService::class);

        return $catalogs->options('risks.categories', $catalogs->currentOrganizationId());
    }

    public static function categoryLabel(string $value): string
    {
        return self::categories()[$value] ?? $value;
    }

    /**
     * @return array<int, string>
     */
    public static function categoryKeys(): array
    {
        return array_keys(self::categories());
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::categories() as $id => $label) {
            $options[] = [
                'id' => $id,
                'label' => $label,
            ];
        }

        return $options;
    }
}
