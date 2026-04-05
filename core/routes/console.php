<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface;
use PymeSec\Core\Audit\AuditRecordData;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;
use PymeSec\Core\Events\Contracts\EventBusInterface;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\Menus\Contracts\MenuRegistryInterface;
use PymeSec\Core\Notifications\Contracts\NotificationServiceInterface;
use PymeSec\Core\Permissions\Contracts\AuthorizationStoreInterface;
use PymeSec\Core\Permissions\Contracts\PermissionRegistryInterface;
use PymeSec\Core\Plugins\Contracts\PluginManagerInterface;
use PymeSec\Core\Plugins\PluginLifecycleManager;
use PymeSec\Core\Plugins\PluginStateStore;
use PymeSec\Core\Tenancy\Contracts\TenancyServiceInterface;
use PymeSec\Core\Workflows\Contracts\WorkflowRegistryInterface;
use PymeSec\Plugins\AutomationCatalog\AutomationCatalogRepository;
use PymeSec\Plugins\AutomationCatalog\AutomationPackRuntimeService;
use PymeSec\Plugins\EvidenceManagement\EvidenceManagementRepository;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('plugins:list', function (PluginManagerInterface $plugins) {
    $rows = array_map(static fn (array $plugin): array => [
        $plugin['id'],
        $plugin['type'],
        $plugin['enabled'] ? 'yes' : 'no',
        $plugin['booted'] ? 'yes' : 'no',
        (string) $plugin['permission_count'],
        (string) $plugin['route_count'],
        (string) $plugin['menu_count'],
        $plugin['reason'] ?? '',
    ], $plugins->status());

    $this->table(
        ['ID', 'Type', 'Enabled', 'Booted', 'Permissions', 'Routes', 'Menus', 'Reason'],
        $rows,
    );
})->purpose('List discovered plugins and their runtime status');

Artisan::command('permissions:list', function (PermissionRegistryInterface $permissions) {
    $rows = array_map(static fn ($permission): array => [
        $permission->key,
        $permission->origin,
        $permission->operation ?? '',
        implode(',', $permission->contexts),
    ], $permissions->all());

    $this->table(
        ['Key', 'Origin', 'Operation', 'Contexts'],
        $rows,
    );
})->purpose('List registered core and plugin permissions');

Artisan::command('roles:list', function (AuthorizationStoreInterface $store) {
    $rows = array_map(static fn (array $role): array => [
        $role['key'],
        $role['label'],
        (string) count($role['permissions']),
        ($role['is_system'] ?? false) ? 'system' : 'custom',
    ], $store->roleRecords());

    $this->table(
        ['Key', 'Label', 'Permissions', 'Source'],
        $rows,
    );
})->purpose('List persisted authorization roles');

Artisan::command('grants:list', function (AuthorizationStoreInterface $store) {
    $rows = array_map(static fn (array $grant): array => [
        $grant['id'],
        $grant['target_type'],
        $grant['target_id'],
        $grant['grant_type'],
        $grant['value'],
        $grant['context_type'],
        $grant['organization_id'] ?? '',
        $grant['scope_id'] ?? '',
    ], $store->grantRecords());

    $this->table(
        ['ID', 'Target Type', 'Target ID', 'Grant Type', 'Value', 'Context', 'Organization', 'Scope'],
        $rows,
    );
})->purpose('List persisted authorization grants');

Artisan::command('audit:list {--limit=20}', function (AuditTrailInterface $audit) {
    $rows = array_map(static fn ($record): array => [
        $record->createdAt,
        $record->eventType,
        $record->outcome,
        $record->originComponent,
        $record->organizationId ?? '',
        $record->targetType ?? '',
        $record->targetId ?? '',
    ], $audit->latest((int) $this->option('limit')));

    $this->table(
        ['When', 'Event', 'Outcome', 'Origin', 'Organization', 'Target Type', 'Target ID'],
        $rows,
    );
})->purpose('List latest audit log records');

Artisan::command('audit:export {--format=jsonl} {--limit=200} {--event_type=} {--outcome=} {--origin_component=} {--principal_id=} {--organization_id=} {--target_type=} {--target_id=} {--execution_origin=} {--created_from=} {--created_to=}', function (AuditTrailInterface $audit) {
    $format = (string) $this->option('format');
    $format = in_array($format, ['jsonl', 'csv'], true) ? $format : 'jsonl';

    $filters = array_filter([
        'event_type' => $this->option('event_type'),
        'outcome' => $this->option('outcome'),
        'origin_component' => $this->option('origin_component'),
        'principal_id' => $this->option('principal_id'),
        'organization_id' => $this->option('organization_id'),
        'target_type' => $this->option('target_type'),
        'target_id' => $this->option('target_id'),
        'execution_origin' => $this->option('execution_origin'),
        'created_from' => $this->option('created_from'),
        'created_to' => $this->option('created_to'),
    ], static fn (mixed $value): bool => is_string($value) && $value !== '');

    $records = $audit->latest((int) $this->option('limit'), $filters);

    if ($format === 'csv') {
        $stream = fopen('php://temp', 'r+');

        fputcsv($stream, [
            'id',
            'created_at',
            'event_type',
            'outcome',
            'origin_component',
            'principal_id',
            'membership_id',
            'organization_id',
            'scope_id',
            'target_type',
            'target_id',
            'execution_origin',
            'summary',
            'correlation',
        ]);

        foreach ($records as $record) {
            fputcsv($stream, [
                $record->id,
                $record->createdAt,
                $record->eventType,
                $record->outcome,
                $record->originComponent,
                $record->principalId ?? '',
                $record->membershipId ?? '',
                $record->organizationId ?? '',
                $record->scopeId ?? '',
                $record->targetType ?? '',
                $record->targetId ?? '',
                $record->executionOrigin ?? '',
                json_encode($record->summary, JSON_UNESCAPED_SLASHES),
                json_encode($record->correlation, JSON_UNESCAPED_SLASHES),
            ]);
        }

        rewind($stream);
        $this->output->write(stream_get_contents($stream) ?: '');
        fclose($stream);

        return 0;
    }

    foreach ($records as $record) {
        $this->line(json_encode($record->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    return 0;
})->purpose('Export audit log records in jsonl or csv format');

Artisan::command('events:list {--limit=20} {--name=} {--origin_component=} {--organization_id=} {--scope_id=}', function (EventBusInterface $events) {
    $rows = array_map(static fn ($event): array => [
        $event->publishedAt ?? '',
        $event->name,
        $event->originComponent,
        $event->organizationId ?? '',
        $event->scopeId ?? '',
    ], $events->latest((int) $this->option('limit'), array_filter([
        'name' => $this->option('name'),
        'origin_component' => $this->option('origin_component'),
        'organization_id' => $this->option('organization_id'),
        'scope_id' => $this->option('scope_id'),
    ], static fn (mixed $value): bool => is_string($value) && $value !== '')));

    $this->table(
        ['When', 'Event', 'Origin', 'Organization', 'Scope'],
        $rows,
    );
})->purpose('List latest public platform and plugin events');

Artisan::command('notifications:list {--limit=20} {--status=} {--type=} {--principal_id=} {--organization_id=} {--scope_id=}', function (NotificationServiceInterface $notifications) {
    $rows = array_map(static fn ($notification): array => [
        $notification->id,
        $notification->status,
        $notification->type,
        $notification->principalId ?? '',
        $notification->organizationId ?? '',
        $notification->scopeId ?? '',
        $notification->title,
    ], $notifications->latest((int) $this->option('limit'), array_filter([
        'status' => $this->option('status'),
        'type' => $this->option('type'),
        'principal_id' => $this->option('principal_id'),
        'organization_id' => $this->option('organization_id'),
        'scope_id' => $this->option('scope_id'),
    ], static fn (mixed $value): bool => is_string($value) && $value !== '')));

    $this->table(
        ['ID', 'Status', 'Type', 'Principal', 'Organization', 'Scope', 'Title'],
        $rows,
    );
})->purpose('List stored notifications and reminder state');

Artisan::command('notifications:dispatch-due', function (NotificationServiceInterface $notifications) {
    $count = $notifications->dispatchDue();

    $this->info(sprintf('Dispatched %d notification(s).', $count));

    return 0;
})->purpose('Dispatch pending notifications whose delivery time is due');

Artisan::command('evidence:queue-reminders {--organization_id=} {--scope_id=}', function (EvidenceManagementRepository $evidence) {
    $count = $evidence->queueDueReminders(
        organizationId: is_string($this->option('organization_id')) && $this->option('organization_id') !== '' ? (string) $this->option('organization_id') : null,
        scopeId: is_string($this->option('scope_id')) && $this->option('scope_id') !== '' ? (string) $this->option('scope_id') : null,
    );

    $this->info(sprintf('Queued %d evidence reminder(s).', $count));

    return 0;
})->purpose('Queue due evidence review and expiry reminders');

Artisan::command('automation:runs {--organization_id=} {--scope_id=} {--pack_id=} {--principal_id=principal-org-a} {--membership_id=} {--trigger=scheduled} {--respect_schedule=0}', function () {
    if (! app()->bound(AutomationPackRuntimeService::class) || ! app()->bound(AutomationCatalogRepository::class)) {
        $this->error('automation-catalog plugin is not enabled.');

        return 1;
    }

    /** @var AutomationPackRuntimeService $runtime */
    $runtime = app(AutomationPackRuntimeService::class);
    /** @var AutomationCatalogRepository $repository */
    $repository = app(AutomationCatalogRepository::class);

    $organizationId = is_string($this->option('organization_id')) && $this->option('organization_id') !== ''
        ? (string) $this->option('organization_id')
        : null;
    $scopeId = is_string($this->option('scope_id')) && $this->option('scope_id') !== ''
        ? (string) $this->option('scope_id')
        : null;
    $packId = is_string($this->option('pack_id')) && $this->option('pack_id') !== ''
        ? (string) $this->option('pack_id')
        : null;
    $principalId = is_string($this->option('principal_id')) && $this->option('principal_id') !== ''
        ? (string) $this->option('principal_id')
        : null;
    $membershipId = is_string($this->option('membership_id')) && $this->option('membership_id') !== ''
        ? (string) $this->option('membership_id')
        : null;
    $trigger = is_string($this->option('trigger')) && in_array((string) $this->option('trigger'), ['manual', 'scheduled'], true)
        ? (string) $this->option('trigger')
        : 'scheduled';
    $respectSchedule = filter_var((string) $this->option('respect_schedule'), FILTER_VALIDATE_BOOL);

    $runs = [];

    if ($packId !== null) {
        $pack = $repository->find($packId);

        if ($pack === null) {
            $this->error(sprintf('Pack [%s] not found.', $packId));

            return 1;
        }

        if ($organizationId !== null && (string) ($pack['organization_id'] ?? '') !== $organizationId) {
            $this->error(sprintf('Pack [%s] does not belong to organization [%s].', $packId, $organizationId));

            return 1;
        }

        if ($scopeId !== null) {
            $packScope = (string) ($pack['scope_id'] ?? '');
            if ($packScope !== '' && $packScope !== $scopeId) {
                $this->error(sprintf('Pack [%s] scope [%s] does not match requested scope [%s].', $packId, $packScope, $scopeId));

                return 1;
            }
        }

        $run = $runtime->runPack($packId, $trigger, $principalId, $membershipId);
        if ($run !== null) {
            $runs[] = $run;
        }
    } else {
        if ($trigger === 'scheduled' && $respectSchedule) {
            $runs = $runtime->runDueScheduledPacks($organizationId, $scopeId, $principalId, $membershipId);
        } else {
            $runs = $runtime->runEnabledPacks($organizationId, $scopeId, $trigger, $principalId, $membershipId);
        }
    }

    if ($runs === []) {
        $this->info('No eligible automation packs were executed.');

        return 0;
    }

    $this->table(
        ['Run ID', 'Pack ID', 'Status', 'Trigger', 'Total', 'OK', 'Fail', 'Skip', 'Started'],
        array_map(static fn (array $run): array => [
            (string) ($run['id'] ?? ''),
            (string) ($run['automation_pack_id'] ?? ''),
            (string) ($run['status'] ?? ''),
            (string) ($run['trigger_mode'] ?? ''),
            (string) ($run['total_mappings'] ?? '0'),
            (string) ($run['success_count'] ?? '0'),
            (string) ($run['failed_count'] ?? '0'),
            (string) ($run['skipped_count'] ?? '0'),
            (string) ($run['started_at'] ?? ''),
        ], $runs),
    );

    $this->info(sprintf('Executed %d automation pack run(s).', count($runs)));

    return 0;
})->purpose('Execute enabled automation pack runtimes and optionally enforce per-pack schedule policy');

Artisan::command('artifacts:list {--limit=20} {--owner_component=} {--subject_type=} {--subject_id=} {--artifact_type=} {--organization_id=} {--scope_id=}', function (ArtifactServiceInterface $artifacts) {
    $rows = array_map(static fn ($artifact): array => [
        $artifact->id,
        $artifact->ownerComponent,
        $artifact->subjectType,
        $artifact->subjectId,
        $artifact->artifactType,
        $artifact->organizationId ?? '',
        $artifact->scopeId ?? '',
        $artifact->originalFilename,
        $artifact->label,
    ], $artifacts->latest((int) $this->option('limit'), array_filter([
        'owner_component' => $this->option('owner_component'),
        'subject_type' => $this->option('subject_type'),
        'subject_id' => $this->option('subject_id'),
        'artifact_type' => $this->option('artifact_type'),
        'organization_id' => $this->option('organization_id'),
        'scope_id' => $this->option('scope_id'),
    ], static fn (mixed $value): bool => is_string($value) && $value !== '')));

    $this->table(
        ['ID', 'Owner', 'Subject Type', 'Subject ID', 'Type', 'Organization', 'Scope', 'Filename', 'Label'],
        $rows,
    );
})->purpose('List stored artifacts and evidence attachments');

Artisan::command('menus:list', function (MenuRegistryInterface $menus) {
    $rows = [];

    foreach ($menus->all() as $menu) {
        $rows[] = [
            $menu['id'],
            $menu['owner'],
            $menu['parent_id'] ?? '',
            $menu['route'] ?? '',
            $menu['permission'] ?? '',
            $menu['area'] ?? 'app',
            (string) $menu['order'],
        ];

        foreach ($menu['children'] as $child) {
            $rows[] = [
                $child['id'],
                $child['owner'],
                $child['parent_id'] ?? '',
                $child['route'] ?? '',
                $child['permission'] ?? '',
                $child['area'] ?? 'app',
                (string) $child['order'],
            ];
        }
    }

    $this->table(
        ['ID', 'Owner', 'Parent', 'Route', 'Permission', 'Area', 'Order'],
        $rows,
    );
})->purpose('List finalized shell menu entries');

Artisan::command('workflows:list', function (WorkflowRegistryInterface $workflows) {
    $rows = array_map(static fn ($workflow): array => [
        $workflow->key,
        $workflow->owner,
        $workflow->initialState,
        (string) count($workflow->states),
        (string) count($workflow->transitions),
    ], $workflows->all());

    $this->table(
        ['Key', 'Owner', 'Initial State', 'States', 'Transitions'],
        $rows,
    );
})->purpose('List registered workflow definitions');

Artisan::command('actors:list {organizationId?} {--scope=} {--principal_id=}', function (FunctionalActorServiceInterface $actors, ?string $organizationId = null) {
    $rows = array_map(static fn ($actor): array => [
        $actor->id,
        $actor->provider,
        $actor->kind,
        $actor->displayName,
        $actor->organizationId,
        $actor->scopeId ?? '',
    ], $actors->actors($organizationId, (string) $this->option('scope')));

    $this->table(
        ['Actor', 'Provider', 'Kind', 'Display Name', 'Organization', 'Scope'],
        $rows,
    );

    $principalId = $this->option('principal_id');

    if (is_string($principalId) && $principalId !== '') {
        $linkRows = array_map(static fn ($link): array => [
            $link->id,
            $link->principalId,
            $link->functionalActorId,
            $link->organizationId,
        ], $actors->linksForPrincipal($principalId, $organizationId));

        $this->newLine();
        $this->table(
            ['Link', 'Principal', 'Actor', 'Organization'],
            $linkRows,
        );
    }
})->purpose('List functional actors and optionally principal linkages');

Artisan::command('actors:assign {actorId} {domainType} {domainId} {assignmentType} {organizationId} {--scope=} {--principal_id=}', function (
    FunctionalActorServiceInterface $actors,
    string $actorId,
    string $domainType,
    string $domainId,
    string $assignmentType,
    string $organizationId
) {
    $assignment = $actors->assignActor(
        actorId: $actorId,
        domainObjectType: $domainType,
        domainObjectId: $domainId,
        assignmentType: $assignmentType,
        organizationId: $organizationId,
        scopeId: $this->option('scope') ?: null,
        assignedByPrincipalId: $this->option('principal_id') ?: null,
    );

    $this->table(
        ['Assignment', 'Actor', 'Type', 'Domain', 'Organization', 'Scope'],
        [[
            $assignment->id,
            $assignment->functionalActorId,
            $assignment->assignmentType,
            sprintf('%s:%s', $assignment->domainObjectType, $assignment->domainObjectId),
            $assignment->organizationId,
            $assignment->scopeId ?? '',
        ]],
    );
})->purpose('Assign a functional actor to a domain object');

Artisan::command('actors:link {principalId} {actorId} {organizationId} {--linked_by=}', function (
    FunctionalActorServiceInterface $actors,
    string $principalId,
    string $actorId,
    string $organizationId
) {
    $link = $actors->linkPrincipal(
        principalId: $principalId,
        actorId: $actorId,
        organizationId: $organizationId,
        linkedByPrincipalId: $this->option('linked_by') ?: null,
    );

    $this->table(
        ['Link', 'Principal', 'Actor', 'Organization'],
        [[
            $link->id,
            $link->principalId,
            $link->functionalActorId,
            $link->organizationId,
        ]],
    );
})->purpose('Link a platform principal to a functional actor');

Artisan::command('tenancy:list {principalId?}', function (TenancyServiceInterface $tenancy, ?string $principalId = null) {
    $organizations = is_string($principalId) && $principalId !== ''
        ? $tenancy->organizationsForPrincipal($principalId)
        : $tenancy->organizations();

    $rows = array_map(function ($organization) use ($tenancy, $principalId): array {
        $memberships = is_string($principalId) && $principalId !== ''
            ? $tenancy->membershipsForPrincipal($principalId, $organization->id)
            : [];

        $scopes = $tenancy->scopesForOrganization($organization->id, $memberships);

        return [
            $organization->id,
            $organization->name,
            $organization->defaultLocale,
            $organization->defaultTimezone,
            (string) count($scopes),
            (string) count($memberships),
        ];
    }, $organizations);

    $this->table(
        ['Organization', 'Name', 'Locale', 'Timezone', 'Scopes', 'Memberships'],
        $rows,
    );
})->purpose('List organizations and scopes, optionally filtered by principal');

Artisan::command('tenancy:archive-organization {organizationId}', function (
    TenancyServiceInterface $tenancy,
    AuditTrailInterface $audit,
    string $organizationId
) {
    if (! $tenancy->archiveOrganization($organizationId)) {
        $audit->record(new AuditRecordData(
            eventType: 'core.tenancy.organization.archived',
            outcome: 'failure',
            originComponent: 'core',
            targetType: 'organization',
            targetId: $organizationId,
            organizationId: $organizationId,
            summary: [
                'operation' => 'archive',
                'reason' => 'state_change_rejected',
                'command' => 'tenancy:archive-organization',
            ],
            executionOrigin: 'artisan',
        ));

        $this->error(sprintf('Organization [%s] was not archived.', $organizationId));

        return 1;
    }

    $this->info(sprintf('Organization [%s] archived.', $organizationId));

    return 0;
})->purpose('Archive an organization through the core tenancy service');

Artisan::command('tenancy:activate-organization {organizationId}', function (
    TenancyServiceInterface $tenancy,
    AuditTrailInterface $audit,
    string $organizationId
) {
    if (! $tenancy->activateOrganization($organizationId)) {
        $audit->record(new AuditRecordData(
            eventType: 'core.tenancy.organization.activated',
            outcome: 'failure',
            originComponent: 'core',
            targetType: 'organization',
            targetId: $organizationId,
            organizationId: $organizationId,
            summary: [
                'operation' => 'activate',
                'reason' => 'state_change_rejected',
                'command' => 'tenancy:activate-organization',
            ],
            executionOrigin: 'artisan',
        ));

        $this->error(sprintf('Organization [%s] was not activated.', $organizationId));

        return 1;
    }

    $this->info(sprintf('Organization [%s] activated.', $organizationId));

    return 0;
})->purpose('Reactivate an organization through the core tenancy service');

Artisan::command('tenancy:archive-scope {scopeId}', function (
    TenancyServiceInterface $tenancy,
    AuditTrailInterface $audit,
    string $scopeId
) {
    if (! $tenancy->archiveScope($scopeId)) {
        $audit->record(new AuditRecordData(
            eventType: 'core.tenancy.scope.archived',
            outcome: 'failure',
            originComponent: 'core',
            targetType: 'scope',
            targetId: $scopeId,
            summary: [
                'operation' => 'archive',
                'reason' => 'state_change_rejected',
                'command' => 'tenancy:archive-scope',
            ],
            executionOrigin: 'artisan',
        ));

        $this->error(sprintf('Scope [%s] was not archived.', $scopeId));

        return 1;
    }

    $this->info(sprintf('Scope [%s] archived.', $scopeId));

    return 0;
})->purpose('Archive a scope through the core tenancy service');

Artisan::command('tenancy:activate-scope {scopeId}', function (
    TenancyServiceInterface $tenancy,
    AuditTrailInterface $audit,
    string $scopeId
) {
    if (! $tenancy->activateScope($scopeId)) {
        $audit->record(new AuditRecordData(
            eventType: 'core.tenancy.scope.activated',
            outcome: 'failure',
            originComponent: 'core',
            targetType: 'scope',
            targetId: $scopeId,
            summary: [
                'operation' => 'activate',
                'reason' => 'state_change_rejected',
                'command' => 'tenancy:activate-scope',
            ],
            executionOrigin: 'artisan',
        ));

        $this->error(sprintf('Scope [%s] was not activated.', $scopeId));

        return 1;
    }

    $this->info(sprintf('Scope [%s] activated.', $scopeId));

    return 0;
})->purpose('Reactivate a scope through the core tenancy service');

Artisan::command('plugins:enable {pluginId}', function (
    string $pluginId,
    PluginManagerInterface $plugins,
    PluginLifecycleManager $lifecycle,
    PluginStateStore $state,
    AuditTrailInterface $audit
) {
    $plugin = collect($plugins->status())->firstWhere('id', $pluginId);

    if (! is_array($plugin)) {
        $audit->record(new AuditRecordData(
            eventType: 'core.plugins.enable',
            outcome: 'failure',
            originComponent: 'core',
            targetType: 'plugin',
            targetId: $pluginId,
            summary: [
                'reason' => 'unknown_plugin',
                'command' => 'plugins:enable',
            ],
            executionOrigin: 'artisan',
        ));

        $this->error(sprintf('Unknown plugin [%s].', $pluginId));

        return 1;
    }

    $result = $lifecycle->enable($pluginId);

    if ($result->ok) {
        $this->info($result->message);
    } else {
        $this->error($result->message);
    }

    $audit->record(new AuditRecordData(
        eventType: 'core.plugins.enable',
        outcome: $result->ok ? 'success' : 'failure',
        originComponent: 'core',
        targetType: 'plugin',
        targetId: $pluginId,
        summary: [
            'command' => 'plugins:enable',
            'reason' => $result->reason,
            'effective_before' => $result->effectiveBefore,
            'effective_after' => $result->effectiveAfter,
            ...$result->details,
        ],
        executionOrigin: 'artisan',
    ));

    $this->line(sprintf('State file: %s', $state->path()));
    $this->line(sprintf(
        'Effective enabled plugins: %s',
        $result->effectiveAfter === [] ? '(none)' : implode(', ', $result->effectiveAfter),
    ));

    return $result->ok ? 0 : 1;
})->purpose('Persist a local override to enable a discovered plugin');

Artisan::command('plugins:disable {pluginId}', function (
    string $pluginId,
    PluginManagerInterface $plugins,
    PluginLifecycleManager $lifecycle,
    PluginStateStore $state,
    AuditTrailInterface $audit
) {
    $plugin = collect($plugins->status())->firstWhere('id', $pluginId);

    if (! is_array($plugin)) {
        $audit->record(new AuditRecordData(
            eventType: 'core.plugins.disable',
            outcome: 'failure',
            originComponent: 'core',
            targetType: 'plugin',
            targetId: $pluginId,
            summary: [
                'reason' => 'unknown_plugin',
                'command' => 'plugins:disable',
            ],
            executionOrigin: 'artisan',
        ));

        $this->error(sprintf('Unknown plugin [%s].', $pluginId));

        return 1;
    }

    $result = $lifecycle->disable($pluginId);

    if ($result->ok) {
        $this->info($result->message);
    } else {
        $this->error($result->message);
    }

    $audit->record(new AuditRecordData(
        eventType: 'core.plugins.disable',
        outcome: $result->ok ? 'success' : 'failure',
        originComponent: 'core',
        targetType: 'plugin',
        targetId: $pluginId,
        summary: [
            'command' => 'plugins:disable',
            'reason' => $result->reason,
            'effective_before' => $result->effectiveBefore,
            'effective_after' => $result->effectiveAfter,
            ...$result->details,
        ],
        executionOrigin: 'artisan',
    ));

    $this->line(sprintf('State file: %s', $state->path()));
    $this->line(sprintf(
        'Effective enabled plugins: %s',
        $result->effectiveAfter === [] ? '(none)' : implode(', ', $result->effectiveAfter),
    ));

    return $result->ok ? 0 : 1;
})->purpose('Persist a local override to disable a discovered plugin');
