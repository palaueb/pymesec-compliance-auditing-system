<style>
    .pill-active  { background: rgba(34,197,94,0.14);  color: #166534; }
    .pill-review  { background: rgba(245,158,11,0.14); color: #92400e; }
    .pill-draft   { background: rgba(31,42,34,0.06);   color: var(--muted); }
    .pill-archived{ background: rgba(31,42,34,0.06);   color: var(--muted); }

    details > summary { cursor: pointer; list-style: none; }
    details > summary::-webkit-details-marker { display: none; }
</style>

<section class="module-screen">
    @if (is_array($selected_flow))
        <div class="surface-card" style="padding:16px; display:grid; gap:16px;">
            <div class="row-between" style="align-items:flex-start;">
                <div>
                    <div class="eyebrow">Data flow</div>
                    <h2 class="screen-title" style="font-size:28px;">{{ $selected_flow['title'] }}</h2>
                    <div class="table-note">{{ $selected_flow['id'] }} · {{ $selected_flow['transfer_type_label'] }}</div>
                    <div class="table-note">{{ $selected_flow['source'] }} → {{ $selected_flow['destination'] }}</div>
                </div>
                <div class="action-cluster">
                    @php $flowStatePill = match($selected_flow['state']) { 'active' => 'pill-active', 'review' => 'pill-review', 'draft' => 'pill-draft', 'archived' => 'pill-archived', default => '' }; @endphp
                    <span class="pill {{ $flowStatePill }}">{{ $selected_flow['state'] }}</span>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
                <div class="metric-card"><div class="metric-label">Evidence</div><div class="metric-value">{{ count($selected_flow['artifacts']) }}</div></div>
                <div class="metric-card"><div class="metric-label">Review due</div><div class="metric-value" style="font-size:20px;">{{ $selected_flow['review_due_on'] !== '' ? $selected_flow['review_due_on'] : 'No date' }}</div></div>
                <div class="metric-card"><div class="metric-label">Scope</div><div class="metric-value" style="font-size:20px;">{{ $selected_flow['scope_id'] !== '' ? $selected_flow['scope_id'] : 'Org-wide' }}</div></div>
                <div class="metric-card"><div class="metric-label">Owner</div><div class="metric-value" style="font-size:20px;">{{ $selected_flow['owner_assignment']['display_name'] ?? 'Unassigned' }}</div></div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Overview</div>
                    <div class="table-note" style="margin-top:10px;">{{ $selected_flow['data_category_summary'] }}</div>
                    <div class="table-note">
                        Asset:
                        @if ($selected_flow['linked_asset_url'] !== null)
                            <a href="{{ $selected_flow['linked_asset_url'] }}">{{ $selected_flow['linked_asset_label'] ?? $selected_flow['linked_asset_id'] }}</a>
                        @else
                            {{ $selected_flow['linked_asset_id'] !== '' ? $selected_flow['linked_asset_id'] : 'None' }}
                        @endif
                    </div>
                    <div class="table-note">
                        Risk:
                        @if ($selected_flow['linked_risk_url'] !== null)
                            <a href="{{ $selected_flow['linked_risk_url'] }}">{{ $selected_flow['linked_risk_label'] ?? $selected_flow['linked_risk_id'] }}</a>
                        @else
                            {{ $selected_flow['linked_risk_id'] !== '' ? $selected_flow['linked_risk_id'] : 'None' }}
                        @endif
                    </div>
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Workflow</div>
                    @if ($selected_flow['transitions'] !== [])
                        <div class="action-cluster" style="margin-top:10px;">
                            @foreach ($selected_flow['transitions'] as $transition)
                                <form method="POST" action="{{ str_replace('__TRANSITION__', $transition, $selected_flow['transition_route']) }}">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.data-flows-privacy.root">
                                    <input type="hidden" name="flow_id" value="{{ $selected_flow['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <button class="button button-secondary" type="submit">{{ ucwords(str_replace('-', ' ', $transition)) }}</button>
                                </form>
                            @endforeach
                        </div>
                    @else
                        <div class="table-note" style="margin-top:10px;">View-only access</div>
                    @endif

                    <div class="data-stack" style="margin-top:12px;">
                        @forelse ($selected_flow['history'] as $history)
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
                                <form class="upload-form" method="POST" action="{{ $selected_flow['artifact_upload_route'] }}" enctype="multipart/form-data" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.data-flows-privacy.root">
                                    <input type="hidden" name="flow_id" value="{{ $selected_flow['id'] }}">
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
                        @forelse ($selected_flow['artifacts'] as $artifact)
                            <div class="data-item">
                                <div class="row-between" style="align-items:flex-start; gap:12px;">
                                    <div>
                                        <div class="entity-title">{{ $artifact['label'] }}</div>
                                        <div class="table-note">{{ $artifact['original_filename'] }}</div>
                                    </div>
                                    <form method="POST" action="{{ route('plugin.evidence-management.promote', ['artifactId' => $artifact['id']]) }}">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                        <input type="hidden" name="scope_id" value="{{ $selected_flow['scope_id'] }}">
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
                            <summary class="button button-ghost" style="display:inline-flex; width:fit-content;">Edit data flow details</summary>
                            <form class="upload-form" method="POST" action="{{ $selected_flow['update_route'] }}" style="margin-top:14px;">
                                @csrf
                                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                <input type="hidden" name="menu" value="plugin.data-flows-privacy.root">
                                <input type="hidden" name="flow_id" value="{{ $selected_flow['id'] }}">
                                <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                                    <div class="field">
                                        <label class="field-label">Title</label>
                                        <input class="field-input" name="title" value="{{ $selected_flow['title'] }}" required>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Transfer type</label>
                                        <select class="field-select" name="transfer_type" required>
                                            @foreach ($transfer_type_options as $option)
                                                <option value="{{ $option['id'] }}" @selected($selected_flow['transfer_type'] === $option['id'])>{{ $option['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Source</label>
                                        <input class="field-input" name="source" value="{{ $selected_flow['source'] }}" required>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Destination</label>
                                        <input class="field-input" name="destination" value="{{ $selected_flow['destination'] }}" required>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Review due</label>
                                        <input class="field-input" name="review_due_on" type="date" value="{{ $selected_flow['review_due_on'] }}">
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Scope</label>
                                        <select class="field-select" name="scope_id">
                                            <option value="">Organization-wide</option>
                                            @foreach ($scope_options as $scope)
                                                <option value="{{ $scope['id'] }}" @selected($selected_flow['scope_id'] === $scope['id'])>{{ $scope['name'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Linked asset</label>
                                        <select class="field-select" name="linked_asset_id">
                                            <option value="">No linked asset</option>
                                            @foreach ($asset_options as $asset)
                                                <option value="{{ $asset['id'] }}" @selected($selected_flow['linked_asset_id'] === $asset['id'])>{{ $asset['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Linked risk</label>
                                        <select class="field-select" name="linked_risk_id">
                                            <option value="">No linked risk</option>
                                            @foreach ($risk_options as $risk)
                                                <option value="{{ $risk['id'] }}" @selected($selected_flow['linked_risk_id'] === $risk['id'])>{{ $risk['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Owner actor</label>
                                        <select class="field-select" name="owner_actor_id">
                                            <option value="">Keep current owner</option>
                                            @foreach ($owner_actor_options as $actor)
                                                <option value="{{ $actor['id'] }}" @selected(($selected_flow['owner_assignment']['id'] ?? null) === $actor['id'])>{{ $actor['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field" style="grid-column:1 / -1;">
                                        <label class="field-label">Data category summary</label>
                                        <input class="field-input" name="data_category_summary" value="{{ $selected_flow['data_category_summary'] }}" required>
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
        @if ($can_manage_privacy)
            <div class="surface-card" id="data-flow-editor" hidden>
                <div class="row-between" style="margin-bottom:14px;">
                    <div>
                        <div class="eyebrow">Create</div>
                        <div class="entity-title" style="font-size:24px;">New data flow</div>
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
                            <select class="field-select" id="flow-transfer-type" name="transfer_type" required>
                                <option value="">Choose transfer type</option>
                                @foreach ($transfer_type_options as $option)
                                    <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </select>
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
                        <button class="button button-primary" type="submit">Create data flow</button>
                    </div>
                </form>
            </div>
        @endif

        <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
            <div class="metric-card"><div class="metric-label">Data flows</div><div class="metric-value">{{ count($data_flows) }}</div></div>
            <div class="metric-card"><div class="metric-label">Active</div><div class="metric-value">{{ collect($data_flows)->where('state', 'active')->count() }}</div></div>
            <div class="metric-card"><div class="metric-label">Under review</div><div class="metric-value">{{ collect($data_flows)->where('state', 'review')->count() }}</div></div>
            <div class="metric-card"><div class="metric-label">Evidence</div><div class="metric-value">{{ collect($data_flows)->sum(fn ($flow) => count($flow['artifacts'])) }}</div></div>
        </div>

        <div class="surface-card">
            <div class="table-note">Open a data flow to manage records, workflow, linked assets and privacy review details.</div>
        </div>

        <div class="table-card">
            <table class="entity-table">
                <thead>
                    <tr>
                        <th>Data flow</th>
                        <th>Route</th>
                        <th>Owner</th>
                        <th>Asset</th>
                        <th>Risk</th>
                        <th>Review due</th>
                        <th>State</th>
                        <th>{{ $can_manage_privacy ? 'Actions' : 'Access' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data_flows as $flow)
                        <tr>
                            <td>
                                <div class="entity-title">{{ $flow['title'] }}</div>
                                <div class="entity-id">{{ $flow['id'] }} · {{ $flow['transfer_type_label'] }}</div>
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
                                @if ($flow['linked_asset_url'] !== null)
                                    <a href="{{ $flow['linked_asset_url'] }}">{{ $flow['linked_asset_label'] ?? $flow['linked_asset_id'] }}</a>
                                @else
                                    <span class="muted-note">{{ $flow['linked_asset_id'] !== '' ? $flow['linked_asset_id'] : 'None' }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($flow['linked_risk_url'] !== null)
                                    <a href="{{ $flow['linked_risk_url'] }}">{{ $flow['linked_risk_label'] ?? $flow['linked_risk_id'] }}</a>
                                @else
                                    <span class="muted-note">{{ $flow['linked_risk_id'] !== '' ? $flow['linked_risk_id'] : 'None' }}</span>
                                @endif
                            </td>
                            <td>{{ $flow['review_due_on'] !== '' ? $flow['review_due_on'] : 'No review date' }}</td>
                            <td>
                                @php $sFlowPill = match($flow['state']) { 'active' => 'pill-active', 'review' => 'pill-review', 'draft' => 'pill-draft', 'archived' => 'pill-archived', default => '' }; @endphp
                                <span class="pill {{ $sFlowPill }}">{{ $flow['state'] }}</span>
                            </td>
                            <td>
                                <a class="button button-secondary" href="{{ $flow['open_url'] }}&{{ http_build_query(['context_label' => 'Data Flows', 'context_back_url' => $data_flows_list_url]) }}">Open</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
