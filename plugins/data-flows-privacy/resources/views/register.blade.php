<section class="module-screen">
    @if ($can_manage_privacy)
        <div class="surface-card" id="data-flow-editor">
            <div class="row-between" style="margin-bottom:14px;">
                <div>
                    <div class="eyebrow">Create</div>
                    <div class="screen-title" style="font-size:26px;">New Data Flow</div>
                </div>
            </div>

            <form class="upload-form" method="POST" action="{{ $create_route }}">
                @csrf
                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                <input type="hidden" name="menu" value="plugin.data-flows-privacy.root">
                <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">

                <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                    <div class="field">
                        <label class="field-label" for="flow-title">Title</label>
                        <input class="field-input" id="flow-title" name="title" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="flow-transfer-type">Transfer type</label>
                        <input class="field-input" id="flow-transfer-type" name="transfer_type" placeholder="internal, vendor, international" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="flow-source">Source</label>
                        <input class="field-input" id="flow-source" name="source" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="flow-destination">Destination</label>
                        <input class="field-input" id="flow-destination" name="destination" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="flow-review-due">Review due</label>
                        <input class="field-input" id="flow-review-due" name="review_due_on" type="date">
                    </div>
                    <div class="field">
                        <label class="field-label" for="flow-scope">Scope</label>
                        <select class="field-select" id="flow-scope" name="scope_id">
                            <option value="">Organization-wide</option>
                            @foreach ($scope_options as $scope)
                                <option value="{{ $scope['id'] }}" @selected(($query['scope_id'] ?? null) === $scope['id'])>{{ $scope['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="flow-asset">Linked asset</label>
                        <select class="field-select" id="flow-asset" name="linked_asset_id">
                            <option value="">No linked asset</option>
                            @foreach ($asset_options as $asset)
                                <option value="{{ $asset['id'] }}">{{ $asset['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="flow-risk">Linked risk</label>
                        <select class="field-select" id="flow-risk" name="linked_risk_id">
                            <option value="">No linked risk</option>
                            @foreach ($risk_options as $risk)
                                <option value="{{ $risk['id'] }}">{{ $risk['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="flow-owner">Owner actor</label>
                        <select class="field-select" id="flow-owner" name="owner_actor_id">
                            <option value="">No owner</option>
                            @foreach ($owner_actor_options as $actor)
                                <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field" style="grid-column:1 / -1;">
                        <label class="field-label" for="flow-summary">Data category summary</label>
                        <input class="field-input" id="flow-summary" name="data_category_summary" required>
                    </div>
                </div>

                <div class="action-cluster" style="margin-top:14px;">
                    <button class="button button-primary" type="submit">Create Data Flow</button>
                </div>
            </form>
        </div>
    @endif

    <div class="overview-grid">
        <div class="metric-card"><div class="metric-label">Data Flows</div><div class="metric-value">{{ count($data_flows) }}</div></div>
        <div class="metric-card"><div class="metric-label">Active</div><div class="metric-value">{{ collect($data_flows)->where('state', 'active')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">Under Review</div><div class="metric-value">{{ collect($data_flows)->where('state', 'review')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">Artifacts</div><div class="metric-value">{{ collect($data_flows)->sum(fn ($flow) => count($flow['artifacts'])) }}</div></div>
    </div>

    <div class="table-card">
        <table class="entity-table">
            <thead>
                <tr>
                    <th>Data Flow</th>
                    <th>Route</th>
                    <th>Owner</th>
                    <th>Links</th>
                    <th>Review Due</th>
                    <th>Evidence</th>
                    <th>State</th>
                    <th>{{ $can_manage_privacy ? 'Actions' : 'Access' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data_flows as $flow)
                    <tr>
                        <td>
                            <div class="entity-title">{{ $flow['title'] }}</div>
                            <div class="entity-id">{{ $flow['id'] }} · {{ $flow['transfer_type'] }}</div>
                            <div class="table-note">{{ $flow['data_category_summary'] }}</div>
                        </td>
                        <td>
                            <div>{{ $flow['source'] }}</div>
                            <div class="table-note">{{ $flow['destination'] }}</div>
                        </td>
                        <td>
                            @if ($flow['owner_assignment'] !== null)
                                <div>{{ $flow['owner_assignment']['display_name'] }}</div>
                                <div class="table-note">{{ $flow['owner_assignment']['kind'] }}</div>
                            @else
                                <span class="muted-note">No owner assigned</span>
                            @endif
                        </td>
                        <td>
                            <div>Asset: {{ $flow['linked_asset_label'] ?? ($flow['linked_asset_id'] !== '' ? $flow['linked_asset_id'] : 'None') }}</div>
                            <div>Risk: {{ $flow['linked_risk_id'] !== '' ? $flow['linked_risk_id'] : 'None' }}</div>
                        </td>
                        <td>{{ $flow['review_due_on'] !== '' ? $flow['review_due_on'] : 'No review date' }}</td>
                        <td>
                            @forelse ($flow['artifacts'] as $artifact)
                                <div class="data-item" style="margin-bottom:8px;">
                                    <div class="entity-title">{{ $artifact['label'] }}</div>
                                    <div class="table-note">{{ $artifact['original_filename'] }}</div>
                                </div>
                            @empty
                                <span class="muted-note">No records yet</span>
                            @endforelse
                            @if ($can_manage_privacy)
                                <form class="upload-form" method="POST" action="{{ $flow['artifact_upload_route'] }}" enctype="multipart/form-data" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.data-flows-privacy.root">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <input type="hidden" name="artifact_type" value="record">
                                    <input type="text" name="label" placeholder="Record label">
                                    <input type="file" name="artifact" required>
                                    <button class="button button-secondary" type="submit">Attach Record</button>
                                </form>
                            @endif
                        </td>
                        <td><span class="pill">{{ $flow['state'] }}</span></td>
                        <td>
                            @if ($flow['transitions'] !== [])
                                <div class="action-cluster">
                                    @foreach ($flow['transitions'] as $transition)
                                        <form method="POST" action="{{ str_replace('__TRANSITION__', $transition, $flow['transition_route']) }}">
                                            @csrf
                                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                            <input type="hidden" name="menu" value="plugin.data-flows-privacy.root">
                                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                            <button class="button button-secondary" type="submit">{{ ucwords(str_replace('-', ' ', $transition)) }}</button>
                                        </form>
                                    @endforeach
                                </div>
                            @else
                                <span class="muted-note">View-only access</span>
                            @endif

                            @if ($can_manage_privacy)
                                <details style="margin-top:10px;">
                                    <summary class="button button-ghost" style="display:inline-flex;">Edit</summary>
                                    <form class="upload-form" method="POST" action="{{ $flow['update_route'] }}" style="margin-top:10px;">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="menu" value="plugin.data-flows-privacy.root">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <div class="field">
                                            <label class="field-label">Title</label>
                                            <input class="field-input" name="title" value="{{ $flow['title'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Transfer type</label>
                                            <input class="field-input" name="transfer_type" value="{{ $flow['transfer_type'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Source</label>
                                            <input class="field-input" name="source" value="{{ $flow['source'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Destination</label>
                                            <input class="field-input" name="destination" value="{{ $flow['destination'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Review due</label>
                                            <input class="field-input" name="review_due_on" type="date" value="{{ $flow['review_due_on'] }}">
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Linked asset</label>
                                            <select class="field-select" name="linked_asset_id">
                                                <option value="">No linked asset</option>
                                                @foreach ($asset_options as $asset)
                                                    <option value="{{ $asset['id'] }}" @selected($flow['linked_asset_id'] === $asset['id'])>{{ $asset['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Linked risk</label>
                                            <select class="field-select" name="linked_risk_id">
                                                <option value="">No linked risk</option>
                                                @foreach ($risk_options as $risk)
                                                    <option value="{{ $risk['id'] }}" @selected($flow['linked_risk_id'] === $risk['id'])>{{ $risk['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Scope</label>
                                            <select class="field-select" name="scope_id">
                                                <option value="">Organization-wide</option>
                                                @foreach ($scope_options as $scope)
                                                    <option value="{{ $scope['id'] }}" @selected($flow['scope_id'] === $scope['id'])>{{ $scope['name'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Owner actor</label>
                                            <select class="field-select" name="owner_actor_id">
                                                <option value="">Keep current owner</option>
                                                @foreach ($owner_actor_options as $actor)
                                                    <option value="{{ $actor['id'] }}" @selected(($flow['owner_assignment']['id'] ?? null) === $actor['id'])>{{ $actor['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Data category summary</label>
                                            <input class="field-input" name="data_category_summary" value="{{ $flow['data_category_summary'] }}" required>
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
