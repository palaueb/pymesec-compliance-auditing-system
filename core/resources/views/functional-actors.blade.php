<section class="module-screen compact">
    <div class="overview-grid">
        <div class="metric-card"><div class="metric-label">Actors</div><div class="metric-value">{{ $metrics['actors'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Principal links</div><div class="metric-value">{{ $metrics['links'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Assignments</div><div class="metric-value">{{ $metrics['assignments'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Organizations</div><div class="metric-value">{{ $metrics['organizations'] }}</div></div>
    </div>

    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
        <div class="table-card">
            <div class="screen-header">
                <div>
                    <h2 class="screen-title" style="font-size:24px;">Actors</h2>
                </div>
            </div>
            <table class="entity-table">
                <thead>
                    <tr>
                        <th>Actor</th>
                        <th>Kind</th>
                        <th>Context</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($actors as $actor)
                        <tr>
                            <td>
                                <div class="entity-title">{{ $actor['display_name'] }}</div>
                                <div class="entity-id">{{ $actor['id'] }}</div>
                            </td>
                            <td>{{ $actor['kind'] }}</td>
                            <td>{{ $actor['organization_id'] }}{{ ($actor['scope_id'] ?? null) !== null ? ' / '.$actor['scope_id'] : '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="table-card">
            <div class="screen-header">
                <div>
                    <h2 class="screen-title" style="font-size:24px;">Principal links</h2>
                </div>
            </div>
            <div class="data-stack">
                @foreach ($links as $link)
                    <div class="data-item">
                        <div class="entity-title">{{ $link['principal_id'] }}</div>
                        <div class="entity-id">{{ $link['functional_actor_id'] }} · {{ $link['organization_id'] }}</div>
                        <div class="table-note">{{ $link['created_at'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="table-card">
        <div class="screen-header">
            <div>
                <h2 class="screen-title" style="font-size:24px;">Assignments</h2>
            </div>
        </div>
        <table class="entity-table">
            <thead>
                <tr>
                    <th>Actor</th>
                    <th>Assignment</th>
                    <th>Object</th>
                    <th>Context</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($assignments as $assignment)
                    <tr>
                        <td>{{ $assignment['functional_actor_id'] }}</td>
                        <td>{{ $assignment['assignment_type'] }}</td>
                        <td>{{ $assignment['domain_object_type'] }} / {{ $assignment['domain_object_id'] }}</td>
                        <td>{{ $assignment['organization_id'] }}{{ ($assignment['scope_id'] ?? null) !== null ? ' / '.$assignment['scope_id'] : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
