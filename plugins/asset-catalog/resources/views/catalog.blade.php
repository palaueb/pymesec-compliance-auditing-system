<section class="module-screen">
    <div class="surface-note">
        <div class="body-copy">Functional catalog on top of the core shell, workflow runtime, permissions, and actor assignments.</div>
    </div>

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
                    <th>{{ $can_manage_assets ? 'Transitions' : 'Access' }}</th>
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
                                {{ $asset['owner'] }}
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
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
