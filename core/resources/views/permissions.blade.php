<section class="module-screen compact">
    <div class="surface-note">
        {{ __('core.permissions.summary') }}
    </div>

    <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
        <div class="metric-card"><div class="metric-label">{{ __('core.permissions.metric.registered') }}</div><div class="metric-value">{{ $metrics['total'] }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('core.permissions.metric.platform_scope') }}</div><div class="metric-value">{{ $metrics['platform'] }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('core.permissions.metric.organization_scope') }}</div><div class="metric-value">{{ $metrics['organization'] }}</div></div>
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
                    <th>{{ __('core.permissions.table.permission') }}</th>
                    <th>{{ __('core.permissions.table.label') }}</th>
                    <th>{{ __('core.permissions.table.origin') }}</th>
                    <th>{{ __('core.permissions.table.contexts') }}</th>
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
