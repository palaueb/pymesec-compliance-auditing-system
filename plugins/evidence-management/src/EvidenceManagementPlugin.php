<?php

namespace PymeSec\Plugins\EvidenceManagement;

use PymeSec\Core\Permissions\AuthorizationContext;
use PymeSec\Core\Permissions\Contracts\AuthorizationServiceInterface;
use PymeSec\Core\Plugins\Contracts\PluginInterface;
use PymeSec\Core\Plugins\PluginContext;
use PymeSec\Core\Tenancy\Contracts\TenancyServiceInterface;
use PymeSec\Core\UI\ScreenDefinition;
use PymeSec\Core\UI\ScreenRenderContext;
use PymeSec\Core\UI\ToolbarAction;

class EvidenceManagementPlugin implements PluginInterface
{
    public function register(PluginContext $context): void
    {
        $context->app()->singleton(EvidenceManagementRepository::class, fn ($app) => new EvidenceManagementRepository(
            artifacts: $app->make(\PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface::class),
            audit: $app->make(\PymeSec\Core\Audit\Contracts\AuditTrailInterface::class),
            events: $app->make(\PymeSec\Core\Events\Contracts\EventBusInterface::class),
            notifications: $app->make(\PymeSec\Core\Notifications\Contracts\NotificationServiceInterface::class),
        ));

        $context->registerScreen(new ScreenDefinition(
            menuId: 'plugin.evidence-management.root',
            owner: 'evidence-management',
            titleKey: 'plugin.evidence-management.screen.root.title',
            subtitleKey: 'plugin.evidence-management.screen.root.subtitle',
            viewPath: $context->path('resources/views/index.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->screenData($context, $screenContext),
            toolbarResolver: function (ScreenRenderContext $screenContext): array {
                $query = $this->baseQuery($screenContext);
                unset($query['evidence_id']);

                if (is_string($screenContext->query['evidence_id'] ?? null) && ($screenContext->query['evidence_id'] ?? '') !== '') {
                    return [
                        new ToolbarAction(
                            label: 'Back to evidence',
                            url: route('core.shell.index', [...$query, 'menu' => 'plugin.evidence-management.root']),
                            variant: 'secondary',
                        ),
                    ];
                }

                return [
                    new ToolbarAction(
                        label: 'New evidence record',
                        url: '#evidence-editor',
                        variant: 'primary',
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
    private function screenData(PluginContext $context, ScreenRenderContext $screenContext): array
    {
        $repository = $context->app()->make(EvidenceManagementRepository::class);
        $authorization = $context->app()->make(AuthorizationServiceInterface::class);
        $tenancy = $context->app()->make(TenancyServiceInterface::class);
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $records = $repository->all($organizationId, $screenContext->scopeId);
        $canManage = $screenContext->principal !== null && $authorization->authorize(new AuthorizationContext(
            principal: $screenContext->principal,
            permission: 'plugin.evidence-management.evidence.manage',
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

        $evidenceRows = array_map(function (array $record) use ($screenContext): array {
            return [
                ...$record,
                'open_url' => route('core.shell.index', [...$this->baseQuery($screenContext), 'menu' => 'plugin.evidence-management.root', 'evidence_id' => $record['id']]),
                'link_rows' => array_map(fn (array $link): array => [
                    ...$link,
                    'open_url' => $this->domainObjectShellUrl($screenContext, $link['domain_type'], $link['domain_id'], $link['organization_id'], $link['scope_id'] !== '' ? $link['scope_id'] : null),
                ], $record['links']),
            ];
        }, $records);

        $selectedEvidenceId = is_string($screenContext->query['evidence_id'] ?? null) && $screenContext->query['evidence_id'] !== ''
            ? (string) $screenContext->query['evidence_id']
            : null;
        $selectedEvidence = null;

        if (is_string($selectedEvidenceId)) {
            foreach ($evidenceRows as $row) {
                if (($row['id'] ?? null) === $selectedEvidenceId) {
                    $selectedEvidence = $row;
                    break;
                }
            }
        }

        if (is_array($selectedEvidence)) {
            if (is_array($selectedEvidence['source'] ?? null)) {
                $source = $selectedEvidence['source'];
                $selectedEvidence['source_open_url'] = is_string($source['domain_type'] ?? null) && is_string($source['domain_id'] ?? null)
                    ? $this->domainObjectShellUrl(
                        $screenContext,
                        (string) $source['domain_type'],
                        (string) $source['domain_id'],
                        $organizationId,
                        is_string($source['scope_id'] ?? null) && $source['scope_id'] !== '' ? (string) $source['scope_id'] : null,
                    )
                    : null;
            }

            $selectedEvidence['download_url'] = is_array($selectedEvidence['artifact'] ?? null) && ($selectedEvidence['artifact']['exists'] ?? false)
                ? route('plugin.evidence-management.download', ['evidenceId' => $selectedEvidence['id']])
                : null;
            $selectedEvidence['preview_url'] = is_array($selectedEvidence['artifact'] ?? null)
                && ($selectedEvidence['artifact']['exists'] ?? false)
                && ($selectedEvidence['artifact']['previewable'] ?? false)
                ? route('plugin.evidence-management.preview', ['evidenceId' => $selectedEvidence['id']])
                : null;
            $selectedEvidence['queue_review_reminder_route'] = route('plugin.evidence-management.reminders.queue', [
                'evidenceId' => $selectedEvidence['id'],
                'type' => 'review-due',
            ]);
            $selectedEvidence['queue_expiry_reminder_route'] = route('plugin.evidence-management.reminders.queue', [
                'evidenceId' => $selectedEvidence['id'],
                'type' => 'expiry-soon',
            ]);
        }

        $listQuery = $this->baseQuery($screenContext);
        unset($listQuery['evidence_id']);

        return [
            'records' => $evidenceRows,
            'selected_evidence' => $selectedEvidence,
            'promotion_candidates' => array_map(function (array $artifact) use ($screenContext): array {
                $source = $artifact['source'] ?? null;
                $sourceOpenUrl = null;

                if (is_array($source) && is_string($source['domain_type'] ?? null) && is_string($source['domain_id'] ?? null)) {
                    $sourceOpenUrl = $this->domainObjectShellUrl(
                        $screenContext,
                        (string) $source['domain_type'],
                        (string) $source['domain_id'],
                        $screenContext->organizationId ?? 'org-a',
                        is_string($source['scope_id'] ?? null) && $source['scope_id'] !== '' ? (string) $source['scope_id'] : null,
                    );
                }

                return [
                    ...$artifact,
                    'promote_route' => route('plugin.evidence-management.promote', ['artifactId' => $artifact['id']]),
                    'source_open_url' => $sourceOpenUrl,
                ];
            }, $repository->promotionCandidates($organizationId, $screenContext->scopeId)),
            'review_queue' => array_map(function (array $record) use ($screenContext): array {
                return [
                    ...$record,
                    'open_url' => route('core.shell.index', [...$this->baseQuery($screenContext), 'menu' => 'plugin.evidence-management.root', 'evidence_id' => $record['id']]),
                ];
            }, $repository->reviewQueue($organizationId, $screenContext->scopeId)),
            'can_manage_evidence' => $canManage,
            'query' => $this->baseQuery($screenContext),
            'list_query' => $listQuery,
            'create_route' => route('plugin.evidence-management.store'),
            'evidence_list_url' => route('core.shell.index', [...$listQuery, 'menu' => 'plugin.evidence-management.root']),
            'scope_options' => array_map(static fn ($scope): array => $scope->toArray(), $scopeContext->scopes),
            'artifact_options' => $repository->artifactOptions($organizationId, $screenContext->scopeId),
            'link_option_groups' => $repository->linkOptions($organizationId, $screenContext->scopeId),
            'kind_options' => [
                'document' => 'Document',
                'workpaper' => 'Workpaper',
                'snapshot' => 'System snapshot',
                'report' => 'Report',
                'ticket' => 'Ticket',
                'log-export' => 'Log export',
                'statement' => 'Statement',
                'other' => 'Other',
            ],
            'status_options' => [
                'draft' => 'Draft',
                'active' => 'Active',
                'approved' => 'Approved',
                'expired' => 'Expired',
                'superseded' => 'Superseded',
            ],
            'metrics' => [
                'records' => count($evidenceRows),
                'approved' => collect($evidenceRows)->where('status', 'approved')->count(),
                'expiring' => collect($evidenceRows)->filter(static function (array $record): bool {
                    if (($record['valid_until'] ?? '') === '') {
                        return false;
                    }

                    return $record['status'] !== 'expired' && $record['valid_until'] <= now()->addDays(30)->toDateString();
                })->count(),
                'review_due' => collect($evidenceRows)->filter(static function (array $record): bool {
                    if (($record['review_due_on'] ?? '') === '') {
                        return false;
                    }

                    return $record['review_due_on'] <= now()->addDays(30)->toDateString();
                })->count(),
                'needs_validation' => collect($evidenceRows)->filter(static function (array $record): bool {
                    return in_array($record['status'], ['active', 'approved'], true) && ($record['validated_at'] ?? '') === '';
                })->count(),
                'linked' => collect($evidenceRows)->filter(static fn (array $record): bool => count($record['links']) > 0)->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function baseQuery(ScreenRenderContext $screenContext): array
    {
        $query = [
            'organization_id' => $screenContext->organizationId,
            'scope_id' => $screenContext->scopeId,
            'locale' => $screenContext->locale,
        ];

        foreach ($screenContext->memberships as $membership) {
            $query['membership_ids'][] = $membership->id;
        }

        return array_filter($query, static fn ($value): bool => $value !== null && $value !== '');
    }

    private function domainObjectShellUrl(
        ScreenRenderContext $screenContext,
        string $domainType,
        string $domainId,
        string $organizationId,
        ?string $scopeId,
    ): ?string {
        $query = $this->baseQuery($screenContext);
        $query['organization_id'] = $organizationId;

        if ($scopeId !== null && $scopeId !== '') {
            $query['scope_id'] = $scopeId;
        } else {
            unset($query['scope_id']);
        }

        return match ($domainType) {
            'asset' => route('core.shell.index', [...$query, 'menu' => 'plugin.asset-catalog.root', 'asset_id' => $domainId]),
            'control' => route('core.shell.index', [...$query, 'menu' => 'plugin.controls-catalog.catalog', 'control_id' => $domainId]),
            'risk' => route('core.shell.index', [...$query, 'menu' => 'plugin.risk-management.root', 'risk_id' => $domainId]),
            'finding' => route('core.shell.index', [...$query, 'menu' => 'plugin.findings-remediation.root', 'finding_id' => $domainId]),
            'policy' => route('core.shell.index', [...$query, 'menu' => 'plugin.policy-exceptions.root', 'policy_id' => $domainId]),
            'policy-exception' => route('core.shell.index', [...$query, 'menu' => 'plugin.policy-exceptions.exceptions', 'exception_id' => $domainId]),
            'data-flow' => route('core.shell.index', [...$query, 'menu' => 'plugin.data-flows-privacy.root', 'flow_id' => $domainId]),
            'processing-activity' => route('core.shell.index', [...$query, 'menu' => 'plugin.data-flows-privacy.activities', 'activity_id' => $domainId]),
            'continuity-service' => route('core.shell.index', [...$query, 'menu' => 'plugin.continuity-bcm.root', 'service_id' => $domainId]),
            'recovery-plan' => route('core.shell.index', [...$query, 'menu' => 'plugin.continuity-bcm.plans', 'plan_id' => $domainId]),
            'assessment' => route('core.shell.index', [...$query, 'menu' => 'plugin.assessments-audits.root', 'assessment_id' => $domainId]),
            default => null,
        };
    }
}
