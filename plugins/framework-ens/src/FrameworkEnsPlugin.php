<?php

namespace PymeSec\Plugins\FrameworkEns;

use PymeSec\Core\Plugins\Contracts\PluginInterface;
use PymeSec\Core\Plugins\PluginContext;
use PymeSec\Plugins\FrameworkPlatform\Contracts\FrameworkPlatformRegistryInterface;

class FrameworkEnsPlugin implements PluginInterface
{
    public function register(PluginContext $context): void
    {
        $context->app()->make(FrameworkPlatformRegistryInterface::class)->register('framework-ens', [
            'onboarding' => [
                'version' => '2026.04',
                'summary' => 'Start with governance, access protection, and recovery controls plus one baseline ENS policy and evidence checklist.',
                'controls' => [
                    [
                        'key' => 'ens-governance',
                        'name' => 'Security governance and policy ownership',
                        'domain' => 'Security governance',
                        'evidence' => 'Maintain accountable owners, approved governance checkpoints, and signed policy baselines for the ENS scope.',
                        'framework_element_ids' => ['ens-org-governance'],
                    ],
                    [
                        'key' => 'ens-recovery',
                        'name' => 'Service continuity and recovery',
                        'domain' => 'Resilience',
                        'evidence' => 'Maintain continuity plans, restore evidence, and recovery exercise outputs for critical services.',
                        'framework_element_ids' => ['ens-protect-backup', 'ens-recover-continuity'],
                    ],
                ],
                'policies' => [
                    [
                        'title' => 'ENS security governance baseline',
                        'area' => 'governance',
                        'version_label' => 'v1.0',
                        'statement' => 'Define ENS accountability, target level review, and policy ownership for the adopted public-sector security baseline.',
                        'linked_control_key' => 'ens-governance',
                    ],
                ],
                'evidence_requests' => [
                    [
                        'label' => 'Target level decision record',
                        'summary' => 'Keep the signed target level rationale, applicability review, and scope decision linked to the adoption record.',
                    ],
                    [
                        'label' => 'Recovery exercise evidence',
                        'summary' => 'Attach the latest recovery exercise outputs, restore checks, and continuity decisions for ENS review.',
                    ],
                ],
            ],
            'reporting' => [
                'management_views' => [
                    [
                        'label' => 'ENS adoption readiness',
                        'summary' => 'Show target level, mapped measures, mandate status, and unresolved review gaps in one leadership view.',
                    ],
                    [
                        'label' => 'Safeguard operations',
                        'summary' => 'Keep governance, protection, detection, and recovery work visible by ENS domain.',
                    ],
                ],
                'export_bundles' => [
                    [
                        'label' => 'ENS compliance brief',
                        'summary' => 'Bundle scope, target level, mapped controls, and latest assessment coverage for stakeholders.',
                    ],
                    [
                        'label' => 'ENS audit evidence set',
                        'summary' => 'Bundle signed mandate, mapped controls, and recovery evidence for formal review.',
                    ],
                ],
            ],
            'updates' => [
                'channel' => 'Framework pack release notes',
                'summary' => 'Track pack changes, applicability adjustments, and adoption guidance through framework pack releases.',
                'guidance' => 'Review pack changes whenever the adopted ENS target level or interpretation guidance changes.',
            ],
        ]);
    }

    public function boot(PluginContext $context): void
    {
        //
    }
}
