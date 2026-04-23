@php
    $selectedActor = is_array($selected_actor ?? null) ? $selected_actor : null;
    $memberships = $query['membership_ids'] ?? [];
    if (! is_array($memberships)) {
        $memberships = [];
    }
@endphp

<section class="module-screen compact">
    <div class="surface-note">{{ __('core.functional-actors.summary') }}</div>

    <div class="overview-grid">
        <div class="metric-card"><div class="metric-label">{{ __('core.functional-actors.metric.actors') }}</div><div class="metric-value">{{ $metrics['actors'] }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('core.functional-actors.metric.principal_links') }}</div><div class="metric-value">{{ $metrics['links'] }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('core.functional-actors.metric.assignments') }}</div><div class="metric-value">{{ $metrics['assignments'] }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('core.functional-actors.metric.organizations') }}</div><div class="metric-value">{{ $metrics['organizations'] }}</div></div>
    </div>

    @if ($selected_principal_id !== null)
        <div class="surface-card" style="padding:16px; display:grid; gap:12px;">
            <div class="row-between" style="align-items:flex-start;">
                <div>
                    <div class="eyebrow">{{ __('core.functional-actors.person_context.eyebrow') }}</div>
                    <div class="entity-title" style="font-size:24px;">{{ $selected_principal_id }}</div>
                    <div class="table-note">{{ __('core.functional-actors.person_context.copy') }}</div>
                </div>
                <a class="button button-ghost" href="{{ $actors_list_url }}">{{ __('core.functional-actors.person_context.clear') }}</a>
            </div>
            <div class="data-stack">
                @forelse ($selected_principal_actors as $actor)
                    <div class="data-item">
                        <div class="entity-title">{{ $actor['display_name'] }}</div>
                        <div class="table-note">{{ $actor['kind'] }} · {{ $actor['organization_id'] }}</div>
                    </div>
                @empty
                    <span class="muted-note">{{ __('core.functional-actors.person_context.empty') }}</span>
                @endforelse
            </div>
        </div>
    @endif

    @if ($can_manage_functional_actors && $selectedActor === null)
        <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
            <div class="surface-card" id="functional-actor-create-editor" hidden style="padding:16px;">
                <div class="metric-label">{{ __('core.functional-actors.create.title') }}</div>
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
                        <label class="field-label">{{ __('core.functional-actors.create.display_name') }}</label>
                        <input class="field-input" name="display_name" required>
                    </div>
                    <div class="field">
                        <label class="field-label">{{ __('core.functional-actors.create.profile_type') }}</label>
                        <select class="field-select" name="kind" required>
                            @foreach ($actor_kind_options as $option)
                                <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="action-cluster" style="margin-top:12px;">
                        <button class="button button-primary" type="submit">{{ __('core.functional-actors.create.button') }}</button>
                    </div>
                </form>
            </div>

            <div class="surface-card" id="functional-actor-principal-link-editor" hidden style="padding:16px;">
                <div class="metric-label">{{ __('core.functional-actors.link.title') }}</div>
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
                        <label class="field-label">{{ __('core.functional-actors.link.person') }}</label>
                        <select class="field-select" name="subject_principal_id" required>
                            <option value="">{{ __('core.functional-actors.link.select_person') }}</option>
                            @foreach ($principal_options as $option)
                                <option value="{{ $option['id'] }}" @selected($selected_principal_id === $option['id'])>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label">{{ __('core.functional-actors.link.profile') }}</label>
                        <select class="field-select" name="actor_id" required>
                            <option value="">{{ __('core.functional-actors.link.select_profile') }}</option>
                            @foreach ($actors as $actor)
                                <option value="{{ $actor['id'] }}">{{ $actor['display_name'] }} ({{ $actor['kind'] }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="action-cluster" style="margin-top:12px;">
                        <button class="button button-primary" type="submit">{{ __('core.functional-actors.link.button') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($selectedActor !== null)
        <div class="table-card">
            <div class="surface-note" style="margin-bottom:16px;">{{ __('core.functional-actors.detail.summary') }}</div>

            <div class="screen-header">
                <div>
                    <h2 class="screen-title" style="font-size:24px;">{{ $selectedActor['display_name'] }}</h2>
                    <p class="screen-subtitle">{{ __('core.functional-actors.detail.subtitle', ['kind' => $selectedActor['kind']]) }}</p>
                </div>
                <div class="action-cluster">
                    <span class="pill">{{ $selectedActor['kind'] }}</span>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
                <div class="metric-card">
                    <div class="metric-label">{{ __('core.functional-actors.detail.actor_id') }}</div>
                    <div class="metric-value" style="font-size:18px;">{{ $selectedActor['id'] }}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">{{ __('core.functional-actors.detail.organization') }}</div>
                    <div class="metric-value" style="font-size:18px;">{{ $selectedActor['organization_id'] }}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">{{ __('core.functional-actors.detail.scope') }}</div>
                    <div class="metric-value" style="font-size:18px;">{{ ($selectedActor['scope_id'] ?? null) !== null ? $selectedActor['scope_id'] : __('core.shell.organization_wide') }}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">{{ __('core.functional-actors.detail.assignments') }}</div>
                    <div class="metric-value">{{ count($selected_assignments) }}</div>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:16px;">
                    <div class="row-between">
                        <div class="field-label">{{ __('core.functional-actors.detail.linked_people') }}</div>
                    </div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($selected_links as $link)
                            <div class="data-item">
                                <div class="entity-title">{{ $link['principal_id'] }}</div>
                                <div class="table-note">{{ $link['organization_id'] }}</div>
                                <div class="table-note">{{ $link['created_at'] }}</div>
                            </div>
                        @empty
                            <span class="muted-note">{{ __('core.functional-actors.detail.no_linked_people') }}</span>
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
                                    <label class="field-label">{{ __('core.functional-actors.link.person') }}</label>
                                    <select class="field-select" name="subject_principal_id" required>
                                        <option value="">{{ __('core.functional-actors.link.select_person') }}</option>
                                        @foreach ($principal_options as $option)
                                            <option value="{{ $option['id'] }}" @selected($selected_principal_id === $option['id'])>{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="action-cluster" style="margin-top:12px;">
                                    <button class="button button-secondary" type="submit">{{ __('core.functional-actors.link.button') }}</button>
                                </div>
                            </form>
                        </div>
                    @endif
                </div>

                <div class="surface-card" style="padding:16px;">
                    <div class="row-between">
                        <div class="field-label">{{ __('core.functional-actors.detail.responsibilities') }}</div>
                    </div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($selected_assignments as $assignment)
                            <div class="data-item">
                                <div class="entity-title">{{ $assignment['assignment_type'] }}</div>
                                <div class="table-note">{{ $assignment['domain_object_type'] }} · {{ $assignment['domain_object_id'] }}</div>
                                @if (is_string($assignment['subject_url'] ?? null))
                                    <a class="button button-ghost" href="{{ $assignment['subject_url'] }}" style="margin-top:10px;">{{ __('core.functional-actors.detail.open_item') }}</a>
                                @endif
                            </div>
                        @empty
                            <span class="muted-note">{{ __('core.functional-actors.detail.no_assignments') }}</span>
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
                                    <label class="field-label">{{ __('core.functional-actors.assign.type') }}</label>
                                    <select class="field-select" name="assignment_type" required>
                                        @foreach ($assignment_type_options as $option)
                                            <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('core.functional-actors.assign.item') }}</label>
                                    <select class="field-select" name="subject_key" required>
                                        <option value="">{{ __('core.functional-actors.assign.select_item') }}</option>
                                        @foreach ($assignable_object_options as $option)
                                            <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="action-cluster" style="margin-top:12px;">
                                    <button class="button button-secondary" type="submit">{{ __('core.functional-actors.assign.button') }}</button>
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
                    <h2 class="screen-title" style="font-size:24px;">{{ __('core.functional-actors.list.title') }}</h2>
                    <p class="screen-subtitle">{{ __('core.functional-actors.list.subtitle') }}</p>
            </div>
        </div>
        <table class="entity-table">
            <thead>
                <tr>
                        <th>{{ __('core.functional-actors.list.actor') }}</th>
                        <th>{{ __('core.functional-actors.list.kind') }}</th>
                        <th>{{ __('core.functional-actors.list.context') }}</th>
                        <th>{{ __('core.functional-actors.list.actions') }}</th>
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
                                <a class="button button-ghost" href="{{ $actor['open_url'] }}">{{ __('core.actions.edit') }}</a>
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
                    <h2 class="screen-title" style="font-size:24px;">{{ __('core.functional-actors.recent_links.title') }}</h2>
                    <p class="screen-subtitle">{{ __('core.functional-actors.recent_links.subtitle') }}</p>
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
                    <span class="muted-note">{{ __('core.functional-actors.recent_links.empty') }}</span>
                @endforelse
            </div>
        </div>

        <div class="table-card">
            <div class="screen-header">
                <div>
                    <h2 class="screen-title" style="font-size:24px;">{{ __('core.functional-actors.recent_assignments.title') }}</h2>
                    <p class="screen-subtitle">{{ __('core.functional-actors.recent_assignments.subtitle') }}</p>
                </div>
            </div>
            <table class="entity-table">
                <thead>
                    <tr>
                        <th>{{ __('core.functional-actors.recent_assignments.actor') }}</th>
                        <th>{{ __('core.functional-actors.recent_assignments.assignment') }}</th>
                        <th>{{ __('core.functional-actors.recent_assignments.object') }}</th>
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
                            <td colspan="3" class="muted-note">{{ __('core.functional-actors.recent_assignments.empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
