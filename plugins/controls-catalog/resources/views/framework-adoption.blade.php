<style>
    .pill-draft    { background: rgba(31,42,34,0.06);   color: var(--muted); }
    .pill-review   { background: rgba(245,158,11,0.14); color: #92400e; }
    .pill-approved { background: rgba(34,197,94,0.14);  color: #166534; }
    .pill-archived { background: rgba(31,42,34,0.06);   color: var(--muted); }
    .pill-ready { background: rgba(34,197,94,0.14); color: #166534; }
    .pill-attention { background: rgba(239,68,68,0.14); color: #991b1b; }
    .pill-onboarding { background: rgba(245,158,11,0.14); color: #92400e; }
    .pill-inactive { background: rgba(31,42,34,0.06); color: var(--muted); }

    details > summary { cursor: pointer; list-style: none; }
    details > summary::-webkit-details-marker { display: none; }
</style>

@php
    $hasFrameworks = $frameworks !== [];
@endphp

<section class="module-screen">
    <div class="overview-grid" style="grid-template-columns:repeat(6, minmax(0, 1fr));">
        <div class="metric-card"><div class="metric-label">{{ __('Frameworks') }}</div><div class="metric-value">{{ count($frameworks) }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('Adopted') }}</div><div class="metric-value">{{ collect($frameworks)->where('adoption_status', 'active')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('Onboarding') }}</div><div class="metric-value">{{ collect($frameworks)->where('adoption_status', 'in-progress')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('Requirements') }}</div><div class="metric-value">{{ count($requirements) }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('Linked controls') }}</div><div class="metric-value">{{ collect($frameworks)->sum('linked_control_count') }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('Mandates') }}</div><div class="metric-value">{{ collect($frameworks)->filter(fn ($framework) => is_array($framework['mandate_document'] ?? null))->count() }}</div></div>
    </div>

    <div class="surface-card">
            <div class="surface-note">
            {{ __('Activate each framework from its own card. When adoption becomes active, upload the signed mandate document from company leadership so the request is traceable by framework, date, and scope.') }}
        </div>
    </div>

    <div class="overview-grid" style="grid-template-columns:repeat(5, minmax(0, 1fr));">
        <div class="metric-card"><div class="metric-label">{{ __('Ready now') }}</div><div class="metric-value">{{ $leadership_snapshot['ready_count'] }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('Needs attention') }}</div><div class="metric-value">{{ $leadership_snapshot['attention_count'] }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('Onboarding') }}</div><div class="metric-value">{{ $leadership_snapshot['onboarding_count'] }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('Missing assessment') }}</div><div class="metric-value">{{ $leadership_snapshot['missing_assessment_count'] }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('Avg coverage') }}</div><div class="metric-value">{{ $leadership_snapshot['average_coverage_percent'] }}%</div></div>
    </div>

    @if ($can_manage_controls)
        <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
            <div class="surface-card">
                <div class="row-between" style="margin-bottom:12px;">
                    <div>
                        <div class="eyebrow">{{ __('Frameworks') }}</div>
                        <div class="entity-title" style="font-size:20px;">{{ __('Add framework') }}</div>
                    </div>
                </div>

                <form class="upload-form" method="POST" action="{{ $create_framework_route }}">
                    @csrf
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                    <input type="hidden" name="menu" value="plugin.controls-catalog.framework-adoption">
                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">

                    <div class="field">
                        <label class="field-label" for="framework-code">{{ __('Code') }}</label>
                        <input class="field-input" id="framework-code" name="code" placeholder="{{ __('ISO 27001') }}" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="framework-name">{{ __('Name') }}</label>
                        <input class="field-input" id="framework-name" name="name" placeholder="{{ __('ISO 27001:2022') }}" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="framework-description">{{ __('What it covers') }}</label>
                        <input class="field-input" id="framework-description" name="description" placeholder="{{ __('Security management baseline') }}">
                    </div>

                    <div class="action-cluster" style="margin-top:12px;">
                        <button class="button button-secondary" type="submit">{{ __('Add framework') }}</button>
                    </div>
                </form>
            </div>

            <div class="surface-card">
                <div class="row-between" style="margin-bottom:12px;">
                    <div>
                        <div class="eyebrow">{{ __('Requirements') }}</div>
                        <div class="entity-title" style="font-size:20px;">{{ __('Add requirement') }}</div>
                    </div>
                </div>

                <div class="surface-note" style="margin-bottom:12px;">
                    {{ __('Add requirements directly inside a framework card when you want them already scoped to that framework. Use this global form only when starting from scratch.') }}
                </div>

                <form class="upload-form" method="POST" action="{{ $create_requirement_route }}">
                    @csrf
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                    <input type="hidden" name="menu" value="plugin.controls-catalog.framework-adoption">
                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">

                    <div class="field">
                        <label class="field-label" for="requirement-framework">{{ __('Framework') }}</label>
                        <select class="field-select" id="requirement-framework" name="framework_id" @if ($hasFrameworks) required @endif>
                            <option value="">{{ $hasFrameworks ? __('Select a framework') : __('Create a framework first') }}</option>
                            @foreach ($frameworks as $framework)
                                <option value="{{ $framework['id'] }}">{{ $framework['code'] }} · {{ $framework['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="requirement-code">{{ __('Requirement code') }}</label>
                        <input class="field-input" id="requirement-code" name="code" placeholder="A.5.18" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="requirement-title">{{ __('Title') }}</label>
                        <input class="field-input" id="requirement-title" name="title" placeholder="{{ __('Access rights') }}" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="requirement-description">{{ __('What to verify') }}</label>
                        <input class="field-input" id="requirement-description" name="description" placeholder="{{ __('Review who gets access and why') }}">
                    </div>

                    <div class="action-cluster" style="margin-top:12px;">
                        <button class="button button-secondary" type="submit" @disabled(! $hasFrameworks)>{{ __('Add requirement') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="data-stack">
        @forelse ($frameworks as $framework)
            @php
                $adoptionPill = match($framework['adoption_status']) {
                    'active' => 'pill-approved',
                    'in-progress' => 'pill-review',
                    'inactive' => 'pill-archived',
                    default => 'pill-draft',
                };
                $mandateDocument = is_array($framework['mandate_document'] ?? null) ? $framework['mandate_document'] : null;
                $readinessPill = match($framework['readiness']['state'] ?? 'inactive') {
                    'ready' => 'pill-ready',
                    'attention' => 'pill-attention',
                    'onboarding' => 'pill-onboarding',
                    default => 'pill-inactive',
                };
                $latestAssessment = is_array($framework['readiness']['latest_assessment'] ?? null) ? $framework['readiness']['latest_assessment'] : null;
                $reportPresets = is_array($framework['readiness']['report_presets'] ?? null) ? $framework['readiness']['report_presets'] : [];
                $reviewSummary = is_array($framework['readiness']['review_summary'] ?? null) ? $framework['readiness']['review_summary'] : [];
            @endphp
            <div class="surface-card">
                <div class="screen-header">
                    <div>
                        <h2 class="screen-title" style="font-size:24px;">{{ $framework['code'] }} · {{ $framework['name'] }}</h2>
                        <p class="screen-subtitle">
                            {{ $framework['source_label'] }}
                            @if ($framework['version'] !== '')
                                · v{{ $framework['version'] }}
                            @endif
                            @if ($framework['kind'] !== '')
                                · {{ ucfirst($framework['kind']) }}
                            @endif
                        </p>
                    </div>
                    <div class="action-cluster">
                        <span class="pill {{ $adoptionPill }}">{{ __($framework['adoption_status']) }}</span>
                        <span class="table-note">{{ $framework['adoption_scope_label'] }}</span>
                    </div>
                </div>

                <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
                    <div class="metric-card"><div class="metric-label">{{ __('Requirements') }}</div><div class="metric-value">{{ $framework['requirement_count'] }}</div></div>
                    <div class="metric-card"><div class="metric-label">{{ __('Mapped') }}</div><div class="metric-value">{{ $framework['mapped_requirement_count'] }}</div></div>
                    <div class="metric-card"><div class="metric-label">{{ __('Controls') }}</div><div class="metric-value">{{ $framework['linked_control_count'] }}</div></div>
                    <div class="metric-card"><div class="metric-label">{{ __('Coverage') }}</div><div class="metric-value">{{ $framework['coverage_percent'] }}%</div></div>
                </div>

                @if ($framework['description'] !== '')
                    <div class="surface-note" style="margin-top:12px;">{{ $framework['description'] }}</div>
                @endif

                <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr)); margin-top:16px;">
                    <div class="surface-card" style="padding:16px;">
                        <div class="row-between">
                            <div class="field-label">{{ __('Readiness snapshot') }}</div>
                            <span class="pill {{ $readinessPill }}">{{ $framework['readiness']['label'] }}</span>
                        </div>

                        <div class="data-stack" style="margin-top:12px;">
                            <div class="data-item">
                                <div class="entity-title">{{ __('Coverage baseline') }}</div>
                                <div class="table-note">{{ __(':percent% mapped requirement coverage across :count linked controls.', ['percent' => $framework['coverage_percent'], 'count' => $framework['linked_control_count']]) }}</div>
                            </div>
                            @if ($latestAssessment !== null)
                                    <div class="data-item">
                                        <div class="entity-title">{{ __('Latest assessment') }}</div>
                                        <div class="table-note">{{ $latestAssessment['latest_assessment_title'] }}</div>
                                        <div class="table-note">{{ __(':status · starts :date', ['status' => $framework['readiness']['latest_assessment_status_label'], 'date' => $latestAssessment['latest_assessment_starts_on']]) }}</div>
                                        <div class="table-note">
                                            {{ __(':count controls reviewed · :pass pass · :partial partial · :fail fail', [
                                                'count' => $framework['readiness']['reviewed_control_count'],
                                                'pass' => $reviewSummary['pass'] ?? 0,
                                                'partial' => $reviewSummary['partial'] ?? 0,
                                                'fail' => $reviewSummary['fail'] ?? 0,
                                            ]) }}
                                        </div>
                                    </div>
                            @else
                                <div class="data-item">
                                <div class="entity-title">{{ __('Latest assessment') }}</div>
                                    <div class="table-note">{{ __('No assessment report is linked to this framework yet.') }}</div>
                                </div>
                            @endif
                        </div>

                        @if (($framework['readiness']['gaps'] ?? []) !== [])
                            <div class="data-stack" style="margin-top:12px;">
                                @foreach ($framework['readiness']['gaps'] as $gap)
                                    <div class="surface-note">{{ $gap }}</div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="surface-card" style="padding:16px;">
                        <div class="field-label">{{ __('Reporting presets') }}</div>
                        <div class="table-note" style="margin-top:6px;">
                            {{ __('Jump directly to the latest framework-linked assessment report without rebuilding filters manually.') }}
                        </div>

                        @if ($reportPresets !== [])
                            <div class="action-cluster" style="margin-top:12px;">
                                <a class="button button-secondary" href="{{ $reportPresets['markdown'] }}">{{ __('Open report') }}</a>
                                <a class="button button-ghost" href="{{ $reportPresets['csv'] }}">{{ __('Export CSV') }}</a>
                                <a class="button button-ghost" href="{{ $reportPresets['json'] }}">{{ __('Export JSON') }}</a>
                            </div>
                            <div class="table-note" style="margin-top:12px;">
                                {{ __('Preset linked to :title.', ['title' => $latestAssessment['latest_assessment_title'] ?? __('the latest assessment')]) }}
                            </div>
                        @else
                            <div class="surface-note" style="margin-top:12px;">
                                {{ __('Reporting presets will appear once an assessment is linked to this framework.') }}
                            </div>
                        @endif
                    </div>
                </div>

                <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr)); margin-top:16px;">
                    <div class="surface-card" style="padding:16px;">
                        <div class="field-label">{{ __('Adoption record') }}</div>
                        <div class="data-stack" style="margin-top:10px;">
                            <div class="data-item">
                                <div class="entity-title">{{ __('Workspace scope') }}</div>
                                <div class="table-note">{{ $framework['adoption_scope_label'] }}</div>
                            </div>
                            <div class="data-item">
                                <div class="entity-title">{{ __('Adopted on') }}</div>
                                <div class="table-note">{{ $framework['adopted_at'] !== '' ? $framework['adopted_at'] : __('Not set') }}</div>
                            </div>
                            <div class="data-item">
                                <div class="entity-title">{{ __('Target level') }}</div>
                                <div class="table-note">{{ $framework['target_level'] !== '' ? __($framework['target_level']) : __('Not set') }}</div>
                            </div>
                        </div>

                        @if ($mandateDocument !== null)
                            <div class="data-item" style="margin-top:12px;">
                                <div class="entity-title">{{ $mandateDocument['label'] }}</div>
                                <div class="table-note">{{ $mandateDocument['original_filename'] }}</div>
                                <div class="table-note">{{ __('Uploaded :date', ['date' => $mandateDocument['created_at']]) }}</div>
                            </div>
                        @else
                            <div class="surface-note" style="margin-top:12px;">
                                {{ __('No signed mandate document uploaded yet.') }}
                            </div>
                        @endif
                    </div>

                    <div class="surface-card" style="padding:16px;">
                        <div class="field-label">{{ __('Requirements library') }}</div>
                        <div class="table-note" style="margin-top:6px;">
                            {{ __(':count requirements are currently attached to this framework.', ['count' => count($framework['requirements'])]) }}
                        </div>

                        <details style="margin-top:12px;">
                            <summary class="button button-ghost" style="display:inline-flex;">{{ __('Browse requirements') }}</summary>
                            <div class="data-stack" style="margin-top:12px;">
                                @forelse ($framework['requirements'] as $requirement)
                                    <div class="data-item">
                                        <div class="entity-title">{{ $requirement['code'] }} · {{ $requirement['title'] }}</div>
                                        @if ($requirement['description'] !== '')
                                            <div class="table-note">{{ $requirement['description'] }}</div>
                                        @endif
                                    </div>
                                @empty
                                    <span class="muted-note">{{ __('No requirements yet') }}</span>
                                @endforelse
                            </div>
                        </details>

                        @if ($can_manage_controls)
                            <details style="margin-top:12px;">
                                <summary class="button button-secondary" style="display:inline-flex;">{{ __('Add requirement to :code', ['code' => $framework['code']]) }}</summary>
                                <form class="upload-form" method="POST" action="{{ $create_requirement_route }}" style="margin-top:12px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.controls-catalog.framework-adoption">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <input type="hidden" name="framework_id" value="{{ $framework['id'] }}">

                                    <div class="field">
                                        <label class="field-label">{{ __('Requirement code') }}</label>
                                        <input class="field-input" name="code" placeholder="A.5.18" required>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Title') }}</label>
                                        <input class="field-input" name="title" placeholder="{{ __('Access rights') }}" required>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('What to verify') }}</label>
                                        <input class="field-input" name="description" placeholder="{{ __('Review who gets access and why') }}">
                                    </div>

                                    <div class="action-cluster" style="margin-top:12px;">
                                        <button class="button button-secondary" type="submit">{{ __('Add requirement') }}</button>
                                    </div>
                                </form>
                            </details>
                        @endif
                    </div>
                </div>

                @if ($can_manage_controls)
                    <div class="surface-card" style="padding:16px; margin-top:16px;">
                        <div class="field-label">{{ __('Manage adoption') }}</div>
                        <div class="table-note" style="margin-top:6px;">
                            {{ __('Upload the signed mandate document when the framework becomes active. If a mandate is already on file, you can update the adoption status without uploading it again.') }}
                        </div>

                        <form class="upload-form" method="POST" action="{{ $framework['adoption_update_route'] }}" enctype="multipart/form-data" style="margin-top:12px;">
                            @csrf
                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                            <input type="hidden" name="menu" value="plugin.controls-catalog.framework-adoption">
                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">

                            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                                <div class="field">
                                    <label class="field-label">{{ __('Scope') }}</label>
                                    <select class="field-select" name="scope_id">
                                        <option value="">{{ __('Organization-wide') }}</option>
                                        @foreach ($scope_options as $scope)
                                            <option value="{{ $scope['id'] }}" @selected($framework['adoption_scope_id'] === $scope['id'])>{{ $scope['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Adoption status') }}</label>
                                    <select class="field-select" name="status" required>
                                        @foreach ($framework_adoption_status_options as $statusValue => $statusLabel)
                                            <option value="{{ $statusValue }}" @selected($framework['adoption_status'] === $statusValue)>{{ $statusLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Target level') }}</label>
                                    <select class="field-select" name="target_level">
                                        <option value="">{{ __('Not set') }}</option>
                                        @foreach ($framework_target_level_options as $levelValue => $levelLabel)
                                            <option value="{{ $levelValue }}" @selected($framework['target_level'] === $levelValue)>{{ $levelLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Adopted on') }}</label>
                                    <input class="field-input" type="date" name="adopted_at" value="{{ $framework['adopted_at'] !== '' ? substr($framework['adopted_at'], 0, 10) : '' }}">
                                </div>
                                <div class="field" style="grid-column:1 / -1;">
                                    <label class="field-label">{{ __('Signed mandate document') }}</label>
                                    <input class="field-input" type="file" name="mandate_document" @if ($framework['adoption_status'] === 'active' && $mandateDocument === null) required @endif>
                                    <div class="table-note" style="margin-top:6px;">
                                        {{ __('Upload the signed document from company leadership that records who requested the framework adoption, when it was approved, and under which mandate.') }}
                                    </div>
                                </div>
                            </div>

                            <div class="action-cluster" style="margin-top:12px;">
                                <button class="button button-secondary" type="submit">{{ __('Save adoption') }}</button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        @empty
            <div class="surface-card">
                <span class="muted-note">{{ __('No frameworks yet.') }}</span>
            </div>
        @endforelse
    </div>
</section>
