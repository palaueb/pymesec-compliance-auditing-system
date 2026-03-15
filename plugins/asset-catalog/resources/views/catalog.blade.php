<section class="module-screen">
    @if (is_array($selected_asset))
        <div class="surface-card" style="padding:16px; display:grid; gap:16px;">
            <div class="row-between" style="align-items:flex-start;">
                <div>
                    <div class="eyebrow">Asset</div>
                    <h2 class="screen-title" style="font-size:28px;">{{ $selected_asset['name'] }}</h2>
                    <div class="table-note">{{ $selected_asset['id'] }}</div>
                    <div class="table-note">{{ ucfirst($selected_asset['type']) }}</div>
                </div>
                <div class="action-cluster">
                    <a class="button button-ghost" href="{{ $assets_list_url }}">Back to assets</a>
                    <span class="pill">{{ $selected_asset['state'] }}</span>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
                <div class="metric-card"><div class="metric-label">Type</div><div class="metric-value" style="font-size:20px;">{{ ucfirst($selected_asset['type']) }}</div></div>
                <div class="metric-card"><div class="metric-label">Criticality</div><div class="metric-value" style="font-size:20px;">{{ ucfirst($selected_asset['criticality']) }}</div></div>
                <div class="metric-card"><div class="metric-label">Classification</div><div class="metric-value" style="font-size:20px;">{{ ucfirst($selected_asset['classification']) }}</div></div>
                <div class="metric-card"><div class="metric-label">Scope</div><div class="metric-value" style="font-size:20px;">{{ $selected_asset['scope_id'] !== '' ? $selected_asset['scope_id'] : 'Org-wide' }}</div></div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Overview</div>
                    <div class="table-note" style="margin-top:10px;">Owner actor: {{ $selected_asset['owner_assignment']['display_name'] ?? 'No actor owner assigned' }}</div>
                    <div class="table-note">Owner label: {{ $selected_asset['owner_label'] !== '' ? $selected_asset['owner_label'] : 'No owner label' }}</div>
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Workflow</div>
                    @if ($selected_asset['transitions'] !== [])
                        <div class="action-cluster" style="margin-top:10px;">
                            @foreach ($selected_asset['transitions'] as $transition)
                                <form method="POST" action="{{ str_replace('__TRANSITION__', $transition, $selected_asset['transition_route']) }}">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.asset-catalog.root">
                                    <input type="hidden" name="asset_id" value="{{ $selected_asset['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <button class="button button-secondary" type="submit">{{ ucwords(str_replace('-', ' ', $transition)) }}</button>
                                </form>
                            @endforeach
                        </div>
                    @else
                        <div class="table-note" style="margin-top:10px;">View-only access</div>
                    @endif

                    <div class="data-stack" style="margin-top:12px;">
                        @forelse ($selected_asset['history'] as $history)
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

            @if ($can_manage_assets)
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Edit asset</div>
                    <form class="upload-form" method="POST" action="{{ $selected_asset['update_route'] }}" style="margin-top:10px;">
                        @csrf
                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                        <input type="hidden" name="menu" value="plugin.asset-catalog.root">
                        <input type="hidden" name="asset_id" value="{{ $selected_asset['id'] }}">
                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                        <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                            <div class="field">
                                <label class="field-label">Name</label>
                                <input class="field-input" name="name" value="{{ $selected_asset['name'] }}" required>
                            </div>
                            <div class="field">
                                <label class="field-label">Type</label>
                                <input class="field-input" name="type" value="{{ $selected_asset['type'] }}" required>
                            </div>
                            <div class="field">
                                <label class="field-label">Criticality</label>
                                <input class="field-input" name="criticality" value="{{ $selected_asset['criticality'] }}" required>
                            </div>
                            <div class="field">
                                <label class="field-label">Classification</label>
                                <input class="field-input" name="classification" value="{{ $selected_asset['classification'] }}" required>
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
                                <label class="field-label">Owner label</label>
                                <input class="field-input" name="owner_label" value="{{ $selected_asset['owner_label'] }}">
                            </div>
                            <div class="field">
                                <label class="field-label">Owner actor</label>
                                <select class="field-select" name="owner_actor_id">
                                    <option value="">Keep current owner</option>
                                    @foreach ($owner_actor_options as $actor)
                                        <option value="{{ $actor['id'] }}" @selected(($selected_asset['owner_assignment']['id'] ?? null) === $actor['id'])>{{ $actor['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="action-cluster" style="margin-top:14px;">
                            <button class="button button-secondary" type="submit">Save changes</button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    @else
        @if ($can_manage_assets)
            <div class="surface-card" id="asset-editor" hidden>
                <div class="row-between" style="margin-bottom:14px;">
                    <div>
                        <div class="eyebrow">Create</div>
                        <div class="entity-title" style="font-size:24px;">New asset</div>
                    </div>
                </div>

                <form class="upload-form" method="POST" action="{{ $create_route }}">
                    @csrf
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                    <input type="hidden" name="menu" value="plugin.asset-catalog.root">
                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">

                    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                        <div class="field">
                            <label class="field-label" for="asset-name">Name</label>
                            <input class="field-input" id="asset-name" name="name" required>
                        </div>
                        <div class="field">
                            <label class="field-label" for="asset-type">Type</label>
                            <input class="field-input" id="asset-type" name="type" placeholder="application, service, endpoint" required>
                        </div>
                        <div class="field">
                            <label class="field-label" for="asset-criticality">Criticality</label>
                            <input class="field-input" id="asset-criticality" name="criticality" placeholder="high, medium, low" required>
                        </div>
                        <div class="field">
                            <label class="field-label" for="asset-classification">Classification</label>
                            <input class="field-input" id="asset-classification" name="classification" placeholder="restricted, internal" required>
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
                            <label class="field-label" for="asset-owner-label">Owner label</label>
                            <input class="field-input" id="asset-owner-label" name="owner_label" placeholder="Finance Operations">
                        </div>
                        <div class="field">
                            <label class="field-label" for="asset-owner-actor">Owner actor</label>
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

        <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
            <div class="metric-card"><div class="metric-label">Assets</div><div class="metric-value">{{ count($assets) }}</div></div>
            <div class="metric-card"><div class="metric-label">Active</div><div class="metric-value">{{ collect($assets)->where('state', 'active')->count() }}</div></div>
            <div class="metric-card"><div class="metric-label">In review</div><div class="metric-value">{{ collect($assets)->where('state', 'review')->count() }}</div></div>
            <div class="metric-card"><div class="metric-label">Retired</div><div class="metric-value">{{ collect($assets)->where('state', 'retired')->count() }}</div></div>
        </div>

        <div class="surface-card">
            <div class="table-note">Open an asset to review ownership, workflow status, and maintenance details.</div>
        </div>

        <div class="table-card">
            <table class="entity-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Owner</th>
                        <th>Criticality</th>
                        <th>Classification</th>
                        <th>State</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($assets as $asset)
                        <tr>
                            <td>
                                <div class="entity-title">{{ $asset['name'] }}</div>
                                <div class="entity-id">{{ $asset['id'] }}</div>
                            </td>
                            <td>{{ ucfirst($asset['type']) }}</td>
                            <td>
                                @if ($asset['owner_assignment'] !== null)
                                    <div>{{ $asset['owner_assignment']['display_name'] }}</div>
                                    <div class="table-note">{{ $asset['owner_assignment']['kind'] }}</div>
                                @else
                                    {{ $asset['owner_label'] !== '' ? $asset['owner_label'] : 'No owner assigned' }}
                                @endif
                            </td>
                            <td>{{ ucfirst($asset['criticality']) }}</td>
                            <td>{{ ucfirst($asset['classification']) }}</td>
                            <td><span class="pill">{{ $asset['state'] }}</span></td>
                            <td>
                                <a class="button button-secondary" href="{{ $asset['open_url'] }}">Edit details</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
