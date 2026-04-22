<?php

namespace PymeSec\Core\FunctionalActors;

class FunctionalActorKindCatalog
{
    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::labels());
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (string $key, string $label): array => [
                'id' => $key,
                'label' => $label,
            ],
            array_keys(self::labels()),
            array_values(self::labels()),
        );
    }

    /**
     * @return array<string, string>
     */
    private static function labels(): array
    {
        return [
            'employee' => 'Employee',
            'contractor' => 'Contractor',
            'external-provider' => 'External provider',
            'team' => 'Team',
            'service-account' => 'Service account',
            'person' => 'Person',
        ];
    }
}
