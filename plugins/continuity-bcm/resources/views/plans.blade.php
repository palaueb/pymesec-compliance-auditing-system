<section class="module-screen">
    <div class="overview-grid">
        <div class="metric-card"><div class="metric-label">Plans</div><div class="metric-value">{{ count($plans) }}</div></div>
        <div class="metric-card"><div class="metric-label">Active</div><div class="metric-value">{{ collect($plans)->where('state', 'active')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">Under Review</div><div class="metric-value">{{ collect($plans)->where('state', 'review')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">Evidence</div><div class="metric-value">{{ collect($plans)->sum(fn ($plan) => count($plan['artifacts'])) }}</div></div>
        <div class="metric-card"><div class="metric-label">Exercises</div><div class="metric-value">{{ collect($plans)->sum(fn ($plan) => count($plan['exercises'])) }}</div></div>
        <div class="metric-card"><div class="metric-label">Test Runs</div><div class="metric-value">{{ collect($plans)->sum(fn ($plan) => count($plan['executions'])) }}</div></div>
    </div>

    <div class="table-card">
        <table class="entity-table">
            <thead>
                <tr>
                    <th>Recovery Plan</th>
                    <th>Owner</th>
                    <th>Links</th>
                    <th>Test Due</th>
                    <th>History</th>
                    <th>Evidence</th>
                    <th>State</th>
                    <th>{{ $can_manage_continuity ? 'Actions' : 'Access' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($plans as $plan)
                    <tr>
                        <td>
                            <div class="entity-title">{{ $plan['title'] }}</div>
                            <div class="entity-id">{{ $plan['id'] }}</div>
                            <div class="table-note">{{ $plan['service']['title'] }}</div>
                            <div class="table-note">{{ $plan['strategy_summary'] }}</div>
                            @if ($can_manage_continuity)
                                <form class="upload-form" method="POST" action="{{ route('plugin.continuity-bcm.plans.store', ['serviceId' => $plan['service']['id']]) }}" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.continuity-bcm.plans">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <input type="hidden" name="scope_id" value="{{ $plan['scope_id'] }}">
                                    <div class="field">
                                        <label class="field-label">Add plan to this service</label>
                                        <input class="field-input" name="title" placeholder="Plan title" required>
                                    </div>
                                    <div class="field">
                                        <input class="field-input" name="strategy_summary" placeholder="Strategy summary" required>
                                    </div>
                                    <div class="field">
                                        <input class="field-input" name="test_due_on" type="date">
                                    </div>
                                    <div class="field">
                                        <select class="field-select" name="linked_policy_id">
                                            <option value="">No linked policy</option>
                                            @foreach ($policy_options as $policy)
                                                <option value="{{ $policy['id'] }}">{{ $policy['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field">
                                        <select class="field-select" name="linked_finding_id">
                                            <option value="">No linked finding</option>
                                            @foreach ($finding_options as $finding)
                                                <option value="{{ $finding['id'] }}">{{ $finding['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field">
                                        <select class="field-select" name="owner_actor_id">
                                            <option value="">No owner</option>
                                            @foreach ($owner_actor_options as $actor)
                                                <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <button class="button button-ghost" type="submit">Add plan</button>
                                </form>
                            @endif
                        </td>
                        <td>
                            @if ($plan['owner_assignment'] !== null)
                                <div>{{ $plan['owner_assignment']['display_name'] }}</div>
                                <div class="table-note">{{ $plan['owner_assignment']['kind'] }}</div>
                            @else
                                <span class="muted-note">No owner assigned</span>
                            @endif
                        </td>
                        <td>
                            <div>Policy: {{ $plan['linked_policy_id'] !== '' ? $plan['linked_policy_id'] : 'None' }}</div>
                            <div>Finding: {{ $plan['linked_finding_id'] !== '' ? $plan['linked_finding_id'] : 'None' }}</div>
                        </td>
                        <td>{{ $plan['test_due_on'] !== '' ? $plan['test_due_on'] : 'No test date' }}</td>
                        <td>
                            <div class="data-stack">
                                @forelse ($plan['exercises'] as $exercise)
                                    <div class="data-item">
                                        <div class="entity-title">{{ ucfirst($exercise['exercise_type']) }} · {{ $exercise['exercise_date'] }}</div>
                                        <div class="table-note">{{ ucfirst($exercise['outcome']) }} · {{ $exercise['scenario_summary'] }}</div>
                                        @if ($exercise['follow_up_summary'] !== '')
                                            <div class="table-note">{{ $exercise['follow_up_summary'] }}</div>
                                        @endif
                                    </div>
                                @empty
                                    <span class="muted-note">No exercises logged</span>
                                @endforelse
                            </div>

                            <div class="data-stack" style="margin-top:10px;">
                                @forelse ($plan['executions'] as $execution)
                                    <div class="data-item">
                                        <div class="entity-title">{{ ucfirst($execution['execution_type']) }} · {{ $execution['executed_on'] }}</div>
                                        <div class="table-note">{{ ucfirst($execution['status']) }}</div>
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

                            @if ($can_manage_continuity)
                                <form class="upload-form" method="POST" action="{{ $plan['exercise_store_route'] }}" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.continuity-bcm.plans">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <input type="date" name="exercise_date" required>
                                    <select class="field-select" name="exercise_type">
                                        <option value="tabletop">Tabletop</option>
                                        <option value="simulation">Simulation</option>
                                        <option value="walkthrough">Walkthrough</option>
                                    </select>
                                    <input type="text" name="scenario_summary" placeholder="Exercise scenario" required>
                                    <select class="field-select" name="outcome">
                                        <option value="pass">Pass</option>
                                        <option value="partial">Partial</option>
                                        <option value="fail">Fail</option>
                                    </select>
                                    <input type="text" name="follow_up_summary" placeholder="Follow-up">
                                    <button class="button button-secondary" type="submit">Log exercise</button>
                                </form>

                                <form class="upload-form" method="POST" action="{{ $plan['execution_store_route'] }}" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.continuity-bcm.plans">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <input type="date" name="executed_on" required>
                                    <select class="field-select" name="execution_type">
                                        <option value="recovery-drill">Recovery drill</option>
                                        <option value="restore-test">Restore test</option>
                                        <option value="failover-test">Failover test</option>
                                    </select>
                                    <select class="field-select" name="status">
                                        <option value="passed">Passed</option>
                                        <option value="partial">Partial</option>
                                        <option value="failed">Failed</option>
                                    </select>
                                    <input type="text" name="participants" placeholder="Participants">
                                    <input type="text" name="notes" placeholder="Execution note">
                                    <button class="button button-secondary" type="submit">Log test run</button>
                                </form>
                            @endif
                        </td>
                        <td>
                            @forelse ($plan['artifacts'] as $artifact)
                                <div class="data-item" style="margin-bottom:8px;">
                                    <div class="entity-title">{{ $artifact['label'] }}</div>
                                    <div class="table-note">{{ $artifact['original_filename'] }}</div>
                                </div>
                            @empty
                                <span class="muted-note">No evidence yet</span>
                            @endforelse
                            @if ($can_manage_continuity)
                                <form class="upload-form" method="POST" action="{{ $plan['artifact_upload_route'] }}" enctype="multipart/form-data" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.continuity-bcm.plans">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <input type="hidden" name="artifact_type" value="recovery-plan">
                                    <input type="text" name="label" placeholder="Evidence label">
                                    <input type="file" name="artifact" required>
                                    <button class="button button-secondary" type="submit">Attach evidence</button>
                                </form>
                            @endif
                        </td>
                        <td><span class="pill">{{ $plan['state'] }}</span></td>
                        <td>
                            @if ($plan['transitions'] !== [])
                                <div class="action-cluster">
                                    @foreach ($plan['transitions'] as $transition)
                                        <form method="POST" action="{{ str_replace('__TRANSITION__', $transition, $plan['transition_route']) }}">
                                            @csrf
                                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                            <input type="hidden" name="menu" value="plugin.continuity-bcm.plans">
                                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                            <button class="button button-secondary" type="submit">{{ ucwords(str_replace('-', ' ', $transition)) }}</button>
                                        </form>
                                    @endforeach
                                </div>
                            @else
                                <span class="muted-note">View-only access</span>
                            @endif

                            @if ($can_manage_continuity)
                                <details style="margin-top:10px;">
                                    <summary class="button button-ghost" style="display:inline-flex;">Edit</summary>
                                    <form class="upload-form" method="POST" action="{{ $plan['update_route'] }}" style="margin-top:10px;">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="menu" value="plugin.continuity-bcm.plans">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <div class="field">
                                            <label class="field-label">Title</label>
                                            <input class="field-input" name="title" value="{{ $plan['title'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Strategy summary</label>
                                            <input class="field-input" name="strategy_summary" value="{{ $plan['strategy_summary'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Test due</label>
                                            <input class="field-input" name="test_due_on" type="date" value="{{ $plan['test_due_on'] }}">
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Linked policy</label>
                                            <select class="field-select" name="linked_policy_id">
                                                <option value="">No linked policy</option>
                                                @foreach ($policy_options as $policy)
                                                    <option value="{{ $policy['id'] }}" @selected($plan['linked_policy_id'] === $policy['id'])>{{ $policy['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Linked finding</label>
                                            <select class="field-select" name="linked_finding_id">
                                                <option value="">No linked finding</option>
                                                @foreach ($finding_options as $finding)
                                                    <option value="{{ $finding['id'] }}" @selected($plan['linked_finding_id'] === $finding['id'])>{{ $finding['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Scope</label>
                                            <select class="field-select" name="scope_id">
                                                <option value="">Organization-wide</option>
                                                @foreach ($scope_options as $scope)
                                                    <option value="{{ $scope['id'] }}" @selected($plan['scope_id'] === $scope['id'])>{{ $scope['name'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Owner actor</label>
                                            <select class="field-select" name="owner_actor_id">
                                                <option value="">Keep current owner</option>
                                                @foreach ($owner_actor_options as $actor)
                                                    <option value="{{ $actor['id'] }}" @selected(($plan['owner_assignment']['id'] ?? null) === $actor['id'])>{{ $actor['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="action-cluster">
                                            <button class="button button-secondary" type="submit">Save changes</button>
                                        </div>
                                    </form>
                                </details>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
