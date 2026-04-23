@php
    $selectedAssessment = is_array($selected_assessment ?? null) ? $selected_assessment : null;
    $assessmentsListUrl = route('core.shell.index', collect($query)->except(['assessment_id'])->put('menu', 'plugin.assessments-audits.root')->all());
    $transitionLabels = [
        'activate' => __('Activate'),
        'sign-off' => __('Sign off'),
        'close' => __('Close'),
        'reopen' => __('Reopen'),
    ];
@endphp

<style>
    .review-card {
        padding: 16px;
        display: grid;
        gap: 12px;
        border-left: 3px solid rgba(31,42,34,0.15);
    }
    .review-card[data-result="pass"]           { border-left-color: var(--success); }
    .review-card[data-result="partial"]        { border-left-color: #f59e0b; }
    .review-card[data-result="fail"]           { border-left-color: var(--warning); }
    .review-card[data-result="not-applicable"] { border-left-color: rgba(31,42,34,0.08); opacity: 0.72; }

    .pill-pass           { background: rgba(34,197,94,0.14);  color: #166534; }
    .pill-partial        { background: rgba(245,158,11,0.14); color: #92400e; }
    .pill-fail           { background: rgba(239,68,68,0.14);  color: #991b1b; }
    .pill-not-applicable { background: rgba(31,42,34,0.05);   color: var(--muted); }
    .pill-signed-off     { background: rgba(59,130,246,0.14); color: #1d4ed8; }

    .review-summary {
        font-size: 13px;
        color: var(--ink);
        line-height: 1.45;
        padding: 10px 12px;
        background: rgba(255,255,255,0.54);
        border: 1px solid rgba(31,42,34,0.07);
        border-radius: 4px;
    }

    .review-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
        padding-top: 4px;
        border-top: 1px solid rgba(31,42,34,0.06);
    }

    details > summary {
        cursor: pointer;
        list-style: none;
    }
    details > summary::-webkit-details-marker { display: none; }

    .section-divider {
        border: none;
        border-top: 1px solid rgba(31,42,34,0.07);
        margin: 0;
    }
</style>

<section class="module-screen">
    <div class="surface-note">
        {{ __('Framework choices follow the adopted framework catalog for the current scope. Assessment status and review results are system-controlled values enforced by the application.') }}
    </div>

    {{-- ── Creation form (only shown via toolbar toggle, hidden by default) ─── --}}
    @if ($can_manage_assessments && $selectedAssessment === null)
        <div class="surface-card" id="assessment-editor" hidden>
            <div class="eyebrow" style="margin-bottom:8px;">{{ __('New assessment') }}</div>
            <form class="upload-form" method="POST" action="{{ $create_route }}">
                @csrf
                <input type="hidden" name="principal_id"   value="{{ $query['principal_id'] ?? '' }}">
                <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                <input type="hidden" name="locale"         value="{{ $query['locale'] }}">
                <input type="hidden" name="menu"           value="plugin.assessments-audits.root">
                <input type="hidden" name="membership_id"  value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">

                <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                    <div class="field">
                        <label class="field-label" for="assessment-title">{{ __('Title') }}</label>
                        <input class="field-input" id="assessment-title" name="title" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="assessment-framework">{{ __('Framework') }}</label>
                        <select class="field-select" id="assessment-framework" name="framework_id">
                            <option value="">{{ __('Any framework') }}</option>
                            @foreach ($framework_options as $framework)
                                <option value="{{ $framework['id'] }}">{{ $framework['label'] }}</option>
                            @endforeach
                        </select>
                        <div class="field-note">{{ __('Adopt frameworks in Controls Catalog to make them available here for the current scope.') }}</div>
                    </div>
                    <div class="field">
                        <label class="field-label" for="assessment-scope">{{ __('Scope') }}</label>
                        <select class="field-select" id="assessment-scope" name="scope_id">
                            <option value="">{{ __('Organization-wide') }}</option>
                            @foreach ($scope_options as $scope)
                                <option value="{{ $scope['id'] }}" @selected(($query['scope_id'] ?? null) === $scope['id'])>{{ $scope['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="assessment-status">{{ __('Status') }}</label>
                        <select class="field-select" id="assessment-status" name="status">
                            @foreach ($status_options as $statusValue => $statusLabel)
                                <option value="{{ $statusValue }}">{{ __($statusLabel) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="assessment-starts">{{ __('Starts on') }}</label>
                        <input class="field-input" id="assessment-starts" name="starts_on" type="date" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="assessment-ends">{{ __('Ends on') }}</label>
                        <input class="field-input" id="assessment-ends" name="ends_on" type="date" required>
                    </div>
                    <div class="field" style="grid-column:1 / -1;">
                        <label class="field-label" for="assessment-summary">{{ __('Summary') }}</label>
                        <input class="field-input" id="assessment-summary" name="summary" required>
                    </div>
                    <div class="field" style="grid-column:1 / -1;">
                        <label class="field-label" for="assessment-controls">{{ __('Checklist controls') }}</label>
                        <select class="field-select" id="assessment-controls" name="control_ids[]" multiple size="{{ min(max(count($control_options), 3), 8) }}">
                            @foreach ($control_options as $control)
                                <option value="{{ $control['id'] }}">{{ $control['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field" style="grid-column:1 / -1;">
                        <label class="field-label" for="assessment-owner-actor">{{ __('Initial owner actor') }}</label>
                        <select class="field-select" id="assessment-owner-actor" name="owner_actor_id">
                            <option value="">{{ __('No owner') }}</option>
                            @foreach ($owner_actor_options as $actor)
                                <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="action-cluster" style="margin-top:14px;">
                    <button class="button button-primary" type="submit">{{ __('Create assessment') }}</button>
                </div>
            </form>
        </div>
    @endif

    {{-- ── Global metrics ───────────────────────────────────────────────────── --}}
    <div class="overview-grid" style="grid-template-columns:repeat(6, minmax(0, 1fr));">
        <div class="metric-card"><div class="metric-label">{{ __('Assessments') }}</div><div class="metric-value">{{ count($campaigns) }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('Pass') }}</div>   <div class="metric-value" style="color:#166534;">{{ collect($campaigns)->sum(fn ($c) => $c['review_summary']['pass']) }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('Partial') }}</div><div class="metric-value" style="color:#92400e;">{{ collect($campaigns)->sum(fn ($c) => $c['review_summary']['partial']) }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('Fail') }}</div>   <div class="metric-value" style="color:#991b1b;">{{ collect($campaigns)->sum(fn ($c) => $c['review_summary']['fail']) }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('Findings') }}</div>  <div class="metric-value">{{ collect($campaigns)->sum(fn ($c) => $c['review_summary']['linked_findings']) }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('Workpapers') }}</div><div class="metric-value">{{ collect($campaigns)->sum(fn ($c) => $c['review_summary']['artifacts']) }}</div></div>
    </div>

    @if ($selectedAssessment !== null)

        {{-- ── Assessment detail ────────────────────────────────────────────── --}}
        <div class="surface-card" style="padding:20px; display:grid; gap:18px;">
            <div class="surface-note">
                {{ __('Assessment Detail keeps checklist reviews, workpapers, linked findings, export actions, sign-off, and summary editing in one campaign workspace. The campaign list stays focused on perimeter and result summary.') }}
            </div>

            {{-- Header --}}
            <div class="screen-header" style="margin-bottom:0; padding-bottom:16px;">
                <div>
                    <div class="eyebrow">{{ __('Assessment') }}</div>
                    <h2 class="screen-title" style="font-size:26px; margin-top:4px;">{{ $selectedAssessment['title'] }}</h2>
                    @if ($selectedAssessment['summary'] !== '')
                        <p class="screen-subtitle" style="margin-top:6px;">{{ $selectedAssessment['summary'] }}</p>
                    @endif
                </div>
                <div class="action-cluster" style="align-items:flex-start;">
                    @php
                        $statusPillClass = match($selectedAssessment['status']) {
                            'active'   => 'pill-pass',
                            'signed-off' => 'pill-signed-off',
                            'closed'   => 'pill-not-applicable',
                            'archived' => 'pill-not-applicable',
                            default    => '',
                        };
                    @endphp
                    <span class="pill {{ $statusPillClass }}">{{ __($status_options[$selectedAssessment['status']] ?? $selectedAssessment['status']) }}</span>
                    <a class="button button-secondary" href="{{ $selectedAssessment['report_route'] }}">{{ __('Export report') }}</a>
                    <a class="button button-ghost" href="{{ $selectedAssessment['report_csv_route'] }}">{{ __('Export CSV') }}</a>
                    <a class="button button-ghost" href="{{ $selectedAssessment['report_json_route'] }}">{{ __('Export bundle') }}</a>
                </div>
            </div>

            {{-- Context metrics --}}
            <div class="overview-grid" style="grid-template-columns:repeat(5, minmax(0, 1fr));">
                <div class="metric-card">
                    <div class="metric-label">{{ __('Scope') }}</div>
                    <div class="metric-value" style="font-size:18px;">{{ $selectedAssessment['scope_name'] ?: __('Organization-wide') }}</div>
                    <div class="meta-copy">{{ $selectedAssessment['framework_name'] ?: __('No framework') }}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">{{ __('Period') }}</div>
                    <div class="metric-value" style="font-size:16px;">{{ $selectedAssessment['starts_on'] }}</div>
                    <div class="meta-copy">{{ __('→') }} {{ $selectedAssessment['ends_on'] }}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">{{ __('Controls') }}</div>
                    <div class="metric-value">{{ count($selectedAssessment['reviews']) }}</div>
                    <div class="meta-copy">
                        {{ $selectedAssessment['review_summary']['pass'] }} {{ __('Pass') }} ·
                        {{ $selectedAssessment['review_summary']['fail'] }} {{ __('Fail') }} ·
                        {{ $selectedAssessment['review_summary']['not-tested'] ?? 0 }} {{ __('Pending') }}
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">{{ __('Evidence') }}</div>
                    <div class="metric-value">{{ $selectedAssessment['review_summary']['artifacts'] }}</div>
                    <div class="meta-copy">{{ $selectedAssessment['review_summary']['linked_findings'] }} {{ __('Linked findings') }}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">{{ __('Owners') }}</div>
                    <div class="metric-value">{{ count($selectedAssessment['owner_assignments']) }}</div>
                    <div class="meta-copy">
                        {{ count($selectedAssessment['owner_assignments']) > 0 ? $selectedAssessment['owner_assignments'][0]['display_name'] : __('No owner assigned') }}
                    </div>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">{{ __('Sign-off') }}</div>
                    <div class="table-note" style="margin-top:10px;">{{ __('Date') }}: {{ $selectedAssessment['signed_off_on'] !== '' ? $selectedAssessment['signed_off_on'] : __('Not signed off yet') }}</div>
                    <div class="table-note">{{ __('By') }}: {{ $selectedAssessment['signed_off_by_principal_id'] !== '' ? $selectedAssessment['signed_off_by_principal_id'] : __('n/a') }}</div>
                    @if ($selectedAssessment['signoff_notes'] !== '')
                        <div class="review-summary" style="margin-top:10px;">{{ $selectedAssessment['signoff_notes'] }}</div>
                    @endif
                </div>
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">{{ __('Closure') }}</div>
                    <div class="table-note" style="margin-top:10px;">{{ __('Date') }}: {{ $selectedAssessment['closed_on'] !== '' ? $selectedAssessment['closed_on'] : __('Not closed yet') }}</div>
                    <div class="table-note">{{ __('By') }}: {{ $selectedAssessment['closed_by_principal_id'] !== '' ? $selectedAssessment['closed_by_principal_id'] : __('n/a') }}</div>
                    @if ($selectedAssessment['closure_summary'] !== '')
                        <div class="review-summary" style="margin-top:10px;">{{ $selectedAssessment['closure_summary'] }}</div>
                    @endif
                </div>
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">{{ __('Ownership') }}</div>
                    <div class="table-note" style="margin-top:10px;">{{ __('Owners') }}: {{ count($selectedAssessment['owner_assignments']) }}</div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($selectedAssessment['owner_assignments'] as $owner)
                            <div class="data-item">
                                <div class="entity-title">{{ $owner['display_name'] }}</div>
                                <div class="table-note">{{ $owner['kind'] }}</div>
                                @if ($can_manage_assessments)
                                    <form method="POST" action="{{ str_replace('__ASSIGNMENT__', $owner['assignment_id'], $selectedAssessment['owner_remove_route']) }}" style="margin-top:8px;">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                        <input type="hidden" name="organization_id" value="{{ $selectedAssessment['organization_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="menu" value="plugin.assessments-audits.root">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <button class="button button-ghost" type="submit">{{ __('Remove owner') }}</button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <span class="muted-note">{{ __('No owner assigned') }}</span>
                        @endforelse
                    </div>
                </div>
            </div>

            @if ($selectedAssessment['framework_breakdown'] !== [])
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">{{ __('Framework coverage') }}</div>
                    <div class="data-stack" style="margin-top:10px;">
                        @foreach ($selectedAssessment['framework_breakdown'] as $framework)
                            <div class="data-item">
                                <div class="entity-title">{{ $framework['framework_code'] }} · {{ $framework['framework_name'] }}</div>
                                <div class="table-note">
                                    {{ $framework['requirement_count'] }} {{ __('Mapped requirements') }} ·
                                    {{ $framework['control_count'] }} {{ __('Linked controls') }} ·
                                    {{ $framework['source'] === 'global' ? __('Global pack') : __('Custom framework') }}
                                </div>
                                <div class="table-note">
                                    {{ $framework['result_summary']['pass'] }} {{ __('Pass') }} ·
                                    {{ $framework['result_summary']['partial'] }} {{ __('Partial') }} ·
                                    {{ $framework['result_summary']['fail'] }} {{ __('Fail') }} ·
                                    {{ $framework['result_summary']['not-tested'] }} {{ __('Pending') }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Edit assessment (collapsed by default) --}}
            @if ($can_manage_assessments)
                <hr class="section-divider">
                @if ($selectedAssessment['transitions'] !== [])
                    <details>
                        <summary class="button button-ghost" style="display:inline-flex; width:fit-content;">{{ __('Workflow and sign-off') }}</summary>
                        <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr)); margin-top:14px;">
                            @foreach ($selectedAssessment['transitions'] as $transition)
                                <div class="surface-card" style="padding:14px;">
                                    <div class="entity-title" style="font-size:14px;">{{ $transitionLabels[$transition] ?? ucwords(str_replace('-', ' ', $transition)) }}</div>
                                    <form class="upload-form" method="POST" action="{{ str_replace('__TRANSITION__', $transition, $selectedAssessment['transition_route']) }}" style="margin-top:10px;">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                        <input type="hidden" name="organization_id" value="{{ $selectedAssessment['organization_id'] }}">
                                        <input type="hidden" name="scope_id" value="{{ $selectedAssessment['scope_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="menu" value="plugin.assessments-audits.root">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        @if ($transition === 'sign-off')
                                            <div class="field">
                                                <label class="field-label">{{ __('Signed off on') }}</label>
                                                <input class="field-input" type="date" name="signed_off_on" value="{{ now()->toDateString() }}">
                                            </div>
                                            <div class="field">
                                                <label class="field-label">{{ __('Sign-off notes') }}</label>
                                                <textarea class="field-input" name="signoff_notes" rows="3">{{ $selectedAssessment['signoff_notes'] }}</textarea>
                                            </div>
                                        @endif
                                        @if ($transition === 'close')
                                            <div class="field">
                                                <label class="field-label">{{ __('Closed on') }}</label>
                                                <input class="field-input" type="date" name="closed_on" value="{{ now()->toDateString() }}">
                                            </div>
                                            <div class="field">
                                                <label class="field-label">{{ __('Closure summary') }}</label>
                                                <textarea class="field-input" name="closure_summary" rows="3">{{ $selectedAssessment['closure_summary'] }}</textarea>
                                            </div>
                                        @endif
                                        <div class="action-cluster" style="margin-top:10px;">
                                            <button class="button {{ in_array($transition, ['sign-off', 'close'], true) ? 'button-primary' : 'button-secondary' }}" type="submit">{{ $transitionLabels[$transition] ?? ucwords(str_replace('-', ' ', $transition)) }}</button>
                                        </div>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    </details>
                    <hr class="section-divider">
                @endif

                <details>
                    <summary class="button button-ghost" style="display:inline-flex; width:fit-content;">{{ __('Edit assessment details') }}</summary>
                    <form class="upload-form" method="POST" action="{{ $selectedAssessment['update_route'] }}" style="margin-top:14px;">
                        @csrf
                        <input type="hidden" name="principal_id"    value="{{ $query['principal_id'] ?? '' }}">
                        <input type="hidden" name="organization_id" value="{{ $selectedAssessment['organization_id'] }}">
                        <input type="hidden" name="locale"          value="{{ $query['locale'] }}">
                        <input type="hidden" name="menu"            value="plugin.assessments-audits.root">
                        <input type="hidden" name="membership_id"   value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                        <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                            <div class="field">
                                <label class="field-label">{{ __('Title') }}</label>
                                <input class="field-input" name="title" value="{{ $selectedAssessment['title'] }}" required>
                            </div>
                            <div class="field">
                                <label class="field-label">{{ __('Framework') }}</label>
                                <select class="field-select" name="framework_id">
                                    <option value="">{{ __('Any framework') }}</option>
                                    @foreach ($framework_options as $framework)
                                        <option value="{{ $framework['id'] }}" @selected($selectedAssessment['framework_id'] === $framework['id'])>{{ $framework['label'] }}</option>
                                    @endforeach
                                </select>
                                <div class="field-note">{{ __('Only adopted frameworks are offered here when the workspace already has adoption records.') }}</div>
                            </div>
                            <div class="field">
                                <label class="field-label">{{ __('Scope') }}</label>
                                <select class="field-select" name="scope_id">
                                    <option value="">{{ __('Organization-wide') }}</option>
                                    @foreach ($scope_options as $scope)
                                        <option value="{{ $scope['id'] }}" @selected($selectedAssessment['scope_id'] === $scope['id'])>{{ $scope['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label class="field-label">{{ __('Status') }}</label>
                                <select class="field-select" name="status">
                                    @foreach ($status_options as $statusValue => $statusLabel)
                                        <option value="{{ $statusValue }}" @selected($selectedAssessment['status'] === $statusValue)>{{ __($statusLabel) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label class="field-label">{{ __('Starts on') }}</label>
                                <input class="field-input" name="starts_on" type="date" value="{{ $selectedAssessment['starts_on'] }}" required>
                            </div>
                            <div class="field">
                                <label class="field-label">{{ __('Ends on') }}</label>
                                <input class="field-input" name="ends_on" type="date" value="{{ $selectedAssessment['ends_on'] }}" required>
                            </div>
                            <div class="field" style="grid-column:1 / -1;">
                                <label class="field-label">{{ __('Summary') }}</label>
                                <input class="field-input" name="summary" value="{{ $selectedAssessment['summary'] }}" required>
                            </div>
                            <div class="field" style="grid-column:1 / -1;">
                                <label class="field-label">{{ __('Checklist controls') }}</label>
                                <select class="field-select" name="control_ids[]" multiple size="{{ min(max(count($control_options), 3), 8) }}">
                                    @foreach ($control_options as $control)
                                        <option value="{{ $control['id'] }}" @selected(collect($selectedAssessment['controls'])->contains(fn ($ac) => $ac['id'] === $control['id']))>{{ $control['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field" style="grid-column:1 / -1;">
                                <label class="field-label">{{ __('Add owner actor') }}</label>
                                <select class="field-select" name="owner_actor_id">
                                    <option value="">{{ __('Do not add owner') }}</option>
                                    @foreach ($owner_actor_options as $actor)
                                        <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                                    @endforeach
                                </select>
                                <div class="field-note">{{ __('Selecting an actor adds another owner instead of replacing the current set.') }}</div>
                            </div>
                            @if (($selectedAssessment['owner_assignments'] ?? []) !== [])
                                <div class="field" style="grid-column:1 / -1;">
                                    <label class="field-label">{{ __('Current owners') }}</label>
                                    <div class="data-stack">
                                        @foreach ($selectedAssessment['owner_assignments'] as $owner)
                                            <div class="data-item">
                                                <div class="entity-title">{{ $owner['display_name'] }}</div>
                                                <div class="table-note">{{ $owner['kind'] }}</div>
                                                <form method="POST" action="{{ str_replace('__ASSIGNMENT__', $owner['assignment_id'], $selectedAssessment['owner_remove_route']) }}" style="margin-top:8px;">
                                                    @csrf
                                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                                    <input type="hidden" name="organization_id" value="{{ $selectedAssessment['organization_id'] }}">
                                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                                    <input type="hidden" name="menu" value="plugin.assessments-audits.root">
                                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                                    <button class="button button-ghost" type="submit">{{ __('Remove owner') }}</button>
                                                </form>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div class="action-cluster" style="margin-top:12px;">
                            <button class="button button-secondary" type="submit">{{ __('Save changes') }}</button>
                        </div>
                    </form>
                </details>
            @endif

        </div>

        {{-- ── Checklist section header ─────────────────────────────────────── --}}
        <div class="row-between" style="align-items:center; padding: 0 2px;">
            <div>
                <div class="eyebrow">{{ __('Checklist') }}</div>
                <div class="entity-title" style="margin-top:2px;">{{ count($selectedAssessment['reviews']) }} {{ __('Controls in scope') }}</div>
            </div>
            @php
                $pendingCount = collect($selectedAssessment['reviews'])->where('result', 'not-tested')->count();
            @endphp
            @if ($pendingCount > 0)
                <span class="pill" style="background:rgba(245,158,11,0.14); color:#92400e;">{{ $pendingCount }} {{ __('Pending') }}</span>
            @else
                <span class="pill pill-pass">{{ __('All reviewed') }}</span>
            @endif
        </div>

        {{-- ── Control cards ────────────────────────────────────────────────── --}}
        <div class="data-stack">
            @foreach ($selectedAssessment['reviews'] as $review)
                @php
                    $result     = $review['result'];
                    $pillClass  = match($result) {
                        'pass'           => 'pill-pass',
                        'partial'        => 'pill-partial',
                        'fail'           => 'pill-fail',
                        'not-applicable' => 'pill-not-applicable',
                        default          => '',
                    };
                    $isPending   = $result === 'not-tested';
                    $hasReview   = ! $isPending;
                    $hasFinding  = is_array($review['linked_finding'] ?? null);
                @endphp

                <section class="surface-card review-card" data-result="{{ $result }}">

                    {{-- ── Control header ──────────────────────────────────────── --}}
                    <div class="workflow-header">
                        <div style="display:grid; gap:3px;">
                            <div class="table-note">{{ $review['control_framework'] }} · {{ $review['control_domain'] }}</div>
                            <div class="entity-title" style="font-size:15px;">{{ $review['control_name'] }}</div>
                            @if ($review['control_evidence'] !== '')
                                <div class="table-note" style="margin-top:2px;">{{ __('Expected') }}: {{ $review['control_evidence'] }}</div>
                            @endif
                        </div>
                        <div style="display:flex; flex-direction:column; align-items:flex-end; gap:6px; flex-shrink:0;">
                            <span class="pill {{ $pillClass }}">{{ __($result_options[$result] ?? $result) }}</span>
                            @if ($hasReview && $review['reviewed_on'] !== '')
                                <span class="table-note">{{ $review['reviewed_on'] }}</span>
                            @endif
                        </div>
                    </div>

                    {{-- ── Review summary (shown when already reviewed) ─────────── --}}
                    @if ($hasReview && ($review['test_notes'] !== '' || $review['conclusion'] !== ''))
                        <div class="review-summary">
                            @if ($review['conclusion'] !== '')
                                <strong>{{ __('Conclusion') }}:</strong> {{ $review['conclusion'] }}
                            @endif
                            @if ($review['test_notes'] !== '' && $review['conclusion'] !== $review['test_notes'])
                                <div style="margin-top:4px; color:var(--muted);">{{ $review['test_notes'] }}</div>
                            @endif
                        </div>
                    @endif

                    {{-- ── Evidence & findings tags ─────────────────────────────── --}}
                    @if (count($review['artifacts']) > 0 || $hasFinding)
                        <div style="display:grid; gap:6px;">
                            <div style="display:flex; flex-wrap:wrap; gap:6px;">
                                @if (count($review['artifacts']) > 0)
                                    <span class="tag">{{ count($review['artifacts']) }} {{ __('Workpaper') }}{{ count($review['artifacts']) !== 1 ? 's' : '' }}</span>
                                @endif
                                @if ($hasFinding)
                                    <span class="tag" style="background:rgba(239,68,68,0.1); color:#991b1b;">
                                        {{ $review['linked_finding']['title'] }} · {{ __(ucfirst($review['linked_finding']['severity'])) }}
                                    </span>
                                @endif
                            </div>
                            @if (count($review['artifacts']) > 0)
                                <div class="data-stack">
                                    @foreach ($review['artifacts'] as $artifact)
                                        <div class="data-item">
                                            <div class="row-between" style="align-items:flex-start; gap:12px;">
                                                <div>
                                                    <div class="entity-title" style="font-size:12px;">{{ $artifact['label'] }}</div>
                                                    <div class="table-note">{{ $artifact['original_filename'] }}</div>
                                                </div>
                                                <form method="POST" action="{{ route('plugin.evidence-management.promote', ['artifactId' => $artifact['id']]) }}">
                                                    @csrf
                                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                                    <input type="hidden" name="organization_id" value="{{ $selectedAssessment['organization_id'] }}">
                                                    <input type="hidden" name="scope_id" value="{{ $selectedAssessment['scope_id'] }}">
                                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                                    <button class="button button-ghost" type="submit">{{ __('Promote to evidence') }}</button>
                                                </form>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- ── Actions ──────────────────────────────────────────────── --}}
                    @if ($can_manage_assessments)
                        <div class="review-actions">

                            {{-- Record / Update review (primary if pending, secondary if reviewed) --}}
                            <details>
                                <summary class="button {{ $isPending ? 'button-primary' : 'button-secondary' }}" style="display:inline-flex;">
                                    {{ $isPending ? __('Record review') : __('Update review') }}
                                </summary>
                                <form class="upload-form" method="POST" action="{{ $review['review_update_route'] }}" style="margin-top:12px;">
                                    @csrf
                                    <input type="hidden" name="principal_id"    value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $selectedAssessment['organization_id'] }}">
                                    <input type="hidden" name="scope_id"        value="{{ $selectedAssessment['scope_id'] }}">
                                    <input type="hidden" name="locale"          value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu"            value="plugin.assessments-audits.root">
                                    <input type="hidden" name="membership_id"   value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                                        <div class="field">
                                            <label class="field-label">{{ __('Result') }}</label>
                                            <select class="field-select" name="result">
                                                @foreach ($result_options as $resultValue => $resultLabel)
                                                    <option value="{{ $resultValue }}" @selected($result === $resultValue)>{{ __($resultLabel) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">{{ __('Reviewed on') }}</label>
                                            <input class="field-input" type="date" name="reviewed_on" value="{{ $review['reviewed_on'] }}">
                                        </div>
                                        <div class="field" style="grid-column:1 / -1;">
                                            <label class="field-label">{{ __('Test notes') }}</label>
                                            <textarea class="field-input" name="test_notes" rows="3">{{ $review['test_notes'] }}</textarea>
                                        </div>
                                        <div class="field" style="grid-column:1 / -1;">
                                            <label class="field-label">{{ __('Conclusion') }}</label>
                                            <textarea class="field-input" name="conclusion" rows="2">{{ $review['conclusion'] }}</textarea>
                                        </div>
                                    </div>
                                    <div class="action-cluster" style="margin-top:10px;">
                                        <button class="button button-secondary" type="submit">{{ __('Save review') }}</button>
                                    </div>
                                </form>
                            </details>

                            {{-- Add workpaper --}}
                            <details>
                                <summary class="button button-ghost" style="display:inline-flex;">{{ __('Add workpaper') }}</summary>
                                <form class="upload-form" method="POST" action="{{ $review['artifact_upload_route'] }}" enctype="multipart/form-data" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id"    value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $selectedAssessment['organization_id'] }}">
                                    <input type="hidden" name="scope_id"        value="{{ $selectedAssessment['scope_id'] }}">
                                    <input type="hidden" name="locale"          value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu"            value="plugin.assessments-audits.root">
                                    <input type="hidden" name="membership_id"   value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <input type="hidden" name="artifact_type"   value="workpaper">
                                    <div class="field">
                                        <label class="field-label">{{ __('Label') }}</label>
                                        <input class="field-input" name="label" value="{{ __('Assessment workpaper') }}">
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('File') }}</label>
                                        <input type="file" name="artifact" required>
                                    </div>
                                    <div class="action-cluster" style="margin-top:10px;">
                                        <button class="button button-secondary" type="submit">{{ __('Upload workpaper') }}</button>
                                    </div>
                                </form>
                            </details>

                            {{-- Create finding (only if none linked) --}}
                            @if (! $hasFinding)
                                <details>
                                    <summary class="button button-ghost" style="display:inline-flex;">{{ __('Create finding') }}</summary>
                                    <form class="upload-form" method="POST" action="{{ $review['finding_store_route'] }}" style="margin-top:10px;">
                                        @csrf
                                        <input type="hidden" name="principal_id"    value="{{ $query['principal_id'] ?? '' }}">
                                        <input type="hidden" name="organization_id" value="{{ $selectedAssessment['organization_id'] }}">
                                        <input type="hidden" name="scope_id"        value="{{ $selectedAssessment['scope_id'] }}">
                                        <input type="hidden" name="locale"          value="{{ $query['locale'] }}">
                                        <input type="hidden" name="menu"            value="plugin.assessments-audits.root">
                                        <input type="hidden" name="membership_id"   value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                                            <div class="field">
                                                <label class="field-label">{{ __('Title') }}</label>
                                                <input class="field-input" name="title" value="{{ $review['control_name'] }} {{ __('finding') }}" required>
                                            </div>
                                            <div class="field">
                                                <label class="field-label">{{ __('Severity') }}</label>
                                                <select class="field-select" name="severity">
                                                    <option value="low">{{ __('Low') }}</option>
                                                    <option value="medium" selected>{{ __('Medium') }}</option>
                                                    <option value="high">{{ __('High') }}</option>
                                                    <option value="critical">{{ __('Critical') }}</option>
                                                </select>
                                            </div>
                                            <div class="field">
                                                <label class="field-label">{{ __('Due on') }}</label>
                                                <input class="field-input" type="date" name="due_on">
                                            </div>
                                            <div class="field" style="grid-column:1 / -1;">
                                                <label class="field-label">{{ __('Description') }}</label>
                                                <textarea class="field-input" name="description" rows="3" required>{{ $review['conclusion'] !== '' ? $review['conclusion'] : __('Assessment review identified a gap that requires remediation.') }}</textarea>
                                            </div>
                                        </div>
                                        <div class="action-cluster" style="margin-top:10px;">
                                            <button class="button button-secondary" type="submit">{{ __('Create finding') }}</button>
                                        </div>
                                    </form>
                                </details>
                            @endif

                        </div>{{-- /review-actions --}}
                    @endif

                    {{-- ── Requirements (collapsed, reference info) ─────────────── --}}
                    @if (count($review['requirements']) > 0)
                        <details>
                            <summary class="button button-ghost" style="display:inline-flex; font-size:12px; min-height:32px; padding:6px 10px;">
                                {{ count($review['requirements']) }} {{ __('Mapped requirement') }}{{ count($review['requirements']) !== 1 ? 's' : '' }}
                            </summary>
                            <div class="data-stack" style="margin-top:10px;">
                                @foreach ($review['requirements'] as $requirement)
                                    <div class="data-item" style="display:grid; gap:2px;">
                                        <div class="entity-title" style="font-size:13px;">
                                            {{ $requirement['framework_code'] }} · {{ $requirement['requirement_code'] }}
                                        </div>
                                        <div class="table-note">{{ $requirement['requirement_title'] }}</div>
                                        <div class="table-note">{{ __('Coverage') }}: {{ __(ucfirst($requirement['coverage'])) }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endif

                </section>
            @endforeach
        </div>

    @else

        {{-- ── Assessment list ──────────────────────────────────────────────── --}}
        <div class="surface-card">
            <div class="entity-title">{{ __('Assessment campaign list') }}</div>
            <div class="table-note" style="margin-top:6px;">{{ __('This list stays focused on perimeter and result summary. Open an assessment to record reviews, upload workpapers, link findings, and export the campaign package.') }}</div>
        </div>

        <div class="table-card">
            <table class="entity-table">
                <thead>
                    <tr>
                        <th>{{ __('Assessment') }}</th>
                        <th>{{ __('Scope / Framework') }}</th>
                        <th>{{ __('Progress') }}</th>
                        <th>{{ __('State') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($campaigns as $campaign)
                        <tr>
                            <td>
                                <div class="entity-title">{{ $campaign['title'] }}</div>
                                <div class="entity-id">{{ $campaign['id'] }}</div>
                            </td>
                            <td>
                                <div>{{ $campaign['scope_name'] ?: __('Organization-wide') }}</div>
                                <div class="table-note">{{ $campaign['framework_name'] ?: '—' }}</div>
                                <div class="table-note">{{ $campaign['starts_on'] }} – {{ $campaign['ends_on'] }}</div>
                                <div class="table-note">{{ __('Owners') }}: {{ count($campaign['owner_assignments']) }}</div>
                            </td>
                            <td>
                                @php $total = count($campaign['reviews']); @endphp
                                <div class="table-note">
                                    {{ $total }} {{ __('Controls') }} ·
                                    <span style="color:#166534;">{{ $campaign['review_summary']['pass'] }} {{ __('Pass') }}</span> ·
                                    <span style="color:#991b1b;">{{ $campaign['review_summary']['fail'] }} {{ __('Fail') }}</span>
                                </div>
                                <div class="table-note">{{ $campaign['review_summary']['artifacts'] }} {{ __('Workpapers') }} · {{ $campaign['review_summary']['linked_findings'] }} {{ __('Findings') }}</div>
                            </td>
                            <td>
                                @php
                                    $sPill = match($campaign['status']) {
                                        'active'   => 'pill-pass',
                                        'signed-off' => 'pill-signed-off',
                                        'closed'   => 'pill-not-applicable',
                                        'archived' => 'pill-not-applicable',
                                        default    => '',
                                    };
                                @endphp
                                <span class="pill {{ $sPill }}">{{ __($status_options[$campaign['status']] ?? $campaign['status']) }}</span>
                            </td>
                            <td>
                                <a class="button button-secondary" href="{{ $campaign['open_url'] }}&{{ http_build_query(['context_label' => __('Assessments'), 'context_back_url' => $assessmentsListUrl]) }}">{{ __('Open') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align:center; padding:28px;">
                                <span class="muted-note">{{ __('No assessments yet.') }}</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    @endif
</section>
