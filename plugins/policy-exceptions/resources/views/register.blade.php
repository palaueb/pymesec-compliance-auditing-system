<section class="module-screen">
    @if ($can_manage_policies)
        <div class="surface-card" id="policy-editor">
            <div class="row-between" style="margin-bottom:14px;">
                <div>
                    <div class="eyebrow">Create</div>
                    <div class="screen-title" style="font-size:26px;">New Policy</div>
                </div>
            </div>

            <form class="upload-form" method="POST" action="{{ $create_route }}">
                @csrf
                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                <input type="hidden" name="menu" value="plugin.policy-exceptions.root">
                <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">

                <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                    <div class="field">
                        <label class="field-label" for="policy-title">Title</label>
                        <input class="field-input" id="policy-title" name="title" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="policy-area">Area</label>
                        <input class="field-input" id="policy-area" name="area" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="policy-version">Version</label>
                        <input class="field-input" id="policy-version" name="version_label" value="v1.0" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="policy-review-due">Review due</label>
                        <input class="field-input" id="policy-review-due" name="review_due_on" type="date">
                    </div>
                    <div class="field">
                        <label class="field-label" for="policy-control">Linked control</label>
                        <select class="field-select" id="policy-control" name="linked_control_id">
                            <option value="">No linked control</option>
                            @foreach ($control_options as $control)
                                <option value="{{ $control['id'] }}">{{ $control['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="policy-scope">Scope</label>
                        <select class="field-select" id="policy-scope" name="scope_id">
                            <option value="">Organization-wide</option>
                            @foreach ($scope_options as $scope)
                                <option value="{{ $scope['id'] }}" @selected(($query['scope_id'] ?? null) === $scope['id'])>{{ $scope['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="policy-owner">Owner actor</label>
                        <select class="field-select" id="policy-owner" name="owner_actor_id">
                            <option value="">No owner</option>
                            @foreach ($owner_actor_options as $actor)
                                <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field" style="grid-column:1 / -1;">
                        <label class="field-label" for="policy-statement">Statement</label>
                        <textarea class="field-input" id="policy-statement" name="statement" rows="4" required></textarea>
                    </div>
                </div>

                <div class="action-cluster" style="margin-top:14px;">
                    <button class="button button-primary" type="submit">Create Policy</button>
                </div>
            </form>
        </div>
    @endif

    <div class="overview-grid">
        <div class="metric-card"><div class="metric-label">Policies</div><div class="metric-value">{{ count($policies) }}</div></div>
        <div class="metric-card"><div class="metric-label">Active</div><div class="metric-value">{{ collect($policies)->where('state', 'active')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">Under Review</div><div class="metric-value">{{ collect($policies)->where('state', 'review')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">Approved Exceptions</div><div class="metric-value">{{ collect($policies)->sum('active_exception_count') }}</div></div>
    </div>

    <div class="table-card">
        <table class="entity-table">
            <thead>
                <tr>
                    <th>Policy</th>
                    <th>Area</th>
                    <th>Owner</th>
                    <th>Linked Control</th>
                    <th>Review Due</th>
                    <th>Documents</th>
                    <th>State</th>
                    <th>{{ $can_manage_policies ? 'Actions' : 'Access' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($policies as $policy)
                    <tr>
                        <td>
                            <div class="entity-title">{{ $policy['title'] }}</div>
                            <div class="entity-id">{{ $policy['id'] }} · {{ $policy['version_label'] }}</div>
                            <div class="table-note">{{ $policy['statement'] }}</div>
                            <div class="table-note">{{ $policy['exception_count'] }} exceptions</div>
                        </td>
                        <td>{{ $policy['area'] }}</td>
                        <td>
                            @if ($policy['owner_assignment'] !== null)
                                <div>{{ $policy['owner_assignment']['display_name'] }}</div>
                                <div class="table-note">{{ $policy['owner_assignment']['kind'] }}</div>
                            @else
                                <span class="muted-note">No owner assigned</span>
                            @endif
                        </td>
                        <td>{{ $policy['linked_control_id'] !== '' ? $policy['linked_control_id'] : 'None' }}</td>
                        <td>{{ $policy['review_due_on'] !== '' ? $policy['review_due_on'] : 'No review date' }}</td>
                        <td>
                            @forelse ($policy['artifacts'] as $artifact)
                                <div class="data-item" style="margin-bottom:8px;">
                                    <div class="entity-title">{{ $artifact['label'] }}</div>
                                    <div class="table-note">{{ $artifact['original_filename'] }}</div>
                                </div>
                            @empty
                                <span class="muted-note">No documents yet</span>
                            @endforelse
                            @if ($can_manage_policies)
                                <form class="upload-form" method="POST" action="{{ $policy['artifact_upload_route'] }}" enctype="multipart/form-data" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.policy-exceptions.root">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <input type="hidden" name="artifact_type" value="document">
                                    <input type="text" name="label" placeholder="Document label">
                                    <input type="file" name="artifact" required>
                                    <button class="button button-secondary" type="submit">Attach Document</button>
                                </form>
                            @endif
                        </td>
                        <td><span class="pill">{{ $policy['state'] }}</span></td>
                        <td>
                            @if ($policy['transitions'] !== [])
                                <div class="action-cluster">
                                    @foreach ($policy['transitions'] as $transition)
                                        <form method="POST" action="{{ str_replace('__TRANSITION__', $transition, $policy['transition_route']) }}">
                                            @csrf
                                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                            <input type="hidden" name="menu" value="plugin.policy-exceptions.root">
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
                                    <form class="upload-form" method="POST" action="{{ $policy['update_route'] }}" style="margin-top:10px;">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="menu" value="plugin.policy-exceptions.root">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <div class="field">
                                            <label class="field-label">Title</label>
                                            <input class="field-input" name="title" value="{{ $policy['title'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Area</label>
                                            <input class="field-input" name="area" value="{{ $policy['area'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Version</label>
                                            <input class="field-input" name="version_label" value="{{ $policy['version_label'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Review due</label>
                                            <input class="field-input" name="review_due_on" type="date" value="{{ $policy['review_due_on'] }}">
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Linked control</label>
                                            <select class="field-select" name="linked_control_id">
                                                <option value="">No linked control</option>
                                                @foreach ($control_options as $control)
                                                    <option value="{{ $control['id'] }}" @selected($policy['linked_control_id'] === $control['id'])>{{ $control['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Scope</label>
                                            <select class="field-select" name="scope_id">
                                                <option value="">Organization-wide</option>
                                                @foreach ($scope_options as $scope)
                                                    <option value="{{ $scope['id'] }}" @selected($policy['scope_id'] === $scope['id'])>{{ $scope['name'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Owner actor</label>
                                            <select class="field-select" name="owner_actor_id">
                                                <option value="">Keep current owner</option>
                                                @foreach ($owner_actor_options as $actor)
                                                    <option value="{{ $actor['id'] }}" @selected(($policy['owner_assignment']['id'] ?? null) === $actor['id'])>{{ $actor['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Statement</label>
                                            <textarea class="field-input" name="statement" rows="3" required>{{ $policy['statement'] }}</textarea>
                                        </div>
                                        <div class="action-cluster">
                                            <button class="button button-secondary" type="submit">Save Changes</button>
                                        </div>
                                    </form>
                                </details>

                                <details style="margin-top:10px;">
                                    <summary class="button button-ghost" style="display:inline-flex;">Add Exception</summary>
                                    <form class="upload-form" method="POST" action="{{ $policy['exception_store_route'] }}" style="margin-top:10px;">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="menu" value="plugin.policy-exceptions.exceptions">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <div class="field">
                                            <label class="field-label">Exception title</label>
                                            <input class="field-input" name="title" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Linked finding</label>
                                            <select class="field-select" name="linked_finding_id">
                                                <option value="">No linked finding</option>
                                                @foreach ($finding_options as $finding)
                                                    <option value="{{ $finding['id'] }}">{{ $finding['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Expires on</label>
                                            <input class="field-input" name="expires_on" type="date">
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
                                            <label class="field-label">Rationale</label>
                                            <textarea class="field-input" name="rationale" rows="2" required></textarea>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Compensating control</label>
                                            <textarea class="field-input" name="compensating_control" rows="2"></textarea>
                                        </div>
                                        <div class="action-cluster">
                                            <button class="button button-secondary" type="submit">Create Exception</button>
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
