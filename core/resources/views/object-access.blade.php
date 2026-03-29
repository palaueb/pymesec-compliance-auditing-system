@php
    $memberships = $query['membership_ids'] ?? [];
    if (! is_array($memberships)) {
        $memberships = [];
    }
@endphp

<section class="module-screen compact">
    <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
        <div class="metric-card"><div class="metric-label">Actors</div><div class="metric-value">{{ $metrics['actors'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Assignments</div><div class="metric-value">{{ $metrics['assignments'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Governed objects</div><div class="metric-value">{{ $metrics['governed_objects'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Linked principals</div><div class="metric-value">{{ $metrics['principals_with_links'] }}</div></div>
    </div>

    @if (! $has_organization_context)
        <div class="surface-note">
            Select an organization first. Object access matrices are always resolved inside one organization context.
        </div>
    @else
        <div class="surface-note">
            Roles still open workspaces. This screen governs which records stay visible once a person is linked to functional actors that own or review specific objects.
        </div>

        <div class="overview-grid" style="grid-template-columns:1.1fr 1fr;">
            <div class="table-card">
                <div class="screen-header">
                    <div>
                        <h2 class="screen-title" style="font-size:22px;">Inspect principal visibility</h2>
                        <p class="screen-subtitle">Choose a person to inspect linked actors, direct governed objects, and per-domain visibility mode.</p>
                    </div>
                </div>

                <form class="upload-form" method="GET" action="{{ route('core.admin.index') }}">
                    <input type="hidden" name="menu" value="core.object-access">
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                    <input type="hidden" name="organization_id" value="{{ $organization_id ?? '' }}">
                    <input type="hidden" name="scope_id" value="{{ $scope_id ?? '' }}">
                    <input type="hidden" name="locale" value="{{ $query['locale'] ?? 'en' }}">
                    <input type="hidden" name="theme" value="{{ $query['theme'] ?? '' }}">
                    @foreach ($memberships as $membershipId)
                        <input type="hidden" name="membership_ids[]" value="{{ $membershipId }}">
                    @endforeach

                    <div class="field">
                        <label class="field-label">Person</label>
                        <select class="field-select" name="subject_principal_id">
                            <option value="">Select a person</option>
                            @foreach ($principal_options as $option)
                                <option value="{{ $option['id'] }}" @selected($selected_principal_id === $option['id'])>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="action-cluster" style="margin-top:12px;">
                        <button class="button button-secondary" type="submit">Inspect visibility</button>
                    </div>
                </form>

                @if ($selected_principal_id !== null)
                    <div class="surface-card" style="padding:14px; margin-top:16px;">
                        <div class="entity-title">{{ $selected_principal_id }}</div>
                        <div class="table-note">Linked actors: {{ $selected_principal_actors !== [] ? implode(', ', array_map(static fn (array $actor): string => $actor['display_name'], $selected_principal_actors)) : 'none' }}</div>
                    </div>

                    <div class="table-card" style="margin-top:16px;">
                        <table class="entity-table">
                            <thead>
                                <tr>
                                    <th>Domain</th>
                                    <th>Mode</th>
                                    <th>Visible</th>
                                    <th>Direct assignments</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($domain_visibility as $row)
                                    <tr>
                                        <td><div class="entity-title">{{ $row['label'] }}</div></td>
                                        <td>{{ $row['mode'] }}</td>
                                        <td>{{ $row['visible_count'] }}</td>
                                        <td>{{ $row['direct_assignment_count'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="table-card" style="margin-top:16px;">
                        <table class="entity-table">
                            <thead>
                                <tr>
                                    <th>Object</th>
                                    <th>Assignments</th>
                                    <th>Actors</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($selected_principal_matrix as $row)
                                    <tr>
                                        <td>
                                            <div class="entity-title">{{ $row['label'] }}</div>
                                            <div class="entity-id">{{ $row['domain_label'] }}</div>
                                        </td>
                                        <td>{{ implode(', ', $row['assignment_types']) }}</td>
                                        <td>{{ implode(', ', $row['actors']) }}</td>
                                        <td>
                                            <div class="action-cluster">
                                                <a class="button button-ghost" href="{{ route('core.admin.index', [...$query, 'menu' => 'core.object-access', 'subject_principal_id' => $selected_principal_id, 'subject_key' => $row['subject_key']]) }}">Open matrix</a>
                                                @if (is_string($row['open_url'] ?? null))
                                                    <a class="button button-ghost" href="{{ $row['open_url'] }}">Open record</a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="table-note">No direct governed objects were found for this person in the active organization.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="surface-card" style="padding:16px;">
                <div class="screen-header">
                    <div>
                        <h2 class="screen-title" style="font-size:22px;">Govern object matrix</h2>
                        <p class="screen-subtitle">Assign or retire actor ownership and review responsibilities for a specific workspace object.</p>
                    </div>
                </div>

                <form class="upload-form" method="POST" action="{{ $assign_object_access_route }}">
                    @csrf
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                    <input type="hidden" name="organization_id" value="{{ $organization_id ?? '' }}">
                    <input type="hidden" name="scope_id" value="{{ $scope_id ?? '' }}">
                    <input type="hidden" name="locale" value="{{ $query['locale'] ?? 'en' }}">
                    <input type="hidden" name="theme" value="{{ $query['theme'] ?? '' }}">
                    <input type="hidden" name="menu" value="core.object-access">
                    <input type="hidden" name="subject_principal_id" value="{{ $selected_principal_id ?? '' }}">
                    @foreach ($memberships as $membershipId)
                        <input type="hidden" name="membership_ids[]" value="{{ $membershipId }}">
                    @endforeach

                    <div class="field">
                        <label class="field-label">Workspace object</label>
                        <select class="field-select" name="subject_key" required @disabled(! $can_manage_object_access)>
                            <option value="">Select an object</option>
                            @foreach ($assignable_object_options as $option)
                                <option value="{{ $option['id'] }}" @selected($selected_subject_key === $option['id'])>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label class="field-label">Functional actor</label>
                        <select class="field-select" name="actor_id" required @disabled(! $can_manage_object_access)>
                            <option value="">Select an actor</option>
                            @foreach ($actor_options as $option)
                                <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label class="field-label">Assignment type</label>
                        <select class="field-select" name="assignment_type" required @disabled(! $can_manage_object_access)>
                            @foreach ($assignment_type_options as $option)
                                <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if ($can_manage_object_access)
                        <div class="action-cluster" style="margin-top:12px;">
                            <button class="button button-primary" type="submit">Assign access</button>
                        </div>
                    @endif
                </form>

                <div class="table-card" style="margin-top:18px;">
                    <div class="screen-header">
                        <div>
                            <h3 class="screen-title" style="font-size:18px;">Current object assignments</h3>
                            <p class="screen-subtitle">{{ $selected_subject_key !== null ? $selected_subject_key : 'Select an object above to inspect the matrix.' }}</p>
                        </div>
                    </div>

                    <table class="entity-table">
                        <thead>
                            <tr>
                                <th>Actor</th>
                                <th>Principals</th>
                                <th>Type</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($selected_object_assignments as $assignment)
                                <tr>
                                    <td>
                                        <div class="entity-title">{{ $assignment['actor_label'] }}</div>
                                        <div class="entity-id">{{ $assignment['functional_actor_id'] }}</div>
                                    </td>
                                    <td>{{ $assignment['principal_ids'] !== [] ? implode(', ', $assignment['principal_ids']) : 'none linked' }}</td>
                                    <td>{{ $assignment['assignment_type'] }}</td>
                                    <td>
                                        @if ($can_manage_object_access)
                                            <form method="POST" action="{{ $deactivate_object_access_route($assignment['id']) }}">
                                                @csrf
                                                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                                <input type="hidden" name="organization_id" value="{{ $organization_id ?? '' }}">
                                                <input type="hidden" name="scope_id" value="{{ $scope_id ?? '' }}">
                                                <input type="hidden" name="locale" value="{{ $query['locale'] ?? 'en' }}">
                                                <input type="hidden" name="theme" value="{{ $query['theme'] ?? '' }}">
                                                <input type="hidden" name="menu" value="core.object-access">
                                                <input type="hidden" name="subject_principal_id" value="{{ $selected_principal_id ?? '' }}">
                                                <input type="hidden" name="subject_key" value="{{ $selected_subject_key ?? '' }}">
                                                @foreach ($memberships as $membershipId)
                                                    <input type="hidden" name="membership_ids[]" value="{{ $membershipId }}">
                                                @endforeach
                                                <button class="button button-ghost" type="submit">Remove</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="table-note">No active assignments for the selected object.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</section>
