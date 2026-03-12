<section class="module-screen">
    @if ($can_manage_findings)
        <div class="surface-card" id="finding-editor">
            <div class="row-between" style="margin-bottom:14px;">
                <div>
                    <div class="eyebrow">Create</div>
                    <div class="screen-title" style="font-size:26px;">New Finding</div>
                </div>
            </div>

            <form class="upload-form" method="POST" action="{{ $create_route }}">
                @csrf
                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
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
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
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
                        <label class="field-label" for="finding-owner">Owner actor</label>
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
                    <button class="button button-primary" type="submit">Create Finding</button>
                </div>
            </form>
        </div>
    @endif

    <div class="overview-grid">
        <div class="metric-card"><div class="metric-label">Findings</div><div class="metric-value">{{ count($findings) }}</div></div>
        <div class="metric-card"><div class="metric-label">Open</div><div class="metric-value">{{ collect($findings)->where('state', 'open')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">Remediating</div><div class="metric-value">{{ collect($findings)->where('state', 'remediating')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">Actions Open</div><div class="metric-value">{{ collect($findings)->sum('open_action_count') }}</div></div>
    </div>

    <div class="table-card">
        <table class="entity-table">
            <thead>
                <tr>
                    <th>Finding</th>
                    <th>Severity</th>
                    <th>Owner</th>
                    <th>Links</th>
                    <th>Due</th>
                    <th>Evidence</th>
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
                        <td><span class="pill">{{ $finding['severity'] }}</span></td>
                        <td>
                            @if ($finding['owner_assignment'] !== null)
                                <div>{{ $finding['owner_assignment']['display_name'] }}</div>
                                <div class="table-note">{{ $finding['owner_assignment']['kind'] }}</div>
                            @else
                                <span class="muted-note">No owner assigned</span>
                            @endif
                        </td>
                        <td>
                            <div>Control: {{ $finding['linked_control_id'] !== '' ? $finding['linked_control_id'] : 'None' }}</div>
                            <div>Risk: {{ $finding['linked_risk_id'] !== '' ? $finding['linked_risk_id'] : 'None' }}</div>
                        </td>
                        <td>{{ $finding['due_on'] !== '' ? $finding['due_on'] : 'No target date' }}</td>
                        <td>
                            @forelse ($finding['artifacts'] as $artifact)
                                <div class="data-item" style="margin-bottom:8px;">
                                    <div class="entity-title">{{ $artifact['label'] }}</div>
                                    <div class="table-note">{{ $artifact['original_filename'] }}</div>
                                </div>
                            @empty
                                <span class="muted-note">No artifacts yet</span>
                            @endforelse
                            @if ($can_manage_findings)
                                <form class="upload-form" method="POST" action="{{ $finding['artifact_upload_route'] }}" enctype="multipart/form-data" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.findings-remediation.root">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <input type="hidden" name="artifact_type" value="evidence">
                                    <input type="text" name="label" placeholder="Evidence label">
                                    <input type="file" name="artifact" required>
                                    <button class="button button-secondary" type="submit">Attach Evidence</button>
                                </form>
                            @endif
                        </td>
                        <td><span class="pill">{{ $finding['state'] }}</span></td>
                        <td>
                            @if ($finding['transitions'] !== [])
                                <div class="action-cluster">
                                    @foreach ($finding['transitions'] as $transition)
                                        <form method="POST" action="{{ str_replace('__TRANSITION__', $transition, $finding['transition_route']) }}">
                                            @csrf
                                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                            <input type="hidden" name="menu" value="plugin.findings-remediation.root">
                                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                            <button class="button button-secondary" type="submit">{{ ucwords(str_replace('-', ' ', $transition)) }}</button>
                                        </form>
                                    @endforeach
                                </div>
                            @else
                                <span class="muted-note">View-only access</span>
                            @endif

                            @if ($can_manage_findings)
                                <details style="margin-top:10px;">
                                    <summary class="button button-ghost" style="display:inline-flex;">Edit</summary>
                                    <form class="upload-form" method="POST" action="{{ $finding['update_route'] }}" style="margin-top:10px;">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="menu" value="plugin.findings-remediation.root">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <div class="field">
                                            <label class="field-label">Title</label>
                                            <input class="field-input" name="title" value="{{ $finding['title'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Severity</label>
                                            <select class="field-select" name="severity" required>
                                                @foreach (['low', 'medium', 'high', 'critical'] as $severity)
                                                    <option value="{{ $severity }}" @selected($finding['severity'] === $severity)>{{ ucfirst($severity) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Linked control</label>
                                            <select class="field-select" name="linked_control_id">
                                                <option value="">No linked control</option>
                                                @foreach ($control_options as $control)
                                                    <option value="{{ $control['id'] }}" @selected($finding['linked_control_id'] === $control['id'])>{{ $control['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Linked risk</label>
                                            <select class="field-select" name="linked_risk_id">
                                                <option value="">No linked risk</option>
                                                @foreach ($risk_options as $risk)
                                                    <option value="{{ $risk['id'] }}" @selected($finding['linked_risk_id'] === $risk['id'])>{{ $risk['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Due date</label>
                                            <input class="field-input" name="due_on" type="date" value="{{ $finding['due_on'] }}">
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Scope</label>
                                            <select class="field-select" name="scope_id">
                                                <option value="">Organization-wide</option>
                                                @foreach ($scope_options as $scope)
                                                    <option value="{{ $scope['id'] }}" @selected($finding['scope_id'] === $scope['id'])>{{ $scope['name'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Owner actor</label>
                                            <select class="field-select" name="owner_actor_id">
                                                <option value="">Keep current owner</option>
                                                @foreach ($owner_actor_options as $actor)
                                                    <option value="{{ $actor['id'] }}" @selected(($finding['owner_assignment']['id'] ?? null) === $actor['id'])>{{ $actor['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Description</label>
                                            <textarea class="field-input" name="description" rows="3" required>{{ $finding['description'] }}</textarea>
                                        </div>
                                        <div class="action-cluster">
                                            <button class="button button-secondary" type="submit">Save Changes</button>
                                        </div>
                                    </form>
                                </details>

                                <details style="margin-top:10px;">
                                    <summary class="button button-ghost" style="display:inline-flex;">Add Remediation Action</summary>
                                    <form class="upload-form" method="POST" action="{{ $finding['action_store_route'] }}" style="margin-top:10px;">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="menu" value="plugin.findings-remediation.board">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <div class="field">
                                            <label class="field-label">Action title</label>
                                            <input class="field-input" name="title" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Status</label>
                                            <select class="field-select" name="status" required>
                                                @foreach (['planned', 'in-progress', 'blocked', 'done'] as $status)
                                                    <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Due date</label>
                                            <input class="field-input" name="due_on" type="date">
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Owner actor</label>
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
                                            <button class="button button-secondary" type="submit">Create Action</button>
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
