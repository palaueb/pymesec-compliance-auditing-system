<section class="module-screen compact">
    <div class="overview-grid">
        <div class="metric-card"><div class="metric-label">Enabled</div><div class="metric-value">{{ $metrics['enabled'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Booted</div><div class="metric-value">{{ $metrics['booted'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Needs attention</div><div class="metric-value">{{ $metrics['attention'] }}</div></div>
    </div>

    <div class="surface-note">
        Plugin lifecycle is still managed by environment configuration and CLI. This screen gives you the real runtime picture without leaving the shell.
    </div>

    <div class="table-card">
        <table class="entity-table">
            <thead>
                <tr>
                    <th>Plugin</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Capabilities</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($plugins as $plugin)
                    <tr>
                        <td>
                            <div class="entity-title">{{ $plugin['name'] }}</div>
                            <div class="entity-id">{{ $plugin['id'] }} · {{ $plugin['version'] }}</div>
                        </td>
                        <td>{{ $plugin['type'] }}</td>
                        <td>
                            <span class="pill">{{ $plugin['enabled'] ? 'enabled' : 'disabled' }}</span>
                            <div class="table-note">{{ $plugin['booted'] ? 'booted' : 'not booted' }}</div>
                        </td>
                        <td>{{ $plugin['permission_count'] }} perms · {{ $plugin['route_count'] }} routes · {{ $plugin['menu_count'] }} menus</td>
                        <td>{{ $plugin['reason'] ?? 'ok' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
