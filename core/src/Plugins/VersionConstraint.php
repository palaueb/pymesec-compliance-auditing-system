<?php

namespace PymeSec\Core\Plugins;

class VersionConstraint
{
    public static function matches(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);

        if ($constraint === '' || $constraint === '*') {
            return true;
        }

        foreach (array_map('trim', explode('||', $constraint)) as $candidate) {
            if ($candidate === '') {
                continue;
            }

            if (self::matchesSingle($version, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private static function matchesSingle(string $version, string $constraint): bool
    {
        if (str_starts_with($constraint, '^')) {
            return self::matchesCaret($version, substr($constraint, 1));
        }

        return version_compare($version, $constraint, '>=')
            && version_compare($version, $constraint, '<=');
    }

    private static function matchesCaret(string $version, string $baseVersion): bool
    {
        $parts = self::normalize($baseVersion);
        [$major, $minor, $patch] = $parts;

        if ($major > 0) {
            $upperBound = ($major + 1).'.0.0';
        } elseif ($minor > 0) {
            $upperBound = '0.'.($minor + 1).'.0';
        } else {
            $upperBound = '0.0.'.($patch + 1);
        }

        return version_compare($version, $baseVersion, '>=')
            && version_compare($version, $upperBound, '<');
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private static function normalize(string $version): array
    {
        $parts = array_map('intval', explode('.', $version));

        return [
            $parts[0] ?? 0,
            $parts[1] ?? 0,
            $parts[2] ?? 0,
        ];
    }
}
