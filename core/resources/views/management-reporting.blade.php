<section class="module-screen compact management-reporting-screen">
    <div class="surface-card" style="padding:18px; margin-bottom:16px;">
        <div class="eyebrow">{{ __('core.management-reporting.screen.eyebrow') }}</div>
        <h2 class="screen-title" style="font-size:26px; margin-top:6px;">{{ __('core.management-reporting.screen.hero_title') }}</h2>
        <p class="screen-subtitle">{{ __('core.management-reporting.screen.hero_copy') }}</p>
    </div>

    <div class="overview-grid" style="grid-template-columns:repeat(6, minmax(0, 1fr));">
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
            {{ __('core.management-reporting.no_organization') }}
        </div>
    @else
        <div class="surface-note">
            {{ __('core.management-reporting.organization_context', ['organization' => $organization_name]) }}
            @if (! empty($scope_name)) {{ __('core.management-reporting.scope_context', ['scope' => $scope_name]) }} @endif
            {{ __('core.management-reporting.visibility_note') }}
        </div>
    @endif

    <div class="surface-card" style="padding:16px;">
        <div class="screen-header" style="margin-bottom:0; border-bottom:0; padding-bottom:0;">
            <div>
                <h2 class="screen-title" style="font-size:24px;">{{ __('core.management-reporting.executive.title') }}</h2>
                <p class="screen-subtitle">{{ __('core.management-reporting.executive.subtitle') }}</p>
            </div>
        </div>
    </div>

    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr)); align-items:start;">
        @include('partials.management-reporting-executive-section', [
            'title' => __('core.management-reporting.section.assessments.title'),
            'subtitle' => __('core.management-reporting.section.assessments.subtitle'),
            'section' => $assessments,
        ])

        @include('partials.management-reporting-executive-section', [
            'title' => __('core.management-reporting.section.evidence.title'),
            'subtitle' => __('core.management-reporting.section.evidence.subtitle'),
            'section' => $evidence,
        ])
    </div>

    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr)); align-items:start;">
        @include('partials.management-reporting-executive-section', [
            'title' => __('core.management-reporting.section.risks.title'),
            'subtitle' => __('core.management-reporting.section.risks.subtitle'),
            'section' => $risks,
        ])

        @include('partials.management-reporting-executive-section', [
            'title' => __('core.management-reporting.section.findings.title'),
            'subtitle' => __('core.management-reporting.section.findings.subtitle'),
            'section' => $findings,
        ])
    </div>

    <div class="overview-grid" style="grid-template-columns:repeat(1, minmax(0, 1fr)); align-items:start;">
        @include('partials.management-reporting-executive-section', [
            'title' => __('core.management-reporting.section.vendors.title'),
            'subtitle' => __('core.management-reporting.section.vendors.subtitle'),
            'section' => $vendors,
        ])
    </div>

    <div class="surface-card" style="padding:16px;">
        <div class="screen-header" style="margin-bottom:0;">
            <div>
                <h2 class="screen-title" style="font-size:24px;">{{ __('core.management-reporting.operational.title') }}</h2>
                <p class="screen-subtitle">{{ __('core.management-reporting.operational.subtitle') }}</p>
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
                        <th>{{ __('core.management-reporting.table.campaign') }}</th>
                        <th>{{ __('core.management-reporting.table.status') }}</th>
                        <th>{{ __('core.management-reporting.table.reviews') }}</th>
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
                            <td>{{ $row['pass_count'] }} {{ __('core.management-reporting.review.pass') }} · {{ $row['partial_count'] }} {{ __('core.management-reporting.review.partial') }} · {{ $row['fail_count'] }} {{ __('core.management-reporting.review.fail') }} · {{ $row['linked_findings'] }} {{ __('core.management-reporting.linked_findings') }}</td>
                            <td class="table-actions"><a class="button button-ghost" href="{{ $row['open_url'] }}">{{ __('core.actions.open') }}</a></td>
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
                        <th>{{ __('core.management-reporting.table.evidence') }}</th>
                        <th>{{ __('core.management-reporting.table.attention') }}</th>
                        <th>{{ __('core.management-reporting.table.dates') }}</th>
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
                                {{ __('core.management-reporting.review_due') }} {{ $row['review_due_on'] !== '' ? $row['review_due_on'] : __('n/a') }}
                                <br>
                                {{ __('core.management-reporting.valid_until') }} {{ $row['valid_until'] !== '' ? $row['valid_until'] : __('n/a') }}
                            </td>
                            <td class="table-actions"><a class="button button-ghost" href="{{ $row['open_url'] }}">{{ __('core.actions.open') }}</a></td>
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
                        <th>{{ __('core.management-reporting.table.risk') }}</th>
                        <th>{{ __('core.management-reporting.table.state') }}</th>
                        <th>{{ __('core.management-reporting.table.scores') }}</th>
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
                            <td>{{ __('core.management-reporting.inherent') }} {{ $row['inherent_score'] }} · {{ __('core.management-reporting.residual') }} {{ $row['residual_score'] }}</td>
                            <td class="table-actions"><a class="button button-ghost" href="{{ $row['open_url'] }}">{{ __('core.actions.open') }}</a></td>
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
                        <th>{{ __('core.management-reporting.table.finding') }}</th>
                        <th>{{ __('core.management-reporting.table.state') }}</th>
                        <th>{{ __('core.management-reporting.table.due') }}</th>
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
                            <td>{{ $row['state_label'] }} · {{ $row['open_action_count'] }} {{ __('core.management-reporting.open_actions') }}</td>
                            <td>{{ $row['due_on'] !== '' ? $row['due_on'] : __('n/a') }}</td>
                            <td class="table-actions"><a class="button button-ghost" href="{{ $row['open_url'] }}">{{ __('core.actions.open') }}</a></td>
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

    <div class="overview-grid" style="grid-template-columns:repeat(1, minmax(0, 1fr)); align-items:start;">
        <div class="table-card">
            <div class="screen-header">
                <div>
                    <h2 class="screen-title" style="font-size:22px;">{{ $vendors['attention']['title'] }}</h2>
                    <p class="screen-subtitle">{{ $vendors['attention']['copy'] }}</p>
                </div>
            </div>
            <table class="entity-table">
                <thead>
                    <tr>
                        <th>{{ __('core.management-reporting.table.vendor') }}</th>
                        <th>{{ __('core.management-reporting.table.review_posture') }}</th>
                        <th>{{ __('core.management-reporting.table.follow_up') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vendors['rows'] as $row)
                        <tr>
                            <td>
                                <div class="entity-title">{{ $row['title'] }}</div>
                                <div class="entity-meta">{{ $row['scope_label'] }} · {{ $row['tier_label'] }} tier · {{ $row['vendor_status_label'] }}</div>
                            </td>
                            <td>
                                {{ $row['decision_state_label'] }} · {{ $row['attention_reason'] }}
                                <br>
                                {{ __('core.management-reporting.next_review_due') }} {{ $row['next_review_due_on'] !== '' ? $row['next_review_due_on'] : __('n/a') }}
                            </td>
                            <td>{{ $row['open_questionnaire_count'] }} {{ __('core.management-reporting.questionnaires') }} · {{ $row['open_action_count'] }} {{ __('core.management-reporting.remediation') }}</td>
                            <td class="table-actions"><a class="button button-ghost" href="{{ $row['open_url'] }}">{{ __('core.actions.open') }}</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="muted-note">{{ $vendors['empty_copy'] }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
