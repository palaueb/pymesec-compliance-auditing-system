<section class="module-screen compact">
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

    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr)); align-items:start;">
        <div class="table-card">
            <div class="screen-header">
                <div>
                    <h2 class="screen-title" style="font-size:24px;">Assessments</h2>
                    <p class="screen-subtitle">Campaign status, review results, and the linked findings load carried by current assessments.</p>
                </div>
                @if (is_string($assessments['section_url'] ?? null))
                    <a class="button button-secondary" href="{{ $assessments['section_url'] }}">Open module</a>
                @endif
            </div>
            <div class="data-points">
                <div class="data-point"><span>Campaigns</span><strong>{{ $assessments['metrics']['campaigns'] ?? 0 }}</strong></div>
                <div class="data-point"><span>Active</span><strong>{{ $assessments['metrics']['active'] ?? 0 }}</strong></div>
                <div class="data-point"><span>Failing reviews</span><strong>{{ $assessments['metrics']['failing_reviews'] ?? 0 }}</strong></div>
                <div class="data-point"><span>Linked findings</span><strong>{{ $assessments['metrics']['linked_findings'] ?? 0 }}</strong></div>
            </div>
            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr)); margin-top:16px;">
                <div class="surface-card" style="padding:14px;">
                    <div class="eyebrow">Campaign status</div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($assessments['status_breakdown'] as $row)
                            <div class="data-item"><span>{{ $row['label'] }}</span><strong>{{ $row['count'] }}</strong></div>
                        @empty
                            <div class="table-note">{{ $assessments['empty_copy'] }}</div>
                        @endforelse
                    </div>
                </div>
                <div class="surface-card" style="padding:14px;">
                    <div class="eyebrow">Review results</div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($assessments['result_breakdown'] as $row)
                            <div class="data-item"><span>{{ $row['label'] }}</span><strong>{{ $row['count'] }}</strong></div>
                        @empty
                            <div class="table-note">{{ $assessments['empty_copy'] }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
            <table class="entity-table" style="margin-top:16px;">
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
                    <h2 class="screen-title" style="font-size:24px;">Evidence</h2>
                    <p class="screen-subtitle">Review queue, expiry attention, and validation gaps for the current workspace context.</p>
                </div>
                @if (is_string($evidence['section_url'] ?? null))
                    <a class="button button-secondary" href="{{ $evidence['section_url'] }}">Open module</a>
                @endif
            </div>
            <div class="data-points">
                <div class="data-point"><span>Records</span><strong>{{ $evidence['metrics']['records'] ?? 0 }}</strong></div>
                <div class="data-point"><span>Approved</span><strong>{{ $evidence['metrics']['approved'] ?? 0 }}</strong></div>
                <div class="data-point"><span>Review due</span><strong>{{ $evidence['metrics']['review_due'] ?? 0 }}</strong></div>
                <div class="data-point"><span>Needs validation</span><strong>{{ $evidence['metrics']['needs_validation'] ?? 0 }}</strong></div>
            </div>
            <div class="surface-card" style="padding:14px; margin-top:16px;">
                <div class="eyebrow">Status mix</div>
                <div class="data-stack" style="margin-top:10px;">
                    @forelse ($evidence['status_breakdown'] as $row)
                        <div class="data-item"><span>{{ $row['label'] }}</span><strong>{{ $row['count'] }}</strong></div>
                    @empty
                        <div class="table-note">{{ $evidence['empty_copy'] }}</div>
                    @endforelse
                </div>
            </div>
            <table class="entity-table" style="margin-top:16px;">
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
                    <h2 class="screen-title" style="font-size:24px;">Risks</h2>
                    <p class="screen-subtitle">Workflow load and residual score concentration across visible risks.</p>
                </div>
                @if (is_string($risks['section_url'] ?? null))
                    <a class="button button-secondary" href="{{ $risks['section_url'] }}">Open module</a>
                @endif
            </div>
            <div class="data-points">
                <div class="data-point"><span>Risks</span><strong>{{ $risks['metrics']['risks'] ?? 0 }}</strong></div>
                <div class="data-point"><span>In workflow</span><strong>{{ $risks['metrics']['in_workflow'] ?? 0 }}</strong></div>
                <div class="data-point"><span>Assessing</span><strong>{{ $risks['metrics']['assessing'] ?? 0 }}</strong></div>
                <div class="data-point"><span>Average residual</span><strong>{{ $risks['metrics']['average_residual'] ?? 0 }}</strong></div>
            </div>
            <div class="surface-card" style="padding:14px; margin-top:16px;">
                <div class="eyebrow">Workflow state</div>
                <div class="data-stack" style="margin-top:10px;">
                    @forelse ($risks['state_breakdown'] as $row)
                        <div class="data-item"><span>{{ $row['label'] }}</span><strong>{{ $row['count'] }}</strong></div>
                    @empty
                        <div class="table-note">{{ $risks['empty_copy'] }}</div>
                    @endforelse
                </div>
            </div>
            <table class="entity-table" style="margin-top:16px;">
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
                    <h2 class="screen-title" style="font-size:24px;">Findings</h2>
                    <p class="screen-subtitle">Severity mix, overdue exposure, and remediation action pressure.</p>
                </div>
                @if (is_string($findings['section_url'] ?? null))
                    <a class="button button-secondary" href="{{ $findings['section_url'] }}">Open module</a>
                @endif
            </div>
            <div class="data-points">
                <div class="data-point"><span>Findings</span><strong>{{ $findings['metrics']['findings'] ?? 0 }}</strong></div>
                <div class="data-point"><span>Open</span><strong>{{ $findings['metrics']['open'] ?? 0 }}</strong></div>
                <div class="data-point"><span>Overdue</span><strong>{{ $findings['metrics']['overdue'] ?? 0 }}</strong></div>
                <div class="data-point"><span>Open actions</span><strong>{{ $findings['metrics']['open_actions'] ?? 0 }}</strong></div>
            </div>
            <div class="overview-grid" style="grid-template-columns:repeat(3, minmax(0, 1fr)); margin-top:16px;">
                <div class="surface-card" style="padding:14px;">
                    <div class="eyebrow">Workflow state</div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($findings['state_breakdown'] as $row)
                            <div class="data-item"><span>{{ $row['label'] }}</span><strong>{{ $row['count'] }}</strong></div>
                        @empty
                            <div class="table-note">{{ $findings['empty_copy'] }}</div>
                        @endforelse
                    </div>
                </div>
                <div class="surface-card" style="padding:14px;">
                    <div class="eyebrow">Severity</div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($findings['severity_breakdown'] as $row)
                            <div class="data-item"><span>{{ $row['label'] }}</span><strong>{{ $row['count'] }}</strong></div>
                        @empty
                            <div class="table-note">{{ $findings['empty_copy'] }}</div>
                        @endforelse
                    </div>
                </div>
                <div class="surface-card" style="padding:14px;">
                    <div class="eyebrow">Action status</div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($findings['action_breakdown'] as $row)
                            <div class="data-item"><span>{{ $row['label'] }}</span><strong>{{ $row['count'] }}</strong></div>
                        @empty
                            <div class="table-note">{{ $findings['empty_copy'] }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
            <table class="entity-table" style="margin-top:16px;">
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
