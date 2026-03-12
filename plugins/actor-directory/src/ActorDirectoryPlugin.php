<?php

namespace PymeSec\Plugins\ActorDirectory;

use PymeSec\Core\Contracts\FunctionalActorPluginInterface;
use PymeSec\Core\Events\Contracts\EventBusInterface;
use PymeSec\Core\Events\PublicEvent;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\Plugins\PluginContext;
use PymeSec\Core\UI\ScreenDefinition;
use PymeSec\Core\UI\ScreenRenderContext;
use PymeSec\Core\UI\ToolbarAction;

class ActorDirectoryPlugin implements FunctionalActorPluginInterface
{
    public function functionalActorProviderKey(): string
    {
        return 'actor-directory';
    }

    public function register(PluginContext $context): void
    {
        $context->subscribeToEvent('plugin.asset-catalog.workflows.transitioned', function (PublicEvent $event) use ($context): void {
            $context->app()->make(EventBusInterface::class)->publish(new PublicEvent(
                name: 'plugin.actor-directory.asset-transition.observed',
                originComponent: 'actor-directory',
                organizationId: $event->organizationId,
                scopeId: $event->scopeId,
                payload: [
                    'source_event' => $event->name,
                    'subject_type' => $event->payload['subject_type'] ?? null,
                    'subject_id' => $event->payload['subject_id'] ?? null,
                    'transition_key' => $event->payload['transition_key'] ?? null,
                ],
            ));
        });

        $context->registerScreen(new ScreenDefinition(
            menuId: 'plugin.actor-directory.root',
            owner: 'actor-directory',
            titleKey: 'plugin.actor-directory.screen.directory.title',
            subtitleKey: 'plugin.actor-directory.screen.directory.subtitle',
            viewPath: $context->path('resources/views/directory.blade.php'),
            dataResolver: function (ScreenRenderContext $screenContext) use ($context): array {
                return $this->directoryData($context, $screenContext);
            },
            toolbarResolver: function (ScreenRenderContext $screenContext): array {
                $query = $this->baseQuery($screenContext);

                return [
                    new ToolbarAction(
                        label: 'Assignments',
                        url: route('core.shell.index', [...$query, 'menu' => 'plugin.actor-directory.assignments']),
                        variant: 'secondary',
                    ),
                    new ToolbarAction(
                        label: 'Core API',
                        url: route('core.functional-actors.index', $query),
                        variant: 'primary',
                        target: '_self',
                    ),
                ];
            },
        ));

        $context->registerScreen(new ScreenDefinition(
            menuId: 'plugin.actor-directory.assignments',
            owner: 'actor-directory',
            titleKey: 'plugin.actor-directory.screen.assignments.title',
            subtitleKey: 'plugin.actor-directory.screen.assignments.subtitle',
            viewPath: $context->path('resources/views/assignments.blade.php'),
            dataResolver: function (ScreenRenderContext $screenContext) use ($context): array {
                return $this->assignmentData($context, $screenContext);
            },
            toolbarResolver: function (ScreenRenderContext $screenContext): array {
                return [
                    new ToolbarAction(
                        label: 'Actor directory',
                        url: route('core.shell.index', [...$this->baseQuery($screenContext), 'menu' => 'plugin.actor-directory.root']),
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
    private function directoryData(PluginContext $context, ScreenRenderContext $screenContext): array
    {
        $actors = $context->app()->make(FunctionalActorServiceInterface::class);
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $scopeId = $screenContext->scopeId;
        $rows = [];

        foreach ($actors->actors($organizationId, $scopeId) as $actor) {
            $links = $actors->linksForActor($actor->id);
            $assignments = array_values(array_filter(
                $actors->assignments($organizationId, $scopeId),
                static fn ($assignment): bool => $assignment->functionalActorId === $actor->id,
            ));

            $rows[] = [
                'actor' => $actor,
                'links' => $links,
                'assignments' => $assignments,
            ];
        }

        return [
            'rows' => $rows,
            'query' => $this->baseQuery($screenContext),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function assignmentData(PluginContext $context, ScreenRenderContext $screenContext): array
    {
        $actors = $context->app()->make(FunctionalActorServiceInterface::class);
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $scopeId = $screenContext->scopeId;
        $actorsById = [];

        foreach ($actors->actors($organizationId, $scopeId) as $actor) {
            $actorsById[$actor->id] = $actor;
        }

        $rows = [];

        foreach ($actors->assignments($organizationId, $scopeId) as $assignment) {
            $rows[] = [
                'assignment' => $assignment,
                'actor' => $actorsById[$assignment->functionalActorId] ?? null,
            ];
        }

        return [
            'rows' => $rows,
        ];
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
}
