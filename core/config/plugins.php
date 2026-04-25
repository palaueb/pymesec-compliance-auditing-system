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
    'core_version' => env('CORE_VERSION', '0.3.1'),
    'repository_url' => env('CORE_REPOSITORY_URL', 'https://github.com/palaueb/pymesec-compliance-auditing-system'),
    'automation_catalog' => [
        'official_repository' => [
            'label' => env('AUTOMATION_CATALOG_OFFICIAL_REPOSITORY_LABEL', 'PymeSec Official Repository'),
            'url' => env('AUTOMATION_CATALOG_OFFICIAL_REPOSITORY_URL', 'https://repository.pimesec.com/repository.json'),
            'sign_url' => env('AUTOMATION_CATALOG_OFFICIAL_REPOSITORY_SIGN_URL', ''),
            'trust_tier' => env('AUTOMATION_CATALOG_OFFICIAL_REPOSITORY_TRUST_TIER', 'trusted-first-party'),
            'public_key_pem' => env('AUTOMATION_CATALOG_OFFICIAL_REPOSITORY_PUBLIC_KEY_PEM', ''),
            'public_key_path' => env('AUTOMATION_CATALOG_OFFICIAL_REPOSITORY_PUBLIC_KEY_PATH', ''),
        ],
    ],
    'paths' => array_values($paths),
    'state_path' => $statePath,
    'enabled' => array_values(array_filter(array_map(
        static fn (string $pluginId): string => trim($pluginId),
        explode(',', (string) env('PLUGINS_ENABLED', 'hello-world,asset-catalog,actor-directory,controls-catalog,risk-management,questionnaires,collaboration,third-party-risk,findings-remediation,policy-exceptions,data-flows-privacy,continuity-bcm,automation-catalog,assessments-audits,evidence-management,identity-local,identity-ldap,framework-platform,framework-gdpr,framework-ens,framework-iso27001,framework-nis2'))
    ))),
];
