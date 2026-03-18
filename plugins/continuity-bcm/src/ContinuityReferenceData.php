<?php

namespace PymeSec\Plugins\ContinuityBcm;

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

    public static function impactTierLabel(string $value): string
    {
        return self::label('impact_tier', $value);
    }

    public static function dependencyKindLabel(string $value): string
    {
        return self::label('dependency_kind', $value);
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
        $values = config('reference_data.continuity.'.$group, []);

        if (! is_array($values)) {
            return [];
        }

        return array_filter($values, static fn ($label, $key): bool => is_string($key) && $key !== '' && is_string($label) && $label !== '', ARRAY_FILTER_USE_BOTH);
    }
}
