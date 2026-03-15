@php
    $selectedActor = is_array($selected_actor ?? null) ? $selected_actor : null;
@endphp

<section class="module-screen compact">
    <div class="overview-grid">
        <div class="metric-card"><div class="metric-label">Actors</div><div class="metric-value">{{ $metrics['actors'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Principal links</div><div class="metric-value">{{ $metrics['links'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Assignments</div><div class="metric-value">{{ $metrics['assignments'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Organizations</div><div class="metric-value">{{ $metrics['organizations'] }}</div></div>
    </div>

    @if ($selectedActor !== null)
        <div class="table-card">
            <div class="screen-header">
                <div>
                    <h2 class="screen-title" style="font-size:24px;">{{ $selectedActor['display_name'] }}</h2>
                    <p class="screen-subtitle">{{ $selectedActor['kind'] }} profile used to assign ownership and accountability across the workspace.</p>
                </div>
                <div class="action-cluster">
                    <span class="pill">{{ $selectedActor['kind'] }}</span>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
                <div class="metric-card">
                    <div class="metric-label">Actor ID</div>
                    <div class="metric-value" style="font-size:18px;">{{ $selectedActor['id'] }}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Organization</div>
                    <div class="metric-value" style="font-size:18px;">{{ $selectedActor['organization_id'] }}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Scope</div>
                    <div class="metric-value" style="font-size:18px;">{{ ($selectedActor['scope_id'] ?? null) !== null ? $selectedActor['scope_id'] : 'Organization-wide' }}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Assignments</div>
                    <div class="metric-value">{{ count($selected_assignments) }}</div>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:16px;">
                    <div class="field-label">Linked people</div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($selected_links as $link)
                            <div class="data-item">
                                <div class="entity-title">{{ $link['principal_id'] }}</div>
                                <div class="table-note">{{ $link['organization_id'] }}</div>
                                <div class="table-note">{{ $link['created_at'] }}</div>
                            </div>
                        @empty
                            <span class="muted-note">No people linked to this profile yet.</span>
                        @endforelse
                    </div>
                </div>

                <div class="surface-card" style="padding:16px;">
                    <div class="field-label">Responsibilities</div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($selected_assignments as $assignment)
                            <div class="data-item">
                                <div class="entity-title">{{ $assignment['assignment_type'] }}</div>
                                <div class="table-note">{{ $assignment['domain_object_type'] }} · {{ $assignment['domain_object_id'] }}</div>
                                @if (is_string($assignment['subject_url'] ?? null))
                                    <a class="button button-ghost" href="{{ $assignment['subject_url'] }}" style="margin-top:10px;">Open item</a>
                                @endif
                            </div>
                        @empty
                            <span class="muted-note">No assignments recorded for this profile.</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="table-card">
        <table class="entity-table">
            <thead>
                <tr>
                    <th>Actor</th>
                    <th>Kind</th>
                    <th>Context</th>
                    <th>Actions</th>
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
                        <td>
                            <div class="action-cluster">
                                <a class="button button-ghost" href="{{ $actor['open_url'] }}">Edit details</a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
        <div class="table-card">
            <div class="screen-header">
                <div>
                    <h2 class="screen-title" style="font-size:24px;">Recent links</h2>
                    <p class="screen-subtitle">People linked to functional profiles most recently.</p>
                </div>
            </div>
            <div class="data-stack">
                @forelse ($links as $link)
                    <div class="data-item">
                        <div class="entity-title">{{ $link['principal_id'] }}</div>
                        <div class="entity-id">{{ $link['functional_actor_id'] }} · {{ $link['organization_id'] }}</div>
                        <div class="table-note">{{ $link['created_at'] }}</div>
                    </div>
                @empty
                    <span class="muted-note">No links recorded yet.</span>
                @endforelse
            </div>
        </div>

        <div class="table-card">
            <div class="screen-header">
                <div>
                    <h2 class="screen-title" style="font-size:24px;">Recent assignments</h2>
                    <p class="screen-subtitle">Responsibilities assigned to functional profiles across managed records.</p>
                </div>
            </div>
            <table class="entity-table">
                <thead>
                    <tr>
                        <th>Actor</th>
                        <th>Assignment</th>
                        <th>Object</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (array_slice($assignments, 0, 12) as $assignment)
                        <tr>
                            <td>{{ $assignment['functional_actor_id'] }}</td>
                            <td>{{ $assignment['assignment_type'] }}</td>
                            <td>
                                @if (is_string($assignment['subject_url'] ?? null))
                                    <a href="{{ $assignment['subject_url'] }}">{{ $assignment['domain_object_type'] }} / {{ $assignment['domain_object_id'] }}</a>
                                @else
                                    {{ $assignment['domain_object_type'] }} / {{ $assignment['domain_object_id'] }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="muted-note">No assignments recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
