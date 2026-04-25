<?php

namespace PymeSec\Plugins\ControlsCatalog;

use PymeSec\Core\Audit\AuditRecordData;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;
use PymeSec\Core\Events\Contracts\EventBusInterface;
use PymeSec\Core\Events\PublicEvent;
use PymeSec\Core\Plugins\Contracts\PluginManagerInterface;
use PymeSec\Plugins\PolicyExceptions\PolicyExceptionsRepository;

class FrameworkOnboardingService
{
    public function __construct(
        private readonly ControlsCatalogRepository $controls,
        private readonly PolicyExceptionsRepository $policies,
        private readonly PluginManagerInterface $plugins,
        private readonly AuditTrailInterface $audit,
        private readonly EventBusInterface $events,
    ) {}

    /**
     * @param  array<string, mixed>  $platform
     * @return array<string, mixed>
     */
    public function apply(
        string $frameworkId,
        string $organizationId,
        ?string $scopeId,
        string $principalId,
        ?string $membershipId,
        array $platform,
    ): array {
        $onboarding = is_array($platform['onboarding'] ?? null) ? $platform['onboarding'] : [];
        $starterControls = is_array($onboarding['controls'] ?? null) ? $onboarding['controls'] : [];
        $starterPolicies = is_array($onboarding['policies'] ?? null) ? $onboarding['policies'] : [];

        $existingControls = [];
        foreach ($this->controls->all($organizationId, $scopeId) as $control) {
            $existingControls[strtolower(trim((string) ($control['name'] ?? '')))] = $control;
        }

        $existingPolicies = [];
        if ($this->pluginEnabled('policy-exceptions')) {
            foreach ($this->policies->allPolicies($organizationId, $scopeId) as $policy) {
                $existingPolicies[strtolower(trim((string) ($policy['title'] ?? '')))] = $policy;
            }
        }

        $controlKeyMap = [];
        $createdControlIds = [];
        $createdPolicyIds = [];
        $attachedMappings = 0;

        foreach ($starterControls as $definition) {
            $controlName = trim((string) ($definition['name'] ?? ''));

            if ($controlName === '') {
                continue;
            }

            $control = $existingControls[strtolower($controlName)] ?? null;

            if ($control === null) {
                $control = $this->controls->create([
                    'organization_id' => $organizationId,
                    'scope_id' => $scopeId,
                    'framework_id' => $frameworkId,
                    'framework' => null,
                    'name' => $controlName,
                    'domain' => (string) ($definition['domain'] ?? 'Framework onboarding'),
                    'evidence' => (string) ($definition['evidence'] ?? ''),
                ]);
                $existingControls[strtolower($controlName)] = $control;
                $createdControlIds[] = (string) $control['id'];
            }

            $key = is_string($definition['key'] ?? null) ? (string) $definition['key'] : null;
            if ($key !== null && $key !== '') {
                $controlKeyMap[$key] = $control;
            }

            foreach (($definition['framework_element_ids'] ?? []) as $requirementId) {
                if (! is_string($requirementId) || $requirementId === '') {
                    continue;
                }

                $this->controls->attachRequirement(
                    controlId: (string) $control['id'],
                    requirementId: $requirementId,
                    organizationId: $organizationId,
                    coverage: 'supports',
                    notes: 'Applied by framework onboarding kit.',
                );
                $attachedMappings++;
            }
        }

        if ($this->pluginEnabled('policy-exceptions')) {
            foreach ($starterPolicies as $definition) {
                $policyTitle = trim((string) ($definition['title'] ?? ''));

                if ($policyTitle === '') {
                    continue;
                }

                $policy = $existingPolicies[strtolower($policyTitle)] ?? null;

                if ($policy !== null) {
                    continue;
                }

                $linkedControl = null;
                $linkedControlKey = is_string($definition['linked_control_key'] ?? null)
                    ? (string) $definition['linked_control_key']
                    : '';

                if ($linkedControlKey !== '' && isset($controlKeyMap[$linkedControlKey])) {
                    $linkedControl = $controlKeyMap[$linkedControlKey];
                }

                $policy = $this->policies->createPolicy([
                    'organization_id' => $organizationId,
                    'scope_id' => $scopeId,
                    'title' => $policyTitle,
                    'area' => (string) ($definition['area'] ?? 'governance'),
                    'version_label' => (string) ($definition['version_label'] ?? 'v1.0'),
                    'statement' => (string) ($definition['statement'] ?? ''),
                    'linked_control_id' => is_array($linkedControl) ? (string) ($linkedControl['id'] ?? '') : null,
                    'review_due_on' => (string) ($definition['review_due_on'] ?? ''),
                ]);

                $existingPolicies[strtolower($policyTitle)] = $policy;
                $createdPolicyIds[] = (string) $policy['id'];
            }
        }

        $summary = [
            'framework_id' => $frameworkId,
            'created_controls' => $createdControlIds,
            'created_policies' => $createdPolicyIds,
            'attached_mapping_count' => $attachedMappings,
            'onboarding_version' => (string) ($onboarding['version'] ?? '1'),
        ];

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.controls-catalog.framework-onboarding.applied',
            outcome: 'success',
            originComponent: 'controls-catalog',
            principalId: $principalId,
            membershipId: $membershipId,
            organizationId: $organizationId,
            scopeId: $scopeId,
            targetType: 'framework-adoption',
            targetId: $frameworkId,
            summary: $summary,
            executionOrigin: 'controls-catalog',
        ));

        $this->events->publish(new PublicEvent(
            name: 'plugin.controls-catalog.framework-onboarding.applied',
            originComponent: 'controls-catalog',
            organizationId: $organizationId,
            scopeId: $scopeId,
            payload: $summary,
        ));

        return $summary;
    }

    private function pluginEnabled(string $pluginId): bool
    {
        foreach ($this->plugins->status() as $plugin) {
            if (($plugin['id'] ?? null) !== $pluginId) {
                continue;
            }

            return ($plugin['enabled'] ?? false) === true && ($plugin['booted'] ?? false) === true;
        }

        return false;
    }
}
