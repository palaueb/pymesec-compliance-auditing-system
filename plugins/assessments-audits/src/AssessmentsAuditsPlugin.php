<?php

namespace PymeSec\Plugins\AssessmentsAudits;

use PymeSec\Core\Permissions\AuthorizationContext;
use PymeSec\Core\Permissions\Contracts\AuthorizationServiceInterface;
use PymeSec\Core\Plugins\Contracts\PluginInterface;
use PymeSec\Core\Plugins\PluginContext;
use PymeSec\Core\Tenancy\Contracts\TenancyServiceInterface;
use PymeSec\Core\UI\ScreenDefinition;
use PymeSec\Core\UI\ScreenRenderContext;
use PymeSec\Core\UI\ToolbarAction;
use PymeSec\Plugins\ControlsCatalog\ControlsCatalogRepository;

class AssessmentsAuditsPlugin implements PluginInterface
{
    public function register(PluginContext $context): void
    {
        $context->app()->singleton(AssessmentsAuditsRepository::class, fn () => new AssessmentsAuditsRepository());

        $context->registerScreen(new ScreenDefinition(
            menuId: 'plugin.assessments-audits.root',
            owner: 'assessments-audits',
            titleKey: 'plugin.assessments-audits.screen.root.title',
            subtitleKey: 'plugin.assessments-audits.screen.root.subtitle',
            viewPath: $context->path('resources/views/index.blade.php'),
            dataResolver: fn (ScreenRenderContext $screenContext): array => $this->screenData($context, $screenContext),
            toolbarResolver: function (ScreenRenderContext $screenContext): array {
                $query = $this->baseQuery($screenContext);
                unset($query['assessment_id']);

                if (is_string($screenContext->query['assessment_id'] ?? null) && ($screenContext->query['assessment_id'] ?? '') !== '') {
                    return [
                        new ToolbarAction(
                            label: 'Back to assessments',
                            url: route('core.shell.index', [...$query, 'menu' => 'plugin.assessments-audits.root']),
                            variant: 'secondary',
                        ),
                    ];
                }

                return [
                    new ToolbarAction(
                        label: 'New assessment',
                        url: '#assessment-editor',
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
        $repository = $context->app()->make(AssessmentsAuditsRepository::class);
        $controls = $context->app()->make(ControlsCatalogRepository::class);
        $authorization = $context->app()->make(AuthorizationServiceInterface::class);
        $tenancy = $context->app()->make(TenancyServiceInterface::class);
        $organizationId = $screenContext->organizationId ?? 'org-a';
        $campaigns = $repository->all($organizationId, $screenContext->scopeId);
        $frameworkOptions = $repository->frameworkOptions($organizationId);
        $canManage = $screenContext->principal !== null && $authorization->authorize(new AuthorizationContext(
            principal: $screenContext->principal,
            permission: 'plugin.assessments-audits.assessments.manage',
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

        $campaignRows = array_map(function (array $campaign) use ($controls, $frameworkOptions, $repository, $screenContext, $scopeContext): array {
                $scopeName = 'Organization-wide';

                foreach ($scopeContext->scopes as $scope) {
                    if ($scope->id === $campaign['scope_id']) {
                        $scopeName = $scope->name;
                        break;
                    }
                }

                $frameworkName = 'Any framework';

                foreach ($frameworkOptions as $framework) {
                    if ($framework['id'] === $campaign['framework_id']) {
                        $frameworkName = $framework['label'];
                        break;
                    }
                }

                $requirementsByControl = $controls->requirementsForControls(
                    array_map(static fn (array $control): string => $control['id'], $campaign['controls']),
                );
                $reviews = array_map(function (array $review) use ($campaign, $requirementsByControl): array {
                    return [
                        ...$review,
                        'requirements' => $requirementsByControl[$review['control_id']] ?? [],
                        'review_update_route' => route('plugin.assessments-audits.reviews.update', [
                            'assessmentId' => $campaign['id'],
                            'controlId' => $review['control_id'],
                        ]),
                        'artifact_upload_route' => route('plugin.assessments-audits.reviews.artifacts.store', [
                            'assessmentId' => $campaign['id'],
                            'controlId' => $review['control_id'],
                        ]),
                        'finding_store_route' => route('plugin.assessments-audits.reviews.findings.store', [
                            'assessmentId' => $campaign['id'],
                            'controlId' => $review['control_id'],
                        ]),
                    ];
                }, $repository->reviews($campaign['id']));

                return [
                    ...$campaign,
                    'open_url' => route('core.shell.index', [...$this->baseQuery($screenContext), 'menu' => 'plugin.assessments-audits.root', 'assessment_id' => $campaign['id']]),
                    'scope_name' => $scopeName,
                    'framework_name' => $frameworkName,
                    'reviews' => $reviews,
                    'update_route' => route('plugin.assessments-audits.update', ['assessmentId' => $campaign['id']]),
                    'report_route' => route('plugin.assessments-audits.report', [
                        'assessmentId' => $campaign['id'],
                        ...$this->baseQuery($screenContext),
                    ]),
                ];
            }, $campaigns);
        $selectedAssessmentId = is_string($screenContext->query['assessment_id'] ?? null) && $screenContext->query['assessment_id'] !== ''
            ? (string) $screenContext->query['assessment_id']
            : null;
        $selectedAssessment = null;

        if (is_string($selectedAssessmentId)) {
            foreach ($campaignRows as $campaign) {
                if (($campaign['id'] ?? null) === $selectedAssessmentId) {
                    $selectedAssessment = $campaign;
                    break;
                }
            }
        }

        $listQuery = $this->baseQuery($screenContext);
        unset($listQuery['assessment_id']);

        return [
            'campaigns' => $campaignRows,
            'selected_assessment' => $selectedAssessment,
            'can_manage_assessments' => $canManage,
            'query' => $this->baseQuery($screenContext),
            'list_query' => $listQuery,
            'create_route' => route('plugin.assessments-audits.store'),
            'framework_options' => $frameworkOptions,
            'control_options' => $repository->controlOptions($organizationId, $screenContext->scopeId),
            'scope_options' => array_map(static fn ($scope): array => $scope->toArray(), $scopeContext->scopes),
            'result_options' => [
                'not-tested' => 'Not tested',
                'pass' => 'Pass',
                'partial' => 'Partial',
                'fail' => 'Fail',
                'not-applicable' => 'Not applicable',
            ],
            'status_options' => [
                'draft' => 'Draft',
                'active' => 'Active',
                'closed' => 'Closed',
            ],
            'assessments_list_url' => route('core.shell.index', [...$listQuery, 'menu' => 'plugin.assessments-audits.root']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function baseQuery(ScreenRenderContext $screenContext): array
    {
        $query = [
            'principal_id' => $screenContext->principal?->id,
            'organization_id' => $screenContext->organizationId,
            'scope_id' => $screenContext->scopeId,
            'locale' => $screenContext->locale,
        ];

        foreach ($screenContext->memberships as $membership) {
            $query['membership_ids'][] = $membership->id;
        }

        return array_filter($query, static fn ($value): bool => $value !== null && $value !== '');
    }
}
