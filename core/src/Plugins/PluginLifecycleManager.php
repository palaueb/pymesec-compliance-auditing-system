<?php

namespace PymeSec\Core\Plugins;

class PluginLifecycleManager
{
    /**
     * @param  array<int, string>  $baseEnabled
     */
    public function __construct(
        private readonly PluginDiscovery $discovery,
        private readonly PluginStateStore $state,
        private readonly array $baseEnabled,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $runtimeStatus
     * @return array<int, array<string, mixed>>
     */
    public function enrichStatus(array $runtimeStatus): array
    {
        $runtimeById = [];

        foreach ($runtimeStatus as $plugin) {
            if (! is_array($plugin) || ! is_string($plugin['id'] ?? null)) {
                continue;
            }

            $runtimeById[$plugin['id']] = $plugin;
        }

        $overrides = $this->state->overrides();
        $effectiveEnabled = $this->state->effectiveEnabled($this->baseEnabled);
        $dependents = $this->dependentMap();
        $enriched = [];

        foreach ($this->discovery->discover() as $descriptor) {
            $pluginId = $descriptor->id();
            $runtime = $runtimeById[$pluginId] ?? ['id' => $pluginId];
            $manifest = $descriptor->manifest();
            $dependencies = $manifest->dependencies();
            $requiredDependencies = $manifest->requiredDependencyPluginIds();
            $enabledDependents = array_values(array_filter(
                $dependents[$pluginId] ?? [],
                fn (string $dependentPluginId): bool => in_array($dependentPluginId, $effectiveEnabled, true),
            ));
            $configuredEnabled = in_array($pluginId, $this->baseEnabled, true);
            $effective = in_array($pluginId, $effectiveEnabled, true);
            $overrideState = in_array($pluginId, $overrides['enabled'], true)
                ? 'enabled'
                : (in_array($pluginId, $overrides['disabled'], true) ? 'disabled' : null);
            $missingRequiredDependencies = array_values(array_filter(
                $requiredDependencies,
                fn (string $dependencyId): bool => ! in_array($dependencyId, $effectiveEnabled, true),
            ));

            $lifecycle = $effective
                ? [
                    'operation' => 'disable',
                    'blocked' => $enabledDependents !== [],
                    'reason' => $enabledDependents !== [] ? 'required_by_enabled_plugins' : null,
                    'dependencies' => $enabledDependents,
                ]
                : [
                    'operation' => 'enable',
                    'blocked' => $missingRequiredDependencies !== [],
                    'reason' => $missingRequiredDependencies !== [] ? 'required_dependencies_not_enabled' : null,
                    'dependencies' => $missingRequiredDependencies,
                ];

            $enriched[] = [
                ...$runtime,
                'description' => $manifest->description(),
                'dependencies' => array_values(array_map(
                    static fn (array $dependency): string => $dependency['target'],
                    $dependencies,
                )),
                'required_dependencies' => $requiredDependencies,
                'dependent_plugins' => $enabledDependents,
                'configured_enabled' => $configuredEnabled,
                'effective_enabled' => $effective,
                'override_state' => $overrideState,
                'lifecycle_source' => $overrideState === null
                    ? ($configuredEnabled ? 'config_enabled' : 'config_disabled')
                    : ($overrideState === 'enabled' ? 'override_enabled' : 'override_disabled'),
                'settings_menu_id' => $manifest->settingsMenuId(),
                'lifecycle' => $lifecycle,
            ];
        }

        return $enriched;
    }

    public function enable(string $pluginId): PluginLifecycleResult
    {
        return $this->changeState(trim($pluginId), 'enable');
    }

    public function disable(string $pluginId): PluginLifecycleResult
    {
        return $this->changeState(trim($pluginId), 'disable');
    }

    /**
     * @return array<string, PluginDescriptor>
     */
    private function descriptorsById(): array
    {
        $descriptors = [];

        foreach ($this->discovery->discover() as $descriptor) {
            $descriptors[$descriptor->id()] = $descriptor;
        }

        return $descriptors;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function dependentMap(): array
    {
        $dependents = [];

        foreach ($this->discovery->discover() as $descriptor) {
            foreach ($descriptor->manifest()->requiredDependencyPluginIds() as $dependencyId) {
                $dependents[$dependencyId][] = $descriptor->id();
            }
        }

        foreach ($dependents as &$pluginIds) {
            $pluginIds = array_values(array_unique($pluginIds));
            sort($pluginIds);
        }
        unset($pluginIds);

        return $dependents;
    }

    private function changeState(string $pluginId, string $operation): PluginLifecycleResult
    {
        $descriptors = $this->descriptorsById();

        if (! isset($descriptors[$pluginId])) {
            return new PluginLifecycleResult(
                ok: false,
                pluginId: $pluginId,
                operation: $operation,
                message: sprintf('Unknown plugin [%s].', $pluginId),
                reason: 'unknown_plugin',
            );
        }

        $effectiveBefore = $this->state->effectiveEnabled($this->baseEnabled);
        $descriptor = $descriptors[$pluginId];

        if ($operation === 'enable') {
            $missingRequiredDependencies = array_values(array_filter(
                $descriptor->manifest()->requiredDependencyPluginIds(),
                fn (string $dependencyId): bool => ! in_array($dependencyId, $effectiveBefore, true),
            ));

            if ($missingRequiredDependencies !== []) {
                return new PluginLifecycleResult(
                    ok: false,
                    pluginId: $pluginId,
                    operation: $operation,
                    message: sprintf(
                        'Plugin [%s] requires enabled dependencies: %s.',
                        $pluginId,
                        implode(', ', $missingRequiredDependencies),
                    ),
                    reason: 'required_dependencies_not_enabled',
                    effectiveBefore: $effectiveBefore,
                    effectiveAfter: $effectiveBefore,
                    details: ['dependencies' => $missingRequiredDependencies],
                );
            }

            $this->state->enable($pluginId, $this->baseEnabled);
            $effectiveAfter = $this->state->effectiveEnabled($this->baseEnabled);

            return new PluginLifecycleResult(
                ok: true,
                pluginId: $pluginId,
                operation: $operation,
                message: $effectiveBefore === $effectiveAfter
                    ? sprintf('Plugin [%s] is already enabled.', $pluginId)
                    : sprintf('Plugin [%s] will be enabled on the next bootstrap.', $pluginId),
                effectiveBefore: $effectiveBefore,
                effectiveAfter: $effectiveAfter,
            );
        }

        $dependentMap = $this->dependentMap();
        $enabledDependents = array_values(array_filter(
            $dependentMap[$pluginId] ?? [],
            fn (string $dependentPluginId): bool => in_array($dependentPluginId, $effectiveBefore, true),
        ));

        if ($enabledDependents !== []) {
            return new PluginLifecycleResult(
                ok: false,
                pluginId: $pluginId,
                operation: $operation,
                message: sprintf(
                    'Plugin [%s] is still required by enabled plugins: %s.',
                    $pluginId,
                    implode(', ', $enabledDependents),
                ),
                reason: 'required_by_enabled_plugins',
                effectiveBefore: $effectiveBefore,
                effectiveAfter: $effectiveBefore,
                details: ['dependents' => $enabledDependents],
            );
        }

        $this->state->disable($pluginId, $this->baseEnabled);
        $effectiveAfter = $this->state->effectiveEnabled($this->baseEnabled);

        return new PluginLifecycleResult(
            ok: true,
            pluginId: $pluginId,
            operation: $operation,
            message: $effectiveBefore === $effectiveAfter
                ? sprintf('Plugin [%s] is already disabled.', $pluginId)
                : sprintf('Plugin [%s] will be disabled on the next bootstrap.', $pluginId),
            effectiveBefore: $effectiveBefore,
            effectiveAfter: $effectiveAfter,
        );
    }
}
