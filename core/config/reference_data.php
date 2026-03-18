<?php

return [
    'assets' => [
        'types' => [
            'application' => 'Application',
            'storage' => 'Storage',
            'endpoint' => 'Endpoint',
            'network' => 'Network',
            'service' => 'Service',
        ],
        'criticality' => [
            'high' => 'High',
            'medium' => 'Medium',
            'low' => 'Low',
        ],
        'classification' => [
            'public' => 'Public',
            'internal' => 'Internal',
            'restricted' => 'Restricted',
            'confidential' => 'Confidential',
        ],
    ],
    'continuity' => [
        'impact_tier' => [
            'critical' => 'Critical',
            'high' => 'High',
            'medium' => 'Medium',
            'low' => 'Low',
        ],
        'dependency_kind' => [
            'critical' => 'Critical',
            'supporting' => 'Supporting',
            'external' => 'External',
        ],
    ],
    'privacy' => [
        'transfer_type' => [
            'internal' => 'Internal',
            'vendor' => 'Vendor',
            'customer' => 'Customer',
            'cross-border' => 'Cross-border',
            'regulator' => 'Regulator',
        ],
        'lawful_basis' => [
            'consent' => 'Consent',
            'contract' => 'Contract',
            'legal-obligation' => 'Legal obligation',
            'vital-interests' => 'Vital interests',
            'public-task' => 'Public task',
            'legitimate-interests' => 'Legitimate interests',
        ],
    ],
];
