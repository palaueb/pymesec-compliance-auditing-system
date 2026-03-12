<section class="module-screen">
    <div class="overview-grid">
        <div class="metric-card"><div class="metric-label">Exceptions</div><div class="metric-value">{{ count($exceptions) }}</div></div>
        <div class="metric-card"><div class="metric-label">Requested</div><div class="metric-value">{{ collect($exceptions)->where('state', 'requested')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">Approved</div><div class="metric-value">{{ collect($exceptions)->where('state', 'approved')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">Expired/Revoked</div><div class="metric-value">{{ collect($exceptions)->whereIn('state', ['expired', 'revoked'])->count() }}</div></div>
    </div>

    <div class="table-card">
        <table class="entity-table">
            <thead>
                <tr>
                    <th>Exception</th>
                    <th>Policy</th>
                    <th>Owner</th>
                    <th>Finding</th>
                    <th>Expires</th>
                    <th>Evidence</th>
                    <th>State</th>
                    <th>{{ $can_manage_policies ? 'Actions' : 'Access' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($exceptions as $exception)
                    <tr>
                        <td>
                            <div class="entity-title">{{ $exception['title'] }}</div>
                            <div class="entity-id">{{ $exception['id'] }}</div>
                            <div class="table-note">{{ $exception['rationale'] }}</div>
                            <div class="table-note">{{ $exception['compensating_control'] !== '' ? $exception['compensating_control'] : 'No compensating control defined' }}</div>
                        </td>
                        <td>
                            <div>{{ $exception['policy']['title'] }}</div>
                            <div class="table-note">{{ $exception['policy']['id'] }}</div>
                        </td>
                        <td>
                            @if ($exception['owner_assignment'] !== null)
                                <div>{{ $exception['owner_assignment']['display_name'] }}</div>
                                <div class="table-note">{{ $exception['owner_assignment']['kind'] }}</div>
                            @else
                                <span class="muted-note">No owner assigned</span>
                            @endif
                        </td>
                        <td>{{ $exception['linked_finding_id'] !== '' ? $exception['linked_finding_id'] : 'No linked finding' }}</td>
                        <td>{{ $exception['expires_on'] !== '' ? $exception['expires_on'] : 'No expiry set' }}</td>
                        <td>
                            @forelse ($exception['artifacts'] as $artifact)
                                <div class="data-item" style="margin-bottom:8px;">
                                    <div class="entity-title">{{ $artifact['label'] }}</div>
                                    <div class="table-note">{{ $artifact['original_filename'] }}</div>
                                </div>
                            @empty
                                <span class="muted-note">No evidence yet</span>
                            @endforelse
                            @if ($can_manage_policies)
                                <form class="upload-form" method="POST" action="{{ $exception['artifact_upload_route'] }}" enctype="multipart/form-data" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.policy-exceptions.exceptions">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <input type="hidden" name="artifact_type" value="evidence">
                                    <input type="text" name="label" placeholder="Evidence label">
                                    <input type="file" name="artifact" required>
                                    <button class="button button-secondary" type="submit">Attach Evidence</button>
                                </form>
                            @endif
                        </td>
                        <td><span class="pill">{{ $exception['state'] }}</span></td>
                        <td>
                            @if ($exception['transitions'] !== [])
                                <div class="action-cluster">
                                    @foreach ($exception['transitions'] as $transition)
                                        <form method="POST" action="{{ str_replace('__TRANSITION__', $transition, $exception['transition_route']) }}">
                                            @csrf
                                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                            <input type="hidden" name="menu" value="plugin.policy-exceptions.exceptions">
                                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                            <button class="button button-secondary" type="submit">{{ ucwords(str_replace('-', ' ', $transition)) }}</button>
                                        </form>
                                    @endforeach
                                </div>
                            @else
                                <span class="muted-note">View-only access</span>
                            @endif

                            @if ($can_manage_policies)
                                <details style="margin-top:10px;">
                                    <summary class="button button-ghost" style="display:inline-flex;">Edit</summary>
                                    <form class="upload-form" method="POST" action="{{ $exception['update_route'] }}" style="margin-top:10px;">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="menu" value="plugin.policy-exceptions.exceptions">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <div class="field">
                                            <label class="field-label">Title</label>
                                            <input class="field-input" name="title" value="{{ $exception['title'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Linked finding</label>
                                            <select class="field-select" name="linked_finding_id">
                                                <option value="">No linked finding</option>
                                                @foreach ($finding_options as $finding)
                                                    <option value="{{ $finding['id'] }}" @selected($exception['linked_finding_id'] === $finding['id'])>{{ $finding['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Expires on</label>
                                            <input class="field-input" name="expires_on" type="date" value="{{ $exception['expires_on'] }}">
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Scope</label>
                                            <select class="field-select" name="scope_id">
                                                <option value="">Organization-wide</option>
                                                @foreach ($scope_options as $scope)
                                                    <option value="{{ $scope['id'] }}" @selected($exception['scope_id'] === $scope['id'])>{{ $scope['name'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Owner actor</label>
                                            <select class="field-select" name="owner_actor_id">
                                                <option value="">Keep current owner</option>
                                                @foreach ($owner_actor_options as $actor)
                                                    <option value="{{ $actor['id'] }}" @selected(($exception['owner_assignment']['id'] ?? null) === $actor['id'])>{{ $actor['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Rationale</label>
                                            <textarea class="field-input" name="rationale" rows="2" required>{{ $exception['rationale'] }}</textarea>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Compensating control</label>
                                            <textarea class="field-input" name="compensating_control" rows="2">{{ $exception['compensating_control'] }}</textarea>
                                        </div>
                                        <div class="action-cluster">
                                            <button class="button button-secondary" type="submit">Save Exception</button>
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
