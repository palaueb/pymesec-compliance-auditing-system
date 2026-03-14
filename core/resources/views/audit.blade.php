<section class="module-screen compact">
    <div class="overview-grid">
        <div class="metric-card"><div class="metric-label">Visible events</div><div class="metric-value">{{ $metrics['events'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Failures</div><div class="metric-value">{{ $metrics['failures'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Components</div><div class="metric-value">{{ $metrics['components'] }}</div></div>
    </div>

    <div class="surface-note">
        Exports are available from the toolbar. This view intentionally stays readable first and forensic second.
    </div>

    <div class="table-card">
        <table class="entity-table">
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Actor</th>
                    <th>Component</th>
                    <th>Target</th>
                    <th>When</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($records as $record)
                    <tr>
                        <td>
                            <div class="entity-title">{{ $record['event_type'] }}</div>
                            <div class="entity-id">{{ $record['outcome'] }}</div>
                        </td>
                        <td>{{ $record['principal_id'] ?? 'system' }}</td>
                        <td>{{ $record['origin_component'] }}</td>
                        <td>{{ $record['target_type'] ?? 'n/a' }} / {{ $record['target_id'] ?? 'n/a' }}</td>
                        <td>{{ $record['created_at'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
