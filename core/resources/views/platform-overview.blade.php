<section class="module-screen compact">
    <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
        <div class="metric-card"><div class="metric-label">{{ __('core.platform.metric.modules') }}</div><div class="metric-value">{{ $metrics['plugins'] }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('core.platform.metric.permissions') }}</div><div class="metric-value">{{ $metrics['permissions'] }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('core.platform.metric.roles') }}</div><div class="metric-value">{{ $metrics['roles'] }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('core.platform.metric.organizations') }}</div><div class="metric-value">{{ $metrics['organizations'] }}</div></div>
    </div>

    <div class="surface-note">
        {{ __('core.platform.summary') }}
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
                <h2 class="screen-title" style="font-size:26px;">{{ __('core.platform.recent_title') }}</h2>
                <p class="screen-subtitle">{{ __('core.platform.recent_subtitle') }}</p>
            </div>
        </div>
        <table class="entity-table">
            <thead>
                <tr>
                    <th>{{ __('core.platform.table.event') }}</th>
                    <th>{{ __('core.platform.table.outcome') }}</th>
                    <th>{{ __('core.platform.table.component') }}</th>
                    <th>{{ __('core.platform.table.when') }}</th>
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
