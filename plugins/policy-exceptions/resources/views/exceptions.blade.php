<style>
    .pill-approved  { background: rgba(34,197,94,0.14);  color: #166534; }
    .pill-requested { background: rgba(245,158,11,0.14); color: #92400e; }
    .pill-expired   { background: rgba(31,42,34,0.06);   color: var(--muted); }
    .pill-revoked   { background: rgba(239,68,68,0.12);  color: #991b1b; }

    details > summary { cursor: pointer; list-style: none; }
    details > summary::-webkit-details-marker { display: none; }
</style>

@php
    $exceptionStateLabels = [
        'requested' => __('Requested'),
        'approved' => __('Approved'),
        'expired' => __('Expired'),
        'revoked' => __('Revoked'),
    ];

    $exceptionTransitionLabels = [
        'approve' => __('Approve'),
        'expire' => __('Expire'),
        'revoke' => __('Revoke'),
        'resubmit' => __('Resubmit'),
    ];
@endphp

<section class="module-screen">
    @if (is_array($selected_exception))
        <div class="surface-card" style="padding:16px; display:grid; gap:16px;">
            <div class="surface-note">
                {{ __('Exception Detail keeps workflow, evidence, ownership, linked findings, and exception maintenance in one workspace. Use the exception list to browse exceptions and open the one you want to work on.') }}
            </div>

            <div class="row-between" style="align-items:flex-start;">
                <div>
                    <div class="eyebrow">{{ __('Exception Detail') }}</div>
                    <h2 class="screen-title" style="font-size:28px;">{{ $selected_exception['title'] }}</h2>
                    <div class="table-note">{{ $selected_exception['id'] }}</div>
                    <div class="table-note">{{ $selected_exception['policy']['title'] }}</div>
                </div>
                <div class="action-cluster">
                    @php $excStatePill = match($selected_exception['state']) { 'approved' => 'pill-approved', 'requested' => 'pill-requested', 'expired' => 'pill-expired', 'revoked' => 'pill-revoked', default => '' }; @endphp
                    <span class="pill {{ $excStatePill }}">{{ $exceptionStateLabels[$selected_exception['state']] ?? $selected_exception['state'] }}</span>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
                <div class="metric-card"><div class="metric-label">{{ __('Evidence') }}</div><div class="metric-value">{{ count($selected_exception['artifacts']) }}</div></div>
                <div class="metric-card"><div class="metric-label">{{ __('Expires') }}</div><div class="metric-value" style="font-size:20px;">{{ $selected_exception['expires_on'] !== '' ? $selected_exception['expires_on'] : __('No date') }}</div></div>
                <div class="metric-card"><div class="metric-label">{{ __('Scope') }}</div><div class="metric-value" style="font-size:20px;">{{ $selected_exception['scope_id'] !== '' ? $selected_exception['scope_id'] : __('Org-wide') }}</div></div>
                <div class="metric-card"><div class="metric-label">{{ __('Owners') }}</div><div class="metric-value" style="font-size:20px;">{{ count($selected_exception['owner_assignments']) }}</div></div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">{{ __('Overview') }}</div>
                    <div class="table-note" style="margin-top:10px;">{{ $selected_exception['rationale'] }}</div>
                    <div class="table-note">{{ __('Compensating control') }}: {{ $selected_exception['compensating_control'] !== '' ? $selected_exception['compensating_control'] : __('Not defined') }}</div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($selected_exception['owner_assignments'] as $owner)
                            <div class="data-item">
                                <div class="entity-title">{{ $owner['display_name'] }}</div>
                                <div class="table-note">{{ $owner['kind'] }}</div>
                                @if ($can_manage_policies)
                                    <form method="POST" action="{{ str_replace('__ASSIGNMENT__', $owner['assignment_id'], $selected_exception['owner_remove_route']) }}" style="margin-top:8px;">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="menu" value="plugin.policy-exceptions.exceptions">
                                        <input type="hidden" name="exception_id" value="{{ $selected_exception['id'] }}">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <button class="button button-ghost" type="submit">{{ __('Remove owner') }}</button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <span class="muted-note">{{ __('No owner assigned') }}</span>
                        @endforelse
                    </div>
                    <div class="table-note">{{ __('Policy') }}: <a href="{{ $selected_exception['policy_url'] }}">{{ $selected_exception['policy']['title'] }}</a></div>
                    <div class="table-note">
                        {{ __('Finding') }}:
                        @if ($selected_exception['linked_finding_url'] !== null)
                            <a href="{{ $selected_exception['linked_finding_url'] }}">{{ $selected_exception['linked_finding_label'] ?? $selected_exception['linked_finding_id'] }}</a>
                        @else
                            {{ $selected_exception['linked_finding_id'] !== '' ? $selected_exception['linked_finding_id'] : __('None') }}
                        @endif
                    </div>
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">{{ __('Workflow') }}</div>
                    @if ($selected_exception['transitions'] !== [])
                        <div class="action-cluster" style="margin-top:10px;">
                            @foreach ($selected_exception['transitions'] as $transition)
                                <form method="POST" action="{{ str_replace('__TRANSITION__', $transition, $selected_exception['transition_route']) }}">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.policy-exceptions.exceptions">
                                    <input type="hidden" name="exception_id" value="{{ $selected_exception['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <button class="button button-secondary" type="submit">{{ $exceptionTransitionLabels[$transition] ?? ucwords(str_replace('-', ' ', $transition)) }}</button>
                                </form>
                            @endforeach
                        </div>
                    @else
                        <div class="table-note" style="margin-top:10px;">{{ __('View-only access') }}</div>
                    @endif

                    <div class="data-stack" style="margin-top:12px;">
                        @forelse ($selected_exception['history'] as $history)
                            <div class="data-item">
                                <div class="entity-title">{{ $exceptionTransitionLabels[$history->transitionKey] ?? $history->transitionKey }}</div>
                                <div class="table-note">{{ $exceptionStateLabels[$history->fromState] ?? $history->fromState }} → {{ $exceptionStateLabels[$history->toState] ?? $history->toState }}</div>
                            </div>
                        @empty
                            <span class="muted-note">{{ __('No transitions recorded yet') }}</span>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="row-between">
                        <div class="metric-label">{{ __('Evidence') }}</div>
                        @if ($can_manage_policies)
                            <details>
                                <summary class="button button-ghost" style="display:inline-flex;">{{ __('Attach evidence') }}</summary>
                                <form class="upload-form" method="POST" action="{{ $selected_exception['artifact_upload_route'] }}" enctype="multipart/form-data" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.policy-exceptions.exceptions">
                                    <input type="hidden" name="exception_id" value="{{ $selected_exception['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <input type="hidden" name="artifact_type" value="evidence">
                                    <input class="field-input" type="text" name="label" placeholder="{{ __('Evidence label') }}">
                                    <input class="field-input" type="file" name="artifact" required>
                                    <button class="button button-secondary" type="submit">{{ __('Upload evidence') }}</button>
                                </form>
                            </details>
                        @endif
                    </div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($selected_exception['artifacts'] as $artifact)
                            <div class="data-item">
                                <div class="row-between" style="align-items:flex-start; gap:12px;">
                                    <div>
                                        <div class="entity-title">{{ $artifact['label'] }}</div>
                                        <div class="table-note">{{ $artifact['original_filename'] }}</div>
                                    </div>
                                    <form method="POST" action="{{ route('plugin.evidence-management.promote', ['artifactId' => $artifact['id']]) }}">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                        <input type="hidden" name="scope_id" value="{{ $selected_exception['scope_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <button class="button button-ghost" type="submit">{{ __('Promote to evidence') }}</button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <span class="muted-note">{{ __('No evidence yet') }}</span>
                        @endforelse
                    </div>
                </div>

                @if ($can_manage_policies)
                    <div class="surface-card" style="padding:14px;">
                        <details>
                            <summary class="button button-ghost" style="display:inline-flex; width:fit-content;">{{ __('Edit exception details') }}</summary>
                            <form class="upload-form" method="POST" action="{{ $selected_exception['update_route'] }}" style="margin-top:14px;">
                                @csrf
                                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                <input type="hidden" name="menu" value="plugin.policy-exceptions.exceptions">
                                <input type="hidden" name="exception_id" value="{{ $selected_exception['id'] }}">
                                <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                <div class="field">
                                    <label class="field-label">{{ __('Title') }}</label>
                                    <input class="field-input" name="title" value="{{ $selected_exception['title'] }}" required>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Linked finding') }}</label>
                                    <select class="field-select" name="linked_finding_id">
                                        <option value="">{{ __('No linked finding') }}</option>
                                        @foreach ($finding_options as $finding)
                                            <option value="{{ $finding['id'] }}" @selected($selected_exception['linked_finding_id'] === $finding['id'])>{{ $finding['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Expires on') }}</label>
                                    <input class="field-input" name="expires_on" type="date" value="{{ $selected_exception['expires_on'] }}">
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Scope') }}</label>
                                    <select class="field-select" name="scope_id">
                                        <option value="">{{ __('Organization-wide') }}</option>
                                        @foreach ($scope_options as $scope)
                                            <option value="{{ $scope['id'] }}" @selected($selected_exception['scope_id'] === $scope['id'])>{{ $scope['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Add owner actor') }}</label>
                                    <select class="field-select" name="owner_actor_id">
                                        <option value="">{{ __('Do not add owner') }}</option>
                                        @foreach ($owner_actor_options as $actor)
                                            <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <div class="table-note">{{ __('Selecting an actor adds another owner instead of replacing the current set.') }}</div>
                                </div>
                                @if (($selected_exception['owner_assignments'] ?? []) !== [])
                                    <div class="field" style="grid-column:1 / -1;">
                                        <label class="field-label">{{ __('Current owners') }}</label>
                                        <div class="data-stack">
                                            @foreach ($selected_exception['owner_assignments'] as $owner)
                                                <div class="data-item">
                                                    <div class="entity-title">{{ $owner['display_name'] }}</div>
                                                    <div class="table-note">{{ $owner['kind'] }}</div>
                                                    <form method="POST" action="{{ str_replace('__ASSIGNMENT__', $owner['assignment_id'], $selected_exception['owner_remove_route']) }}" style="margin-top:8px;">
                                                        @csrf
                                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                                        <input type="hidden" name="menu" value="plugin.policy-exceptions.exceptions">
                                                        <input type="hidden" name="exception_id" value="{{ $selected_exception['id'] }}">
                                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                                        <button class="button button-ghost" type="submit">{{ __('Remove owner') }}</button>
                                                    </form>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                                <div class="field">
                                    <label class="field-label">{{ __('Rationale') }}</label>
                                    <textarea class="field-input" name="rationale" rows="2" required>{{ $selected_exception['rationale'] }}</textarea>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Compensating control') }}</label>
                                    <textarea class="field-input" name="compensating_control" rows="2">{{ $selected_exception['compensating_control'] }}</textarea>
                                </div>
                                <div class="action-cluster">
                                    <button class="button button-secondary" type="submit">{{ __('Save exception') }}</button>
                                </div>
                            </form>
                        </details>
                    </div>
                @endif
            </div>
        </div>
    @else
        <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
            <div class="metric-card"><div class="metric-label">{{ __('Exceptions') }}</div><div class="metric-value">{{ count($exceptions) }}</div></div>
            <div class="metric-card"><div class="metric-label">{{ __('Requested') }}</div><div class="metric-value">{{ collect($exceptions)->where('state', 'requested')->count() }}</div></div>
            <div class="metric-card"><div class="metric-label">{{ __('Approved') }}</div><div class="metric-value">{{ collect($exceptions)->where('state', 'approved')->count() }}</div></div>
            <div class="metric-card"><div class="metric-label">{{ __('Expired or revoked') }}</div><div class="metric-value">{{ collect($exceptions)->whereIn('state', ['expired', 'revoked'])->count() }}</div></div>
        </div>

        <div class="surface-card">
            <div class="entity-title">{{ __('Exception list') }}</div>
            <div class="table-note" style="margin-top:6px;">{{ __('This list stays focused on policy context, owner summary, expiry, state, and Open. Use Exception Detail to manage workflow, evidence, and linked findings.') }}</div>
        </div>

        <div class="table-card">
            <table class="entity-table">
                <thead>
                    <tr>
                        <th>{{ __('Exception') }}</th>
                        <th>{{ __('Policy') }}</th>
                        <th>{{ __('Owner') }}</th>
                        <th>{{ __('Finding') }}</th>
                        <th>{{ __('Expires') }}</th>
                        <th>{{ __('State') }}</th>
                        <th>{{ $can_manage_policies ? __('Actions') : __('Access') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($exceptions as $exception)
                        <tr>
                            <td>
                                <div class="entity-title">{{ $exception['title'] }}</div>
                                <div class="entity-id">{{ $exception['id'] }}</div>
                                <div class="table-note">{{ $exception['rationale'] }}</div>
                                <div class="table-note">{{ $exception['compensating_control'] !== '' ? $exception['compensating_control'] : __('No compensating control defined') }}</div>
                            </td>
                            <td>
                                <a href="{{ $exception['policy_url'] }}">{{ $exception['policy']['title'] }}</a>
                                <div class="table-note">{{ $exception['policy']['id'] }}</div>
                            </td>
                            <td>
                                @if (($exception['owner_assignments'] ?? []) !== [])
                                    <div>{{ $exception['owner_assignments'][0]['display_name'] }}</div>
                                    @if (count($exception['owner_assignments']) > 1)
                                        <div class="table-note">+{{ count($exception['owner_assignments']) - 1 }} {{ (count($exception['owner_assignments']) - 1) === 1 ? __('more owner') : __('more owners') }}</div>
                                    @else
                                        <div class="table-note">{{ $exception['owner_assignments'][0]['kind'] }}</div>
                                    @endif
                                @else
                                    <span class="muted-note">{{ __('No owner assigned') }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($exception['linked_finding_url'] !== null)
                                    <a href="{{ $exception['linked_finding_url'] }}">{{ $exception['linked_finding_label'] ?? $exception['linked_finding_id'] }}</a>
                                @else
                                    <span class="muted-note">{{ $exception['linked_finding_id'] !== '' ? $exception['linked_finding_id'] : __('No linked finding') }}</span>
                                @endif
                            </td>
                            <td>{{ $exception['expires_on'] !== '' ? $exception['expires_on'] : __('No expiry set') }}</td>
                            <td>
                                @php $sExcPill = match($exception['state']) { 'approved' => 'pill-approved', 'requested' => 'pill-requested', 'expired' => 'pill-expired', 'revoked' => 'pill-revoked', default => '' }; @endphp
                                <span class="pill {{ $sExcPill }}">{{ $exceptionStateLabels[$exception['state']] ?? $exception['state'] }}</span>
                            </td>
                            <td>
                                <a class="button button-secondary" href="{{ $exception['open_url'] }}&{{ http_build_query(['context_label' => __('Exceptions'), 'context_back_url' => $exceptions_list_url]) }}">{{ __('Open') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
