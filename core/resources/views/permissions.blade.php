<section class="module-screen compact">
    <div class="surface-note">
        Governance page. This catalog stays read-only and explains which permissions exist, where they come from, and in which contexts they apply.
    </div>

    <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
        <div class="metric-card"><div class="metric-label">Registered</div><div class="metric-value">{{ $metrics['total'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Platform scope</div><div class="metric-value">{{ $metrics['platform'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Organization scope</div><div class="metric-value">{{ $metrics['organization'] }}</div></div>
    </div>

    <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
        @foreach ($origins as $origin)
            <div class="rail-item">
                <div class="rail-label">{{ $origin['origin'] }}</div>
                <div class="sidebar-context-value">{{ $origin['count'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="table-card">
        <table class="entity-table">
            <thead>
                <tr>
                    <th>Permission</th>
                    <th>Label</th>
                    <th>Origin</th>
                    <th>Contexts</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($permissions as $permission)
                    <tr>
                        <td><div class="entity-title">{{ $permission['key'] }}</div></td>
                        <td>{{ $permission['label'] }}</td>
                        <td>{{ $permission['origin'] }}</td>
                        <td>{{ implode(', ', $permission['contexts']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
