<section class="module-screen">
    @if (is_array($selected_exception))
        <div class="surface-card" style="padding:16px; display:grid; gap:16px;">
            <div class="row-between" style="align-items:flex-start;">
                <div>
                    <div class="eyebrow">Exception</div>
                    <h2 class="screen-title" style="font-size:28px;">{{ $selected_exception['title'] }}</h2>
                    <div class="table-note">{{ $selected_exception['id'] }}</div>
                    <div class="table-note">{{ $selected_exception['policy']['title'] }}</div>
                </div>
                <div class="action-cluster">
                    <a class="button button-ghost" href="{{ $exceptions_list_url }}">Back to exceptions</a>
                    <span class="pill">{{ $selected_exception['state'] }}</span>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
                <div class="metric-card"><div class="metric-label">Evidence</div><div class="metric-value">{{ count($selected_exception['artifacts']) }}</div></div>
                <div class="metric-card"><div class="metric-label">Expires</div><div class="metric-value" style="font-size:20px;">{{ $selected_exception['expires_on'] !== '' ? $selected_exception['expires_on'] : 'No date' }}</div></div>
                <div class="metric-card"><div class="metric-label">Scope</div><div class="metric-value" style="font-size:20px;">{{ $selected_exception['scope_id'] !== '' ? $selected_exception['scope_id'] : 'Org-wide' }}</div></div>
                <div class="metric-card"><div class="metric-label">Owner</div><div class="metric-value" style="font-size:20px;">{{ $selected_exception['owner_assignment']['display_name'] ?? 'Unassigned' }}</div></div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Overview</div>
                    <div class="table-note" style="margin-top:10px;">{{ $selected_exception['rationale'] }}</div>
                    <div class="table-note">Compensating control: {{ $selected_exception['compensating_control'] !== '' ? $selected_exception['compensating_control'] : 'Not defined' }}</div>
                    <div class="table-note">Policy: <a href="{{ $selected_exception['policy_url'] }}">{{ $selected_exception['policy']['title'] }}</a></div>
                    <div class="table-note">
                        Finding:
                        @if ($selected_exception['linked_finding_url'] !== null)
                            <a href="{{ $selected_exception['linked_finding_url'] }}">{{ $selected_exception['linked_finding_label'] ?? $selected_exception['linked_finding_id'] }}</a>
                        @else
                            {{ $selected_exception['linked_finding_id'] !== '' ? $selected_exception['linked_finding_id'] : 'None' }}
                        @endif
                    </div>
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Workflow</div>
                    @if ($selected_exception['transitions'] !== [])
                        <div class="action-cluster" style="margin-top:10px;">
                            @foreach ($selected_exception['transitions'] as $transition)
                                <form method="POST" action="{{ str_replace('__TRANSITION__', $transition, $selected_exception['transition_route']) }}">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.policy-exceptions.exceptions">
                                    <input type="hidden" name="exception_id" value="{{ $selected_exception['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <button class="button button-secondary" type="submit">{{ ucwords(str_replace('-', ' ', $transition)) }}</button>
                                </form>
                            @endforeach
                        </div>
                    @else
                        <div class="table-note" style="margin-top:10px;">View-only access</div>
                    @endif

                    <div class="data-stack" style="margin-top:12px;">
                        @forelse ($selected_exception['history'] as $history)
                            <div class="data-item">
                                <div class="entity-title">{{ $history->transitionKey }}</div>
                                <div class="table-note">{{ $history->fromState }} -> {{ $history->toState }}</div>
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
                        @if ($can_manage_policies)
                            <details>
                                <summary class="button button-ghost" style="display:inline-flex;">Attach evidence</summary>
                                <form class="upload-form" method="POST" action="{{ $selected_exception['artifact_upload_route'] }}" enctype="multipart/form-data" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.policy-exceptions.exceptions">
                                    <input type="hidden" name="exception_id" value="{{ $selected_exception['id'] }}">
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
                        @forelse ($selected_exception['artifacts'] as $artifact)
                            <div class="data-item">
                                <div class="entity-title">{{ $artifact['label'] }}</div>
                                <div class="table-note">{{ $artifact['original_filename'] }}</div>
                            </div>
                        @empty
                            <span class="muted-note">No evidence yet</span>
                        @endforelse
                    </div>
                </div>

                @if ($can_manage_policies)
                    <div class="surface-card" style="padding:14px;">
                        <div class="metric-label">Edit exception</div>
                        <form class="upload-form" method="POST" action="{{ $selected_exception['update_route'] }}" style="margin-top:10px;">
                            @csrf
                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                            <input type="hidden" name="menu" value="plugin.policy-exceptions.exceptions">
                            <input type="hidden" name="exception_id" value="{{ $selected_exception['id'] }}">
                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                            <div class="field">
                                <label class="field-label">Title</label>
                                <input class="field-input" name="title" value="{{ $selected_exception['title'] }}" required>
                            </div>
                            <div class="field">
                                <label class="field-label">Linked finding</label>
                                <select class="field-select" name="linked_finding_id">
                                    <option value="">No linked finding</option>
                                    @foreach ($finding_options as $finding)
                                        <option value="{{ $finding['id'] }}" @selected($selected_exception['linked_finding_id'] === $finding['id'])>{{ $finding['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label class="field-label">Expires on</label>
                                <input class="field-input" name="expires_on" type="date" value="{{ $selected_exception['expires_on'] }}">
                            </div>
                            <div class="field">
                                <label class="field-label">Scope</label>
                                <select class="field-select" name="scope_id">
                                    <option value="">Organization-wide</option>
                                    @foreach ($scope_options as $scope)
                                        <option value="{{ $scope['id'] }}" @selected($selected_exception['scope_id'] === $scope['id'])>{{ $scope['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label class="field-label">Owner actor</label>
                                <select class="field-select" name="owner_actor_id">
                                    <option value="">Keep current owner</option>
                                    @foreach ($owner_actor_options as $actor)
                                        <option value="{{ $actor['id'] }}" @selected(($selected_exception['owner_assignment']['id'] ?? null) === $actor['id'])>{{ $actor['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label class="field-label">Rationale</label>
                                <textarea class="field-input" name="rationale" rows="2" required>{{ $selected_exception['rationale'] }}</textarea>
                            </div>
                            <div class="field">
                                <label class="field-label">Compensating control</label>
                                <textarea class="field-input" name="compensating_control" rows="2">{{ $selected_exception['compensating_control'] }}</textarea>
                            </div>
                            <div class="action-cluster">
                                <button class="button button-secondary" type="submit">Save exception</button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    @else
        <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
            <div class="metric-card"><div class="metric-label">Exceptions</div><div class="metric-value">{{ count($exceptions) }}</div></div>
            <div class="metric-card"><div class="metric-label">Requested</div><div class="metric-value">{{ collect($exceptions)->where('state', 'requested')->count() }}</div></div>
            <div class="metric-card"><div class="metric-label">Approved</div><div class="metric-value">{{ collect($exceptions)->where('state', 'approved')->count() }}</div></div>
            <div class="metric-card"><div class="metric-label">Expired or revoked</div><div class="metric-value">{{ collect($exceptions)->whereIn('state', ['expired', 'revoked'])->count() }}</div></div>
        </div>

        <div class="surface-card">
            <div class="table-note">Open an exception to manage workflow, evidence, linked findings and approvals.</div>
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
                                <a href="{{ $exception['policy_url'] }}">{{ $exception['policy']['title'] }}</a>
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
                            <td>
                                @if ($exception['linked_finding_url'] !== null)
                                    <a href="{{ $exception['linked_finding_url'] }}">{{ $exception['linked_finding_label'] ?? $exception['linked_finding_id'] }}</a>
                                @else
                                    <span class="muted-note">{{ $exception['linked_finding_id'] !== '' ? $exception['linked_finding_id'] : 'No linked finding' }}</span>
                                @endif
                            </td>
                            <td>{{ $exception['expires_on'] !== '' ? $exception['expires_on'] : 'No expiry set' }}</td>
                            <td><span class="pill">{{ $exception['state'] }}</span></td>
                            <td>
                                <a class="button button-secondary" href="{{ $exception['open_url'] }}">Edit details</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
