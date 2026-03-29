<section class="module-screen">
    @if (is_array($selected_plan))
        <div class="surface-card" style="padding:16px; display:grid; gap:16px;">
            <div class="row-between" style="align-items:flex-start;">
                <div>
                    <div class="eyebrow">Recovery plan</div>
                    <h2 class="screen-title" style="font-size:28px;">{{ $selected_plan['title'] }}</h2>
                    <div class="table-note">{{ $selected_plan['id'] }}</div>
                    <div class="table-note">{{ $selected_plan['service']['title'] }}</div>
                </div>
                <span class="pill">{{ $selected_plan['state'] }}</span>
            </div>

            <div class="overview-grid">
                <div class="metric-card"><div class="metric-label">Test due</div><div class="metric-value">{{ $selected_plan['test_due_on'] !== '' ? $selected_plan['test_due_on'] : 'None' }}</div></div>
                <div class="metric-card"><div class="metric-label">Evidence</div><div class="metric-value">{{ count($selected_plan['artifacts']) }}</div></div>
                <div class="metric-card"><div class="metric-label">Exercises</div><div class="metric-value">{{ count($selected_plan['exercises']) }}</div></div>
                <div class="metric-card"><div class="metric-label">Test runs</div><div class="metric-value">{{ count($selected_plan['executions']) }}</div></div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Overview</div>
                    <div class="entity-title" style="margin-top:10px;">{{ $selected_plan['strategy_summary'] }}</div>
                    <div class="table-note" style="margin-top:12px;">Policy: {{ $selected_plan['linked_policy_id'] !== '' ? $selected_plan['linked_policy_id'] : 'None' }}</div>
                    <div class="table-note">Finding: {{ $selected_plan['linked_finding_id'] !== '' ? $selected_plan['linked_finding_id'] : 'None' }}</div>
                    <div class="table-note">Scope: {{ $selected_plan['scope_id'] !== '' ? $selected_plan['scope_id'] : 'Organization-wide' }}</div>
                    <div class="table-note">Owners: {{ count($selected_plan['owner_assignments']) }}</div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($selected_plan['owner_assignments'] as $owner)
                            <div class="data-item">
                                <div class="entity-title">{{ $owner['display_name'] }}</div>
                                <div class="table-note">{{ $owner['kind'] }}</div>
                                @if ($can_manage_continuity)
                                    <form method="POST" action="{{ str_replace('__ASSIGNMENT__', $owner['assignment_id'], $selected_plan['owner_remove_route']) }}" style="margin-top:8px;">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="menu" value="plugin.continuity-bcm.plans">
                                        <input type="hidden" name="plan_id" value="{{ $selected_plan['id'] }}">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <button class="button button-ghost" type="submit">Remove owner</button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <span class="muted-note">No owner assigned</span>
                        @endforelse
                    </div>
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Workflow</div>
                    @if ($selected_plan['transitions'] !== [])
                        <div class="action-cluster" style="margin-top:10px;">
                            @foreach ($selected_plan['transitions'] as $transition)
                                <form method="POST" action="{{ str_replace('__TRANSITION__', $transition, $selected_plan['transition_route']) }}">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.continuity-bcm.plans">
                                    <input type="hidden" name="plan_id" value="{{ $selected_plan['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <button class="button button-secondary" type="submit">{{ ucwords(str_replace('-', ' ', $transition)) }}</button>
                                </form>
                            @endforeach
                        </div>
                    @else
                        <div class="table-note" style="margin-top:10px;">View-only access</div>
                    @endif

                    @if ($can_manage_continuity)
                        <details style="margin-top:12px;">
                            <summary class="button button-ghost" style="display:inline-flex;">Edit plan</summary>
                            <form class="upload-form" method="POST" action="{{ $selected_plan['update_route'] }}" style="margin-top:10px;">
                                @csrf
                                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                <input type="hidden" name="menu" value="plugin.continuity-bcm.plans">
                                <input type="hidden" name="plan_id" value="{{ $selected_plan['id'] }}">
                                <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                <div class="field">
                                    <label class="field-label">Title</label>
                                    <input class="field-input" name="title" value="{{ $selected_plan['title'] }}" required>
                                </div>
                                <div class="field">
                                    <label class="field-label">Strategy summary</label>
                                    <input class="field-input" name="strategy_summary" value="{{ $selected_plan['strategy_summary'] }}" required>
                                </div>
                                <div class="field">
                                    <label class="field-label">Test due</label>
                                    <input class="field-input" name="test_due_on" type="date" value="{{ $selected_plan['test_due_on'] }}">
                                </div>
                                <div class="field">
                                    <label class="field-label">Linked policy</label>
                                    <select class="field-select" name="linked_policy_id">
                                        <option value="">No linked policy</option>
                                        @foreach ($policy_options as $policy)
                                            <option value="{{ $policy['id'] }}" @selected($selected_plan['linked_policy_id'] === $policy['id'])>{{ $policy['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">Linked finding</label>
                                    <select class="field-select" name="linked_finding_id">
                                        <option value="">No linked finding</option>
                                        @foreach ($finding_options as $finding)
                                            <option value="{{ $finding['id'] }}" @selected($selected_plan['linked_finding_id'] === $finding['id'])>{{ $finding['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">Scope</label>
                                    <select class="field-select" name="scope_id">
                                        <option value="">Organization-wide</option>
                                        @foreach ($scope_options as $scope)
                                            <option value="{{ $scope['id'] }}" @selected($selected_plan['scope_id'] === $scope['id'])>{{ $scope['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">Add owner actor</label>
                                    <select class="field-select" name="owner_actor_id">
                                        <option value="">Do not add owner</option>
                                        @foreach ($owner_actor_options as $actor)
                                            <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <div class="table-note">Selecting an actor adds another owner instead of replacing the current set.</div>
                                </div>
                                @if (($selected_plan['owner_assignments'] ?? []) !== [])
                                    <div class="field" style="grid-column:1 / -1;">
                                        <label class="field-label">Current owners</label>
                                        <div class="data-stack">
                                            @foreach ($selected_plan['owner_assignments'] as $owner)
                                                <div class="data-item">
                                                    <div class="entity-title">{{ $owner['display_name'] }}</div>
                                                    <div class="table-note">{{ $owner['kind'] }}</div>
                                                    <form method="POST" action="{{ str_replace('__ASSIGNMENT__', $owner['assignment_id'], $selected_plan['owner_remove_route']) }}" style="margin-top:8px;">
                                                        @csrf
                                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                                        <input type="hidden" name="menu" value="plugin.continuity-bcm.plans">
                                                        <input type="hidden" name="plan_id" value="{{ $selected_plan['id'] }}">
                                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                                        <button class="button button-ghost" type="submit">Remove owner</button>
                                                    </form>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                                <div class="action-cluster">
                                    <button class="button button-secondary" type="submit">Save changes</button>
                                </div>
                            </form>
                        </details>
                    @endif
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(3, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="row-between">
                        <div class="metric-label">Exercises</div>
                        @if ($can_manage_continuity)
                            <details>
                                <summary class="button button-ghost" style="display:inline-flex;">Log exercise</summary>
                                <form class="upload-form" method="POST" action="{{ $selected_plan['exercise_store_route'] }}" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.continuity-bcm.plans">
                                    <input type="hidden" name="plan_id" value="{{ $selected_plan['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <input type="date" name="exercise_date" required>
                                    <select class="field-select" name="exercise_type">
                                        @foreach ($exercise_type_options as $exerciseType)
                                            <option value="{{ $exerciseType['id'] }}">{{ $exerciseType['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="scenario_summary" placeholder="Exercise scenario" required>
                                    <select class="field-select" name="outcome">
                                        @foreach ($exercise_outcome_options as $outcome)
                                            <option value="{{ $outcome['id'] }}">{{ $outcome['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="follow_up_summary" placeholder="Follow-up">
                                    <button class="button button-secondary" type="submit">Save exercise</button>
                                </form>
                            </details>
                        @endif
                    </div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($selected_plan['exercises'] as $exercise)
                            <div class="data-item">
                                <div class="entity-title">{{ $exercise['exercise_type_label'] }} · {{ $exercise['exercise_date'] }}</div>
                                <div class="table-note">{{ $exercise['outcome_label'] }} · {{ $exercise['scenario_summary'] }}</div>
                                @if ($exercise['follow_up_summary'] !== '')
                                    <div class="table-note">{{ $exercise['follow_up_summary'] }}</div>
                                @endif
                            </div>
                        @empty
                            <span class="muted-note">No exercises logged</span>
                        @endforelse
                    </div>
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="row-between">
                        <div class="metric-label">Test runs</div>
                        @if ($can_manage_continuity)
                            <details>
                                <summary class="button button-ghost" style="display:inline-flex;">Log test run</summary>
                                <form class="upload-form" method="POST" action="{{ $selected_plan['execution_store_route'] }}" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.continuity-bcm.plans">
                                    <input type="hidden" name="plan_id" value="{{ $selected_plan['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <input type="date" name="executed_on" required>
                                    <select class="field-select" name="execution_type">
                                        @foreach ($execution_type_options as $executionType)
                                            <option value="{{ $executionType['id'] }}">{{ $executionType['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <select class="field-select" name="status">
                                        @foreach ($execution_status_options as $status)
                                            <option value="{{ $status['id'] }}">{{ $status['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="participants" placeholder="Participants">
                                    <input type="text" name="notes" placeholder="Execution note">
                                    <button class="button button-secondary" type="submit">Save test run</button>
                                </form>
                            </details>
                        @endif
                    </div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($selected_plan['executions'] as $execution)
                            <div class="data-item">
                                <div class="entity-title">{{ $execution['execution_type_label'] }} · {{ $execution['executed_on'] }}</div>
                                <div class="table-note">{{ $execution['status_label'] }}</div>
                                @if ($execution['participants'] !== '')
                                    <div class="table-note">{{ $execution['participants'] }}</div>
                                @endif
                                @if ($execution['notes'] !== '')
                                    <div class="table-note">{{ $execution['notes'] }}</div>
                                @endif
                            </div>
                        @empty
                            <span class="muted-note">No test runs logged</span>
                        @endforelse
                    </div>
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="row-between">
                        <div class="metric-label">Evidence</div>
                        @if ($can_manage_continuity)
                            <details>
                                <summary class="button button-ghost" style="display:inline-flex;">Attach evidence</summary>
                                <form class="upload-form" method="POST" action="{{ $selected_plan['artifact_upload_route'] }}" enctype="multipart/form-data" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.continuity-bcm.plans">
                                    <input type="hidden" name="plan_id" value="{{ $selected_plan['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <input type="hidden" name="artifact_type" value="recovery-plan">
                                    <input type="text" name="label" placeholder="Evidence label">
                                    <input type="file" name="artifact" required>
                                    <button class="button button-secondary" type="submit">Upload evidence</button>
                                </form>
                            </details>
                        @endif
                    </div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($selected_plan['artifacts'] as $artifact)
                            <div class="data-item">
                                <div class="row-between" style="align-items:flex-start; gap:12px;">
                                    <div>
                                        <div class="entity-title">{{ $artifact['label'] }}</div>
                                        <div class="table-note">{{ $artifact['original_filename'] }}</div>
                                    </div>
                                    <form method="POST" action="{{ route('plugin.evidence-management.promote', ['artifactId' => $artifact['id']]) }}">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                        <input type="hidden" name="scope_id" value="{{ $selected_plan['scope_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <button class="button button-ghost" type="submit">Promote to evidence</button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <span class="muted-note">No evidence yet</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="overview-grid">
            <div class="metric-card"><div class="metric-label">Plans</div><div class="metric-value">{{ count($plans) }}</div></div>
            <div class="metric-card"><div class="metric-label">Active</div><div class="metric-value">{{ collect($plans)->where('state', 'active')->count() }}</div></div>
            <div class="metric-card"><div class="metric-label">Under review</div><div class="metric-value">{{ collect($plans)->where('state', 'review')->count() }}</div></div>
            <div class="metric-card"><div class="metric-label">With evidence</div><div class="metric-value">{{ collect($plans)->filter(fn ($plan) => count($plan['artifacts']) > 0)->count() }}</div></div>
        </div>

        <div class="surface-card" style="padding:14px;">
            <div class="row-between" style="gap:12px; align-items:flex-start;">
                <div>
                    <div class="entity-title">Recovery plans list</div>
                    <div class="table-note">Open a plan to work on exercises, test runs, evidence, links, ownership, and transitions. To create a new recovery plan, first open the related continuity service.</div>
                </div>
                @if ($can_manage_continuity)
                    <a class="button button-secondary" href="{{ route('core.shell.index', [...$list_query, 'menu' => 'plugin.continuity-bcm.root']) }}#continuity-service-plans">Choose service</a>
                @endif
            </div>
        </div>

        <div class="table-card">
            <table class="entity-table">
                <thead>
                    <tr>
                        <th>Recovery Plan</th>
                        <th>Service</th>
                        <th>Owner</th>
                        <th>Links</th>
                        <th>Test Due</th>
                        <th>State</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($plans as $plan)
                        <tr>
                            <td>
                                <div class="entity-title">{{ $plan['title'] }}</div>
                                <div class="entity-id">{{ $plan['id'] }}</div>
                                <div class="table-note">{{ $plan['strategy_summary'] }}</div>
                            </td>
                            <td>{{ $plan['service']['title'] }}</td>
                            <td>
                                @if (($plan['owner_assignments'] ?? []) !== [])
                                    <div>{{ $plan['owner_assignments'][0]['display_name'] }}</div>
                                    @if (count($plan['owner_assignments']) > 1)
                                        <div class="table-note">+{{ count($plan['owner_assignments']) - 1 }} more owner{{ count($plan['owner_assignments']) > 2 ? 's' : '' }}</div>
                                    @else
                                        <div class="table-note">{{ $plan['owner_assignments'][0]['kind'] }}</div>
                                    @endif
                                @else
                                    <span class="muted-note">No owner assigned</span>
                                @endif
                            </td>
                            <td>
                                @if ($plan['linked_policy_id'] !== '') <span class="tag">Policy</span> @endif
                                @if ($plan['linked_finding_id'] !== '') <span class="tag">Finding</span> @endif
                                @if ($plan['linked_policy_id'] === '' && $plan['linked_finding_id'] === '') <span class="muted-note">—</span> @endif
                            </td>
                            <td>{{ $plan['test_due_on'] !== '' ? $plan['test_due_on'] : 'No test date' }}</td>
                            <td><span class="pill">{{ $plan['state'] }}</span></td>
                            <td>
                                <a class="button button-secondary" href="{{ $plan['open_url'] }}&{{ http_build_query(['context_label' => 'Recovery Plans', 'context_back_url' => $plans_list_url]) }}">Open</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
