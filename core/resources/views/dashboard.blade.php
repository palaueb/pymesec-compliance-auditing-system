<section class="module-screen compact">
    <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
        <div class="metric-card"><div class="metric-label">Assets in view</div><div class="metric-value">{{ $metrics['assets'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Risks under assessment</div><div class="metric-value">{{ $metrics['risks_assessing'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Controls in review</div><div class="metric-value">{{ $metrics['controls_review'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Open findings</div><div class="metric-value">{{ $metrics['findings_open'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Requested exceptions</div><div class="metric-value">{{ $metrics['exceptions_requested'] }}</div></div>
    </div>

    <div class="surface-note">
        @if ($organization)
            Working inside <strong>{{ $organization }}</strong>@if ($scope), scope <strong>{{ $scope }}</strong>@endif.
        @else
            Select an organization to focus the dashboard on one workspace.
        @endif
        @if ($role_sets !== [])
            Current role sets: {{ implode(', ', $role_sets) }}.
        @endif
    </div>

    <div class="overview-grid" style="grid-template-columns:1.4fr 1fr;">
        <div class="table-card">
            <div class="screen-header">
                <div>
                    <h2 class="screen-title" style="font-size:26px;">Today in your workspace</h2>
                    <p class="screen-subtitle">Open the areas that usually need review, evidence, or follow-up.</p>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                @forelse ($quick_links as $link)
                    <a class="rail-item" href="{{ $link['url'] }}" style="text-decoration:none; color:inherit;">
                        <div class="entity-title">{{ $link['label'] }}</div>
                        <div class="table-note">{{ $link['copy'] }}</div>
                    </a>
                @empty
                    <div class="surface-note" style="grid-column:1 / -1;">
                        No workspaces are visible for the current access context yet.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="surface-card" style="padding:18px;">
            <div class="eyebrow">Audit focus</div>
            <h2 class="screen-title" style="font-size:26px; margin-top:6px;">What to review next</h2>
            <p class="screen-subtitle">Use this as a quick triage view before diving into one module.</p>
            <div class="data-stack" style="margin-top:14px;">
                <div class="data-item">Follow assets still under lifecycle review.</div>
                <div class="data-item">Check risks that remain in assessment.</div>
                <div class="data-item">Push controls waiting for evidence or approval.</div>
                <div class="data-item">Keep findings and exceptions moving toward closure.</div>
            </div>
        </div>
    </div>

    <div class="table-card">
        <div class="screen-header">
            <div>
                <h2 class="screen-title" style="font-size:26px;">Recent audit activity</h2>
                <p class="screen-subtitle">Latest sensitive changes recorded by the platform audit trail.</p>
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
                @forelse ($recent_audit as $record)
                    <tr>
                        <td>
                            <div class="entity-title">{{ $record['event_type'] }}</div>
                            <div class="entity-id">{{ $record['target_type'] ?? 'n/a' }} / {{ $record['target_id'] ?? 'n/a' }}</div>
                        </td>
                        <td>{{ $record['outcome'] }}</td>
                        <td>{{ $record['origin_component'] }}</td>
                        <td>{{ $record['created_at'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="muted-note">No audit activity has been recorded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
