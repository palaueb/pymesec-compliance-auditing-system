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
        'exercise_type' => [
            'tabletop' => 'Tabletop',
            'simulation' => 'Simulation',
            'walkthrough' => 'Walkthrough',
        ],
        'exercise_outcome' => [
            'pass' => 'Pass',
            'partial' => 'Partial',
            'fail' => 'Fail',
        ],
        'execution_type' => [
            'recovery-drill' => 'Recovery drill',
            'restore-test' => 'Restore test',
            'failover-test' => 'Failover test',
        ],
        'execution_status' => [
            'passed' => 'Passed',
            'partial' => 'Partial',
            'failed' => 'Failed',
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
    'findings' => [
        'severity' => [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'critical' => 'Critical',
        ],
        'remediation_status' => [
            'planned' => 'Planned',
            'in-progress' => 'In progress',
            'blocked' => 'Blocked',
            'done' => 'Done',
        ],
    ],
    'risks' => [
        'categories' => [
            'cybersecurity' => 'Cybersecurity',
            'continuity' => 'Continuity',
            'privacy' => 'Privacy',
            'third-party' => 'Third-party',
            'compliance' => 'Compliance',
            'operations' => 'Operations',
        ],
    ],
    'policies' => [
        'areas' => [
            'identity' => 'Identity',
            'resilience' => 'Resilience',
            'operations' => 'Operations',
            'third-parties' => 'Third parties',
            'governance' => 'Governance',
            'privacy' => 'Privacy',
        ],
    ],
    'assessments' => [
        'review_result' => [
            'not-tested' => 'Not tested',
            'pass' => 'Pass',
            'partial' => 'Partial',
            'fail' => 'Fail',
            'not-applicable' => 'Not applicable',
        ],
        'status' => [
            'draft' => 'Draft',
            'active' => 'Active',
            'signed-off' => 'Signed off',
            'closed' => 'Closed',
        ],
    ],
];
