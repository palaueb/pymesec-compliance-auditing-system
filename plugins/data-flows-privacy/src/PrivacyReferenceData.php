<?php

namespace PymeSec\Plugins\DataFlowsPrivacy;

use PymeSec\Core\ReferenceData\ReferenceCatalogService;

class PrivacyReferenceData
{
    /**
     * @return array<string, string>
     */
    public static function transferTypes(): array
    {
        return self::group('transfer_type');
    }

    /**
     * @return array<string, string>
     */
    public static function lawfulBases(): array
    {
        return self::group('lawful_basis');
    }

    public static function transferTypeLabel(string $value): string
    {
        return self::label('transfer_type', $value);
    }

    public static function lawfulBasisLabel(string $value): string
    {
        return self::label('lawful_basis', $value);
    }

    /**
     * @return array<int, string>
     */
    public static function transferTypeKeys(): array
    {
        return array_keys(self::transferTypes());
    }

    /**
     * @return array<int, string>
     */
    public static function lawfulBasisKeys(): array
    {
        return array_keys(self::lawfulBases());
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

        return $catalogs->options('privacy.'.$group, $catalogs->currentOrganizationId());
    }
}
