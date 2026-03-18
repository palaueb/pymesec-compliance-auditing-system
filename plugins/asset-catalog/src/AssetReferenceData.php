<?php

namespace PymeSec\Plugins\AssetCatalog;

class AssetReferenceData
{
    /**
     * @return array<string, string>
     */
    public static function types(): array
    {
        return self::group('types');
    }

    /**
     * @return array<string, string>
     */
    public static function criticality(): array
    {
        return self::group('criticality');
    }

    /**
     * @return array<string, string>
     */
    public static function classification(): array
    {
        return self::group('classification');
    }

    public static function typeLabel(string $value): string
    {
        return self::label('types', $value);
    }

    public static function criticalityLabel(string $value): string
    {
        return self::label('criticality', $value);
    }

    public static function classificationLabel(string $value): string
    {
        return self::label('classification', $value);
    }

    /**
     * @return array<int, string>
     */
    public static function typeKeys(): array
    {
        return array_keys(self::types());
    }

    /**
     * @return array<int, string>
     */
    public static function criticalityKeys(): array
    {
        return array_keys(self::criticality());
    }

    /**
     * @return array<int, string>
     */
    public static function classificationKeys(): array
    {
        return array_keys(self::classification());
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
        $values = config('reference_data.assets.'.$group, []);

        if (! is_array($values)) {
            return [];
        }

        return array_filter($values, static fn ($label, $key): bool => is_string($key) && $key !== '' && is_string($label) && $label !== '', ARRAY_FILTER_USE_BOTH);
    }
}
