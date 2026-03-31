<?php

namespace PymeSec\Core\Reporting;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PymeSec\Plugins\AssessmentsAudits\AssessmentReferenceData;
use PymeSec\Plugins\FindingsRemediation\FindingsReferenceData;

class ManagementReportingService
{
    public function __construct(
        private readonly WorkspaceReportingContext $workspaceContext,
        private readonly ReportingMetricCatalog $metricCatalog,
    ) {}

    /**
     * @param  array<string, mixed>  $baseQuery
     * @return array<string, mixed>
     */
    public function build(?string $principalId, ?string $organizationId, ?string $scopeId, array $baseQuery): array
    {
        if (! is_string($organizationId) || $organizationId === '') {
            return [
                'headline_metrics' => [],
                'assessments' => $this->emptySection(),
                'evidence' => $this->emptySection(),
                'risks' => $this->emptySection(),
                'findings' => $this->emptySection(),
            ];
        }

        $scopeLabels = $this->workspaceContext->scopeLabels($organizationId);
        $visibleAssessmentIds = $this->workspaceContext->visibleObjectIds($principalId, $organizationId, $scopeId, 'assessment');
        $visibleRiskIds = $this->workspaceContext->visibleObjectIds($principalId, $organizationId, $scopeId, 'risk');
        $visibleFindingIds = $this->workspaceContext->visibleObjectIds($principalId, $organizationId, $scopeId, 'finding');

        $assessments = $this->assessmentSection($organizationId, $scopeId, $visibleAssessmentIds, $scopeLabels, $baseQuery);
        $evidence = $this->evidenceSection($organizationId, $scopeId, $scopeLabels, $baseQuery);
        $risks = $this->riskSection($organizationId, $scopeId, $visibleRiskIds, $scopeLabels, $baseQuery);
        $findings = $this->findingSection($organizationId, $scopeId, $visibleFindingIds, $scopeLabels, $baseQuery);

        return [
            'headline_metrics' => [
                $this->metricCatalog->headline('active_assessments', $assessments['metrics']['active'] ?? 0),
                $this->metricCatalog->headline('failing_reviews', $assessments['metrics']['failing_reviews'] ?? 0),
                $this->metricCatalog->headline('evidence_review_due', $evidence['metrics']['review_due'] ?? 0),
                $this->metricCatalog->headline('risks_in_workflow', $risks['metrics']['in_workflow'] ?? 0),
                $this->metricCatalog->headline('overdue_findings', $findings['metrics']['overdue'] ?? 0),
            ],
            'assessments' => $assessments,
            'evidence' => $evidence,
            'risks' => $risks,
            'findings' => $findings,
        ];
    }

    /**
     * @param  array<int, string>|null  $visibleIds
     * @param  array<string, string>  $scopeLabels
     * @param  array<string, mixed>  $baseQuery
     * @return array<string, mixed>
     */
    private function assessmentSection(
        string $organizationId,
        ?string $scopeId,
        ?array $visibleIds,
        array $scopeLabels,
        array $baseQuery,
    ): array {
        if (! Schema::hasTable('assessment_campaigns')) {
            return $this->emptySection();
        }

        $campaigns = $this->workspaceContext
            ->scopedQuery('assessment_campaigns', $organizationId, $scopeId, true, $visibleIds)
            ->orderByDesc('starts_on')
            ->orderBy('title')
            ->get([
                'id',
                'scope_id',
                'title',
                'status',
                'starts_on',
                'ends_on',
            ]);

        $campaignIds = $campaigns->pluck('id')->map(static fn ($id): string => (string) $id)->all();
        $reviewCountsByAssessment = [];
        $linkedFindings = 0;
        $failingReviews = 0;
        $resultBreakdown = [];

        foreach (AssessmentReferenceData::reviewResults() as $result => $label) {
            $resultBreakdown[] = [
                'id' => $result,
                'label' => $label,
                'count' => 0,
            ];
        }

        $resultIndex = [];

        foreach ($resultBreakdown as $index => $row) {
            $resultIndex[$row['id']] = $index;
        }

        if ($campaignIds !== [] && Schema::hasTable('assessment_control_reviews')) {
            foreach (DB::table('assessment_control_reviews')
                ->whereIn('assessment_id', $campaignIds)
                ->get(['assessment_id', 'result', 'linked_finding_id']) as $review) {
                $assessmentId = (string) $review->assessment_id;
                $result = is_string($review->result ?? null) ? (string) $review->result : 'not-tested';

                if (! isset($reviewCountsByAssessment[$assessmentId])) {
                    $reviewCountsByAssessment[$assessmentId] = [
                        'pass' => 0,
                        'partial' => 0,
                        'fail' => 0,
                        'not-tested' => 0,
                        'linked_findings' => 0,
                    ];
                }

                if (! isset($reviewCountsByAssessment[$assessmentId][$result])) {
                    $reviewCountsByAssessment[$assessmentId][$result] = 0;
                }

                $reviewCountsByAssessment[$assessmentId][$result]++;

                if (isset($resultIndex[$result])) {
                    $rowIndex = $resultIndex[$result];
                    $resultBreakdown[$rowIndex]['count']++;
                }

                if ($result === 'fail') {
                    $failingReviews++;
                }

                if (is_string($review->linked_finding_id ?? null) && $review->linked_finding_id !== '') {
                    $linkedFindings++;
                    $reviewCountsByAssessment[$assessmentId]['linked_findings']++;
                }
            }
        }

        $statusBreakdown = [];

        foreach (AssessmentReferenceData::statuses() as $status => $label) {
            $statusBreakdown[] = [
                'id' => $status,
                'label' => $label,
                'count' => (int) $campaigns->where('status', $status)->count(),
            ];
        }

        $campaignRows = [];

        foreach ($campaigns->take(5) as $campaign) {
            $campaignId = (string) $campaign->id;
            $summary = $reviewCountsByAssessment[$campaignId] ?? [
                'pass' => 0,
                'partial' => 0,
                'fail' => 0,
                'not-tested' => 0,
                'linked_findings' => 0,
            ];

            $campaignRows[] = [
                'title' => (string) $campaign->title,
                'scope_label' => $this->scopeLabel(is_string($campaign->scope_id ?? null) ? $campaign->scope_id : null, $scopeLabels),
                'status_label' => AssessmentReferenceData::statusLabel((string) $campaign->status),
                'starts_on' => (string) $campaign->starts_on,
                'ends_on' => (string) $campaign->ends_on,
                'pass_count' => (int) ($summary['pass'] ?? 0),
                'partial_count' => (int) ($summary['partial'] ?? 0),
                'fail_count' => (int) ($summary['fail'] ?? 0),
                'linked_findings' => (int) ($summary['linked_findings'] ?? 0),
                'open_url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.assessments-audits.root', 'assessment_id' => $campaignId]),
            ];
        }

        return [
            'metrics' => [
                'campaigns' => $campaigns->count(),
                'active' => $campaigns->where('status', 'active')->count(),
                'closed' => $campaigns->where('status', 'closed')->count(),
                'failing_reviews' => $failingReviews,
                'linked_findings' => $linkedFindings,
            ],
            'summary_metrics' => [
                $this->metricCatalog->summary('campaigns', $campaigns->count()),
                $this->metricCatalog->summary('active_assessments', $campaigns->where('status', 'active')->count()),
                $this->metricCatalog->summary('failing_reviews', $failingReviews),
                $this->metricCatalog->summary('linked_findings', $linkedFindings),
            ],
            'breakdowns' => [
                $this->breakdown('Campaign status', $statusBreakdown),
                $this->breakdown('Review results', $resultBreakdown),
            ],
            'attention' => [
                'title' => 'Latest campaigns',
                'copy' => 'Open the campaigns carrying the most current review load from this workspace.',
            ],
            'rows' => $campaignRows,
            'empty_copy' => 'No assessment campaigns are visible in the current workspace context.',
            'section_url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.assessments-audits.root']),
        ];
    }

    /**
     * @param  array<string, string>  $scopeLabels
     * @param  array<string, mixed>  $baseQuery
     * @return array<string, mixed>
     */
    private function evidenceSection(
        string $organizationId,
        ?string $scopeId,
        array $scopeLabels,
        array $baseQuery,
    ): array {
        if (! Schema::hasTable('evidence_records')) {
            return $this->emptySection();
        }

        $today = now()->toDateString();
        $windowEnd = now()->addDays(30)->toDateString();
        $records = $this->workspaceContext
            ->scopedQuery('evidence_records', $organizationId, $scopeId, true)
            ->orderBy('review_due_on')
            ->orderBy('valid_until')
            ->orderByDesc('updated_at')
            ->get([
                'id',
                'scope_id',
                'title',
                'status',
                'review_due_on',
                'valid_until',
                'validated_at',
            ]);

        $statusBreakdown = [];

        foreach ($records->pluck('status')->filter(static fn ($status): bool => is_string($status) && $status !== '')->unique()->sort()->values()->all() as $status) {
            $statusBreakdown[] = [
                'id' => $status,
                'label' => $this->humanize($status),
                'count' => (int) $records->where('status', $status)->count(),
            ];
        }

        $attentionRows = [];

        foreach ($records as $record) {
            $reviewDueOn = is_string($record->review_due_on ?? null) ? $record->review_due_on : '';
            $validUntil = is_string($record->valid_until ?? null) ? $record->valid_until : '';
            $reason = null;
            $sortDate = $reviewDueOn !== '' ? $reviewDueOn : $validUntil;

            if ($reviewDueOn !== '' && $reviewDueOn <= $today) {
                $reason = 'Review overdue';
            } elseif ($reviewDueOn !== '' && $reviewDueOn <= $windowEnd) {
                $reason = 'Review due soon';
            } elseif ($validUntil !== '' && $validUntil < $today) {
                $reason = 'Expired';
            } elseif ($validUntil !== '' && $validUntil <= $windowEnd) {
                $reason = 'Expiry approaching';
            }

            if ($reason === null) {
                continue;
            }

            $attentionRows[] = [
                'title' => (string) $record->title,
                'scope_label' => $this->scopeLabel(is_string($record->scope_id ?? null) ? $record->scope_id : null, $scopeLabels),
                'status_label' => $this->humanize((string) $record->status),
                'review_due_on' => $reviewDueOn,
                'valid_until' => $validUntil,
                'attention_reason' => $reason,
                'sort_date' => $sortDate,
                'open_url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.evidence-management.root', 'evidence_id' => (string) $record->id]),
            ];
        }

        usort($attentionRows, static function (array $left, array $right): int {
            return strcmp((string) ($left['sort_date'] ?? '9999-12-31'), (string) ($right['sort_date'] ?? '9999-12-31'));
        });

        return [
            'metrics' => [
                'records' => $records->count(),
                'approved' => $records->where('status', 'approved')->count(),
                'review_due' => $records->filter(static fn (object $record): bool => is_string($record->review_due_on ?? null) && $record->review_due_on !== '' && $record->review_due_on <= $windowEnd)->count(),
                'expired' => $records->filter(static fn (object $record): bool => is_string($record->valid_until ?? null) && $record->valid_until !== '' && $record->valid_until < $today)->count(),
                'expiring' => $records->filter(static fn (object $record): bool => is_string($record->valid_until ?? null) && $record->valid_until !== '' && $record->valid_until >= $today && $record->valid_until <= $windowEnd)->count(),
                'needs_validation' => $records->filter(static fn (object $record): bool => in_array((string) $record->status, ['active', 'approved'], true) && ! is_string($record->validated_at ?? null))->count(),
            ],
            'summary_metrics' => [
                $this->metricCatalog->summary('records', $records->count()),
                $this->metricCatalog->summary('approved', $records->where('status', 'approved')->count()),
                $this->metricCatalog->summary('review_due', $records->filter(static fn (object $record): bool => is_string($record->review_due_on ?? null) && $record->review_due_on !== '' && $record->review_due_on <= $windowEnd)->count()),
                $this->metricCatalog->summary('needs_validation', $records->filter(static fn (object $record): bool => in_array((string) $record->status, ['active', 'approved'], true) && ! is_string($record->validated_at ?? null))->count()),
            ],
            'breakdowns' => [
                $this->breakdown('Status mix', $statusBreakdown),
            ],
            'attention' => [
                'title' => 'Evidence attention queue',
                'copy' => 'Review evidence records that are overdue, due soon, expired, or approaching expiry.',
            ],
            'rows' => array_slice($attentionRows, 0, 5),
            'empty_copy' => 'No evidence records are currently queued for review or expiry attention.',
            'section_url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.evidence-management.root']),
        ];
    }

    /**
     * @param  array<int, string>|null  $visibleIds
     * @param  array<string, string>  $scopeLabels
     * @param  array<string, mixed>  $baseQuery
     * @return array<string, mixed>
     */
    private function riskSection(
        string $organizationId,
        ?string $scopeId,
        ?array $visibleIds,
        array $scopeLabels,
        array $baseQuery,
    ): array {
        if (! Schema::hasTable('risks')) {
            return $this->emptySection();
        }

        $riskRows = $this->workspaceContext
            ->scopedQuery('risks', $organizationId, $scopeId, false, $visibleIds)
            ->orderByDesc('residual_score')
            ->orderBy('title')
            ->get([
                'id',
                'scope_id',
                'title',
                'inherent_score',
                'residual_score',
            ]);

        $stateCounts = [
            'identified' => 0,
            'assessing' => 0,
            'treated' => 0,
            'accepted' => 0,
        ];
        $rows = [];
        $residualTotal = 0;

        foreach ($riskRows as $risk) {
            $state = $this->workflowState(
                workflowKey: 'plugin.risk-management.risk-lifecycle',
                subjectType: 'risk',
                subjectId: (string) $risk->id,
                organizationId: $organizationId,
                scopeId: is_string($risk->scope_id ?? null) && $risk->scope_id !== '' ? (string) $risk->scope_id : $scopeId,
                fallbackState: 'identified',
            );
            $stateCounts[$state] = ($stateCounts[$state] ?? 0) + 1;
            $residualTotal += (int) $risk->residual_score;

            $rows[] = [
                'title' => (string) $risk->title,
                'scope_label' => $this->scopeLabel(is_string($risk->scope_id ?? null) ? $risk->scope_id : null, $scopeLabels),
                'state_label' => $this->humanize($state),
                'inherent_score' => (int) $risk->inherent_score,
                'residual_score' => (int) $risk->residual_score,
                'open_url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.risk-management.root', 'risk_id' => (string) $risk->id]),
            ];
        }

        $stateBreakdown = [];

        foreach ($stateCounts as $state => $count) {
            $stateBreakdown[] = [
                'id' => $state,
                'label' => $this->humanize($state),
                'count' => $count,
            ];
        }

        return [
            'metrics' => [
                'risks' => $riskRows->count(),
                'in_workflow' => $stateCounts['identified'] + $stateCounts['assessing'] + $stateCounts['treated'],
                'assessing' => $stateCounts['assessing'],
                'accepted' => $stateCounts['accepted'],
                'average_residual' => $riskRows->count() > 0 ? round($residualTotal / $riskRows->count(), 1) : 0,
            ],
            'summary_metrics' => [
                $this->metricCatalog->summary('risks', $riskRows->count()),
                $this->metricCatalog->summary('risks_in_workflow', $stateCounts['identified'] + $stateCounts['assessing'] + $stateCounts['treated']),
                $this->metricCatalog->summary('assessing', $stateCounts['assessing']),
                $this->metricCatalog->summary('average_residual', $riskRows->count() > 0 ? round($residualTotal / $riskRows->count(), 1) : 0),
            ],
            'breakdowns' => [
                $this->breakdown('Workflow state', $stateBreakdown),
            ],
            'attention' => [
                'title' => 'Highest residual risks',
                'copy' => 'Use this queue to inspect the visible risks carrying the most residual exposure.',
            ],
            'rows' => array_slice($rows, 0, 5),
            'empty_copy' => 'No risks are visible in the current workspace context.',
            'section_url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.risk-management.root']),
        ];
    }

    /**
     * @param  array<int, string>|null  $visibleIds
     * @param  array<string, string>  $scopeLabels
     * @param  array<string, mixed>  $baseQuery
     * @return array<string, mixed>
     */
    private function findingSection(
        string $organizationId,
        ?string $scopeId,
        ?array $visibleIds,
        array $scopeLabels,
        array $baseQuery,
    ): array {
        if (! Schema::hasTable('findings')) {
            return $this->emptySection();
        }

        $today = now()->toDateString();
        $findings = $this->workspaceContext
            ->scopedQuery('findings', $organizationId, $scopeId, false, $visibleIds)
            ->orderBy('due_on')
            ->orderBy('severity')
            ->orderBy('title')
            ->get([
                'id',
                'scope_id',
                'title',
                'severity',
                'due_on',
            ]);

        $findingIds = $findings->pluck('id')->map(static fn ($id): string => (string) $id)->all();
        $stateCounts = [
            'open' => 0,
            'triaged' => 0,
            'remediating' => 0,
            'resolved' => 0,
        ];
        $severityCounts = [];

        foreach (FindingsReferenceData::severityLevels() as $severity => $label) {
            $severityCounts[$severity] = [
                'id' => $severity,
                'label' => $label,
                'count' => 0,
            ];
        }

        $actionStatusCounts = [];
        $openActionsByFinding = [];

        if ($findingIds !== [] && Schema::hasTable('remediation_actions')) {
            foreach (DB::table('remediation_actions')
                ->whereIn('finding_id', $findingIds)
                ->get(['finding_id', 'status']) as $action) {
                $status = is_string($action->status ?? null) ? (string) $action->status : 'planned';
                $findingId = (string) $action->finding_id;
                $actionStatusCounts[$status] = ($actionStatusCounts[$status] ?? 0) + 1;

                if ($status !== 'done') {
                    $openActionsByFinding[$findingId] = ($openActionsByFinding[$findingId] ?? 0) + 1;
                }
            }
        }

        $rows = [];
        $overdueCount = 0;

        foreach ($findings as $finding) {
            $severity = (string) $finding->severity;

            if (isset($severityCounts[$severity])) {
                $severityCounts[$severity]['count']++;
            } else {
                $severityCounts[$severity] = [
                    'id' => $severity,
                    'label' => FindingsReferenceData::severityLabel($severity),
                    'count' => 1,
                ];
            }

            $state = $this->workflowState(
                workflowKey: 'plugin.findings-remediation.finding-lifecycle',
                subjectType: 'finding',
                subjectId: (string) $finding->id,
                organizationId: $organizationId,
                scopeId: is_string($finding->scope_id ?? null) && $finding->scope_id !== '' ? (string) $finding->scope_id : $scopeId,
                fallbackState: 'open',
            );

            $stateCounts[$state] = ($stateCounts[$state] ?? 0) + 1;

            $dueOn = is_string($finding->due_on ?? null) ? $finding->due_on : '';

            if ($dueOn !== '' && $dueOn < $today && $state !== 'resolved') {
                $overdueCount++;
            }

            $rows[] = [
                'title' => (string) $finding->title,
                'scope_label' => $this->scopeLabel(is_string($finding->scope_id ?? null) ? $finding->scope_id : null, $scopeLabels),
                'severity_label' => FindingsReferenceData::severityLabel($severity),
                'state_label' => $this->humanize($state),
                'due_on' => $dueOn,
                'open_action_count' => (int) ($openActionsByFinding[(string) $finding->id] ?? 0),
                'open_url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.findings-remediation.root', 'finding_id' => (string) $finding->id]),
            ];
        }

        $stateBreakdown = [];

        foreach ($stateCounts as $state => $count) {
            $stateBreakdown[] = [
                'id' => $state,
                'label' => $this->humanize($state),
                'count' => $count,
            ];
        }

        $severityBreakdown = array_values($severityCounts);
        usort($severityBreakdown, static fn (array $left, array $right): int => strcmp((string) $left['label'], (string) $right['label']));

        $actionBreakdown = [];

        foreach ($actionStatusCounts as $status => $count) {
            $actionBreakdown[] = [
                'id' => $status,
                'label' => FindingsReferenceData::remediationStatusLabel($status),
                'count' => $count,
            ];
        }

        usort($actionBreakdown, static fn (array $left, array $right): int => strcmp((string) $left['label'], (string) $right['label']));

        return [
            'metrics' => [
                'findings' => $findings->count(),
                'open' => $stateCounts['open'] + $stateCounts['triaged'] + $stateCounts['remediating'],
                'overdue' => $overdueCount,
                'high_critical' => array_sum(array_map(
                    static fn (array $row): int => in_array($row['id'], ['high', 'critical'], true) ? (int) $row['count'] : 0,
                    $severityBreakdown,
                )),
                'open_actions' => array_sum($openActionsByFinding),
            ],
            'summary_metrics' => [
                $this->metricCatalog->summary('findings', $findings->count()),
                $this->metricCatalog->summary('open_findings', $stateCounts['open'] + $stateCounts['triaged'] + $stateCounts['remediating']),
                $this->metricCatalog->summary('overdue_findings', $overdueCount),
                $this->metricCatalog->summary('open_actions', array_sum($openActionsByFinding)),
            ],
            'breakdowns' => [
                $this->breakdown('Workflow state', $stateBreakdown),
                $this->breakdown('Severity', $severityBreakdown),
                $this->breakdown('Action status', $actionBreakdown),
            ],
            'attention' => [
                'title' => 'Open and overdue findings',
                'copy' => 'Use this queue to move from executive exposure into the findings that need follow-up.',
            ],
            'rows' => array_slice($rows, 0, 5),
            'empty_copy' => 'No findings are visible in the current workspace context.',
            'section_url' => route('core.shell.index', [...$baseQuery, 'menu' => 'plugin.findings-remediation.root']),
        ];
    }

    /**
     * @param  array<int, array{id: string, label: string, count: int}>  $rows
     * @return array<string, mixed>
     */
    private function breakdown(string $title, array $rows): array
    {
        return [
            'title' => $title,
            'rows' => $rows,
        ];
    }

    private function workflowState(
        string $workflowKey,
        string $subjectType,
        string $subjectId,
        string $organizationId,
        ?string $scopeId,
        string $fallbackState,
    ): string {
        if (! Schema::hasTable('workflow_instances')) {
            return $fallbackState;
        }

        $state = DB::table('workflow_instances')
            ->where('workflow_key', $workflowKey)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('organization_id', $organizationId)
            ->when(is_string($scopeId) && $scopeId !== '', function (Builder $query) use ($scopeId): void {
                $query->where(function (Builder $scopedQuery) use ($scopeId): void {
                    $scopedQuery->whereNull('scope_id')
                        ->orWhere('scope_id', $scopeId);
                });
            })
            ->value('current_state');

        return is_string($state) && $state !== '' ? $state : $fallbackState;
    }

    /**
     * @param  array<string, string>  $scopeLabels
     */
    private function scopeLabel(?string $scopeId, array $scopeLabels): string
    {
        if (! is_string($scopeId) || $scopeId === '') {
            return 'Organization-wide';
        }

        return $scopeLabels[$scopeId] ?? $scopeId;
    }

    private function humanize(string $value): string
    {
        return Str::title(str_replace(['-', '_'], ' ', $value));
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySection(): array
    {
        return [
            'metrics' => [],
            'summary_metrics' => [],
            'breakdowns' => [],
            'attention' => [
                'title' => 'Operational attention',
                'copy' => '',
            ],
            'rows' => [],
            'empty_copy' => 'No records are visible in the current workspace context.',
            'section_url' => null,
        ];
    }
}
