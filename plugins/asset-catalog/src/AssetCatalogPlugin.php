<?php

namespace PymeSec\Plugins\AssetCatalog;

use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\ObjectAccess\ObjectAccessService;
use PymeSec\Core\Plugins\Contracts\PluginInterface;
use PymeSec\Core\Plugins\PluginContext;
use PymeSec\Core\Permissions\AuthorizationContext;
use PymeSec\Core\Permissions\Contracts\AuthorizationServiceInterface;
use PymeSec\Core\Tenancy\Contracts\TenancyServiceInterface;
use PymeSec\Core\UI\ScreenDefinition;
use PymeSec\Core\UI\ScreenRenderContext;
use PymeSec\Core\UI\ToolbarAction;
use PymeSec\Core\Workflows\Contracts\WorkflowRegistryInterface;
use PymeSec\Core\Workflows\Contracts\WorkflowServiceInterface;
use PymeSec\Core\Workflows\WorkflowDefinition;
use PymeSec\Core\Workflows\WorkflowTransitionDefinition;

class AssetCatalogPlugin implements PluginInterface
{
    public function register(PluginContext $context): void
    {
        $context->app()->singleton(AssetCatalogRepository::class, fn () => new AssetCatalogRepository());

        $context->app()->make(WorkflowRegistryInterface::class)->register(new WorkflowDefinition(
            key: 'plugin.asset-catalog.asset-lifecycle',
            owner: 'asset-catalog',
            label: 'Asset lifecycle',
            initialState: 'draft',
            states: ['draft', 'review', 'active', 'retired'],
            transitions: [
                new WorkflowTransitionDefinition(
                    key: 'submit-review',
                    fromStates: ['draft', 'active'],
                    toState: 'review',
                    permission: 'plugin.asset-catalog.assets.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'approve',
                    fromStates: ['review'],
                    toState: 'active',
                    permission: 'plugin.asset-catalog.assets.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'retire',
                    fromStates: ['active', 'review'],
                    toState: 'retired',
                    permission: 'plugin.asset-catalog.assets.manage',
                ),
                new WorkflowTransitionDefinition(
                    key: 'reopen',
                    fromStates: ['retired'],
                    toState: 'draft',
                    permission: 'plugin.asset-catalog.assets.manage',
                ),
            ],
        ));

        $context->registerScreen(new ScreenDefinition(
            menuId: 'plugin.asset-catalog.root',
            owner: 'asset-catalog',
            titleKey: 'plugin.asset-catalog.screen.catalog.title',
            subtitleKey: 'plugin.asset-catalog.screen.catalog.subtitle',
            viewPath: $context->path('resources/views/catalog.blade.php'),
            dataResolver: function (ScreenRenderContext $screenContext) use ($context): array {
                return $this->catalogData($context, $screenContext);
            },
            toolbarResolver: function (ScreenRenderContext $screenContext) use ($context): array {
                $query = $this->baseQuery($screenContext, false);

                if (is_string($screenContext->query['asset_id'] ?? null) && ($screenContext->query['asset_id'] ?? '') !== '') {
                    return [
                        new ToolbarAction(
                            label: 'Back to assets',
                            url: route('core.shell.index', [...$query, 'menu' => 'plugin.asset-catalog.root']),
                            variant: 'secondary',
                        ),
                        new ToolbarAction(
                            label: 'Lifecycle board',
                            url: route('core.shell.index', [...$query, 'menu' => 'plugin.asset-catalog.lifecycle']),
                            variant: 'secondary',
                        ),
                    ];
                }

                return [
                    new ToolbarAction(
                        label: 'Add asset',
                        url: '#asset-editor',
                        variant: 'primary',
                    ),
                    new ToolbarAction(
                        label: 'Lifecycle board',
                        url: route('core.shell.index', [...$query, 'menu' => 'plugin.asset-catalog.lifecycle']),
                        variant: 'secondary',
                    ),
                ];
            },
        ));

        $context->registerScreen(new ScreenDefinition(
            menuId: 'plugin.asset-catalog.lifecycle',
            owner: 'asset-catalog',
            titleKey: 'plugin.asset-catalog.screen.lifecycle.title',
            subtitleKey: 'plugin.asset-catalog.screen.lifecycle.subtitle',
            viewPath: $context->path('resources/views/lifecycle.blade.php'),
            dataResolver: function (ScreenRenderContext $screenContext) use ($context): array {
                return $this->lifecycleData($context, $screenContext);
            },
            toolbarResolver: function (ScreenRenderContext $screenContext): array {
                return [
                    new ToolbarAction(
                        label: 'Asset catalog',
                        url: route('core.shell.index', [...$this->baseQuery($screenContext), 'menu' => 'plugin.asset-catalog.root']),
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
        $repository = $context->app()->make(AssetCatalogRepository::class);
        $workflow = $context->app()->make(WorkflowServiceInterface::class);
        $actors = $context->app()->make(FunctionalActorServiceInterface::class);
        $authorization = $context->app()->make(AuthorizationServiceInterface::class);
        $objectAccess = $context->app()->make(ObjectAccessService::class);
        $tenancy = $context->app()->make(TenancyServiceInterface::class);
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $canManageAssets = $screenContext->principal !== null && $authorization->authorize(new AuthorizationContext(
            principal: $screenContext->principal,
            permission: 'plugin.asset-catalog.assets.manage',
            memberships: $screenContext->memberships,
            organizationId: $organizationId,
            scopeId: $screenContext->scopeId,
        ))->allowed();
        $assets = [];

        $catalog = $objectAccess->filterRecords(
            records: $repository->all($organizationId, $screenContext->scopeId),
            idKey: 'id',
            principalId: $screenContext->principal?->id,
            organizationId: $organizationId,
            scopeId: $screenContext->scopeId,
            domainObjectType: 'asset',
        );

        foreach ($catalog as $asset) {
            $instance = $workflow->instanceFor(
                workflowKey: 'plugin.asset-catalog.asset-lifecycle',
                subjectType: 'asset',
                subjectId: $asset['id'],
                organizationId: $organizationId,
                scopeId: $screenContext->scopeId,
            );

            $assets[] = [
                ...$asset,
                'type_label' => AssetReferenceData::typeLabel($asset['type']),
                'criticality_label' => AssetReferenceData::criticalityLabel($asset['criticality']),
                'classification_label' => AssetReferenceData::classificationLabel($asset['classification']),
                'owner_assignment' => $this->ownerAssignment($actors, $asset['id'], $organizationId, $screenContext->scopeId),
                'state' => $instance->currentState,
                'transitions' => $canManageAssets
                    ? $this->transitionsForState($instance->currentState)
                    : [],
                'transition_route' => route('plugin.asset-catalog.transition', ['assetId' => $asset['id'], 'transitionKey' => '__TRANSITION__']),
                'update_route' => route('plugin.asset-catalog.update', ['assetId' => $asset['id']]),
                'history' => $workflow->history('plugin.asset-catalog.asset-lifecycle', 'asset', $asset['id']),
                'open_url' => route('core.shell.index', [...$this->baseQuery($screenContext, false), 'menu' => 'plugin.asset-catalog.root', 'asset_id' => $asset['id']]),
            ];
        }

        $selectedAssetId = is_string($screenContext->query['asset_id'] ?? null) && $screenContext->query['asset_id'] !== ''
            ? (string) $screenContext->query['asset_id']
            : null;
        $selectedAsset = null;

        if (is_string($selectedAssetId)) {
            foreach ($assets as $asset) {
                if ($asset['id'] === $selectedAssetId) {
                    $selectedAsset = $asset;
                    break;
                }
            }
        }

        $scopeContext = $tenancy->resolveContext(
            principalId: $screenContext->principal?->id,
            requestedOrganizationId: $organizationId,
            requestedScopeId: $screenContext->scopeId,
            requestedMembershipIds: array_map(static fn ($membership): string => $membership->id, $screenContext->memberships),
        );

        return [
            'assets' => $assets,
            'selected_asset' => $selectedAsset,
            'can_manage_assets' => $canManageAssets,
            'query' => $this->baseQuery($screenContext),
            'list_query' => $this->baseQuery($screenContext, false),
            'create_route' => route('plugin.asset-catalog.store'),
            'owner_actor_options' => array_map(static fn ($actor): array => [
                'id' => $actor->id,
                'label' => $actor->displayName,
            ], $actors->actors($organizationId, $screenContext->scopeId)),
            'asset_type_options' => AssetReferenceData::optionsFor('types'),
            'asset_criticality_options' => AssetReferenceData::optionsFor('criticality'),
            'asset_classification_options' => AssetReferenceData::optionsFor('classification'),
            'scope_options' => array_map(static fn ($scope): array => $scope->toArray(), $scopeContext->scopes),
            'assets_list_url' => route('core.shell.index', [...$this->baseQuery($screenContext, false), 'menu' => 'plugin.asset-catalog.root']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lifecycleData(PluginContext $context, ScreenRenderContext $screenContext): array
    {
        $repository = $context->app()->make(AssetCatalogRepository::class);
        $objectAccess = $context->app()->make(ObjectAccessService::class);
        $workflow = $context->app()->make(WorkflowServiceInterface::class);
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $rows = [];

        $catalog = $objectAccess->filterRecords(
            records: $repository->all($organizationId, $screenContext->scopeId),
            idKey: 'id',
            principalId: $screenContext->principal?->id,
            organizationId: $organizationId,
            scopeId: $screenContext->scopeId,
            domainObjectType: 'asset',
        );

        foreach ($catalog as $asset) {
            $instance = $workflow->instanceFor(
                workflowKey: 'plugin.asset-catalog.asset-lifecycle',
                subjectType: 'asset',
                subjectId: $asset['id'],
                organizationId: $organizationId,
                scopeId: $screenContext->scopeId,
            );

            $rows[] = [
                'asset' => $asset,
                'instance' => $instance,
                'history' => $workflow->history('plugin.asset-catalog.asset-lifecycle', 'asset', $asset['id']),
            ];
        }

        return [
            'rows' => $rows,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function transitionsForState(string $state): array
    {
        return match ($state) {
            'draft' => ['submit-review'],
            'review' => ['approve', 'retire'],
            'active' => ['submit-review', 'retire'],
            'retired' => ['reopen'],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function baseQuery(ScreenRenderContext $context, bool $includeSelection = true): array
    {
        $query = $context->query;

        $query['principal_id'] = $context->principal?->id ?? ($query['principal_id'] ?? 'principal-org-a');
        $query['organization_id'] = $context->organizationId ?? ($query['organization_id'] ?? 'org-a');
        $query['locale'] = $context->locale;

        if ($context->scopeId !== null) {
            $query['scope_id'] = $context->scopeId;
        }

        foreach ($context->memberships as $membership) {
            $query['membership_ids'][] = $membership->id;
        }

        if (! $includeSelection) {
            unset($query['asset_id']);
        }

        return $query;
    }

    /**
     * @return array<string, string> | null
     */
    private function ownerAssignment(
        FunctionalActorServiceInterface $actors,
        string $assetId,
        string $organizationId,
        ?string $scopeId,
    ): ?array {
        foreach ($actors->assignmentsFor('asset', $assetId, $organizationId, $scopeId) as $assignment) {
            if ($assignment->assignmentType !== 'owner') {
                continue;
            }

            $actor = $actors->findActor($assignment->functionalActorId);

            if ($actor === null) {
                return null;
            }

            return [
                'id' => $actor->id,
                'display_name' => $actor->displayName,
                'kind' => $actor->kind,
            ];
        }

        return null;
    }
}
