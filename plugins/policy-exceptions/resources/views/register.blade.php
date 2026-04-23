<style>
    .pill-active   { background: rgba(34,197,94,0.14);  color: #166534; }
    .pill-draft    { background: rgba(31,42,34,0.06);   color: var(--muted); }
    .pill-review   { background: rgba(245,158,11,0.14); color: #92400e; }
    .pill-archived { background: rgba(31,42,34,0.06);   color: var(--muted); }

    .pill-approved  { background: rgba(34,197,94,0.14);  color: #166534; }
    .pill-requested { background: rgba(245,158,11,0.14); color: #92400e; }
    .pill-expired   { background: rgba(31,42,34,0.06);   color: var(--muted); }
    .pill-revoked   { background: rgba(239,68,68,0.12);  color: #991b1b; }

    details > summary { cursor: pointer; list-style: none; }
    details > summary::-webkit-details-marker { display: none; }
</style>

@php
    $policyStateLabels = [
        'draft' => __('Draft'),
        'review' => __('In review'),
        'active' => __('Active'),
        'retired' => __('Retired'),
        'archived' => __('Archived'),
    ];

    $policyTransitionLabels = [
        'submit-review' => __('Submit review'),
        'activate' => __('Activate'),
        'send-back' => __('Send back'),
        'retire' => __('Retire'),
    ];

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
    <div class="surface-note">
        {{ __('Policy areas are business-managed catalog values from `Reference catalogs`. Policy and exception workflow states such as `draft`, `review`, `active`, or `approved` are system-controlled.') }}
    </div>

    @if (is_array($selected_policy))
        <div class="surface-card" style="padding:16px; display:grid; gap:16px;">
            <div class="surface-note">
                {{ __('Policy Detail keeps workflow, linked controls, documents, approved exceptions, ownership, and policy maintenance in one workspace. Use the policy list to browse policies and open the one you want to work on.') }}
            </div>

            <div class="row-between" style="align-items:flex-start;">
                <div>
                    <div class="eyebrow">{{ __('Policy Detail') }}</div>
                    <h2 class="screen-title" style="font-size:28px;">{{ $selected_policy['title'] }}</h2>
                    <div class="table-note">{{ $selected_policy['id'] }} · {{ $selected_policy['version_label'] }}</div>
                    <div class="table-note">{{ $selected_policy['area_label'] }}</div>
                </div>
                <div class="action-cluster">
                    @php $polStatePill = match($selected_policy['state']) { 'active' => 'pill-active', 'draft' => 'pill-draft', 'review' => 'pill-review', 'archived' => 'pill-archived', default => '' }; @endphp
                    <span class="pill {{ $polStatePill }}">{{ $policyStateLabels[$selected_policy['state']] ?? $selected_policy['state'] }}</span>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
                <div class="metric-card"><div class="metric-label">{{ __('Exceptions') }}</div><div class="metric-value">{{ $selected_policy['exception_count'] }}</div></div>
                <div class="metric-card"><div class="metric-label">{{ __('Approved exceptions') }}</div><div class="metric-value">{{ $selected_policy['active_exception_count'] }}</div></div>
                <div class="metric-card"><div class="metric-label">{{ __('Documents') }}</div><div class="metric-value">{{ count($selected_policy['artifacts']) }}</div></div>
                <div class="metric-card"><div class="metric-label">{{ __('Review due') }}</div><div class="metric-value" style="font-size:20px;">{{ $selected_policy['review_due_on'] !== '' ? $selected_policy['review_due_on'] : __('No date') }}</div></div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">{{ __('Overview') }}</div>
                    <div class="table-note" style="margin-top:10px;">{{ $selected_policy['statement'] }}</div>
                    <div class="table-note">{{ __('Owners') }}: {{ count($selected_policy['owner_assignments']) }}</div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($selected_policy['owner_assignments'] as $owner)
                            <div class="data-item">
                                <div class="entity-title">{{ $owner['display_name'] }}</div>
                                <div class="table-note">{{ $owner['kind'] }}</div>
                                @if ($can_manage_policies)
                                    <form method="POST" action="{{ str_replace('__ASSIGNMENT__', $owner['assignment_id'], $selected_policy['owner_remove_route']) }}" style="margin-top:8px;">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="menu" value="plugin.policy-exceptions.root">
                                        <input type="hidden" name="policy_id" value="{{ $selected_policy['id'] }}">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <button class="button button-ghost" type="submit">{{ __('Remove owner') }}</button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <span class="muted-note">{{ __('No owner assigned') }}</span>
                        @endforelse
                    </div>
                    <div class="table-note">
                        {{ __('Control') }}:
                        @if ($selected_policy['linked_control_url'] !== null)
                            <a href="{{ $selected_policy['linked_control_url'] }}">{{ $selected_policy['linked_control_label'] ?? $selected_policy['linked_control_id'] }}</a>
                        @else
                            {{ $selected_policy['linked_control_id'] !== '' ? $selected_policy['linked_control_id'] : __('None') }}
                        @endif
                    </div>
                    <div class="table-note">{{ __('Scope') }}: {{ $selected_policy['scope_id'] !== '' ? $selected_policy['scope_id'] : __('Organization-wide') }}</div>
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">{{ __('Workflow') }}</div>
                    @if ($selected_policy['transitions'] !== [])
                        <div class="action-cluster" style="margin-top:10px;">
                            @foreach ($selected_policy['transitions'] as $transition)
                                <form method="POST" action="{{ str_replace('__TRANSITION__', $transition, $selected_policy['transition_route']) }}">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.policy-exceptions.root">
                                    <input type="hidden" name="policy_id" value="{{ $selected_policy['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <button class="button button-secondary" type="submit">{{ $policyTransitionLabels[$transition] ?? ucwords(str_replace('-', ' ', $transition)) }}</button>
                                </form>
                            @endforeach
                        </div>
                    @else
                        <div class="table-note" style="margin-top:10px;">{{ __('View-only access') }}</div>
                    @endif

                    <div class="data-stack" style="margin-top:12px;">
                        @forelse ($selected_policy['history'] as $history)
                            <div class="data-item">
                                <div class="entity-title">{{ $policyTransitionLabels[$history->transitionKey] ?? $history->transitionKey }}</div>
                                <div class="table-note">{{ $policyStateLabels[$history->fromState] ?? $history->fromState }} → {{ $policyStateLabels[$history->toState] ?? $history->toState }}</div>
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
                        <div class="metric-label">{{ __('Documents') }}</div>
                        @if ($can_manage_policies)
                            <details>
                                <summary class="button button-ghost" style="display:inline-flex;">{{ __('Attach document') }}</summary>
                                <form class="upload-form" method="POST" action="{{ $selected_policy['artifact_upload_route'] }}" enctype="multipart/form-data" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.policy-exceptions.root">
                                    <input type="hidden" name="policy_id" value="{{ $selected_policy['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <input type="hidden" name="artifact_type" value="document">
                                    <input class="field-input" type="text" name="label" placeholder="{{ __('Document label') }}">
                                    <input class="field-input" type="file" name="artifact" required>
                                    <button class="button button-secondary" type="submit">{{ __('Upload document') }}</button>
                                </form>
                            </details>
                        @endif
                    </div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($selected_policy['artifacts'] as $artifact)
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
                                        <input type="hidden" name="scope_id" value="{{ $selected_policy['scope_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <button class="button button-ghost" type="submit">{{ __('Promote to evidence') }}</button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <span class="muted-note">{{ __('No documents yet') }}</span>
                        @endforelse
                    </div>
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="row-between">
                        <div class="metric-label">{{ __('Exceptions') }}</div>
                        @if ($can_manage_policies)
                            <details>
                                <summary class="button button-ghost" style="display:inline-flex;">{{ __('Add exception') }}</summary>
                                <form class="upload-form" method="POST" action="{{ $selected_policy['exception_store_route'] }}" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.policy-exceptions.root">
                                    <input type="hidden" name="policy_id" value="{{ $selected_policy['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <div class="field">
                                        <label class="field-label">{{ __('Exception title') }}</label>
                                        <input class="field-input" name="title" required>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Linked finding') }}</label>
                                        <select class="field-select" name="linked_finding_id">
                                            <option value="">{{ __('No linked finding') }}</option>
                                            @foreach ($finding_options as $finding)
                                                <option value="{{ $finding['id'] }}">{{ $finding['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Expires on') }}</label>
                                        <input class="field-input" name="expires_on" type="date">
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Initial owner actor') }}</label>
                                        <select class="field-select" name="owner_actor_id">
                                            <option value="">{{ __('No owner') }}</option>
                                            @foreach ($owner_actor_options as $actor)
                                                <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Rationale') }}</label>
                                        <textarea class="field-input" name="rationale" rows="2" required></textarea>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Compensating control') }}</label>
                                        <textarea class="field-input" name="compensating_control" rows="2"></textarea>
                                    </div>
                                    <div class="action-cluster">
                                        <button class="button button-secondary" type="submit">{{ __('Create exception') }}</button>
                                    </div>
                                </form>
                            </details>
                        @endif
                    </div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($selected_policy['exceptions'] as $exception)
                            <div class="data-item">
                                <div class="row-between" style="align-items:flex-start;">
                                    <div>
                                        <div class="entity-title">{{ $exception['title'] }}</div>
                                        <div class="table-note">{{ $exceptionStateLabels[$exception['state']] ?? ucfirst($exception['state']) }}{{ $exception['expires_on'] !== '' ? ' · '.__('expires :date', ['date' => $exception['expires_on']]) : '' }}</div>
                                        <div class="table-note">
                                            @if (($exception['owner_assignments'] ?? []) !== [])
                                                {{ $exception['owner_assignments'][0]['display_name'] }}{{ count($exception['owner_assignments']) > 1 ? ' +'.(count($exception['owner_assignments']) - 1).' '.(count($exception['owner_assignments']) - 1 === 1 ? __('more owner') : __('more owners')) : '' }}
                                            @else
                                                {{ __('No owner assigned') }}
                                            @endif
                                        </div>
                                    </div>
                                    @php $excPill = match($exception['state']) { 'approved' => 'pill-approved', 'requested' => 'pill-requested', 'expired' => 'pill-expired', 'revoked' => 'pill-revoked', default => '' }; @endphp
                                    <span class="pill {{ $excPill }}">{{ $exceptionStateLabels[$exception['state']] ?? $exception['state'] }}</span>
                                </div>
                                @if ($exception['linked_finding_url'] !== null)
                                    <div class="table-note" style="margin-top:6px;">{{ __('Finding') }}: <a href="{{ $exception['linked_finding_url'] }}">{{ $exception['linked_finding_label'] ?? $exception['linked_finding_id'] }}</a></div>
                                @endif
                                <div class="action-cluster" style="margin-top:10px;">
                                    <a class="button button-secondary" href="{{ $exception['open_url'] }}&{{ http_build_query(['context_label' => __('Exceptions'), 'context_back_url' => $exceptions_list_url]) }}">{{ __('Open') }}</a>
                                </div>
                            </div>
                        @empty
                            <span class="muted-note">{{ __('No exceptions yet') }}</span>
                        @endforelse
                    </div>
                </div>
            </div>

            @if ($can_manage_policies)
                <div class="surface-card" style="padding:14px;">
                    <details>
                        <summary class="button button-ghost" style="display:inline-flex; width:fit-content;">{{ __('Edit policy details') }}</summary>
                        <form class="upload-form" method="POST" action="{{ $selected_policy['update_route'] }}" style="margin-top:14px;">
                            @csrf
                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                            <input type="hidden" name="menu" value="plugin.policy-exceptions.root">
                            <input type="hidden" name="policy_id" value="{{ $selected_policy['id'] }}">
                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                                <div class="field">
                                    <label class="field-label">{{ __('Title') }}</label>
                                    <input class="field-input" name="title" value="{{ $selected_policy['title'] }}" required>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Area') }}</label>
                                    <select class="field-select" name="area" required>
                                        @foreach ($area_options as $option)
                                            <option value="{{ $option['id'] }}" @selected($selected_policy['area'] === $option['id'])>{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <div class="table-note">{{ __('Business-managed policy areas come from `Reference catalogs`; workflow states remain system controlled.') }}</div>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Version') }}</label>
                                    <input class="field-input" name="version_label" value="{{ $selected_policy['version_label'] }}" required>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Review due') }}</label>
                                    <input class="field-input" name="review_due_on" type="date" value="{{ $selected_policy['review_due_on'] }}">
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Linked control') }}</label>
                                    <select class="field-select" name="linked_control_id">
                                        <option value="">{{ __('No linked control') }}</option>
                                        @foreach ($control_options as $control)
                                            <option value="{{ $control['id'] }}" @selected($selected_policy['linked_control_id'] === $control['id'])>{{ $control['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Scope') }}</label>
                                    <select class="field-select" name="scope_id">
                                        <option value="">{{ __('Organization-wide') }}</option>
                                        @foreach ($scope_options as $scope)
                                            <option value="{{ $scope['id'] }}" @selected($selected_policy['scope_id'] === $scope['id'])>{{ $scope['name'] }}</option>
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
                                @if (($selected_policy['owner_assignments'] ?? []) !== [])
                                    <div class="field" style="grid-column:1 / -1;">
                                        <label class="field-label">{{ __('Current owners') }}</label>
                                        <div class="data-stack">
                                            @foreach ($selected_policy['owner_assignments'] as $owner)
                                                <div class="data-item">
                                                    <div class="entity-title">{{ $owner['display_name'] }}</div>
                                                    <div class="table-note">{{ $owner['kind'] }}</div>
                                                    <form method="POST" action="{{ str_replace('__ASSIGNMENT__', $owner['assignment_id'], $selected_policy['owner_remove_route']) }}" style="margin-top:8px;">
                                                        @csrf
                                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                                        <input type="hidden" name="menu" value="plugin.policy-exceptions.root">
                                                        <input type="hidden" name="policy_id" value="{{ $selected_policy['id'] }}">
                                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                                        <button class="button button-ghost" type="submit">{{ __('Remove owner') }}</button>
                                                    </form>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                                <div class="field" style="grid-column:1 / -1;">
                                    <label class="field-label">{{ __('Statement') }}</label>
                                    <textarea class="field-input" name="statement" rows="4" required>{{ $selected_policy['statement'] }}</textarea>
                                </div>
                            </div>
                            <div class="action-cluster" style="margin-top:14px;">
                                <button class="button button-secondary" type="submit">{{ __('Save changes') }}</button>
                            </div>
                        </form>
                    </details>
                </div>
            @endif
        </div>
    @else
        @if ($can_manage_policies)
                <div class="surface-card" id="policy-editor" hidden>
                    <div class="row-between" style="margin-bottom:14px;">
                        <div>
                        <div class="eyebrow">{{ __('Create') }}</div>
                        <div class="entity-title" style="font-size:24px;">{{ __('New policy') }}</div>
                    </div>
                </div>

                <form class="upload-form" method="POST" action="{{ $create_route }}">
                    @csrf
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                    <input type="hidden" name="menu" value="plugin.policy-exceptions.root">
                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">

                    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                        <div class="field">
                            <label class="field-label" for="policy-title">{{ __('Title') }}</label>
                            <input class="field-input" id="policy-title" name="title" required>
                        </div>
                        <div class="field">
                            <label class="field-label" for="policy-area">{{ __('Area') }}</label>
                            <select class="field-select" id="policy-area" name="area" required>
                                @foreach ($area_options as $option)
                                    <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                            <div class="table-note">{{ __('Business-managed policy areas come from `Reference catalogs`; workflow states remain system controlled.') }}</div>
                        </div>
                        <div class="field">
                            <label class="field-label" for="policy-version">{{ __('Version') }}</label>
                            <input class="field-input" id="policy-version" name="version_label" value="v1.0" required>
                        </div>
                        <div class="field">
                            <label class="field-label" for="policy-review-due">{{ __('Review due') }}</label>
                            <input class="field-input" id="policy-review-due" name="review_due_on" type="date">
                        </div>
                        <div class="field">
                            <label class="field-label" for="policy-control">{{ __('Linked control') }}</label>
                            <select class="field-select" id="policy-control" name="linked_control_id">
                                <option value="">{{ __('No linked control') }}</option>
                                @foreach ($control_options as $control)
                                    <option value="{{ $control['id'] }}">{{ $control['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="policy-scope">{{ __('Scope') }}</label>
                            <select class="field-select" id="policy-scope" name="scope_id">
                                <option value="">{{ __('Organization-wide') }}</option>
                                @foreach ($scope_options as $scope)
                                    <option value="{{ $scope['id'] }}" @selected(($query['scope_id'] ?? null) === $scope['id'])>{{ $scope['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="policy-owner">{{ __('Initial owner actor') }}</label>
                            <select class="field-select" id="policy-owner" name="owner_actor_id">
                                <option value="">{{ __('No owner') }}</option>
                                @foreach ($owner_actor_options as $actor)
                                    <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field" style="grid-column:1 / -1;">
                            <label class="field-label" for="policy-statement">{{ __('Statement') }}</label>
                            <textarea class="field-input" id="policy-statement" name="statement" rows="4" required></textarea>
                        </div>
                    </div>

                    <div class="action-cluster" style="margin-top:14px;">
                        <button class="button button-primary" type="submit">{{ __('Create policy') }}</button>
                    </div>
                </form>
            </div>
        @endif

        <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
            <div class="metric-card"><div class="metric-label">{{ __('Policies') }}</div><div class="metric-value">{{ count($policies) }}</div></div>
            <div class="metric-card"><div class="metric-label">{{ __('Active') }}</div><div class="metric-value">{{ collect($policies)->where('state', 'active')->count() }}</div></div>
            <div class="metric-card"><div class="metric-label">{{ __('Under review') }}</div><div class="metric-value">{{ collect($policies)->where('state', 'review')->count() }}</div></div>
            <div class="metric-card"><div class="metric-label">{{ __('Approved exceptions') }}</div><div class="metric-value">{{ collect($policies)->sum('active_exception_count') }}</div></div>
        </div>

        <div class="surface-card">
            <div class="entity-title">{{ __('Policy list') }}</div>
            <div class="table-note" style="margin-top:6px;">{{ __('This list stays focused on area, owner summary, linked controls, review due, state, and Open. Use Policy Detail to manage workflow, documents, and approved exceptions.') }}</div>
        </div>

        <div class="table-card">
            <table class="entity-table">
                <thead>
                    <tr>
                        <th>{{ __('Policy') }}</th>
                        <th>{{ __('Area') }}</th>
                        <th>{{ __('Owner') }}</th>
                        <th>{{ __('Linked control') }}</th>
                        <th>{{ __('Review due') }}</th>
                        <th>{{ __('State') }}</th>
                        <th>{{ $can_manage_policies ? __('Actions') : __('Access') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($policies as $policy)
                        <tr>
                            <td>
                                <div class="entity-title">{{ $policy['title'] }}</div>
                                <div class="entity-id">{{ $policy['id'] }} · {{ $policy['version_label'] }}</div>
                                <div class="table-note">{{ $policy['statement'] }}</div>
                                        <div class="table-note">{{ $policy['exception_count'] }} {{ __('exceptions') }}</div>
                            </td>
                            <td>{{ $policy['area_label'] }}</td>
                            <td>
                                @if (($policy['owner_assignments'] ?? []) !== [])
                                    <div>{{ $policy['owner_assignments'][0]['display_name'] }}</div>
                                    @if (count($policy['owner_assignments']) > 1)
                                        <div class="table-note">+{{ count($policy['owner_assignments']) - 1 }} {{ (count($policy['owner_assignments']) - 1) === 1 ? __('more owner') : __('more owners') }}</div>
                                    @else
                                        <div class="table-note">{{ $policy['owner_assignments'][0]['kind'] }}</div>
                                    @endif
                                @else
                                    <span class="muted-note">{{ __('No owner assigned') }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($policy['linked_control_url'] !== null)
                                    <a href="{{ $policy['linked_control_url'] }}">{{ $policy['linked_control_label'] ?? $policy['linked_control_id'] }}</a>
                                @else
                                    <span class="muted-note">{{ $policy['linked_control_id'] !== '' ? $policy['linked_control_id'] : __('None') }}</span>
                                @endif
                            </td>
                            <td>{{ $policy['review_due_on'] !== '' ? $policy['review_due_on'] : __('No review date') }}</td>
                            <td>
                                @php $sPolPill = match($policy['state']) { 'active' => 'pill-active', 'draft' => 'pill-draft', 'review' => 'pill-review', 'archived' => 'pill-archived', default => '' }; @endphp
                                <span class="pill {{ $sPolPill }}">{{ $policyStateLabels[$policy['state']] ?? $policy['state'] }}</span>
                            </td>
                            <td>
                                <a class="button button-secondary" href="{{ $policy['open_url'] }}&{{ http_build_query(['context_label' => __('Policies'), 'context_back_url' => $policies_list_url]) }}">{{ __('Open') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
