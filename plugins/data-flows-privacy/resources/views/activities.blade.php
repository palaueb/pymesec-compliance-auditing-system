<style>
    .pill-active  { background: rgba(34,197,94,0.14);  color: #166534; }
    .pill-review  { background: rgba(245,158,11,0.14); color: #92400e; }
    .pill-draft   { background: rgba(31,42,34,0.06);   color: var(--muted); }
    .pill-archived{ background: rgba(31,42,34,0.06);   color: var(--muted); }

    details > summary { cursor: pointer; list-style: none; }
    details > summary::-webkit-details-marker { display: none; }
</style>

<section class="module-screen">
    <div class="surface-note">
        Lawful bases are business-managed catalog values from `Reference catalogs`. Processing activity workflow states are system-controlled.
    </div>

    @if (is_array($selected_activity))
        <div class="surface-card" style="padding:16px; display:grid; gap:16px;">
            <div class="surface-note">
                Processing Activity Detail keeps records, workflow, linked objects, ownership, and activity maintenance in one workspace. Use the activity list to browse processing activities and open the one you want to work on.
            </div>

            <div class="row-between" style="align-items:flex-start;">
                <div>
                    <div class="eyebrow">Processing Activity Detail</div>
                    <h2 class="screen-title" style="font-size:28px;">{{ $selected_activity['title'] }}</h2>
                    <div class="table-note">{{ $selected_activity['id'] }} · {{ $selected_activity['lawful_basis_label'] }}</div>
                    <div class="table-note">{{ $selected_activity['purpose'] }}</div>
                </div>
                <div class="action-cluster">
                    @php $actStatePill = match($selected_activity['state']) { 'active' => 'pill-active', 'review' => 'pill-review', 'draft' => 'pill-draft', 'archived' => 'pill-archived', default => '' }; @endphp
                    <span class="pill {{ $actStatePill }}">{{ $selected_activity['state'] }}</span>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
                <div class="metric-card"><div class="metric-label">Evidence</div><div class="metric-value">{{ count($selected_activity['artifacts']) }}</div></div>
                <div class="metric-card"><div class="metric-label">Review due</div><div class="metric-value" style="font-size:20px;">{{ $selected_activity['review_due_on'] !== '' ? $selected_activity['review_due_on'] : 'No date' }}</div></div>
                <div class="metric-card"><div class="metric-label">Scope</div><div class="metric-value" style="font-size:20px;">{{ $selected_activity['scope_id'] !== '' ? $selected_activity['scope_id'] : 'Org-wide' }}</div></div>
                <div class="metric-card"><div class="metric-label">Owners</div><div class="metric-value" style="font-size:20px;">{{ count($selected_activity['owner_assignments']) }}</div></div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Overview</div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($selected_activity['owner_assignments'] as $owner)
                            <div class="data-item">
                                <div class="entity-title">{{ $owner['display_name'] }}</div>
                                <div class="table-note">{{ $owner['kind'] }}</div>
                                @if ($can_manage_privacy)
                                    <form method="POST" action="{{ str_replace('__ASSIGNMENT__', $owner['assignment_id'], $selected_activity['owner_remove_route']) }}" style="margin-top:8px;">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="menu" value="plugin.data-flows-privacy.activities">
                                        <input type="hidden" name="activity_id" value="{{ $selected_activity['id'] }}">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <button class="button button-ghost" type="submit">Remove owner</button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <span class="muted-note">No owner assigned</span>
                        @endforelse
                    </div>
                    <div class="table-note" style="margin-top:10px;">
                        Data flow:
                        @if ($selected_activity['linked_data_flow_url'] !== null)
                            <a href="{{ $selected_activity['linked_data_flow_url'] }}">{{ $selected_activity['linked_data_flow_label'] ?? $selected_activity['linked_data_flow_ids'] }}</a>
                        @else
                            {{ $selected_activity['linked_data_flow_ids'] !== '' ? $selected_activity['linked_data_flow_ids'] : 'None' }}
                        @endif
                    </div>
                    <div class="table-note">
                        Risk:
                        @if ($selected_activity['linked_risk_url'] !== null)
                            <a href="{{ $selected_activity['linked_risk_url'] }}">{{ $selected_activity['linked_risk_label'] ?? $selected_activity['linked_risk_ids'] }}</a>
                        @else
                            {{ $selected_activity['linked_risk_ids'] !== '' ? $selected_activity['linked_risk_ids'] : 'None' }}
                        @endif
                    </div>
                    <div class="table-note">
                        Policy:
                        @if ($selected_activity['linked_policy_url'] !== null)
                            <a href="{{ $selected_activity['linked_policy_url'] }}">{{ $selected_activity['linked_policy_label'] ?? $selected_activity['linked_policy_id'] }}</a>
                        @else
                            {{ $selected_activity['linked_policy_id'] !== '' ? $selected_activity['linked_policy_id'] : 'None' }}
                        @endif
                    </div>
                    <div class="table-note">
                        Finding:
                        @if ($selected_activity['linked_finding_url'] !== null)
                            <a href="{{ $selected_activity['linked_finding_url'] }}">{{ $selected_activity['linked_finding_label'] ?? $selected_activity['linked_finding_id'] }}</a>
                        @else
                            {{ $selected_activity['linked_finding_id'] !== '' ? $selected_activity['linked_finding_id'] : 'None' }}
                        @endif
                    </div>
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Workflow</div>
                    @if ($selected_activity['transitions'] !== [])
                        <div class="action-cluster" style="margin-top:10px;">
                            @foreach ($selected_activity['transitions'] as $transition)
                                <form method="POST" action="{{ str_replace('__TRANSITION__', $transition, $selected_activity['transition_route']) }}">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.data-flows-privacy.activities">
                                    <input type="hidden" name="activity_id" value="{{ $selected_activity['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <button class="button button-secondary" type="submit">{{ ucwords(str_replace('-', ' ', $transition)) }}</button>
                                </form>
                            @endforeach
                        </div>
                    @else
                        <div class="table-note" style="margin-top:10px;">View-only access</div>
                    @endif

                    <div class="data-stack" style="margin-top:12px;">
                        @forelse ($selected_activity['history'] as $history)
                            <div class="data-item">
                                <div class="entity-title">{{ $history->transitionKey }}</div>
                                <div class="table-note">{{ $history->fromState }} → {{ $history->toState }}</div>
                            </div>
                        @empty
                            <span class="muted-note">No transitions recorded yet</span>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="row-between">
                        <div class="metric-label">Evidence</div>
                        @if ($can_manage_privacy)
                            <details>
                                <summary class="button button-ghost" style="display:inline-flex;">Attach record</summary>
                                <form class="upload-form" method="POST" action="{{ $selected_activity['artifact_upload_route'] }}" enctype="multipart/form-data" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.data-flows-privacy.activities">
                                    <input type="hidden" name="activity_id" value="{{ $selected_activity['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <input type="hidden" name="artifact_type" value="record">
                                    <input class="field-input" type="text" name="label" placeholder="Record label">
                                    <input class="field-input" type="file" name="artifact" required>
                                    <button class="button button-secondary" type="submit">Upload record</button>
                                </form>
                            </details>
                        @endif
                    </div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($selected_activity['artifacts'] as $artifact)
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
                                        <input type="hidden" name="scope_id" value="{{ $selected_activity['scope_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <button class="button button-ghost" type="submit">Promote to evidence</button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <span class="muted-note">No records yet</span>
                        @endforelse
                    </div>
                </div>

                @if ($can_manage_privacy)
                    <div class="surface-card" style="padding:14px;">
                        <details>
                        <summary class="button button-ghost" style="display:inline-flex; width:fit-content;">Edit activity details</summary>
                        <form class="upload-form" method="POST" action="{{ $selected_activity['update_route'] }}" style="margin-top:14px;">
                            @csrf
                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                            <input type="hidden" name="menu" value="plugin.data-flows-privacy.activities">
                            <input type="hidden" name="activity_id" value="{{ $selected_activity['id'] }}">
                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                                <div class="field">
                                    <label class="field-label">Title</label>
                                    <input class="field-input" name="title" value="{{ $selected_activity['title'] }}" required>
                                </div>
                                <div class="field">
                                    <label class="field-label">Lawful basis</label>
                                    <select class="field-select" name="lawful_basis" required>
                                        @foreach ($lawful_basis_options as $option)
                                            <option value="{{ $option['id'] }}" @selected($selected_activity['lawful_basis'] === $option['id'])>{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">Review due</label>
                                    <input class="field-input" name="review_due_on" type="date" value="{{ $selected_activity['review_due_on'] }}">
                                </div>
                                <div class="field">
                                    <label class="field-label">Scope</label>
                                    <select class="field-select" name="scope_id">
                                        <option value="">Organization-wide</option>
                                        @foreach ($scope_options as $scope)
                                            <option value="{{ $scope['id'] }}" @selected($selected_activity['scope_id'] === $scope['id'])>{{ $scope['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">Linked data flow</label>
                                    <select class="field-select" name="linked_data_flow_ids">
                                        <option value="">No linked data flow</option>
                                        @foreach ($data_flow_options as $flow)
                                            <option value="{{ $flow['id'] }}" @selected($selected_activity['linked_data_flow_ids'] === $flow['id'])>{{ $flow['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">Linked risk</label>
                                    <select class="field-select" name="linked_risk_ids">
                                        <option value="">No linked risk</option>
                                        @foreach ($risk_options as $risk)
                                            <option value="{{ $risk['id'] }}" @selected($selected_activity['linked_risk_ids'] === $risk['id'])>{{ $risk['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">Linked policy</label>
                                    <select class="field-select" name="linked_policy_id">
                                        <option value="">No linked policy</option>
                                        @foreach ($policy_options as $policy)
                                            <option value="{{ $policy['id'] }}" @selected($selected_activity['linked_policy_id'] === $policy['id'])>{{ $policy['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">Linked finding</label>
                                    <select class="field-select" name="linked_finding_id">
                                        <option value="">No linked finding</option>
                                        @foreach ($finding_options as $finding)
                                            <option value="{{ $finding['id'] }}" @selected($selected_activity['linked_finding_id'] === $finding['id'])>{{ $finding['label'] }}</option>
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
                                @if (($selected_activity['owner_assignments'] ?? []) !== [])
                                    <div class="field" style="grid-column:1 / -1;">
                                        <label class="field-label">Current owners</label>
                                        <div class="data-stack">
                                            @foreach ($selected_activity['owner_assignments'] as $owner)
                                                <div class="data-item">
                                                    <div class="entity-title">{{ $owner['display_name'] }}</div>
                                                    <div class="table-note">{{ $owner['kind'] }}</div>
                                                    <form method="POST" action="{{ str_replace('__ASSIGNMENT__', $owner['assignment_id'], $selected_activity['owner_remove_route']) }}" style="margin-top:8px;">
                                                        @csrf
                                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                                        <input type="hidden" name="menu" value="plugin.data-flows-privacy.activities">
                                                        <input type="hidden" name="activity_id" value="{{ $selected_activity['id'] }}">
                                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                                        <button class="button button-ghost" type="submit">Remove owner</button>
                                                    </form>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                                <div class="field" style="grid-column:1 / -1;">
                                    <label class="field-label">Purpose</label>
                                    <input class="field-input" name="purpose" value="{{ $selected_activity['purpose'] }}" required>
                                </div>
                            </div>
                            <div class="action-cluster" style="margin-top:14px;">
                                <button class="button button-secondary" type="submit">Save changes</button>
                            </div>
                        </form>
                        </details>
                    </div>
                @endif
            </div>
        </div>
    @else
        <div class="surface-card">
            <div class="entity-title">Processing activity list</div>
            <div class="table-note" style="margin-top:6px;">This list stays focused on lawful basis, owner summary, linked records, review due, state, and Open. Use Processing Activity Detail to manage records, workflow, and linked object maintenance.</div>
        </div>

        @if ($can_manage_privacy)
            <div class="surface-card" id="privacy-activity-editor" hidden>
                <div class="row-between" style="margin-bottom:14px;">
                    <div>
                        <div class="eyebrow">Create</div>
                        <div class="entity-title" style="font-size:24px;">New processing activity</div>
                    </div>
                </div>

                <form class="upload-form" method="POST" action="{{ $create_route }}">
                    @csrf
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                    <input type="hidden" name="menu" value="plugin.data-flows-privacy.activities">
                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">

                    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                        <div class="field">
                            <label class="field-label" for="activity-title">Title</label>
                            <input class="field-input" id="activity-title" name="title" required>
                        </div>
                        <div class="field">
                            <label class="field-label" for="activity-lawful-basis">Lawful basis</label>
                            <select class="field-select" id="activity-lawful-basis" name="lawful_basis" required>
                                <option value="">Choose lawful basis</option>
                                @foreach ($lawful_basis_options as $option)
                                    <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="activity-review-due">Review due</label>
                            <input class="field-input" id="activity-review-due" name="review_due_on" type="date">
                        </div>
                        <div class="field">
                            <label class="field-label" for="activity-scope">Scope</label>
                            <select class="field-select" id="activity-scope" name="scope_id">
                                <option value="">Organization-wide</option>
                                @foreach ($scope_options as $scope)
                                    <option value="{{ $scope['id'] }}" @selected(($query['scope_id'] ?? null) === $scope['id'])>{{ $scope['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="activity-data-flow">Linked data flow</label>
                            <select class="field-select" id="activity-data-flow" name="linked_data_flow_ids">
                                <option value="">No linked data flow</option>
                                @foreach ($data_flow_options as $flow)
                                    <option value="{{ $flow['id'] }}">{{ $flow['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="activity-risk">Linked risk</label>
                            <select class="field-select" id="activity-risk" name="linked_risk_ids">
                                <option value="">No linked risk</option>
                                @foreach ($risk_options as $risk)
                                    <option value="{{ $risk['id'] }}">{{ $risk['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="activity-policy">Linked policy</label>
                            <select class="field-select" id="activity-policy" name="linked_policy_id">
                                <option value="">No linked policy</option>
                                @foreach ($policy_options as $policy)
                                    <option value="{{ $policy['id'] }}">{{ $policy['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="activity-finding">Linked finding</label>
                            <select class="field-select" id="activity-finding" name="linked_finding_id">
                                <option value="">No linked finding</option>
                                @foreach ($finding_options as $finding)
                                    <option value="{{ $finding['id'] }}">{{ $finding['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="activity-owner">Initial owner actor</label>
                            <select class="field-select" id="activity-owner" name="owner_actor_id">
                                <option value="">No owner</option>
                                @foreach ($owner_actor_options as $actor)
                                    <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field" style="grid-column:1 / -1;">
                            <label class="field-label" for="activity-purpose">Purpose</label>
                            <input class="field-input" id="activity-purpose" name="purpose" required>
                        </div>
                    </div>

                    <div class="action-cluster" style="margin-top:14px;">
                        <button class="button button-primary" type="submit">Create activity</button>
                    </div>
                </form>
            </div>
        @endif

        <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
            <div class="metric-card"><div class="metric-label">Activities</div><div class="metric-value">{{ count($activities) }}</div></div>
            <div class="metric-card"><div class="metric-label">Active</div><div class="metric-value">{{ collect($activities)->where('state', 'active')->count() }}</div></div>
            <div class="metric-card"><div class="metric-label">Under review</div><div class="metric-value">{{ collect($activities)->where('state', 'review')->count() }}</div></div>
            <div class="metric-card"><div class="metric-label">Evidence</div><div class="metric-value">{{ collect($activities)->sum(fn ($activity) => count($activity['artifacts'])) }}</div></div>
        </div>

        <div class="surface-card">
            <div class="table-note">Open a processing activity to manage records, workflow and linked privacy obligations.</div>
        </div>

        <div class="table-card">
            <table class="entity-table">
                <thead>
                    <tr>
                        <th>Processing activity</th>
                        <th>Owner</th>
                        <th>Data flow</th>
                        <th>Risk</th>
                        <th>Policy</th>
                        <th>Finding</th>
                        <th>Review due</th>
                        <th>State</th>
                        <th>{{ $can_manage_privacy ? 'Actions' : 'Access' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($activities as $activity)
                        <tr>
                            <td>
                                <div class="entity-title">{{ $activity['title'] }}</div>
                                <div class="entity-id">{{ $activity['id'] }} · {{ $activity['lawful_basis_label'] }}</div>
                                <div class="table-note">{{ $activity['purpose'] }}</div>
                            </td>
                            <td>
                                @if (($activity['owner_assignments'] ?? []) !== [])
                                    <div>{{ $activity['owner_assignments'][0]['display_name'] }}</div>
                                    @if (count($activity['owner_assignments']) > 1)
                                        <div class="table-note">+{{ count($activity['owner_assignments']) - 1 }} more owner{{ count($activity['owner_assignments']) > 2 ? 's' : '' }}</div>
                                    @else
                                        <div class="table-note">{{ $activity['owner_assignments'][0]['kind'] }}</div>
                                    @endif
                                @else
                                    <span class="muted-note">No owner assigned</span>
                                @endif
                            </td>
                            <td>
                                @if ($activity['linked_data_flow_url'] !== null)
                                    <a href="{{ $activity['linked_data_flow_url'] }}">{{ $activity['linked_data_flow_label'] ?? $activity['linked_data_flow_ids'] }}</a>
                                @else
                                    <span class="muted-note">{{ $activity['linked_data_flow_ids'] !== '' ? $activity['linked_data_flow_ids'] : 'None' }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($activity['linked_risk_url'] !== null)
                                    <a href="{{ $activity['linked_risk_url'] }}">{{ $activity['linked_risk_label'] ?? $activity['linked_risk_ids'] }}</a>
                                @else
                                    <span class="muted-note">{{ $activity['linked_risk_ids'] !== '' ? $activity['linked_risk_ids'] : 'None' }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($activity['linked_policy_url'] !== null)
                                    <a href="{{ $activity['linked_policy_url'] }}">{{ $activity['linked_policy_label'] ?? $activity['linked_policy_id'] }}</a>
                                @else
                                    <span class="muted-note">{{ $activity['linked_policy_id'] !== '' ? $activity['linked_policy_id'] : 'None' }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($activity['linked_finding_url'] !== null)
                                    <a href="{{ $activity['linked_finding_url'] }}">{{ $activity['linked_finding_label'] ?? $activity['linked_finding_id'] }}</a>
                                @else
                                    <span class="muted-note">{{ $activity['linked_finding_id'] !== '' ? $activity['linked_finding_id'] : 'None' }}</span>
                                @endif
                            </td>
                            <td>{{ $activity['review_due_on'] !== '' ? $activity['review_due_on'] : 'No review date' }}</td>
                            <td>
                                @php $sActPill = match($activity['state']) { 'active' => 'pill-active', 'review' => 'pill-review', 'draft' => 'pill-draft', 'archived' => 'pill-archived', default => '' }; @endphp
                                <span class="pill {{ $sActPill }}">{{ $activity['state'] }}</span>
                            </td>
                            <td>
                                <a class="button button-secondary" href="{{ $activity['open_url'] }}&{{ http_build_query(['context_label' => 'Activities', 'context_back_url' => $activities_list_url]) }}">Open</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
