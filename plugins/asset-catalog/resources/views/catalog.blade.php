<style>
    .asset-screen { display: grid; gap: 16px; }
    .asset-table {
        width: 100%;
        border-collapse: collapse;
        background: rgba(255,255,255,0.56);
        border: 1px solid var(--line);
    }
    .asset-table th,
    .asset-table td {
        padding: 12px 14px;
        border-bottom: 1px solid rgba(31,42,34,0.08);
        text-align: left;
        vertical-align: top;
        font-size: 14px;
    }
    .asset-table th {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--muted);
        background: rgba(255,255,255,0.42);
    }
    .state-pill {
        display: inline-flex;
        align-items: center;
        border: 1px solid var(--line);
        padding: 4px 8px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        background: rgba(255,255,255,0.55);
        text-transform: capitalize;
    }
    .asset-actions { display: flex; flex-wrap: wrap; gap: 8px; }
    .asset-actions form { margin: 0; }
    .asset-btn {
        border: 1px solid var(--line);
        background: rgba(255,255,255,0.68);
        color: var(--ink);
        padding: 6px 10px;
        border-radius: 4px;
        font-weight: 700;
        cursor: pointer;
    }
    .asset-headline {
        display: grid;
        gap: 4px;
    }
    .asset-meta {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }
    .asset-kpi {
        background: rgba(255,255,255,0.44);
        border: 1px solid var(--line);
        padding: 14px;
    }
    .asset-kpi small {
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    .asset-kpi strong {
        display: block;
        margin-top: 6px;
        font-size: 22px;
        font-family: var(--font-heading);
    }
    @media (max-width: 960px) {
        .asset-meta { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
</style>
<section class="asset-screen">
    <div class="asset-headline">
        <p style="margin:0;color:var(--muted);">Functional plugin on top of the core shell, permission engine, and workflow runtime.</p>
    </div>

    <div class="asset-meta">
        <div class="asset-kpi"><small>Assets</small><strong>{{ count($assets) }}</strong></div>
        <div class="asset-kpi"><small>Active</small><strong>{{ collect($assets)->where('state', 'active')->count() }}</strong></div>
        <div class="asset-kpi"><small>In Review</small><strong>{{ collect($assets)->where('state', 'review')->count() }}</strong></div>
        <div class="asset-kpi"><small>Retired</small><strong>{{ collect($assets)->where('state', 'retired')->count() }}</strong></div>
    </div>

    <table class="asset-table">
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
                        <strong>{{ $asset['name'] }}</strong><br>
                        <span style="color:var(--muted);font-size:12px;">{{ $asset['id'] }}</span>
                    </td>
                    <td>{{ ucfirst($asset['type']) }}</td>
                    <td>
                        @if ($asset['owner_assignment'] !== null)
                            {{ $asset['owner_assignment']['display_name'] }}<br>
                            <span style="color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:0.06em;">{{ $asset['owner_assignment']['kind'] }}</span>
                        @else
                            {{ $asset['owner'] }}
                        @endif
                    </td>
                    <td>{{ ucfirst($asset['criticality']) }}</td>
                    <td>{{ ucfirst($asset['classification']) }}</td>
                    <td><span class="state-pill">{{ $asset['state'] }}</span></td>
                    <td>
                        @if ($asset['transitions'] !== [])
                            <div class="asset-actions">
                                @foreach ($asset['transitions'] as $transition)
                                    <form method="POST" action="{{ str_replace('__TRANSITION__', $transition, $asset['transition_route']) }}">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="menu" value="plugin.asset-catalog.root">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <button class="asset-btn" type="submit">{{ ucwords(str_replace('-', ' ', $transition)) }}</button>
                                    </form>
                                @endforeach
                            </div>
                        @else
                            <span style="color:var(--muted);font-size:12px;">View-only access</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</section>
