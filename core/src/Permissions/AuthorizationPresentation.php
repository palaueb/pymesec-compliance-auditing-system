<?php

namespace PymeSec\Core\Permissions;

final class AuthorizationPresentation
{
    /**
     * @param  array<int, string>  $permissions
     */
    public static function roleCategory(array $permissions): string
    {
        $categories = collect($permissions)
            ->map(static fn (string $permission): string => self::permissionCategory($permission))
            ->unique()
            ->values()
            ->all();

        if ($categories === []) {
            return 'work';
        }

        if (count($categories) === 1) {
            return $categories[0];
        }

        if (in_array('platform', $categories, true)) {
            return 'platform';
        }

        return 'mixed';
    }

    public static function permissionCategory(string $permission): string
    {
        if (str_starts_with($permission, 'core.')) {
            return 'platform';
        }

        if (
            str_starts_with($permission, 'plugin.identity-local.')
            || str_starts_with($permission, 'plugin.identity-ldap.')
        ) {
            return 'access';
        }

        return 'work';
    }

    public static function categoryLabel(string $category): string
    {
        return match ($category) {
            'platform' => 'Platform administration',
            'access' => 'Access administration',
            'mixed' => 'Cross-area',
            default => 'Operational workspaces',
        };
    }

    public static function categoryDescription(string $category): string
    {
        return match ($category) {
            'platform' => 'Core administration, platform lifecycle, tenancy, and audit operations.',
            'access' => 'Identity, memberships, directory sync, and access governance inside an organization.',
            'mixed' => 'Custom role sets that blend access governance with operational work areas.',
            default => 'Day-to-day workspaces such as assets, controls, risks, findings, privacy, and continuity.',
        };
    }
}
