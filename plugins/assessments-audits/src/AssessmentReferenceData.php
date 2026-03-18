<?php

namespace PymeSec\Plugins\AssessmentsAudits;

use PymeSec\Core\ReferenceData\ReferenceCatalogService;

class AssessmentReferenceData
{
    /**
     * @return array<string, string>
     */
    public static function reviewResults(): array
    {
        return self::group('review_result');
    }

    /**
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return self::group('status');
    }

    public static function reviewResultLabel(string $value): string
    {
        return self::label('review_result', $value);
    }

    public static function statusLabel(string $value): string
    {
        return self::label('status', $value);
    }

    /**
     * @return array<int, string>
     */
    public static function reviewResultKeys(): array
    {
        return array_keys(self::reviewResults());
    }

    /**
     * @return array<int, string>
     */
    public static function statusKeys(): array
    {
        return array_keys(self::statuses());
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

        return $catalogs->options('assessments.'.$group, $catalogs->currentOrganizationId());
    }
}
