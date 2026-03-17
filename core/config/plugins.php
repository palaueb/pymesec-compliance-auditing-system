<?php

$configuredPaths = array_filter(array_map(
    static fn (string $path): string => trim($path),
    explode(',', (string) env('PLUGIN_PATHS', ''))
));

$paths = $configuredPaths !== []
    ? $configuredPaths
    : [base_path('../plugins')];

$configuredStatePath = env('PLUGIN_STATE_PATH');

$statePath = is_string($configuredStatePath) && $configuredStatePath !== ''
    ? (str_starts_with($configuredStatePath, '/')
        ? $configuredStatePath
        : storage_path($configuredStatePath))
    : storage_path('app/private/plugin-state.json');

return [
    'core_version' => env('CORE_VERSION', '0.1.0'),
    'paths' => array_values($paths),
    'state_path' => $statePath,
    'enabled' => array_values(array_filter(array_map(
        static fn (string $pluginId): string => trim($pluginId),
        explode(',', (string) env('PLUGINS_ENABLED', 'hello-world,asset-catalog,actor-directory,controls-catalog,risk-management,findings-remediation,policy-exceptions,data-flows-privacy,continuity-bcm,assessments-audits,identity-local,identity-ldap,framework-iso27001,framework-nis2'))
    ))),
];
