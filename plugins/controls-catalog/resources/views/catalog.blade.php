<section class="module-screen">
    @if ($can_manage_controls)
        <div class="surface-card" id="control-editor">
            <div class="row-between" style="margin-bottom:14px;">
                <div>
                    <div class="eyebrow">Create</div>
                    <div class="screen-title" style="font-size:26px;">New Control</div>
                </div>
            </div>

            <form class="upload-form" method="POST" action="{{ $create_route }}">
                @csrf
                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                <input type="hidden" name="menu" value="plugin.controls-catalog.root">
                <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">

                <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                    <div class="field">
                        <label class="field-label" for="control-name">Name</label>
                        <input class="field-input" id="control-name" name="name" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="control-framework">Framework</label>
                        <input class="field-input" id="control-framework" name="framework" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="control-domain">Domain</label>
                        <input class="field-input" id="control-domain" name="domain" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="control-scope">Scope</label>
                        <select class="field-select" id="control-scope" name="scope_id">
                            <option value="">Organization-wide</option>
                            @foreach ($scope_options as $scope)
                                <option value="{{ $scope['id'] }}" @selected(($query['scope_id'] ?? null) === $scope['id'])>{{ $scope['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field" style="grid-column:1 / -1;">
                        <label class="field-label" for="control-evidence">Evidence summary</label>
                        <input class="field-input" id="control-evidence" name="evidence" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="control-owner">Owner actor</label>
                        <select class="field-select" id="control-owner" name="owner_actor_id">
                            <option value="">No owner</option>
                            @foreach ($owner_actor_options as $actor)
                                <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="action-cluster" style="margin-top:14px;">
                    <button class="button button-primary" type="submit">Create Control</button>
                </div>
            </form>
        </div>
    @endif

    <div class="overview-grid">
        <div class="metric-card"><div class="metric-label">Controls</div><div class="metric-value">{{ count($controls) }}</div></div>
        <div class="metric-card"><div class="metric-label">In Review</div><div class="metric-value">{{ collect($controls)->where('state', 'review')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">Approved</div><div class="metric-value">{{ collect($controls)->where('state', 'approved')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">Artifacts</div><div class="metric-value">{{ collect($controls)->sum(fn ($control) => count($control['artifacts'])) }}</div></div>
    </div>

    <div class="table-card">
        <table class="entity-table">
            <thead>
                <tr>
                    <th>Control</th>
                    <th>Framework</th>
                    <th>Domain</th>
                    <th>Owner</th>
                    <th>Evidence</th>
                    <th>State</th>
                    <th>{{ $can_manage_controls ? 'Transitions' : 'Access' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($controls as $control)
                    <tr>
                        <td>
                            <div class="entity-title">{{ $control['name'] }}</div>
                            <div class="entity-id">{{ $control['id'] }}</div>
                        </td>
                        <td>{{ $control['framework'] }}</td>
                        <td>{{ $control['domain'] }}</td>
                        <td>
                            @if ($control['owner_assignment'] !== null)
                                <div>{{ $control['owner_assignment']['display_name'] }}</div>
                                <div class="table-note">{{ $control['owner_assignment']['kind'] }}</div>
                            @else
                                <span class="muted-note">No owner assigned</span>
                            @endif
                        </td>
                        <td>
                            <div>{{ $control['evidence'] }}</div>
                            <div class="data-stack" style="margin-top:8px;">
                                @forelse ($control['artifacts'] as $artifact)
                                    <div class="data-item">
                                        <div class="entity-title">{{ $artifact['label'] }}</div>
                                        <div class="table-note">{{ $artifact['original_filename'] }} · {{ $artifact['artifact_type'] }}</div>
                                    </div>
                                @empty
                                    <span class="muted-note">No artifacts yet</span>
                                @endforelse
                            </div>
                            @if ($can_manage_controls)
                                <form class="upload-form" method="POST" action="{{ $control['artifact_upload_route'] }}" enctype="multipart/form-data" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.controls-catalog.root">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <input type="hidden" name="artifact_type" value="evidence">
                                    <input type="text" name="label" placeholder="Evidence label">
                                    <input type="file" name="artifact" required>
                                    <button class="button button-secondary" type="submit">Attach Evidence</button>
                                </form>
                            @endif
                        </td>
                        <td><span class="pill">{{ $control['state'] }}</span></td>
                        <td>
                            @if ($control['transitions'] !== [])
                                <div class="action-cluster">
                                    @foreach ($control['transitions'] as $transition)
                                        <form method="POST" action="{{ str_replace('__TRANSITION__', $transition, $control['transition_route']) }}">
                                            @csrf
                                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                            <input type="hidden" name="menu" value="plugin.controls-catalog.root">
                                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                            <button class="button button-secondary" type="submit">{{ ucwords(str_replace('-', ' ', $transition)) }}</button>
                                        </form>
                                    @endforeach
                                </div>
                            @else
                                <span class="muted-note">View-only access</span>
                            @endif

                            @if ($can_manage_controls)
                                <details style="margin-top:10px;">
                                    <summary class="button button-ghost" style="display:inline-flex;">Edit</summary>
                                    <form class="upload-form" method="POST" action="{{ $control['update_route'] }}" style="margin-top:10px;">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="menu" value="plugin.controls-catalog.root">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <div class="field">
                                            <label class="field-label">Name</label>
                                            <input class="field-input" name="name" value="{{ $control['name'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Framework</label>
                                            <input class="field-input" name="framework" value="{{ $control['framework'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Domain</label>
                                            <input class="field-input" name="domain" value="{{ $control['domain'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Scope</label>
                                            <select class="field-select" name="scope_id">
                                                <option value="">Organization-wide</option>
                                                @foreach ($scope_options as $scope)
                                                    <option value="{{ $scope['id'] }}" @selected($control['scope_id'] === $scope['id'])>{{ $scope['name'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Evidence summary</label>
                                            <input class="field-input" name="evidence" value="{{ $control['evidence'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Owner actor</label>
                                            <select class="field-select" name="owner_actor_id">
                                                <option value="">Keep current owner</option>
                                                @foreach ($owner_actor_options as $actor)
                                                    <option value="{{ $actor['id'] }}" @selected(($control['owner_assignment']['id'] ?? null) === $actor['id'])>{{ $actor['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="action-cluster">
                                            <button class="button button-secondary" type="submit">Save Changes</button>
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
