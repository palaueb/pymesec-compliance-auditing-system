<section class="module-screen">
    @if ($can_manage_assets)
        <div class="surface-card" id="asset-editor">
            <div class="row-between" style="margin-bottom:14px;">
                <div class="screen-title" style="font-size:26px;">New Asset</div>
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
                    <button class="button button-primary" type="submit">Create Asset</button>
                </div>
            </form>
        </div>
    @endif

    <div class="overview-grid">
        <div class="metric-card"><div class="metric-label">Assets</div><div class="metric-value">{{ count($assets) }}</div></div>
        <div class="metric-card"><div class="metric-label">Active</div><div class="metric-value">{{ collect($assets)->where('state', 'active')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">In Review</div><div class="metric-value">{{ collect($assets)->where('state', 'review')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">Retired</div><div class="metric-value">{{ collect($assets)->where('state', 'retired')->count() }}</div></div>
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
                    <th>{{ $can_manage_assets ? 'Actions' : 'Access' }}</th>
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
                            @if ($asset['transitions'] !== [])
                                <div class="action-cluster">
                                    @foreach ($asset['transitions'] as $transition)
                                        <form method="POST" action="{{ str_replace('__TRANSITION__', $transition, $asset['transition_route']) }}">
                                            @csrf
                                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                            <input type="hidden" name="menu" value="plugin.asset-catalog.root">
                                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                            <button class="button button-secondary" type="submit">{{ ucwords(str_replace('-', ' ', $transition)) }}</button>
                                        </form>
                                    @endforeach
                                </div>
                            @else
                                <span class="muted-note">View-only access</span>
                            @endif

                            @if ($can_manage_assets)
                                <details style="margin-top:10px;">
                                    <summary class="button button-ghost" style="display:inline-flex;">Edit</summary>
                                    <form class="upload-form" method="POST" action="{{ route('plugin.asset-catalog.update', ['assetId' => $asset['id']]) }}" style="margin-top:10px;">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="menu" value="plugin.asset-catalog.root">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <div class="field">
                                            <label class="field-label">Name</label>
                                            <input class="field-input" name="name" value="{{ $asset['name'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Type</label>
                                            <input class="field-input" name="type" value="{{ $asset['type'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Criticality</label>
                                            <input class="field-input" name="criticality" value="{{ $asset['criticality'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Classification</label>
                                            <input class="field-input" name="classification" value="{{ $asset['classification'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Scope</label>
                                            <select class="field-select" name="scope_id">
                                                <option value="">Organization-wide</option>
                                                @foreach ($scope_options as $scope)
                                                    <option value="{{ $scope['id'] }}" @selected($asset['scope_id'] === $scope['id'])>{{ $scope['name'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Owner label</label>
                                            <input class="field-input" name="owner_label" value="{{ $asset['owner_label'] }}">
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Owner actor</label>
                                            <select class="field-select" name="owner_actor_id">
                                                <option value="">Keep current owner</option>
                                                @foreach ($owner_actor_options as $actor)
                                                    <option value="{{ $actor['id'] }}" @selected(($asset['owner_assignment']['id'] ?? null) === $actor['id'])>{{ $actor['label'] }}</option>
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
