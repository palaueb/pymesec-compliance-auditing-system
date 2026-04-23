<section class="module-screen compact">
    <div class="overview-grid">
        <div class="surface-card metric-card">
            <div class="metric-label">{{ __('core.functional-assignments.metric.assignments') }}</div>
            <div class="metric-value">{{ $metrics['assignments'] }}</div>
        </div>
        <div class="surface-card metric-card">
            <div class="metric-label">{{ __('core.functional-assignments.metric.actors') }}</div>
            <div class="metric-value">{{ $metrics['actors'] }}</div>
        </div>
        <div class="surface-card metric-card">
            <div class="metric-label">{{ __('core.functional-assignments.metric.domains') }}</div>
            <div class="metric-value">{{ $metrics['domains'] }}</div>
        </div>
    </div>

    <div class="surface-card">
        <div class="entity-title">{{ __('core.functional-assignments.title') }}</div>
        <div class="table-note" style="margin-top:6px;">{{ __('core.functional-assignments.summary') }}</div>
    </div>

    <div class="table-card">
        <table class="entity-table">
            <thead>
                <tr>
                    <th>{{ __('core.functional-assignments.table.actor') }}</th>
                    <th>{{ __('core.functional-assignments.table.domain_object') }}</th>
                    <th>{{ __('core.functional-assignments.table.type') }}</th>
                    <th>{{ __('core.functional-assignments.table.scope') }}</th>
                    <th>{{ __('core.functional-assignments.table.open') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>
                            @if (is_array($row['actor']))
                                <div class="entity-title">{{ $row['actor']['display_name'] }}</div>
                                <div class="table-note">{{ $row['actor']['kind'] }}</div>
                            @else
                                <span class="muted-note">{{ __('core.functional-assignments.unknown_actor') }}</span>
                            @endif
                        </td>
                        <td>{{ $row['domain_object_type'] }}:{{ $row['domain_object_id'] }}</td>
                        <td><span class="tag">{{ $row['assignment_type'] }}</span></td>
                        <td>{{ $row['scope_id'] ?? __('core.shell.organization_wide') }}</td>
                        <td>
                            @if (is_string($row['subject_url'] ?? null))
                                <a class="button button-ghost" href="{{ $row['subject_url'] }}">{{ __('core.actions.open') }}</a>
                            @else
                                <span class="muted-note">{{ __('core.functional-assignments.no_workspace_view') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted-note">{{ __('core.functional-assignments.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
