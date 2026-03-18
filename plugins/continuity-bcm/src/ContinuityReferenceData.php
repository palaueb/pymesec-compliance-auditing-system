<?php

namespace PymeSec\Plugins\ContinuityBcm;

use PymeSec\Core\ReferenceData\ReferenceCatalogService;

class ContinuityReferenceData
{
    /**
     * @return array<string, string>
     */
    public static function impactTiers(): array
    {
        return self::group('impact_tier');
    }

    /**
     * @return array<string, string>
     */
    public static function dependencyKinds(): array
    {
        return self::group('dependency_kind');
    }

    /**
     * @return array<string, string>
     */
    public static function exerciseTypes(): array
    {
        return self::group('exercise_type');
    }

    /**
     * @return array<string, string>
     */
    public static function exerciseOutcomes(): array
    {
        return self::group('exercise_outcome');
    }

    /**
     * @return array<string, string>
     */
    public static function executionTypes(): array
    {
        return self::group('execution_type');
    }

    /**
     * @return array<string, string>
     */
    public static function executionStatuses(): array
    {
        return self::group('execution_status');
    }

    public static function impactTierLabel(string $value): string
    {
        return self::label('impact_tier', $value);
    }

    public static function dependencyKindLabel(string $value): string
    {
        return self::label('dependency_kind', $value);
    }

    public static function exerciseTypeLabel(string $value): string
    {
        return self::label('exercise_type', $value);
    }

    public static function exerciseOutcomeLabel(string $value): string
    {
        return self::label('exercise_outcome', $value);
    }

    public static function executionTypeLabel(string $value): string
    {
        return self::label('execution_type', $value);
    }

    public static function executionStatusLabel(string $value): string
    {
        return self::label('execution_status', $value);
    }

    /**
     * @return array<int, string>
     */
    public static function impactTierKeys(): array
    {
        return array_keys(self::impactTiers());
    }

    /**
     * @return array<int, string>
     */
    public static function dependencyKindKeys(): array
    {
        return array_keys(self::dependencyKinds());
    }

    /**
     * @return array<int, string>
     */
    public static function exerciseTypeKeys(): array
    {
        return array_keys(self::exerciseTypes());
    }

    /**
     * @return array<int, string>
     */
    public static function exerciseOutcomeKeys(): array
    {
        return array_keys(self::exerciseOutcomes());
    }

    /**
     * @return array<int, string>
     */
    public static function executionTypeKeys(): array
    {
        return array_keys(self::executionTypes());
    }

    /**
     * @return array<int, string>
     */
    public static function executionStatusKeys(): array
    {
        return array_keys(self::executionStatuses());
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

        return $catalogs->options('continuity.'.$group, $catalogs->currentOrganizationId());
    }
}
