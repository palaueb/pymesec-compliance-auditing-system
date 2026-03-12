<style>
    .actor-grid { display: grid; gap: 16px; }
    .actor-summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }
    .actor-kpi {
        border: 1px solid var(--line);
        background: rgba(255,255,255,0.44);
        padding: 14px;
    }
    .actor-kpi small {
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    .actor-kpi strong {
        display: block;
        margin-top: 6px;
        font-size: 22px;
        font-family: var(--font-heading);
    }
    .actor-table {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid var(--line);
        background: rgba(255,255,255,0.56);
    }
    .actor-table th,
    .actor-table td {
        padding: 12px 14px;
        border-bottom: 1px solid rgba(31,42,34,0.08);
        text-align: left;
        vertical-align: top;
        font-size: 14px;
    }
    .actor-table th {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--muted);
        background: rgba(255,255,255,0.42);
    }
    .actor-badge {
        display: inline-flex;
        border: 1px solid var(--line);
        padding: 4px 8px;
        font-size: 12px;
        border-radius: 4px;
        background: rgba(255,255,255,0.65);
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }
    @media (max-width: 960px) {
        .actor-summary { grid-template-columns: 1fr; }
    }
</style>
<section class="actor-grid">
    <div class="actor-summary">
        <div class="actor-kpi"><small>Actors</small><strong>{{ count($rows) }}</strong></div>
        <div class="actor-kpi"><small>People</small><strong>{{ collect($rows)->where('actor.kind', 'person')->count() }}</strong></div>
        <div class="actor-kpi"><small>Teams</small><strong>{{ collect($rows)->where('actor.kind', 'team')->count() }}</strong></div>
    </div>

    <table class="actor-table">
        <thead>
            <tr>
                <th>Actor</th>
                <th>Kind</th>
                <th>Linked principals</th>
                <th>Assignments</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>
                        <strong>{{ $row['actor']->displayName }}</strong><br>
                        <span style="color:var(--muted);font-size:12px;">{{ $row['actor']->id }}</span>
                    </td>
                    <td><span class="actor-badge">{{ $row['actor']->kind }}</span></td>
                    <td>
                        @if ($row['links'] === [])
                            <span style="color:var(--muted);font-size:12px;">No principal linkage</span>
                        @else
                            @foreach ($row['links'] as $link)
                                <div>{{ $link->principalId }}</div>
                            @endforeach
                        @endif
                    </td>
                    <td>
                        @if ($row['assignments'] === [])
                            <span style="color:var(--muted);font-size:12px;">No active assignments</span>
                        @else
                            @foreach ($row['assignments'] as $assignment)
                                <div>{{ $assignment->assignmentType }} -> {{ $assignment->domainObjectType }}:{{ $assignment->domainObjectId }}</div>
                            @endforeach
                        @endif
                    </td>
                    <td>{{ $row['actor']->metadata['title'] ?? 'Functional directory entry' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</section>
