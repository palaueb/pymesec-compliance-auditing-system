<?php

namespace PymeSec\Plugins\FrameworkGdpr;

use PymeSec\Core\Plugins\Contracts\PluginInterface;
use PymeSec\Core\Plugins\PluginContext;
use PymeSec\Plugins\FrameworkPlatform\Contracts\FrameworkPlatformRegistryInterface;

class FrameworkGdprPlugin implements PluginInterface
{
    public function register(PluginContext $context): void
    {
        $context->app()->make(FrameworkPlatformRegistryInterface::class)->register('framework-gdpr', [
            'onboarding' => [
                'version' => '2026.04',
                'summary' => 'Start with privacy governance controls, one linked policy, and an evidence checklist for RoPA and breach handling.',
                'controls' => [
                    [
                        'key' => 'ropa-records',
                        'name' => 'Records of processing activities',
                        'domain' => 'Privacy governance',
                        'evidence' => 'Maintain and review the RoPA, accountable owners, and legal basis coverage for each processing activity.',
                        'framework_element_ids' => ['gdpr-article-30'],
                    ],
                    [
                        'key' => 'breach-notification',
                        'name' => 'Personal data breach notification',
                        'domain' => 'Incident response',
                        'evidence' => 'Maintain the internal escalation path, supervisory notification workflow, and post-incident evidence trail.',
                        'framework_element_ids' => ['gdpr-article-33'],
                    ],
                ],
                'policies' => [
                    [
                        'title' => 'Personal data protection policy',
                        'area' => 'privacy',
                        'version_label' => 'v1.0',
                        'statement' => 'Define lawful processing, record ownership, escalation duties, and management expectations for personal data protection.',
                        'linked_control_key' => 'ropa-records',
                    ],
                ],
                'evidence_requests' => [
                    [
                        'label' => 'RoPA export',
                        'summary' => 'Keep the latest processing record export and owner review evidence linked to the privacy workspace.',
                    ],
                    [
                        'label' => 'Breach runbook review',
                        'summary' => 'Attach the latest notification runbook review, tabletop notes, and regulator-facing templates.',
                    ],
                ],
            ],
            'reporting' => [
                'management_views' => [
                    [
                        'label' => 'Privacy governance snapshot',
                        'summary' => 'Summarize adopted scope, mapped controls, open assessment gaps, and missing governance artifacts.',
                    ],
                    [
                        'label' => 'Operational privacy obligations',
                        'summary' => 'Keep RoPA, breach handling, DPIA readiness, and processor governance visible in one management slice.',
                    ],
                ],
                'export_bundles' => [
                    [
                        'label' => 'GDPR leadership brief',
                        'summary' => 'Bundle adoption status, latest assessment evidence, and framework coverage for leadership review.',
                    ],
                    [
                        'label' => 'Privacy audit handoff',
                        'summary' => 'Bundle mapped controls, mandate records, and evidence requests for external privacy review.',
                    ],
                ],
            ],
            'updates' => [
                'channel' => 'Framework pack release notes',
                'summary' => 'Track pack version deltas, legal coverage changes, and onboarding guidance from official framework pack releases.',
                'guidance' => 'Review release notes before changing scope assumptions or adding new regulatory operations in the adopted workspace.',
            ],
        ]);
    }

    public function boot(PluginContext $context): void
    {
        //
    }
}
