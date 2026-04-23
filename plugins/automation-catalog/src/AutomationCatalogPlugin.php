<?php

namespace PymeSec\Plugins\AutomationCatalog;

use PymeSec\Core\Permissions\AuthorizationContext;
use PymeSec\Core\Permissions\Contracts\AuthorizationServiceInterface;
use PymeSec\Core\Plugins\Contracts\PluginInterface;
use PymeSec\Core\Plugins\PluginContext;
use PymeSec\Core\Tenancy\Contracts\TenancyServiceInterface;
use PymeSec\Core\UI\ScreenDefinition;
use PymeSec\Core\UI\ScreenRenderContext;
use PymeSec\Core\UI\ToolbarAction;
use PymeSec\Core\Workflows\Contracts\WorkflowRegistryInterface;
use PymeSec\Plugins\AutomationCatalog\Runtime\AutomationPackRuntimeExecutorRegistry;
use PymeSec\Plugins\AutomationCatalog\Runtime\GenericPackRuntimeExecutor;
use PymeSec\Plugins\AutomationCatalog\Runtime\HelloWorldPackRuntimeExecutor;

class AutomationCatalogPlugin implements PluginInterface
{
    public function register(PluginContext $context): void
    {
        $context->app()->singleton(AutomationCatalogRepository::class, fn () => new AutomationCatalogRepository);
        $context->app()->singleton(AutomationOutputMappingDeliveryService::class);
        $context->app()->singleton(AutomationTargetPosturePropagationService::class);
        $context->app()->singleton(AutomationFailureFindingService::class);
        $context->app()->singleton(AutomationPackageRepositorySyncService::class);
        $context->app()->singleton(HelloWorldPackRuntimeExecutor::class);
        $context->app()->singleton(GenericPackRuntimeExecutor::class);
        $context->app()->singleton(AutomationPackRuntimeExecutorRegistry::class, function ($app): AutomationPackRuntimeExecutorRegistry {
            return new AutomationPackRuntimeExecutorRegistry([
                $app->make(HelloWorldPackRuntimeExecutor::class),
                $app->make(GenericPackRuntimeExecutor::class),
            ]);
        });
        $context->app()->singleton(AutomationPackRuntimeService::class);

        $context->registerScreen(new ScreenDefinition(
            menuId: 'plugin.automation-catalog.root',
            owner: 'automation-catalog',
            titleKey: 'plugin.automation-catalog.screen.root.title',
            subtitleKey: 'plugin.automation-catalog.screen.root.subtitle',
            viewPath: $context->path('resources/views/index.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->catalogData($context, $screenContext),
            toolbarResolver: function (ScreenRenderContext $screenContext): array {
                if (is_string($screenContext->query['pack_id'] ?? null) && ($screenContext->query['pack_id'] ?? '') !== '') {
                    return [
                        new ToolbarAction(
                            label: __('Back to catalog'),
                            url: route('core.shell.index', [...$this->baseQuery($screenContext, false), 'menu' => 'plugin.automation-catalog.root']),
                            variant: 'secondary',
                        ),
                    ];
                }

                return [
                    new ToolbarAction(
                        label: __('Add repository of packs'),
                        url: route('core.shell.index', [...$this->baseQuery($screenContext, false), 'menu' => 'plugin.automation-catalog.root', 'automation_panel' => 'repository-editor']),
                        variant: 'primary',
                    ),
                    new ToolbarAction(
                        label: __('Register local pack'),
                        url: route('core.shell.index', [...$this->baseQuery($screenContext, false), 'menu' => 'plugin.automation-catalog.root', 'automation_panel' => 'pack-editor']),
                        variant: 'secondary',
                    ),
                ];
            },
        ));
    }

    public function boot(PluginContext $context): void
    {
        //
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogData(PluginContext $context, ScreenRenderContext $screenContext): array
    {
        $repository = $context->app()->make(AutomationCatalogRepository::class);
        $authorization = $context->app()->make(AuthorizationServiceInterface::class);
        $tenancy = $context->app()->make(TenancyServiceInterface::class);
        $workflowRegistry = $context->app()->make(WorkflowRegistryInterface::class);
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $canManagePacks = $screenContext->principal !== null && $authorization->authorize(new AuthorizationContext(
            principal: $screenContext->principal,
            permission: 'plugin.automation-catalog.packs.manage',
            memberships: $screenContext->memberships,
            organizationId: $organizationId,
            scopeId: $screenContext->scopeId,
        ))->allowed();

        $scopeContext = $tenancy->resolveContext(
            principalId: $screenContext->principal?->id,
            requestedOrganizationId: $organizationId,
            requestedScopeId: $screenContext->scopeId,
            requestedMembershipIds: array_map(static fn ($membership): string => $membership->id, $screenContext->memberships),
        );

        $packs = array_map(function (array $pack): array {
            return [
                ...$pack,
                'lifecycle_state_label' => match ($pack['lifecycle_state']) {
                    'discovered' => __('Discovered'),
                    'installed' => __('Installed'),
                    'enabled' => __('Enabled'),
                    'disabled' => __('Disabled'),
                    default => __(ucwords(str_replace('-', ' ', $pack['lifecycle_state']))),
                },
                'health_state_label' => match ($pack['health_state']) {
                    'unknown' => __('Unknown'),
                    'healthy' => __('Healthy'),
                    'degraded' => __('Degraded'),
                    'failing' => __('Failing'),
                    default => __(ucwords(str_replace('-', ' ', $pack['health_state']))),
                },
            ];
        }, $repository->all($organizationId, $screenContext->scopeId));
        $installedPacks = array_values(array_filter($packs, static fn (array $pack): bool => $pack['is_installed'] === '1'));

        $selectedPackId = is_string($screenContext->query['pack_id'] ?? null) && $screenContext->query['pack_id'] !== ''
            ? (string) $screenContext->query['pack_id']
            : null;
        $selectedPack = null;

        if (is_string($selectedPackId)) {
            foreach ($packs as $pack) {
                if ($pack['id'] === $selectedPackId) {
                    $selectedPack = $pack;
                    break;
                }
            }
        }

        $selectedPackMappings = is_array($selectedPack)
            ? array_map(function (array $mapping): array {
                $selector = json_decode((string) ($mapping['target_selector_json'] ?? ''), true);
                $selectorTags = is_array($selector['tags'] ?? null)
                    ? implode(', ', array_values(array_filter(array_map(static fn ($tag): string => is_string($tag) ? $tag : '', $selector['tags']), static fn (string $tag): bool => $tag !== '')))
                    : '';

                return [
                    ...$mapping,
                    'mapping_kind_label' => $mapping['mapping_kind'] === 'workflow-transition' ? __('Workflow transition') : __('Evidence refresh'),
                    'target_binding_mode_label' => ($mapping['target_binding_mode'] ?? 'explicit') === 'scope' ? __('Scope resolver') : __('Explicit object'),
                    'posture_propagation_policy_label' => ($mapping['posture_propagation_policy'] ?? 'disabled') === 'status-only'
                        ? __('Status only')
                        : __('Disabled'),
                    'execution_mode_label' => match ($mapping['execution_mode'] ?? 'both') {
                        'runtime-only' => __('Runtime only'),
                        'manual-only' => __('Manual only'),
                        default => __('Both'),
                    },
                    'on_fail_policy_label' => ($mapping['on_fail_policy'] ?? 'no-op') === 'raise-finding'
                        ? __('Raise finding')
                        : (($mapping['on_fail_policy'] ?? 'no-op') === 'raise-finding-and-action'
                            ? __('Raise finding + action')
                            : __('No-op')),
                    'evidence_policy_label' => match ($mapping['evidence_policy'] ?? 'always') {
                        'on-fail' => __('On fail'),
                        'on-change' => __('On change'),
                        default => __('Always'),
                    },
                    'target_selector_tags' => $selectorTags,
                    'last_status_label' => match ($mapping['last_status']) {
                        'success' => __('Success'),
                        'skipped' => __('Skipped'),
                        'failed' => __('Failed'),
                        default => __('Never'),
                    },
                    'apply_route' => route('plugin.automation-catalog.output-mappings.apply', [
                        'packId' => $mapping['automation_pack_id'],
                        'mappingId' => $mapping['id'],
                    ]),
                ];
            }, $repository->outputMappings((string) $selectedPack['id']))
            : [];
        $selectedPackRuns = is_array($selectedPack)
            ? array_map(static function (array $run): array {
                return [
                    ...$run,
                    'status_label' => match ($run['status']) {
                        'success' => __('Success'),
                        'partial' => __('Partial'),
                        'failed' => __('Failed'),
                        'running' => __('Running'),
                        default => ucwords(str_replace('-', ' ', $run['status'])),
                    },
                    'trigger_mode_label' => $run['trigger_mode'] === 'scheduled' ? __('Scheduled') : __('Manual'),
                ];
            }, $repository->recentRunsForPack((string) $selectedPack['id'], 20))
            : [];
        $selectedPackCheckResults = [];
        if (is_array($selectedPack)) {
            $rawCheckResults = $repository->recentCheckResultsForPack((string) $selectedPack['id'], 25);
            $findingByCheckResult = $repository->findingByCheckResultIds(array_map(static fn (array $result): string => (string) ($result['id'] ?? ''), $rawCheckResults));
            $actionByCheckResult = $repository->remediationActionByCheckResultIds(array_map(static fn (array $result): string => (string) ($result['id'] ?? ''), $rawCheckResults));

            $selectedPackCheckResults = array_map(function (array $result) use ($screenContext, $findingByCheckResult, $actionByCheckResult): array {
                $resultId = (string) ($result['id'] ?? '');
                $evidenceId = (string) ($result['evidence_id'] ?? '');
                $findingId = (string) ($result['finding_id'] ?? '');
                if ($findingId === '') {
                    $findingId = $findingByCheckResult[$resultId] ?? '';
                }
                $actionId = (string) ($result['remediation_action_id'] ?? '');
                if ($actionId === '') {
                    $actionId = $actionByCheckResult[$resultId] ?? '';
                }

                return [
                    ...$result,
                    'status_label' => match ($result['status']) {
                        'success' => __('Success'),
                        'skipped' => __('Skipped'),
                        'failed' => __('Failed'),
                        default => ucwords(str_replace('-', ' ', $result['status'])),
                    },
                    'outcome_label' => match ($result['outcome']) {
                        'pass' => __('Pass'),
                        'fail' => __('Fail'),
                        'warn' => __('Warn'),
                        'not-applicable' => __('Not applicable'),
                        default => ucwords(str_replace('-', ' ', $result['outcome'])),
                    },
                    'evidence_id' => $evidenceId,
                    'evidence_open_url' => $evidenceId !== ''
                        ? route('core.shell.index', [...$this->baseQuery($screenContext, false), 'menu' => 'plugin.evidence-management.root', 'evidence_id' => $evidenceId])
                        : '',
                    'finding_id' => $findingId,
                    'finding_open_url' => $findingId !== ''
                        ? route('core.shell.index', [...$this->baseQuery($screenContext, false), 'menu' => 'plugin.findings-remediation.root', 'finding_id' => $findingId])
                        : '',
                    'action_id' => $actionId,
                    'action_open_url' => $actionId !== '' && $findingId !== ''
                        ? route('core.shell.index', [...$this->baseQuery($screenContext, false), 'menu' => 'plugin.findings-remediation.root', 'finding_id' => $findingId, 'action_id' => $actionId])
                        : '',
                ];
            }, $rawCheckResults);
        }

        $workflowCatalog = array_map(static function ($definition): array {
            return [
                'key' => $definition->key,
                'label' => $definition->label,
                'transitions' => array_map(static fn ($transition): array => [
                    'key' => $transition->key,
                    'to_state' => $transition->toState,
                ], $definition->transitions),
            ];
        }, $workflowRegistry->all());

        $repositories = array_map(function (array $repository): array {
            return [
                ...$repository,
                'last_status_label' => match ($repository['last_status']) {
                    'success' => __('Success'),
                    'failed' => __('Failed'),
                    default => __('Never'),
                },
                'refresh_route' => route('plugin.automation-catalog.repositories.refresh', [
                    'repositoryId' => $repository['id'],
                ]),
            ];
        }, $repository->repositories($organizationId, $screenContext->scopeId));

        $requestedScopeId = is_string($screenContext->scopeId) ? $screenContext->scopeId : '';
        $packByKey = [];

        foreach ($packs as $pack) {
            $packKey = (string) ($pack['pack_key'] ?? '');
            if ($packKey === '') {
                continue;
            }

            $existing = $packByKey[$packKey] ?? null;
            if (! is_array($existing)) {
                $packByKey[$packKey] = $pack;

                continue;
            }

            $existingInstalled = ($existing['is_installed'] ?? '0') === '1';
            $candidateInstalled = ($pack['is_installed'] ?? '0') === '1';
            if ($candidateInstalled && ! $existingInstalled) {
                $packByKey[$packKey] = $pack;

                continue;
            }

            $existingScopeId = is_string($existing['scope_id'] ?? null) ? (string) $existing['scope_id'] : '';
            $candidateScopeId = is_string($pack['scope_id'] ?? null) ? (string) $pack['scope_id'] : '';
            if ($requestedScopeId !== '' && $candidateScopeId === $requestedScopeId && $existingScopeId !== $requestedScopeId) {
                $packByKey[$packKey] = $pack;
            }
        }

        $externalCatalogRows = array_map(function (array $row) use ($packByKey, $screenContext, $canManagePacks): array {
            $pack = $packByKey[$row['pack_key']] ?? null;
            $packId = is_array($pack) ? (string) ($pack['id'] ?? '') : '';
            $isInstalled = is_array($pack) && ($pack['is_installed'] ?? '0') === '1';

            return [
                ...$row,
                'local_pack_id' => $packId,
                'local_pack_installed' => $isInstalled ? '1' : '0',
                'local_pack_enabled' => is_array($pack) && ($pack['is_enabled'] ?? '0') === '1' ? '1' : '0',
                'open_url' => $packId !== ''
                    ? route('core.shell.index', [...$this->baseQuery($screenContext, false), 'menu' => 'plugin.automation-catalog.root', 'pack_id' => $packId])
                    : '',
                'install_route' => $packId !== '' ? route('plugin.automation-catalog.install', ['packId' => $packId]) : '',
                'can_install' => ($canManagePacks && $packId !== '' && ! $isInstalled) ? '1' : '0',
            ];
        }, $repository->externalCatalogRows($organizationId, $screenContext->scopeId));
        $activePanel = is_string($screenContext->query['automation_panel'] ?? null)
            ? trim((string) $screenContext->query['automation_panel'])
            : '';
        $hasRepositories = $repositories !== [];
        $showDetailOnly = is_array($selectedPack);
        $showRepositoryPanel = ! $showDetailOnly && ($activePanel === 'repository-editor' || ! $hasRepositories);

        return [
            'packs' => array_map(function (array $pack) use ($screenContext): array {
                return [
                    ...$pack,
                    'open_url' => route('core.shell.index', [...$this->baseQuery($screenContext, false), 'menu' => 'plugin.automation-catalog.root', 'pack_id' => $pack['id']]),
                    'install_route' => route('plugin.automation-catalog.install', ['packId' => $pack['id']]),
                    'enable_route' => route('plugin.automation-catalog.enable', ['packId' => $pack['id']]),
                    'disable_route' => route('plugin.automation-catalog.disable', ['packId' => $pack['id']]),
                    'uninstall_route' => route('plugin.automation-catalog.uninstall', ['packId' => $pack['id']]),
                    'health_route' => route('plugin.automation-catalog.health.update', ['packId' => $pack['id']]),
                ];
            }, $packs),
            'installed_packs' => array_map(function (array $pack) use ($screenContext): array {
                return [
                    ...$pack,
                    'open_url' => route('core.shell.index', [...$this->baseQuery($screenContext, false), 'menu' => 'plugin.automation-catalog.root', 'pack_id' => $pack['id']]),
                    'install_route' => route('plugin.automation-catalog.install', ['packId' => $pack['id']]),
                    'enable_route' => route('plugin.automation-catalog.enable', ['packId' => $pack['id']]),
                    'disable_route' => route('plugin.automation-catalog.disable', ['packId' => $pack['id']]),
                    'uninstall_route' => route('plugin.automation-catalog.uninstall', ['packId' => $pack['id']]),
                    'health_route' => route('plugin.automation-catalog.health.update', ['packId' => $pack['id']]),
                ];
            }, $installedPacks),
            'selected_pack' => $selectedPack,
            'selected_pack_output_mappings' => $selectedPackMappings,
            'selected_pack_runs' => $selectedPackRuns,
            'selected_pack_check_results' => $selectedPackCheckResults,
            'can_manage_packs' => $canManagePacks,
            'query' => $this->baseQuery($screenContext),
            'list_query' => $this->baseQuery($screenContext, false),
            'scope_options' => array_map(static fn ($scope): array => $scope->toArray(), $scopeContext->scopes),
            'packs_list_url' => route('core.shell.index', [...$this->baseQuery($screenContext, false), 'menu' => 'plugin.automation-catalog.root']),
            'pack_store_route' => route('plugin.automation-catalog.store'),
            'repository_store_route' => route('plugin.automation-catalog.repositories.store'),
            'official_repository_install_route' => route('plugin.automation-catalog.repositories.install-official'),
            'output_mapping_store_route' => is_array($selectedPack)
                ? route('plugin.automation-catalog.output-mappings.store', ['packId' => $selectedPack['id']])
                : null,
            'runtime_run_route' => is_array($selectedPack)
                ? route('plugin.automation-catalog.run', ['packId' => $selectedPack['id']])
                : null,
            'show_pack_editor' => ! $showDetailOnly && $activePanel === 'pack-editor',
            'show_repository_panel' => $showRepositoryPanel,
            'show_repository_onboarding' => ! $showDetailOnly && ! $hasRepositories,
            'show_external_catalog' => ! $showDetailOnly && $hasRepositories,
            'show_catalog_chrome' => ! $showDetailOnly,
            'repositories' => $repositories,
            'external_catalog_rows' => $externalCatalogRows,
            'trust_tier_options' => [
                'trusted-first-party' => __('Trusted first-party'),
                'trusted-partner' => __('Trusted partner'),
                'community-reviewed' => __('Community reviewed'),
                'untrusted' => __('Untrusted'),
            ],
            'mapping_kind_options' => [
                'evidence-refresh' => __('Evidence refresh'),
                'workflow-transition' => __('Workflow transition'),
            ],
            'subject_type_options' => [
                'asset' => __('Asset'),
                'control' => __('Control'),
                'risk' => __('Risk'),
                'finding' => __('Finding'),
                'policy' => __('Policy'),
                'policy-exception' => __('Policy exception'),
                'privacy-data-flow' => __('Privacy data flow'),
                'privacy-processing-activity' => __('Privacy processing activity'),
                'continuity-service' => __('Continuity service'),
                'continuity-plan' => __('Continuity plan'),
                'recovery-plan' => __('Recovery plan'),
                'assessment' => __('Assessment'),
                'assessment-review' => __('Assessment review'),
                'vendor-review' => __('Vendor review'),
            ],
            'automation_workflow_catalog' => $workflowCatalog,
            'evidence_kind_options' => [
                'document' => __('Document'),
                'workpaper' => __('Workpaper'),
                'snapshot' => __('System snapshot'),
                'report' => __('Report'),
                'ticket' => __('Ticket'),
                'log-export' => __('Log export'),
                'statement' => __('Statement'),
                'other' => __('Other'),
            ],
            'provider_type_options' => [
                'native' => __('Native'),
                'community' => __('Community'),
                'vendor' => __('Vendor'),
                'internal' => __('Internal'),
            ],
            'provenance_type_options' => [
                'plugin' => __('Plugin'),
                'marketplace' => __('Marketplace'),
                'git' => __('Git'),
                'manual' => __('Manual'),
            ],
            'health_state_options' => [
                'unknown' => __('Unknown'),
                'healthy' => __('Healthy'),
                'degraded' => __('Degraded'),
                'failing' => __('Failing'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function baseQuery(ScreenRenderContext $context, bool $includeSelection = true): array
    {
        $query = $context->query;

        $query['organization_id'] = $context->organizationId ?? ($query['organization_id'] ?? 'org-a');
        $query['locale'] = $context->locale;

        if ($context->scopeId !== null) {
            $query['scope_id'] = $context->scopeId;
        }

        foreach ($context->memberships as $membership) {
            $query['membership_ids'][] = $membership->id;
        }

        if (! $includeSelection) {
            unset($query['pack_id']);
        }

        return $query;
    }
}
