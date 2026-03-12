<?php

namespace PymeSec\Plugins\AssetCatalog;

use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\Plugins\Contracts\PluginInterface;
use PymeSec\Core\Plugins\PluginContext;
use PymeSec\Core\Permissions\AuthorizationContext;
use PymeSec\Core\Permissions\Contracts\AuthorizationServiceInterface;
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
                $query = $this->baseQuery($screenContext);

                return [
                    new ToolbarAction(
                        label: 'Lifecycle board',
                        url: route('core.shell.index', [...$query, 'menu' => 'plugin.asset-catalog.lifecycle']),
                        variant: 'secondary',
                    ),
                    new ToolbarAction(
                        label: 'Plugin route',
                        url: route('plugin.asset-catalog.index', $query),
                        variant: 'primary',
                        target: '_self',
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
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $canManageAssets = $screenContext->principal !== null && $authorization->authorize(new AuthorizationContext(
            principal: $screenContext->principal,
            permission: 'plugin.asset-catalog.assets.manage',
            memberships: $screenContext->memberships,
            organizationId: $organizationId,
            scopeId: $screenContext->scopeId,
        ))->allowed();
        $assets = [];

        foreach ($repository->all($organizationId, $screenContext->scopeId) as $asset) {
            $instance = $workflow->instanceFor(
                workflowKey: 'plugin.asset-catalog.asset-lifecycle',
                subjectType: 'asset',
                subjectId: $asset['id'],
                organizationId: $organizationId,
                scopeId: $screenContext->scopeId,
            );

            $assets[] = [
                ...$asset,
                'owner_assignment' => $this->ownerAssignment($actors, $asset['id'], $organizationId, $screenContext->scopeId),
                'state' => $instance->currentState,
                'transitions' => $canManageAssets
                    ? $this->transitionsForState($instance->currentState)
                    : [],
                'transition_route' => route('plugin.asset-catalog.transition', ['assetId' => $asset['id'], 'transitionKey' => '__TRANSITION__']),
            ];
        }

        return [
            'assets' => $assets,
            'can_manage_assets' => $canManageAssets,
            'query' => $this->baseQuery($screenContext),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lifecycleData(PluginContext $context, ScreenRenderContext $screenContext): array
    {
        $repository = $context->app()->make(AssetCatalogRepository::class);
        $workflow = $context->app()->make(WorkflowServiceInterface::class);
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $rows = [];

        foreach ($repository->all($organizationId, $screenContext->scopeId) as $asset) {
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
    private function baseQuery(ScreenRenderContext $context): array
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
