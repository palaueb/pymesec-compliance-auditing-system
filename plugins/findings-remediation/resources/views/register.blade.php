<style>
    .pill-open        { background: rgba(239,68,68,0.12);  color: #991b1b; }
    .pill-remediating { background: rgba(245,158,11,0.14); color: #92400e; }
    .pill-closed      { background: rgba(31,42,34,0.06);   color: var(--muted); }
    .pill-archived    { background: rgba(31,42,34,0.06);   color: var(--muted); }

    .pill-critical    { background: rgba(239,68,68,0.18);  color: #7f1d1d; }
    .pill-high        { background: rgba(239,68,68,0.12);  color: #991b1b; }
    .pill-medium      { background: rgba(245,158,11,0.12); color: #92400e; }
    .pill-low         { background: rgba(34,197,94,0.12);  color: #166534; }

    .pill-planned     { background: rgba(31,42,34,0.06);   color: var(--muted); }
    .pill-in-progress { background: rgba(245,158,11,0.12); color: #92400e; }
    .pill-blocked     { background: rgba(239,68,68,0.12);  color: #991b1b; }
    .pill-done        { background: rgba(34,197,94,0.12);  color: #166534; }

    details > summary { cursor: pointer; list-style: none; }
    details > summary::-webkit-details-marker { display: none; }
</style>

<section class="module-screen">
    @if (is_array($selected_finding))
        <div class="surface-card" style="padding:16px; display:grid; gap:16px;">
            <div class="row-between" style="align-items:flex-start;">
                <div>
                    <div class="eyebrow">Finding</div>
                    <h2 class="screen-title" style="font-size:28px;">{{ $selected_finding['title'] }}</h2>
                    <div class="table-note">{{ $selected_finding['id'] }}</div>
                    <div class="table-note">{{ $selected_finding['severity_label'] }} severity</div>
                </div>
                <div class="action-cluster">
                    @php
                        $findingStatePill = match($selected_finding['state']) { 'open' => 'pill-open', 'remediating' => 'pill-remediating', 'closed' => 'pill-closed', 'archived' => 'pill-archived', default => '' };
                        $severityPill = match($selected_finding['severity']) { 'critical' => 'pill-critical', 'high' => 'pill-high', 'medium' => 'pill-medium', 'low' => 'pill-low', default => '' };
                    @endphp
                    <span class="pill {{ $findingStatePill }}">{{ $selected_finding['state'] }}</span>
                    <span class="pill {{ $severityPill }}">{{ $selected_finding['severity_label'] }}</span>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
                <div class="metric-card"><div class="metric-label">Actions</div><div class="metric-value">{{ $selected_finding['action_count'] }}</div></div>
                <div class="metric-card"><div class="metric-label">Actions open</div><div class="metric-value">{{ $selected_finding['open_action_count'] }}</div></div>
                <div class="metric-card"><div class="metric-label">Evidence</div><div class="metric-value">{{ count($selected_finding['artifacts']) }}</div></div>
                <div class="metric-card"><div class="metric-label">Due</div><div class="metric-value" style="font-size:20px;">{{ $selected_finding['due_on'] !== '' ? $selected_finding['due_on'] : 'No date' }}</div></div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Overview</div>
                    <div class="table-note" style="margin-top:10px;">{{ $selected_finding['description'] }}</div>
                    <div class="table-note">Owners: {{ count($selected_finding['owner_assignments']) }}</div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($selected_finding['owner_assignments'] as $owner)
                            <div class="data-item">
                                <div class="entity-title">{{ $owner['display_name'] }}</div>
                                <div class="table-note">{{ $owner['kind'] }}</div>
                                @if ($can_manage_findings)
                                    <form method="POST" action="{{ str_replace('__ASSIGNMENT__', $owner['assignment_id'], $selected_finding['owner_remove_route']) }}" style="margin-top:8px;">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="menu" value="plugin.findings-remediation.root">
                                        <input type="hidden" name="finding_id" value="{{ $selected_finding['id'] }}">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <button class="button button-ghost" type="submit">Remove owner</button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <span class="muted-note">No owner assigned</span>
                        @endforelse
                    </div>
                    <div class="table-note">
                        Control:
                        @if ($selected_finding['linked_control_url'] !== null)
                            <a href="{{ $selected_finding['linked_control_url'] }}">{{ $selected_finding['linked_control_label'] ?? $selected_finding['linked_control_id'] }}</a>
                        @else
                            {{ $selected_finding['linked_control_id'] !== '' ? $selected_finding['linked_control_id'] : 'None' }}
                        @endif
                    </div>
                    <div class="table-note">
                        Risk:
                        @if ($selected_finding['linked_risk_url'] !== null)
                            <a href="{{ $selected_finding['linked_risk_url'] }}">{{ $selected_finding['linked_risk_label'] ?? $selected_finding['linked_risk_id'] }}</a>
                        @else
                            {{ $selected_finding['linked_risk_id'] !== '' ? $selected_finding['linked_risk_id'] : 'None' }}
                        @endif
                    </div>
                    <div class="table-note">Scope: {{ $selected_finding['scope_id'] !== '' ? $selected_finding['scope_id'] : 'Organization-wide' }}</div>
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Workflow</div>
                    @if ($selected_finding['transitions'] !== [])
                        <div class="action-cluster" style="margin-top:10px;">
                            @foreach ($selected_finding['transitions'] as $transition)
                                <form method="POST" action="{{ str_replace('__TRANSITION__', $transition, $selected_finding['transition_route']) }}">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.findings-remediation.root">
                                    <input type="hidden" name="finding_id" value="{{ $selected_finding['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <button class="button button-secondary" type="submit">{{ ucwords(str_replace('-', ' ', $transition)) }}</button>
                                </form>
                            @endforeach
                        </div>
                    @else
                        <div class="table-note" style="margin-top:10px;">View-only access</div>
                    @endif

                    <div class="data-stack" style="margin-top:12px;">
                        @forelse ($selected_finding['history'] as $history)
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
                        @if ($can_manage_findings)
                            <details>
                                <summary class="button button-ghost" style="display:inline-flex;">Attach evidence</summary>
                                <form class="upload-form" method="POST" action="{{ $selected_finding['artifact_upload_route'] }}" enctype="multipart/form-data" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.findings-remediation.root">
                                    <input type="hidden" name="finding_id" value="{{ $selected_finding['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <input type="hidden" name="artifact_type" value="evidence">
                                    <input class="field-input" type="text" name="label" placeholder="Evidence label">
                                    <input class="field-input" type="file" name="artifact" required>
                                    <button class="button button-secondary" type="submit">Upload evidence</button>
                                </form>
                            </details>
                        @endif
                    </div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($selected_finding['artifacts'] as $artifact)
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
                                        <input type="hidden" name="scope_id" value="{{ $selected_finding['scope_id'] }}">
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

                <div class="surface-card" style="padding:14px;">
                    <div class="row-between">
                        <div class="metric-label">Remediation actions</div>
                        @if ($can_manage_findings)
                            <details>
                                <summary class="button button-ghost" style="display:inline-flex;">Add action</summary>
                                <form class="upload-form" method="POST" action="{{ $selected_finding['action_store_route'] }}" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.findings-remediation.root">
                                    <input type="hidden" name="finding_id" value="{{ $selected_finding['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <div class="field">
                                        <label class="field-label">Action title</label>
                                        <input class="field-input" name="title" required>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Status</label>
                                        <select class="field-select" name="status" required>
                                            @foreach ($action_status_options as $status)
                                                <option value="{{ $status['id'] }}">{{ $status['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Due date</label>
                                        <input class="field-input" name="due_on" type="date">
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Initial owner actor</label>
                                        <select class="field-select" name="owner_actor_id">
                                            <option value="">No owner</option>
                                            @foreach ($owner_actor_options as $actor)
                                                <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Notes</label>
                                        <textarea class="field-input" name="notes" rows="2"></textarea>
                                    </div>
                                    <div class="action-cluster">
                                        <button class="button button-secondary" type="submit">Create action</button>
                                    </div>
                                </form>
                            </details>
                        @endif
                    </div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($selected_finding['actions'] as $action)
                            <div class="data-item">
                                <div class="row-between" style="align-items:flex-start;">
                                    <div>
                                        <div class="entity-title">{{ $action['title'] }}</div>
                                        <div class="table-note">{{ $action['status_label'] }}{{ $action['due_on'] !== '' ? ' · due '.$action['due_on'] : '' }}</div>
                                        <div class="table-note">
                                            @if (($action['owner_assignments'] ?? []) !== [])
                                                {{ $action['owner_assignments'][0]['display_name'] }}{{ count($action['owner_assignments']) > 1 ? ' +'.(count($action['owner_assignments']) - 1).' more' : '' }}
                                            @else
                                                No owner assigned
                                            @endif
                                        </div>
                                        @if ($action['notes'] !== '')
                                            <div class="table-note">{{ $action['notes'] }}</div>
                                        @endif
                                    </div>
                                    @php $actionPill = match($action['status']) { 'planned' => 'pill-planned', 'in-progress' => 'pill-in-progress', 'blocked' => 'pill-blocked', 'done' => 'pill-done', default => '' }; @endphp
                                    <span class="pill {{ $actionPill }}">{{ $action['status_label'] }}</span>
                                </div>
                                @if ($can_manage_findings)
                                    <details style="margin-top:10px;">
                                        <summary class="button button-ghost" style="display:inline-flex;">Edit action</summary>
                                        <form class="upload-form" method="POST" action="{{ $action['update_route'] }}" style="margin-top:10px;">
                                            @csrf
                                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                            <input type="hidden" name="menu" value="plugin.findings-remediation.root">
                                            <input type="hidden" name="finding_id" value="{{ $selected_finding['id'] }}">
                                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                            <div class="field">
                                                <label class="field-label">Action title</label>
                                                <input class="field-input" name="title" value="{{ $action['title'] }}" required>
                                            </div>
                                            <div class="field">
                                                <label class="field-label">Status</label>
                                                <select class="field-select" name="status" required>
                                                    @foreach ($action_status_options as $status)
                                                        <option value="{{ $status['id'] }}" @selected($action['status'] === $status['id'])>{{ $status['label'] }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="field">
                                                <label class="field-label">Due date</label>
                                                <input class="field-input" name="due_on" type="date" value="{{ $action['due_on'] }}">
                                            </div>
                                            <div class="field">
                                                <label class="field-label">Scope</label>
                                                <select class="field-select" name="scope_id">
                                                    <option value="">Organization-wide</option>
                                                    @foreach ($scope_options as $scope)
                                                        <option value="{{ $scope['id'] }}" @selected($action['scope_id'] === $scope['id'])>{{ $scope['name'] }}</option>
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
                                            @if (($action['owner_assignments'] ?? []) !== [])
                                                <div class="field" style="grid-column:1 / -1;">
                                                    <label class="field-label">Current owners</label>
                                                    <div class="data-stack">
                                                        @foreach ($action['owner_assignments'] as $owner)
                                                            <div class="data-item">
                                                                <div class="entity-title">{{ $owner['display_name'] }}</div>
                                                                <div class="table-note">{{ $owner['kind'] }}</div>
                                                                <form method="POST" action="{{ str_replace('__ASSIGNMENT__', $owner['assignment_id'], $action['owner_remove_route']) }}" style="margin-top:8px;">
                                                                    @csrf
                                                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                                                    <input type="hidden" name="menu" value="plugin.findings-remediation.root">
                                                                    <input type="hidden" name="finding_id" value="{{ $selected_finding['id'] }}">
                                                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                                                    <button class="button button-ghost" type="submit">Remove owner</button>
                                                                </form>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                            <div class="field">
                                                <label class="field-label">Notes</label>
                                                <textarea class="field-input" name="notes" rows="2">{{ $action['notes'] }}</textarea>
                                            </div>
                                            <div class="action-cluster">
                                                <button class="button button-secondary" type="submit">Save action</button>
                                            </div>
                                        </form>
                                    </details>
                                @endif
                            </div>
                        @empty
                            <span class="muted-note">No remediation actions yet</span>
                        @endforelse
                    </div>
                </div>
            </div>

            @if ($can_manage_findings)
                <div class="surface-card" style="padding:14px;">
                    <hr style="border:none; border-top:1px solid rgba(31,42,34,0.07); margin:0 0 14px;">
                    <details>
                        <summary class="button button-ghost" style="display:inline-flex; width:fit-content;">Edit finding details</summary>
                        <form class="upload-form" method="POST" action="{{ $selected_finding['update_route'] }}" style="margin-top:14px;">
                            @csrf
                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                            <input type="hidden" name="menu" value="plugin.findings-remediation.root">
                            <input type="hidden" name="finding_id" value="{{ $selected_finding['id'] }}">
                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                                <div class="field">
                                    <label class="field-label">Title</label>
                                    <input class="field-input" name="title" value="{{ $selected_finding['title'] }}" required>
                                </div>
                                <div class="field">
                                    <label class="field-label">Severity</label>
                                    <select class="field-select" name="severity" required>
                                        @foreach ($severity_options as $severity)
                                            <option value="{{ $severity['id'] }}" @selected($selected_finding['severity'] === $severity['id'])>{{ $severity['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">Linked control</label>
                                    <select class="field-select" name="linked_control_id">
                                        <option value="">No linked control</option>
                                        @foreach ($control_options as $control)
                                            <option value="{{ $control['id'] }}" @selected($selected_finding['linked_control_id'] === $control['id'])>{{ $control['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">Linked risk</label>
                                    <select class="field-select" name="linked_risk_id">
                                        <option value="">No linked risk</option>
                                        @foreach ($risk_options as $risk)
                                            <option value="{{ $risk['id'] }}" @selected($selected_finding['linked_risk_id'] === $risk['id'])>{{ $risk['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">Due date</label>
                                    <input class="field-input" name="due_on" type="date" value="{{ $selected_finding['due_on'] }}">
                                </div>
                                <div class="field">
                                    <label class="field-label">Scope</label>
                                    <select class="field-select" name="scope_id">
                                        <option value="">Organization-wide</option>
                                        @foreach ($scope_options as $scope)
                                            <option value="{{ $scope['id'] }}" @selected($selected_finding['scope_id'] === $scope['id'])>{{ $scope['name'] }}</option>
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
                                @if (($selected_finding['owner_assignments'] ?? []) !== [])
                                    <div class="field" style="grid-column:1 / -1;">
                                        <label class="field-label">Current owners</label>
                                        <div class="data-stack">
                                            @foreach ($selected_finding['owner_assignments'] as $owner)
                                                <div class="data-item">
                                                    <div class="entity-title">{{ $owner['display_name'] }}</div>
                                                    <div class="table-note">{{ $owner['kind'] }}</div>
                                                    <form method="POST" action="{{ str_replace('__ASSIGNMENT__', $owner['assignment_id'], $selected_finding['owner_remove_route']) }}" style="margin-top:8px;">
                                                        @csrf
                                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                                        <input type="hidden" name="menu" value="plugin.findings-remediation.root">
                                                        <input type="hidden" name="finding_id" value="{{ $selected_finding['id'] }}">
                                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                                        <button class="button button-ghost" type="submit">Remove owner</button>
                                                    </form>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                                <div class="field" style="grid-column:1 / -1;">
                                    <label class="field-label">Description</label>
                                    <textarea class="field-input" name="description" rows="3" required>{{ $selected_finding['description'] }}</textarea>
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
    @else
        @if ($can_manage_findings)
            <div class="surface-card" id="finding-editor" hidden>
                <div class="row-between" style="margin-bottom:14px;">
                    <div>
                        <div class="eyebrow">Create</div>
                        <div class="entity-title" style="font-size:24px;">New finding</div>
                    </div>
                </div>

                <form class="upload-form" method="POST" action="{{ $create_route }}">
                    @csrf
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                    <input type="hidden" name="menu" value="plugin.findings-remediation.root">
                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">

                    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                        <div class="field">
                            <label class="field-label" for="finding-title">Title</label>
                            <input class="field-input" id="finding-title" name="title" required>
                        </div>
                        <div class="field">
                            <label class="field-label" for="finding-severity">Severity</label>
                            <select class="field-select" id="finding-severity" name="severity" required>
                                @foreach ($severity_options as $severity)
                                    <option value="{{ $severity['id'] }}" @selected($severity['id'] === 'medium')>{{ $severity['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="finding-control">Linked control</label>
                            <select class="field-select" id="finding-control" name="linked_control_id">
                                <option value="">No linked control</option>
                                @foreach ($control_options as $control)
                                    <option value="{{ $control['id'] }}">{{ $control['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="finding-risk">Linked risk</label>
                            <select class="field-select" id="finding-risk" name="linked_risk_id">
                                <option value="">No linked risk</option>
                                @foreach ($risk_options as $risk)
                                    <option value="{{ $risk['id'] }}">{{ $risk['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="finding-due-on">Due date</label>
                            <input class="field-input" id="finding-due-on" name="due_on" type="date">
                        </div>
                        <div class="field">
                            <label class="field-label" for="finding-scope">Scope</label>
                            <select class="field-select" id="finding-scope" name="scope_id">
                                <option value="">Organization-wide</option>
                                @foreach ($scope_options as $scope)
                                    <option value="{{ $scope['id'] }}" @selected(($query['scope_id'] ?? null) === $scope['id'])>{{ $scope['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="finding-owner">Initial owner actor</label>
                            <select class="field-select" id="finding-owner" name="owner_actor_id">
                                <option value="">No owner</option>
                                @foreach ($owner_actor_options as $actor)
                                    <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field" style="grid-column:1 / -1;">
                            <label class="field-label" for="finding-description">Description</label>
                            <textarea class="field-input" id="finding-description" name="description" rows="3" required></textarea>
                        </div>
                    </div>

                    <div class="action-cluster" style="margin-top:14px;">
                        <button class="button button-primary" type="submit">Create finding</button>
                    </div>
                </form>
            </div>
        @endif

        <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
            <div class="metric-card"><div class="metric-label">Findings</div><div class="metric-value">{{ count($findings) }}</div></div>
            <div class="metric-card"><div class="metric-label">Open</div><div class="metric-value">{{ collect($findings)->where('state', 'open')->count() }}</div></div>
            <div class="metric-card"><div class="metric-label">Remediating</div><div class="metric-value">{{ collect($findings)->where('state', 'remediating')->count() }}</div></div>
            <div class="metric-card"><div class="metric-label">Actions open</div><div class="metric-value">{{ collect($findings)->sum('open_action_count') }}</div></div>
        </div>

        <div class="surface-card">
            <div class="table-note">Open a finding to manage workflow, remediation actions, evidence and linked records.</div>
        </div>

        <div class="table-card">
            <table class="entity-table">
                <thead>
                    <tr>
                        <th>Finding</th>
                        <th>Severity</th>
                        <th>Owner</th>
                        <th>Control</th>
                        <th>Risk</th>
                        <th>Due</th>
                        <th>State</th>
                        <th>{{ $can_manage_findings ? 'Actions' : 'Access' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($findings as $finding)
                        <tr>
                            <td>
                                <div class="entity-title">{{ $finding['title'] }}</div>
                                <div class="entity-id">{{ $finding['id'] }}</div>
                                <div class="table-note">{{ $finding['description'] }}</div>
                                <div class="table-note">{{ $finding['action_count'] }} remediation actions</div>
                            </td>
                            <td>
                                @php $sSevPill = match($finding['severity']) { 'critical' => 'pill-critical', 'high' => 'pill-high', 'medium' => 'pill-medium', 'low' => 'pill-low', default => '' }; @endphp
                                <span class="pill {{ $sSevPill }}">{{ $finding['severity_label'] }}</span>
                            </td>
                            <td>
                                @if (($finding['owner_assignments'] ?? []) !== [])
                                    <div>{{ $finding['owner_assignments'][0]['display_name'] }}</div>
                                    @if (count($finding['owner_assignments']) > 1)
                                        <div class="table-note">+{{ count($finding['owner_assignments']) - 1 }} more owner{{ count($finding['owner_assignments']) > 2 ? 's' : '' }}</div>
                                    @else
                                        <div class="table-note">{{ $finding['owner_assignments'][0]['kind'] }}</div>
                                    @endif
                                @else
                                    <span class="muted-note">No owner assigned</span>
                                @endif
                            </td>
                            <td>
                                @if ($finding['linked_control_url'] !== null)
                                    <a href="{{ $finding['linked_control_url'] }}">{{ $finding['linked_control_label'] ?? $finding['linked_control_id'] }}</a>
                                @else
                                    <span class="muted-note">{{ $finding['linked_control_id'] !== '' ? $finding['linked_control_id'] : 'None' }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($finding['linked_risk_url'] !== null)
                                    <a href="{{ $finding['linked_risk_url'] }}">{{ $finding['linked_risk_label'] ?? $finding['linked_risk_id'] }}</a>
                                @else
                                    <span class="muted-note">{{ $finding['linked_risk_id'] !== '' ? $finding['linked_risk_id'] : 'None' }}</span>
                                @endif
                            </td>
                            <td>{{ $finding['due_on'] !== '' ? $finding['due_on'] : 'No target date' }}</td>
                            <td>
                                @php $sFindPill = match($finding['state']) { 'open' => 'pill-open', 'remediating' => 'pill-remediating', 'closed' => 'pill-closed', 'archived' => 'pill-archived', default => '' }; @endphp
                                <span class="pill {{ $sFindPill }}">{{ $finding['state'] }}</span>
                            </td>
                            <td>
                                <a class="button button-secondary" href="{{ $finding['open_url'] }}&{{ http_build_query(['context_label' => 'Findings', 'context_back_url' => $findings_list_url]) }}">Open</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
