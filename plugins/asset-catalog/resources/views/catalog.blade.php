<style>
    .pill-active   { background: rgba(34,197,94,0.14);  color: #166534; }
    .pill-review   { background: rgba(245,158,11,0.14); color: #92400e; }
    .pill-retired  { background: rgba(31,42,34,0.05);   color: var(--muted); }

    .pill-high     { background: rgba(239,68,68,0.12);  color: #991b1b; }
    .pill-medium   { background: rgba(245,158,11,0.12); color: #92400e; }
    .pill-low      { background: rgba(34,197,94,0.12);  color: #166534; }

    details > summary { cursor: pointer; list-style: none; }
    details > summary::-webkit-details-marker { display: none; }
</style>

<section class="module-screen">
    <div class="surface-note">
        Type, criticality, and classification are business-managed catalog values from `Reference catalogs`. Asset lifecycle states such as `active`, `review`, or `retired` are system-controlled.
    </div>

    {{-- ── Creation form (hidden, toggled via toolbar) ──────────────────────── --}}
    @if ($can_manage_assets)
        <div class="surface-card" id="asset-editor" hidden>
            <div class="eyebrow" style="margin-bottom:8px;">New asset</div>
            <form class="upload-form" method="POST" action="{{ $create_route }}">
                @csrf
                <input type="hidden" name="principal_id"    value="{{ $query['principal_id'] ?? '' }}">
                <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                <input type="hidden" name="locale"          value="{{ $query['locale'] }}">
                <input type="hidden" name="menu"            value="plugin.asset-catalog.root">
                <input type="hidden" name="membership_id"   value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">

                <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                    <div class="field">
                        <label class="field-label" for="asset-name">Name</label>
                        <input class="field-input" id="asset-name" name="name" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="asset-type">Type</label>
                        <select class="field-select" id="asset-type" name="type" required>
                            <option value="">Choose a type</option>
                            @foreach ($asset_type_options as $option)
                                <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="asset-criticality">Criticality</label>
                        <select class="field-select" id="asset-criticality" name="criticality" required>
                            <option value="">Choose a level</option>
                            @foreach ($asset_criticality_options as $option)
                                <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="asset-classification">Classification</label>
                        <select class="field-select" id="asset-classification" name="classification" required>
                            <option value="">Choose a classification</option>
                            @foreach ($asset_classification_options as $option)
                                <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="asset-scope">Scope</label>
                        <select class="field-select" id="asset-scope" name="scope_id">
                            <option value="">Organization-wide</option>
                            @foreach ($scope_options as $scope)
                                <option value="{{ $scope['id'] }}" @selected(($query['scope_id'] ?? null) === $scope['id'])>{{ $scope['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="asset-owner-actor">Initial owner actor</label>
                        <select class="field-select" id="asset-owner-actor" name="owner_actor_id">
                            <option value="">No actor owner</option>
                            @foreach ($owner_actor_options as $actor)
                                <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="action-cluster" style="margin-top:14px;">
                    <button class="button button-primary" type="submit">Create asset</button>
                </div>
            </form>
        </div>
    @endif

    @if (is_array($selected_asset))

        {{-- ── Asset detail ──────────────────────────────────────────────────── --}}
        <div class="surface-card" style="padding:20px; display:grid; gap:18px;">
            <div class="surface-note">
                Asset Detail keeps workflow, accountability, and governed asset changes inside one record workspace. The catalog list stays focused on browse, compare, and open.
            </div>

            {{-- Header --}}
            <div class="screen-header" style="margin-bottom:0; padding-bottom:16px;">
                <div>
                    <div class="eyebrow">Asset</div>
                    <h2 class="screen-title" style="font-size:26px; margin-top:4px;">{{ $selected_asset['name'] }}</h2>
                    <div class="table-note" style="margin-top:4px;">{{ $selected_asset['id'] }}</div>
                </div>
                <div class="action-cluster" style="align-items:flex-start;">
                    @php
                        $statePillClass = match($selected_asset['state']) {
                            'active'  => 'pill-active',
                            'review'  => 'pill-review',
                            'retired' => 'pill-retired',
                            default   => '',
                        };
                    @endphp
                    <span class="pill {{ $statePillClass }}">{{ $selected_asset['state'] }}</span>
                </div>
            </div>

            {{-- Metrics --}}
            <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
                <div class="metric-card">
                    <div class="metric-label">Type</div>
                    <div class="metric-value" style="font-size:18px;">{{ $selected_asset['type_label'] }}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Criticality</div>
                    <div class="metric-value" style="font-size:18px;">{{ $selected_asset['criticality_label'] }}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Classification</div>
                    <div class="metric-value" style="font-size:18px;">{{ $selected_asset['classification_label'] }}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Scope</div>
                    <div class="metric-value" style="font-size:18px;">{{ $selected_asset['scope_id'] !== '' ? $selected_asset['scope_id'] : 'Org-wide' }}</div>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                {{-- Owner --}}
                <div class="surface-card" style="padding:14px; background:rgba(255,255,255,0.4);">
                    <div class="metric-label" style="margin-bottom:8px;">Accountability</div>
                    <div class="table-note" style="margin-bottom:10px;">Owners: {{ count($selected_asset['owner_assignments'] ?? []) }}</div>
                    @if (($selected_asset['owner_assignments'] ?? []) !== [])
                        <div class="data-stack">
                            @foreach ($selected_asset['owner_assignments'] as $owner)
                                <div class="data-item">
                                    <div class="entity-title" style="font-size:14px;">{{ $owner['display_name'] }}</div>
                                    <div class="table-note">{{ $owner['kind'] }}</div>
                                    @if ($can_manage_assets)
                                        <form method="POST" action="{{ str_replace('__ASSIGNMENT__', $owner['assignment_id'], $selected_asset['owner_remove_route']) }}" style="margin-top:8px;">
                                            @csrf
                                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                            <input type="hidden" name="menu" value="plugin.asset-catalog.root">
                                            <input type="hidden" name="asset_id" value="{{ $selected_asset['id'] }}">
                                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                            <button class="button button-ghost" type="submit">Remove owner</button>
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="table-note">No owner assigned</div>
                    @endif
                </div>

                {{-- Workflow --}}
                <div class="surface-card" style="padding:14px; background:rgba(255,255,255,0.4);">
                    <div class="metric-label" style="margin-bottom:8px;">Governance actions</div>
                    @if ($selected_asset['transitions'] !== [])
                        <div class="action-cluster">
                            @foreach ($selected_asset['transitions'] as $transition)
                                <form method="POST" action="{{ str_replace('__TRANSITION__', $transition, $selected_asset['transition_route']) }}">
                                    @csrf
                                    <input type="hidden" name="principal_id"    value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale"          value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu"            value="plugin.asset-catalog.root">
                                    <input type="hidden" name="asset_id"        value="{{ $selected_asset['id'] }}">
                                    <input type="hidden" name="membership_id"   value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <button class="button button-secondary" type="submit">{{ ucwords(str_replace('-', ' ', $transition)) }}</button>
                                </form>
                            @endforeach
                        </div>
                    @else
                        <div class="table-note">View-only access</div>
                    @endif

                    @if (count($selected_asset['history']) > 0)
                        <div class="data-stack" style="margin-top:10px;">
                            @foreach ($selected_asset['history'] as $history)
                                <div class="data-item">
                                    <div class="entity-title" style="font-size:13px;">{{ $history->transitionKey }}</div>
                                    <div class="table-note">{{ $history->fromState }} → {{ $history->toState }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Edit asset (collapsed) --}}
            @if ($can_manage_assets)
                <hr style="border:none; border-top:1px solid rgba(31,42,34,0.07); margin:0;">
                <details>
                    <summary class="button button-ghost" style="display:inline-flex; width:fit-content;">Edit asset details</summary>
                    <form class="upload-form" method="POST" action="{{ $selected_asset['update_route'] }}" style="margin-top:14px;">
                        @csrf
                        <input type="hidden" name="principal_id"    value="{{ $query['principal_id'] ?? '' }}">
                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                        <input type="hidden" name="locale"          value="{{ $query['locale'] }}">
                        <input type="hidden" name="menu"            value="plugin.asset-catalog.root">
                        <input type="hidden" name="asset_id"        value="{{ $selected_asset['id'] }}">
                        <input type="hidden" name="membership_id"   value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                        <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                            <div class="field">
                                <label class="field-label">Name</label>
                                <input class="field-input" name="name" value="{{ $selected_asset['name'] }}" required>
                            </div>
                            <div class="field">
                                <label class="field-label">Type</label>
                                <select class="field-select" name="type" required>
                                    @foreach ($asset_type_options as $option)
                                        <option value="{{ $option['id'] }}" @selected($selected_asset['type'] === $option['id'])>{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label class="field-label">Criticality</label>
                                <select class="field-select" name="criticality" required>
                                    @foreach ($asset_criticality_options as $option)
                                        <option value="{{ $option['id'] }}" @selected($selected_asset['criticality'] === $option['id'])>{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label class="field-label">Classification</label>
                                <select class="field-select" name="classification" required>
                                    @foreach ($asset_classification_options as $option)
                                        <option value="{{ $option['id'] }}" @selected($selected_asset['classification'] === $option['id'])>{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label class="field-label">Scope</label>
                                <select class="field-select" name="scope_id">
                                    <option value="">Organization-wide</option>
                                    @foreach ($scope_options as $scope)
                                        <option value="{{ $scope['id'] }}" @selected($selected_asset['scope_id'] === $scope['id'])>{{ $scope['name'] }}</option>
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
                                <div class="table-note">Selecting an actor adds another accountable owner instead of replacing the current set.</div>
                            </div>
                        </div>
                        <div class="action-cluster" style="margin-top:14px;">
                            <button class="button button-secondary" type="submit">Save changes</button>
                        </div>
                    </form>
                </details>
            @endif

        </div>

    @else

        {{-- ── Summary metrics ───────────────────────────────────────────────── --}}
        <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
            <div class="metric-card"><div class="metric-label">Assets</div><div class="metric-value">{{ count($assets) }}</div></div>
            <div class="metric-card"><div class="metric-label">Active</div>   <div class="metric-value" style="color:#166534;">{{ collect($assets)->where('state', 'active')->count() }}</div></div>
            <div class="metric-card"><div class="metric-label">In review</div><div class="metric-value" style="color:#92400e;">{{ collect($assets)->where('state', 'review')->count() }}</div></div>
            <div class="metric-card"><div class="metric-label">Retired</div>  <div class="metric-value" style="color:var(--muted);">{{ collect($assets)->where('state', 'retired')->count() }}</div></div>
        </div>

        <div class="surface-card" style="padding:14px;">
            <div class="row-between" style="gap:12px; align-items:flex-start;">
                <div>
                    <div class="entity-title">Asset catalog list</div>
                    <div class="table-note">This list stays focused on browse, compare, and open. Ownership, workflow, and governed changes stay in Asset Detail.</div>
                </div>
            </div>
        </div>

        {{-- ── Asset list ─────────────────────────────────────────────────────── --}}
        <div class="table-card">
            <table class="entity-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type / Criticality</th>
                        <th>Owner</th>
                        <th>Classification</th>
                        <th>State</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($assets as $asset)
                        <tr>
                            <td>
                                <div class="entity-title">{{ $asset['name'] }}</div>
                                <div class="entity-id">{{ $asset['id'] }}</div>
                            </td>
                            <td>
                                <div>{{ $asset['type_label'] }}</div>
                                @php $critPill = match(strtolower($asset['criticality'])) { 'high' => 'pill-high', 'medium' => 'pill-medium', 'low' => 'pill-low', default => '' }; @endphp
                                <span class="pill {{ $critPill }}" style="margin-top:4px; display:inline-block;">{{ $asset['criticality_label'] }}</span>
                            </td>
                            <td>
                                @if (($asset['owner_assignments'] ?? []) !== [])
                                    <div>{{ $asset['owner_assignments'][0]['display_name'] }}</div>
                                    @if (count($asset['owner_assignments']) > 1)
                                        <div class="table-note">+{{ count($asset['owner_assignments']) - 1 }} more owner{{ count($asset['owner_assignments']) > 2 ? 's' : '' }}</div>
                                    @else
                                        <div class="table-note">{{ $asset['owner_assignments'][0]['kind'] }}</div>
                                    @endif
                                @else
                                    <span class="table-note">No owner</span>
                                @endif
                            </td>
                            <td>{{ $asset['classification_label'] }}</td>
                            <td>
                                @php
                                    $sPill = match($asset['state']) {
                                        'active'  => 'pill-active',
                                        'review'  => 'pill-review',
                                        'retired' => 'pill-retired',
                                        default   => '',
                                    };
                                @endphp
                                <span class="pill {{ $sPill }}">{{ $asset['state'] }}</span>
                            </td>
                            <td>
                                <a class="button button-secondary" href="{{ $asset['open_url'] }}&{{ http_build_query(['context_label' => 'Assets', 'context_back_url' => $assets_list_url]) }}">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center; padding:28px;">
                                <span class="muted-note">No assets yet.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    @endif
</section>
