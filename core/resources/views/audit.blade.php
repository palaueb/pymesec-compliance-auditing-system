<section class="module-screen compact">
    <div class="overview-grid">
        <div class="metric-card"><div class="metric-label">{{ __('core.audit.metric.events') }}</div><div class="metric-value">{{ $metrics['events'] }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('core.audit.metric.failures') }}</div><div class="metric-value">{{ $metrics['failures'] }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('core.audit.metric.components') }}</div><div class="metric-value">{{ $metrics['components'] }}</div></div>
    </div>

    <div class="surface-note">
        {{ __('core.audit.summary') }}
    </div>

    <div class="table-card">
        <table class="entity-table">
            <thead>
                <tr>
                    <th>{{ __('core.audit.table.event') }}</th>
                    <th>{{ __('core.audit.table.actor') }}</th>
                    <th>{{ __('core.audit.table.component') }}</th>
                    <th>{{ __('core.audit.table.target') }}</th>
                    <th>{{ __('core.audit.table.when') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($records as $record)
                    <tr>
                        <td>
                            <div class="entity-title">{{ $record['event_type'] }}</div>
                            <div class="entity-id">{{ $record['outcome'] }}</div>
                        </td>
                        <td>{{ $record['principal_id'] ?? __('core.status.system') }}</td>
                        <td>{{ $record['origin_component'] }}</td>
                        <td>{{ $record['target_type'] ?? __('n/a') }} / {{ $record['target_id'] ?? __('n/a') }}</td>
                        <td>{{ $record['created_at'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
