<?php

namespace PymeSec\Plugins\FrameworkNis2;

use PymeSec\Core\Plugins\Contracts\PluginInterface;
use PymeSec\Core\Plugins\PluginContext;
use PymeSec\Plugins\FrameworkPlatform\Contracts\FrameworkPlatformRegistryInterface;

class FrameworkNis2Plugin implements PluginInterface
{
    public function register(PluginContext $context): void
    {
        $context->app()->make(FrameworkPlatformRegistryInterface::class)->register('framework-nis2', [
            'onboarding' => [
                'version' => '2026.04',
                'summary' => 'Start with cybersecurity risk governance, incident reporting, one linked policy, and evidence prompts for supplier and incident operations.',
                'controls' => [
                    [
                        'key' => 'risk-governance',
                        'name' => 'Cybersecurity risk-management measures',
                        'domain' => 'Cybersecurity governance',
                        'evidence' => 'Maintain the adopted risk-management baseline, control ownership, and effectiveness review cadence for the NIS2 scope.',
                        'framework_element_ids' => ['nis2-article-21', 'nis2-21-a', 'nis2-21-f'],
                    ],
                    [
                        'key' => 'incident-reporting',
                        'name' => 'Reporting obligations',
                        'domain' => 'Incident management',
                        'evidence' => 'Maintain early warning, 72-hour notification, and final report workflows together with escalation evidence.',
                        'framework_element_ids' => ['nis2-article-23', 'nis2-23-1', 'nis2-23-2', 'nis2-23-3'],
                    ],
                ],
                'policies' => [
                    [
                        'title' => 'Significant incident reporting policy',
                        'area' => 'operations',
                        'version_label' => 'v1.0',
                        'statement' => 'Define notification thresholds, escalation ownership, and regulator-facing timelines for significant incidents.',
                        'linked_control_key' => 'incident-reporting',
                    ],
                ],
                'evidence_requests' => [
                    [
                        'label' => 'Incident notification timeline',
                        'summary' => 'Keep notification timeline evidence, escalation approvals, and final report outputs linked to the NIS2 workspace.',
                    ],
                    [
                        'label' => 'Supplier cybersecurity review',
                        'summary' => 'Attach supplier and direct provider review evidence to support Article 21 supply chain obligations.',
                    ],
                ],
            ],
            'reporting' => [
                'management_views' => [
                    [
                        'label' => 'NIS2 readiness dashboard',
                        'summary' => 'Show risk-management coverage, incident reporting readiness, and missing governance artifacts for the adopted scope.',
                    ],
                    [
                        'label' => 'Operational obligations tracker',
                        'summary' => 'Keep supply chain, incident, and risk-management obligations visible in one management workspace.',
                    ],
                ],
                'export_bundles' => [
                    [
                        'label' => 'NIS2 leadership brief',
                        'summary' => 'Bundle adoption state, mapped obligations, and latest assessment findings for management oversight.',
                    ],
                    [
                        'label' => 'Incident governance handoff',
                        'summary' => 'Bundle reporting workflow evidence and obligation coverage for regulator or auditor review.',
                    ],
                ],
            ],
            'updates' => [
                'channel' => 'Framework pack release notes',
                'summary' => 'Track framework pack deltas, seeded obligations, and operational guidance from official pack releases.',
                'guidance' => 'Review pack changes whenever reporting obligations, supplier expectations, or scope assumptions change.',
            ],
        ]);
    }

    public function boot(PluginContext $context): void
    {
        //
    }
}
