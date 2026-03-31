<section class="module-screen compact management-reporting-screen">
    <div class="surface-card" style="padding:18px; margin-bottom:16px;">
        <div class="eyebrow">Cross-domain executive summary</div>
        <h2 class="screen-title" style="font-size:26px; margin-top:6px;">Management view across assessments, evidence, risks, and findings</h2>
        <p class="screen-subtitle">Use this page to understand current delivery pressure before going into one operational module.</p>
    </div>

    <div class="overview-grid" style="grid-template-columns:repeat(5, minmax(0, 1fr));">
        @foreach ($headline_metrics as $metric)
            <div class="metric-card">
                <div class="metric-label">{{ $metric['label'] }}</div>
                <div class="metric-value">{{ $metric['value'] }}</div>
                <div class="table-note">{{ $metric['copy'] }}</div>
            </div>
        @endforeach
    </div>

    @if (! $has_organization_context)
        <div class="surface-note">
            Select an organization first. Management reporting is always resolved inside one organization context.
        </div>
    @else
        <div class="surface-note">
            Cross-domain reporting for <strong>{{ $organization_name }}</strong>@if (! empty($scope_name)), scoped to <strong>{{ $scope_name }}</strong>@endif.
            Assessments and evidence keep organization-wide records visible when a scope is selected. Risks and findings stay scope-exact and continue to respect delegated object access.
        </div>
    @endif

    <div class="surface-card" style="padding:16px;">
        <div class="screen-header" style="margin-bottom:0; border-bottom:0; padding-bottom:0;">
            <div>
                <h2 class="screen-title" style="font-size:24px;">Executive summary by domain</h2>
                <p class="screen-subtitle">Use these four sections to compare domain pressure without dropping into operational queues yet.</p>
            </div>
        </div>
    </div>

    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr)); align-items:start;">
        @include('partials.management-reporting-executive-section', [
            'title' => 'Assessments',
            'subtitle' => 'Campaign status, review results, and the linked findings load carried by current assessments.',
            'section' => $assessments,
        ])

        @include('partials.management-reporting-executive-section', [
            'title' => 'Evidence',
            'subtitle' => 'Review queue, expiry attention, and validation gaps for the current workspace context.',
            'section' => $evidence,
        ])
    </div>

    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr)); align-items:start;">
        @include('partials.management-reporting-executive-section', [
            'title' => 'Risks',
            'subtitle' => 'Workflow load and residual score concentration across visible risks.',
            'section' => $risks,
        ])

        @include('partials.management-reporting-executive-section', [
            'title' => 'Findings',
            'subtitle' => 'Severity mix, overdue exposure, and remediation action pressure.',
            'section' => $findings,
        ])
    </div>

    <div class="surface-card" style="padding:16px;">
        <div class="screen-header" style="margin-bottom:0;">
            <div>
                <h2 class="screen-title" style="font-size:24px;">Operational attention</h2>
                <p class="screen-subtitle">Use these queues only after the executive summary tells you where to drill down.</p>
            </div>
        </div>
    </div>

    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr)); align-items:start;">
        <div class="table-card">
            <div class="screen-header">
                <div>
                    <h2 class="screen-title" style="font-size:22px;">{{ $assessments['attention']['title'] }}</h2>
                    <p class="screen-subtitle">{{ $assessments['attention']['copy'] }}</p>
                </div>
            </div>
            <table class="entity-table">
                <thead>
                    <tr>
                        <th>Campaign</th>
                        <th>Status</th>
                        <th>Reviews</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($assessments['rows'] as $row)
                        <tr>
                            <td>
                                <div class="entity-title">{{ $row['title'] }}</div>
                                <div class="entity-meta">{{ $row['scope_label'] }} · {{ $row['starts_on'] }} to {{ $row['ends_on'] }}</div>
                            </td>
                            <td>{{ $row['status_label'] }}</td>
                            <td>{{ $row['pass_count'] }} pass · {{ $row['partial_count'] }} partial · {{ $row['fail_count'] }} fail · {{ $row['linked_findings'] }} linked findings</td>
                            <td class="table-actions"><a class="button button-ghost" href="{{ $row['open_url'] }}">Open</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="muted-note">{{ $assessments['empty_copy'] }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-card">
            <div class="screen-header">
                <div>
                    <h2 class="screen-title" style="font-size:22px;">{{ $evidence['attention']['title'] }}</h2>
                    <p class="screen-subtitle">{{ $evidence['attention']['copy'] }}</p>
                </div>
            </div>
            <table class="entity-table">
                <thead>
                    <tr>
                        <th>Evidence</th>
                        <th>Attention</th>
                        <th>Dates</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($evidence['rows'] as $row)
                        <tr>
                            <td>
                                <div class="entity-title">{{ $row['title'] }}</div>
                                <div class="entity-meta">{{ $row['scope_label'] }} · {{ $row['status_label'] }}</div>
                            </td>
                            <td>{{ $row['attention_reason'] }}</td>
                            <td>
                                Review {{ $row['review_due_on'] !== '' ? $row['review_due_on'] : 'n/a' }}
                                <br>
                                Valid until {{ $row['valid_until'] !== '' ? $row['valid_until'] : 'n/a' }}
                            </td>
                            <td class="table-actions"><a class="button button-ghost" href="{{ $row['open_url'] }}">Open</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="muted-note">{{ $evidence['empty_copy'] }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr)); align-items:start;">
        <div class="table-card">
            <div class="screen-header">
                <div>
                    <h2 class="screen-title" style="font-size:22px;">{{ $risks['attention']['title'] }}</h2>
                    <p class="screen-subtitle">{{ $risks['attention']['copy'] }}</p>
                </div>
            </div>
            <table class="entity-table">
                <thead>
                    <tr>
                        <th>Risk</th>
                        <th>State</th>
                        <th>Scores</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($risks['rows'] as $row)
                        <tr>
                            <td>
                                <div class="entity-title">{{ $row['title'] }}</div>
                                <div class="entity-meta">{{ $row['scope_label'] }}</div>
                            </td>
                            <td>{{ $row['state_label'] }}</td>
                            <td>Inherent {{ $row['inherent_score'] }} · Residual {{ $row['residual_score'] }}</td>
                            <td class="table-actions"><a class="button button-ghost" href="{{ $row['open_url'] }}">Open</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="muted-note">{{ $risks['empty_copy'] }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-card">
            <div class="screen-header">
                <div>
                    <h2 class="screen-title" style="font-size:22px;">{{ $findings['attention']['title'] }}</h2>
                    <p class="screen-subtitle">{{ $findings['attention']['copy'] }}</p>
                </div>
            </div>
            <table class="entity-table">
                <thead>
                    <tr>
                        <th>Finding</th>
                        <th>State</th>
                        <th>Due</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($findings['rows'] as $row)
                        <tr>
                            <td>
                                <div class="entity-title">{{ $row['title'] }}</div>
                                <div class="entity-meta">{{ $row['scope_label'] }} · {{ $row['severity_label'] }}</div>
                            </td>
                            <td>{{ $row['state_label'] }} · {{ $row['open_action_count'] }} open actions</td>
                            <td>{{ $row['due_on'] !== '' ? $row['due_on'] : 'n/a' }}</td>
                            <td class="table-actions"><a class="button button-ghost" href="{{ $row['open_url'] }}">Open</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="muted-note">{{ $findings['empty_copy'] }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
