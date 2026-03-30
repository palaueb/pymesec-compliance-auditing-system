@php
    $selectedActor = is_array($selected_actor ?? null) ? $selected_actor : null;
    $memberships = $query['membership_ids'] ?? [];
    if (! is_array($memberships)) {
        $memberships = [];
    }
@endphp

<section class="module-screen compact">
    <div class="surface-note">
        Governance page. Functional profiles and accountability links live here so ownership stays separate from day-to-day record maintenance.
    </div>

    <div class="overview-grid">
        <div class="metric-card"><div class="metric-label">Actors</div><div class="metric-value">{{ $metrics['actors'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Principal links</div><div class="metric-value">{{ $metrics['links'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Assignments</div><div class="metric-value">{{ $metrics['assignments'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Organizations</div><div class="metric-value">{{ $metrics['organizations'] }}</div></div>
    </div>

    @if ($selected_principal_id !== null)
        <div class="surface-card" style="padding:16px; display:grid; gap:12px;">
            <div class="row-between" style="align-items:flex-start;">
                <div>
                    <div class="eyebrow">Person context</div>
                    <div class="entity-title" style="font-size:24px;">{{ $selected_principal_id }}</div>
                    <div class="table-note">This person can be linked to one or more functional profiles for team placement and object-level accountability.</div>
                </div>
                <a class="button button-ghost" href="{{ $actors_list_url }}">Clear person context</a>
            </div>
            <div class="data-stack">
                @forelse ($selected_principal_actors as $actor)
                    <div class="data-item">
                        <div class="entity-title">{{ $actor['display_name'] }}</div>
                        <div class="table-note">{{ $actor['kind'] }} · {{ $actor['organization_id'] }}</div>
                    </div>
                @empty
                    <span class="muted-note">No functional profiles linked yet.</span>
                @endforelse
            </div>
        </div>
    @endif

    @if ($can_manage_functional_actors && $selectedActor === null)
        <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
            <div class="surface-card" id="functional-actor-create-editor" hidden style="padding:16px;">
                <div class="metric-label">New functional profile</div>
                <form class="upload-form" method="POST" action="{{ $create_actor_route }}" style="margin-top:10px;">
                    @csrf
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] ?? '' }}">
                    <input type="hidden" name="scope_id" value="{{ $query['scope_id'] ?? '' }}">
                    <input type="hidden" name="locale" value="{{ $query['locale'] ?? 'en' }}">
                    <input type="hidden" name="theme" value="{{ $query['theme'] ?? '' }}">
                    <input type="hidden" name="menu" value="core.functional-actors">
                    <input type="hidden" name="subject_principal_id" value="{{ $selected_principal_id }}">
                    @foreach ($memberships as $membershipId)
                        <input type="hidden" name="membership_ids[]" value="{{ $membershipId }}">
                    @endforeach
                    <div class="field">
                        <label class="field-label">Display name</label>
                        <input class="field-input" name="display_name" required>
                    </div>
                    <div class="field">
                        <label class="field-label">Profile type</label>
                        <select class="field-select" name="kind" required>
                            @foreach ($actor_kind_options as $option)
                                <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="action-cluster" style="margin-top:12px;">
                        <button class="button button-primary" type="submit">Create profile</button>
                    </div>
                </form>
            </div>

            <div class="surface-card" id="functional-actor-principal-link-editor" hidden style="padding:16px;">
                <div class="metric-label">Link person to profile</div>
                <form class="upload-form" method="POST" action="{{ $link_principal_route }}" style="margin-top:10px;">
                    @csrf
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] ?? '' }}">
                    <input type="hidden" name="locale" value="{{ $query['locale'] ?? 'en' }}">
                    <input type="hidden" name="theme" value="{{ $query['theme'] ?? '' }}">
                    <input type="hidden" name="menu" value="core.functional-actors">
                    @foreach ($memberships as $membershipId)
                        <input type="hidden" name="membership_ids[]" value="{{ $membershipId }}">
                    @endforeach
                    <div class="field">
                        <label class="field-label">Person</label>
                        <select class="field-select" name="subject_principal_id" required>
                            <option value="">Select a person</option>
                            @foreach ($principal_options as $option)
                                <option value="{{ $option['id'] }}" @selected($selected_principal_id === $option['id'])>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label">Functional profile</label>
                        <select class="field-select" name="actor_id" required>
                            <option value="">Select a profile</option>
                            @foreach ($actors as $actor)
                                <option value="{{ $actor['id'] }}">{{ $actor['display_name'] }} ({{ $actor['kind'] }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="action-cluster" style="margin-top:12px;">
                        <button class="button button-primary" type="submit">Link person</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($selectedActor !== null)
        <div class="table-card">
            <div class="surface-note" style="margin-bottom:16px;">
                Functional Profile Detail keeps linked people and responsibilities in one governance workspace. Use the profile list to browse profiles and open the one you want to manage.
            </div>

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
                    <div class="row-between">
                        <div class="field-label">Linked people</div>
                    </div>
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
                    @if ($can_manage_functional_actors)
                        <div class="surface-card" id="functional-actor-link-editor" hidden style="padding:14px; margin-top:14px;">
                            <form class="upload-form" method="POST" action="{{ $link_principal_route }}">
                                @csrf
                                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                <input type="hidden" name="organization_id" value="{{ $query['organization_id'] ?? '' }}">
                                <input type="hidden" name="locale" value="{{ $query['locale'] ?? 'en' }}">
                                <input type="hidden" name="theme" value="{{ $query['theme'] ?? '' }}">
                                <input type="hidden" name="menu" value="core.functional-actors">
                                <input type="hidden" name="actor_id" value="{{ $selectedActor['id'] }}">
                                @foreach ($memberships as $membershipId)
                                    <input type="hidden" name="membership_ids[]" value="{{ $membershipId }}">
                                @endforeach
                                <div class="field">
                                    <label class="field-label">Person</label>
                                    <select class="field-select" name="subject_principal_id" required>
                                        <option value="">Select a person</option>
                                        @foreach ($principal_options as $option)
                                            <option value="{{ $option['id'] }}" @selected($selected_principal_id === $option['id'])>{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="action-cluster" style="margin-top:12px;">
                                    <button class="button button-secondary" type="submit">Link person</button>
                                </div>
                            </form>
                        </div>
                    @endif
                </div>

                <div class="surface-card" style="padding:16px;">
                    <div class="row-between">
                        <div class="field-label">Responsibilities</div>
                    </div>
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
                    @if ($can_manage_functional_actors)
                        <div class="surface-card" id="functional-actor-assignment-editor" hidden style="padding:14px; margin-top:14px;">
                            <form class="upload-form" method="POST" action="{{ $assign_actor_route }}">
                                @csrf
                                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                <input type="hidden" name="organization_id" value="{{ $query['organization_id'] ?? '' }}">
                                <input type="hidden" name="scope_id" value="{{ $query['scope_id'] ?? '' }}">
                                <input type="hidden" name="locale" value="{{ $query['locale'] ?? 'en' }}">
                                <input type="hidden" name="theme" value="{{ $query['theme'] ?? '' }}">
                                <input type="hidden" name="menu" value="core.functional-actors">
                                <input type="hidden" name="actor_id" value="{{ $selectedActor['id'] }}">
                                @foreach ($memberships as $membershipId)
                                    <input type="hidden" name="membership_ids[]" value="{{ $membershipId }}">
                                @endforeach
                                <div class="field">
                                    <label class="field-label">Responsibility type</label>
                                    <select class="field-select" name="assignment_type" required>
                                        @foreach ($assignment_type_options as $option)
                                            <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">Workspace item</label>
                                    <select class="field-select" name="subject_key" required>
                                        <option value="">Select an item</option>
                                        @foreach ($assignable_object_options as $option)
                                            <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="action-cluster" style="margin-top:12px;">
                                    <button class="button button-secondary" type="submit">Assign responsibility</button>
                                </div>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div class="table-card">
        <div class="screen-header">
            <div>
                <h2 class="screen-title" style="font-size:24px;">Functional profile list</h2>
                <p class="screen-subtitle">Browse profiles, review context, and open one profile when you need governance editing.</p>
            </div>
        </div>
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
