<section class="module-screen">
    <div class="overview-grid">
        <div class="metric-card"><div class="metric-label">Actors</div><div class="metric-value">{{ count($rows) }}</div></div>
        <div class="metric-card"><div class="metric-label">People</div><div class="metric-value">{{ collect($rows)->where('actor.kind', 'person')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">Teams</div><div class="metric-value">{{ collect($rows)->where('actor.kind', 'team')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">Assignments</div><div class="metric-value">{{ collect($rows)->sum(fn ($row) => count($row['assignments'])) }}</div></div>
    </div>

    <div class="table-card">
        <table class="entity-table">
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
                            <div class="entity-title">{{ $row['actor']->displayName }}</div>
                            <div class="entity-id">{{ $row['actor']->id }}</div>
                        </td>
                        <td><span class="tag">{{ $row['actor']->kind }}</span></td>
                        <td>
                            @if ($row['links'] === [])
                                <span class="muted-note">No principal linkage</span>
                            @else
                                <div class="data-stack">
                                    @foreach ($row['links'] as $link)
                                        <div class="data-item">{{ $link->principalId }}</div>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td>
                            @if ($row['assignments'] === [])
                                <span class="muted-note">No active assignments</span>
                            @else
                                <div class="data-stack">
                                    @foreach ($row['assignments'] as $assignment)
                                        <div class="data-item"><span class="tag">{{ $assignment->assignmentType }}</span> {{ $assignment->domainObjectType }}:{{ $assignment->domainObjectId }}</div>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td>{{ $row['actor']->metadata['title'] ?? 'Functional directory entry' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
