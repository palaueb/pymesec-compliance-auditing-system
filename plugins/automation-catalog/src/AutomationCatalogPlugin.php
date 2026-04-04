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

class AutomationCatalogPlugin implements PluginInterface
{
    public function register(PluginContext $context): void
    {
        $context->app()->singleton(AutomationCatalogRepository::class, fn () => new AutomationCatalogRepository);
        $context->app()->singleton(AutomationOutputMappingDeliveryService::class);
        $context->app()->singleton(AutomationPackageRepositorySyncService::class);

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
                            label: 'Back to catalog',
                            url: route('core.shell.index', [...$this->baseQuery($screenContext, false), 'menu' => 'plugin.automation-catalog.root']),
                            variant: 'secondary',
                        ),
                    ];
                }

                return [
                    new ToolbarAction(
                        label: 'Add repository of packs',
                        url: route('core.shell.index', [...$this->baseQuery($screenContext, false), 'menu' => 'plugin.automation-catalog.root', 'automation_panel' => 'repository-editor']),
                        variant: 'primary',
                    ),
                    new ToolbarAction(
                        label: 'Register local pack',
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
                'lifecycle_state_label' => ucwords(str_replace('-', ' ', $pack['lifecycle_state'])),
                'health_state_label' => ucwords(str_replace('-', ' ', $pack['health_state'])),
            ];
        }, $repository->all($organizationId, $screenContext->scopeId));

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
                return [
                    ...$mapping,
                    'mapping_kind_label' => $mapping['mapping_kind'] === 'workflow-transition' ? 'Workflow transition' : 'Evidence refresh',
                    'last_status_label' => match ($mapping['last_status']) {
                        'success' => 'Success',
                        'failed' => 'Failed',
                        default => 'Never',
                    },
                    'apply_route' => route('plugin.automation-catalog.output-mappings.apply', [
                        'packId' => $mapping['automation_pack_id'],
                        'mappingId' => $mapping['id'],
                    ]),
                ];
            }, $repository->outputMappings((string) $selectedPack['id']))
            : [];

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
                    'success' => 'Success',
                    'failed' => 'Failed',
                    default => 'Never',
                },
                'refresh_route' => route('plugin.automation-catalog.repositories.refresh', [
                    'repositoryId' => $repository['id'],
                ]),
            ];
        }, $repository->repositories($organizationId, $screenContext->scopeId));

        $externalCatalogRows = $repository->externalCatalogRows($organizationId, $screenContext->scopeId);
        $activePanel = is_string($screenContext->query['automation_panel'] ?? null)
            ? trim((string) $screenContext->query['automation_panel'])
            : '';

        return [
            'packs' => array_map(function (array $pack) use ($screenContext): array {
                return [
                    ...$pack,
                    'open_url' => route('core.shell.index', [...$this->baseQuery($screenContext, false), 'menu' => 'plugin.automation-catalog.root', 'pack_id' => $pack['id']]),
                    'install_route' => route('plugin.automation-catalog.install', ['packId' => $pack['id']]),
                    'enable_route' => route('plugin.automation-catalog.enable', ['packId' => $pack['id']]),
                    'disable_route' => route('plugin.automation-catalog.disable', ['packId' => $pack['id']]),
                    'health_route' => route('plugin.automation-catalog.health.update', ['packId' => $pack['id']]),
                ];
            }, $packs),
            'selected_pack' => $selectedPack,
            'selected_pack_output_mappings' => $selectedPackMappings,
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
            'show_pack_editor' => $activePanel === 'pack-editor',
            'show_repository_panel' => $activePanel === 'repository-editor',
            'repositories' => $repositories,
            'external_catalog_rows' => $externalCatalogRows,
            'trust_tier_options' => [
                'trusted-first-party' => 'Trusted first-party',
                'trusted-partner' => 'Trusted partner',
                'community-reviewed' => 'Community reviewed',
                'untrusted' => 'Untrusted',
            ],
            'mapping_kind_options' => [
                'evidence-refresh' => 'Evidence refresh',
                'workflow-transition' => 'Workflow transition',
            ],
            'subject_type_options' => [
                'asset' => 'Asset',
                'control' => 'Control',
                'risk' => 'Risk',
                'finding' => 'Finding',
                'policy' => 'Policy',
                'policy-exception' => 'Policy exception',
                'privacy-data-flow' => 'Privacy data flow',
                'privacy-processing-activity' => 'Privacy processing activity',
                'continuity-service' => 'Continuity service',
                'continuity-plan' => 'Continuity plan',
                'recovery-plan' => 'Recovery plan',
                'assessment' => 'Assessment',
                'assessment-review' => 'Assessment review',
                'vendor-review' => 'Vendor review',
            ],
            'automation_workflow_catalog' => $workflowCatalog,
            'evidence_kind_options' => [
                'document' => 'Document',
                'workpaper' => 'Workpaper',
                'snapshot' => 'System snapshot',
                'report' => 'Report',
                'ticket' => 'Ticket',
                'log-export' => 'Log export',
                'statement' => 'Statement',
                'other' => 'Other',
            ],
            'provider_type_options' => [
                'native' => 'Native',
                'community' => 'Community',
                'vendor' => 'Vendor',
                'internal' => 'Internal',
            ],
            'provenance_type_options' => [
                'plugin' => 'Plugin',
                'marketplace' => 'Marketplace',
                'git' => 'Git',
                'manual' => 'Manual',
            ],
            'health_state_options' => [
                'unknown' => 'Unknown',
                'healthy' => 'Healthy',
                'degraded' => 'Degraded',
                'failing' => 'Failing',
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
