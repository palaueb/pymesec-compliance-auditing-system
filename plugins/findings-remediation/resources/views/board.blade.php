<style>
    .pill-planned    { background: rgba(31,42,34,0.06);   color: var(--muted); }
    .pill-in-progress{ background: rgba(245,158,11,0.14); color: #92400e; }
    .pill-blocked    { background: rgba(239,68,68,0.14);  color: #991b1b; }
    .pill-done       { background: rgba(34,197,94,0.14);  color: #166534; }
</style>

<section class="module-screen">
    <div class="overview-grid">
        <div class="metric-card"><div class="metric-label">Actions</div><div class="metric-value">{{ count($actions) }}</div></div>
        <div class="metric-card"><div class="metric-label">Planned</div><div class="metric-value">{{ collect($actions)->where('status', 'planned')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">In progress</div><div class="metric-value">{{ collect($actions)->where('status', 'in-progress')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">Done</div><div class="metric-value">{{ collect($actions)->where('status', 'done')->count() }}</div></div>
    </div>

    <div class="surface-card">
        <div class="table-note">Remediation actions across all findings. Open a finding to update action details, assign owners, or record progress notes.</div>
    </div>

    <div class="table-card">
        <table class="entity-table">
            <thead>
                <tr>
                    <th>Action</th>
                    <th>Finding</th>
                    <th>Status</th>
                    <th>Owner</th>
                    <th>Due</th>
                    <th>Open</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($actions as $action)
                    <tr>
                        <td>
                            <div class="entity-title">{{ $action['title'] }}</div>
                            <div class="entity-id">{{ $action['id'] }}</div>
                            @if ($action['notes'] !== '')
                                <div class="table-note">{{ $action['notes'] }}</div>
                            @endif
                        </td>
                        <td>
                            <div class="entity-title">{{ $action['finding']['title'] }}</div>
                            <div class="table-note">{{ $action['finding']['id'] }}</div>
                        </td>
                        <td>
                            @php $aPill = match($action['status']) {
                                'planned'     => 'pill-planned',
                                'in-progress' => 'pill-in-progress',
                                'blocked'     => 'pill-blocked',
                                'done'        => 'pill-done',
                                default       => '',
                            }; @endphp
                            <span class="pill {{ $aPill }}">{{ $action['status'] }}</span>
                        </td>
                        <td>
                            @if ($action['owner_assignment'] !== null)
                                <div>{{ $action['owner_assignment']['display_name'] }}</div>
                                <div class="table-note">{{ $action['owner_assignment']['kind'] }}</div>
                            @else
                                <span class="muted-note">No owner assigned</span>
                            @endif
                        </td>
                        <td>{{ $action['due_on'] !== '' ? $action['due_on'] : '—' }}</td>
                        <td>
                            <a class="button button-secondary" href="{{ $action['finding_open_url'] }}&{{ http_build_query(['context_label' => 'Remediation board', 'context_back_url' => route('core.shell.index', [...$query, 'menu' => 'plugin.findings-remediation.board'])]) }}">Open finding</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6"><span class="muted-note">No remediation actions yet.</span></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
