<section class="module-screen">
    <div class="overview-grid">
        <div class="metric-card"><div class="metric-label">Actions</div><div class="metric-value">{{ count($actions) }}</div></div>
        <div class="metric-card"><div class="metric-label">Planned</div><div class="metric-value">{{ collect($actions)->where('status', 'planned')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">In Progress</div><div class="metric-value">{{ collect($actions)->where('status', 'in-progress')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">Done</div><div class="metric-value">{{ collect($actions)->where('status', 'done')->count() }}</div></div>
    </div>

    <div class="table-card">
        <table class="entity-table">
            <thead>
                <tr>
                    <th>Action</th>
                    <th>Finding</th>
                    <th>Status</th>
                    <th>Owner</th>
                    <th>Due</th>
                    <th>Notes</th>
                    <th>{{ $can_manage_findings ? 'Update' : 'Access' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($actions as $action)
                    <tr>
                        <td>
                            <div class="entity-title">{{ $action['title'] }}</div>
                            <div class="entity-id">{{ $action['id'] }}</div>
                        </td>
                        <td>
                            <div>{{ $action['finding']['title'] }}</div>
                            <div class="table-note">{{ $action['finding']['id'] }}</div>
                        </td>
                        <td><span class="pill">{{ $action['status'] }}</span></td>
                        <td>
                            @if ($action['owner_assignment'] !== null)
                                <div>{{ $action['owner_assignment']['display_name'] }}</div>
                                <div class="table-note">{{ $action['owner_assignment']['kind'] }}</div>
                            @else
                                <span class="muted-note">No owner assigned</span>
                            @endif
                        </td>
                        <td>{{ $action['due_on'] !== '' ? $action['due_on'] : 'No target date' }}</td>
                        <td>{{ $action['notes'] !== '' ? $action['notes'] : 'No notes yet' }}</td>
                        <td>
                            @if ($can_manage_findings)
                                <form class="upload-form" method="POST" action="{{ $action['update_route'] }}">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.findings-remediation.board">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <div class="field">
                                        <label class="field-label">Title</label>
                                        <input class="field-input" name="title" value="{{ $action['title'] }}" required>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Status</label>
                                        <select class="field-select" name="status" required>
                                            @foreach (['planned', 'in-progress', 'blocked', 'done'] as $status)
                                                <option value="{{ $status }}" @selected($action['status'] === $status)>{{ ucfirst($status) }}</option>
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
                                        <label class="field-label">Owner actor</label>
                                        <select class="field-select" name="owner_actor_id">
                                            <option value="">Keep current owner</option>
                                            @foreach ($owner_actor_options as $actor)
                                                <option value="{{ $actor['id'] }}" @selected(($action['owner_assignment']['id'] ?? null) === $actor['id'])>{{ $actor['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Notes</label>
                                        <textarea class="field-input" name="notes" rows="2">{{ $action['notes'] }}</textarea>
                                    </div>
                                    <div class="action-cluster">
                                        <button class="button button-secondary" type="submit">Save Action</button>
                                    </div>
                                </form>
                            @else
                                <span class="muted-note">View-only access</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
