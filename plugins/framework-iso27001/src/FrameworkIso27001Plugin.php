<?php

namespace PymeSec\Plugins\FrameworkIso27001;

use PymeSec\Core\Plugins\Contracts\PluginInterface;
use PymeSec\Core\Plugins\PluginContext;
use PymeSec\Plugins\FrameworkPlatform\Contracts\FrameworkPlatformRegistryInterface;

class FrameworkIso27001Plugin implements PluginInterface
{
    public function register(PluginContext $context): void
    {
        $context->app()->make(FrameworkPlatformRegistryInterface::class)->register('framework-iso-27001', [
            'onboarding' => [
                'version' => '2026.04',
                'summary' => 'Start the ISMS workspace with policy governance, access control, and backup readiness mapped to Annex A.',
                'controls' => [
                    [
                        'key' => 'isms-policy',
                        'name' => 'Policies for information security',
                        'domain' => 'ISMS governance',
                        'evidence' => 'Maintain approved information security policies, ownership, review cadence, and acknowledgment evidence.',
                        'framework_element_ids' => ['iso27001-5-1', 'iso27001-5-2'],
                    ],
                    [
                        'key' => 'backup-readiness',
                        'name' => 'Backup copies',
                        'domain' => 'Technology resilience',
                        'evidence' => 'Maintain backup schedules, restore test results, and retention evidence for systems in scope.',
                        'framework_element_ids' => ['iso27001-8-13'],
                    ],
                ],
                'policies' => [
                    [
                        'title' => 'Information security policy',
                        'area' => 'governance',
                        'version_label' => 'v1.0',
                        'statement' => 'Define management-approved information security expectations, ownership, and review cadence for the ISMS.',
                        'linked_control_key' => 'isms-policy',
                    ],
                ],
                'evidence_requests' => [
                    [
                        'label' => 'Statement of applicability baseline',
                        'summary' => 'Attach the current scope statement, applicability rationale, and management sign-off for Annex A coverage.',
                    ],
                    [
                        'label' => 'Restore test evidence',
                        'summary' => 'Keep the latest restore test results, issue notes, and corrective actions linked to backup readiness.',
                    ],
                ],
            ],
            'reporting' => [
                'management_views' => [
                    [
                        'label' => 'ISMS readiness overview',
                        'summary' => 'Show adoption status, coverage, missing evidence, and latest assessment gaps for the ISMS scope.',
                    ],
                    [
                        'label' => 'Annex A operational coverage',
                        'summary' => 'Keep mapped controls, open findings, and policy ownership visible by Annex A theme.',
                    ],
                ],
                'export_bundles' => [
                    [
                        'label' => 'ISO leadership review pack',
                        'summary' => 'Bundle adoption, framework coverage, and latest assessment reporting for management review.',
                    ],
                    [
                        'label' => 'ISO audit preparation pack',
                        'summary' => 'Bundle mandate record, mapped controls, and restore evidence for certification readiness.',
                    ],
                ],
            ],
            'updates' => [
                'channel' => 'Framework pack release notes',
                'summary' => 'Track framework pack changes, seeded mappings, and operational guidance from official pack releases.',
                'guidance' => 'Review release notes before updating the Annex A baseline or management review materials for an adopted scope.',
            ],
        ]);
    }

    public function boot(PluginContext $context): void
    {
        //
    }
}
