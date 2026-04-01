<section class="module-screen compact">
    <div class="overview-grid">
        <div class="surface-card metric-card">
            <div class="metric-label">Assignments</div>
            <div class="metric-value">{{ $metrics['assignments'] }}</div>
        </div>
        <div class="surface-card metric-card">
            <div class="metric-label">Actors</div>
            <div class="metric-value">{{ $metrics['actors'] }}</div>
        </div>
        <div class="surface-card metric-card">
            <div class="metric-label">Domains</div>
            <div class="metric-value">{{ $metrics['domains'] }}</div>
        </div>
    </div>

    <div class="surface-card">
        <div class="entity-title">Assignment register</div>
        <div class="table-note" style="margin-top:6px;">This view stays focused on accountability records by actor, governed object, type, and scope. Use Actors to manage the responsible profiles and the linked workspace records to inspect the underlying domain state.</div>
    </div>

    <div class="table-card">
        <table class="entity-table">
            <thead>
                <tr>
                    <th>Actor</th>
                    <th>Domain object</th>
                    <th>Type</th>
                    <th>Scope</th>
                    <th>Open</th>
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
                                <span class="muted-note">Unknown actor</span>
                            @endif
                        </td>
                        <td>{{ $row['domain_object_type'] }}:{{ $row['domain_object_id'] }}</td>
                        <td><span class="tag">{{ $row['assignment_type'] }}</span></td>
                        <td>{{ $row['scope_id'] ?? 'organization-wide' }}</td>
                        <td>
                            @if (is_string($row['subject_url'] ?? null))
                                <a class="button button-ghost" href="{{ $row['subject_url'] }}">Open</a>
                            @else
                                <span class="muted-note">No workspace view</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted-note">No assignments found for the current organization and scope context.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
