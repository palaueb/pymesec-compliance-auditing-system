<section class="module-screen compact">
    <div class="overview-grid">
        <div class="metric-card"><div class="metric-label">Plugins</div><div class="metric-value">{{ $metrics['plugins'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Permissions</div><div class="metric-value">{{ $metrics['permissions'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Roles</div><div class="metric-value">{{ $metrics['roles'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Organizations</div><div class="metric-value">{{ $metrics['organizations'] }}</div></div>
    </div>

    <div class="surface-note">
        Platform administration stays separate from organization workspaces. Use these areas to inspect system state, not to manage day-to-day records.
    </div>

    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
        @foreach ($quick_links as $link)
            <a class="rail-item" href="{{ $link['url'] }}" style="text-decoration:none; color:inherit;">
                <div class="entity-title">{{ $link['label'] }}</div>
                <div class="table-note">{{ $link['copy'] }}</div>
            </a>
        @endforeach
    </div>

    <div class="table-card">
        <div class="screen-header">
            <div>
                <h2 class="screen-title" style="font-size:26px;">Recent audit activity</h2>
                <p class="screen-subtitle">The latest sensitive changes across core and plugins.</p>
            </div>
        </div>
        <table class="entity-table">
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Outcome</th>
                    <th>Component</th>
                    <th>When</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recent_audit as $record)
                    <tr>
                        <td>
                            <div class="entity-title">{{ $record['event_type'] }}</div>
                            <div class="entity-id">{{ $record['target_type'] ?? 'n/a' }} / {{ $record['target_id'] ?? 'n/a' }}</div>
                        </td>
                        <td>{{ $record['outcome'] }}</td>
                        <td>{{ $record['origin_component'] }}</td>
                        <td>{{ $record['created_at'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
