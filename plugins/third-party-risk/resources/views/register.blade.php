<style>
    .pill-prospective { background: rgba(245,158,11,0.14); color: #92400e; }
    .pill-in-review { background: rgba(59,130,246,0.14); color: #1d4ed8; }
    .pill-approved { background: rgba(34,197,94,0.14); color: #166534; }
    .pill-approved-with-conditions { background: rgba(249,115,22,0.14); color: #9a3412; }
    .pill-rejected { background: rgba(239,68,68,0.14); color: #991b1b; }

    details > summary { cursor: pointer; list-style: none; }
    details > summary::-webkit-details-marker { display: none; }

    .vendor-register-grid {
        display: grid;
        gap: 16px;
    }

    .vendor-kpi-strip {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }

    .vendor-kpi {
        border: 1px solid rgba(31,42,34,0.08);
        border-radius: 6px;
        background: rgba(255,255,255,0.52);
        padding: 14px 16px;
        display: grid;
        gap: 6px;
    }

    .vendor-kpi-value {
        font-family: var(--font-heading);
        font-size: 28px;
        line-height: 1;
    }

    .vendor-kpi-copy {
        color: var(--muted);
        font-size: 12px;
        line-height: 1.4;
    }

    .vendor-register-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.8fr) minmax(320px, 0.9fr);
        gap: 16px;
        align-items: start;
    }

    .vendor-panel {
        border: 1px solid rgba(31,42,34,0.08);
        border-radius: 6px;
        background: rgba(255,255,255,0.52);
        padding: 16px;
        display: grid;
        gap: 14px;
    }

    .vendor-panel-header {
        display: flex;
        justify-content: space-between;
        gap: 14px;
        align-items: flex-start;
        padding-bottom: 12px;
        border-bottom: 1px solid rgba(31,42,34,0.08);
    }

    .vendor-panel-header h3 {
        margin: 4px 0 0;
        font-family: var(--font-heading);
        font-size: 24px;
        line-height: 1;
    }

    .vendor-register-table {
        width: 100%;
        border-collapse: collapse;
        background: rgba(255,255,255,0.62);
        border: 1px solid rgba(31,42,34,0.08);
    }

    .vendor-register-table th,
    .vendor-register-table td {
        padding: 13px 12px;
        border-bottom: 1px solid rgba(31,42,34,0.08);
        text-align: left;
        vertical-align: top;
    }

    .vendor-register-table thead th {
        font-size: 11px;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--muted);
        background: rgba(255,255,255,0.76);
    }

    .vendor-register-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .vendor-tier {
        display: inline-flex;
        align-items: center;
        min-height: 28px;
        padding: 5px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        border: 1px solid rgba(31,42,34,0.08);
        background: rgba(255,255,255,0.86);
    }

    .vendor-tier-high,
    .vendor-tier-critical {
        background: rgba(239,68,68,0.1);
        color: #991b1b;
    }

    .vendor-tier-medium {
        background: rgba(245,158,11,0.12);
        color: #92400e;
    }

    .vendor-tier-low {
        background: rgba(34,197,94,0.12);
        color: #166534;
    }

    .vendor-status-badge {
        display: inline-flex;
        align-items: center;
        min-height: 28px;
        padding: 5px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        border: 1px solid rgba(31,42,34,0.08);
        background: rgba(255,255,255,0.86);
    }

    .vendor-status-active {
        background: rgba(34,197,94,0.12);
        color: #166534;
    }

    .vendor-status-prospective {
        background: rgba(245,158,11,0.12);
        color: #92400e;
    }

    .vendor-status-suspended,
    .vendor-status-inactive {
        background: rgba(239,68,68,0.1);
        color: #991b1b;
    }

    .vendor-mini-stack,
    .vendor-rail-list {
        display: grid;
        gap: 10px;
    }

    .vendor-mini-item {
        border: 1px solid rgba(31,42,34,0.08);
        border-radius: 6px;
        background: rgba(255,255,255,0.66);
        padding: 12px 13px;
        display: grid;
        gap: 6px;
    }

    .vendor-mini-top {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        align-items: flex-start;
    }

    .vendor-mini-meta {
        color: var(--muted);
        font-size: 12px;
        line-height: 1.4;
    }

    .vendor-filter-strip {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .vendor-filter-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-height: 34px;
        padding: 7px 12px;
        border-radius: 999px;
        border: 1px solid rgba(31,42,34,0.12);
        background: rgba(255,255,255,0.72);
        color: inherit;
        text-decoration: none;
        font-size: 12px;
        font-weight: 700;
    }

    .vendor-filter-link.is-active {
        background: rgba(176,94,38,0.12);
        border-color: rgba(176,94,38,0.22);
        color: #7a3d13;
    }

    .vendor-filter-count {
        color: var(--muted);
        font-weight: 600;
    }

    .vendor-timeline {
        display: grid;
        gap: 10px;
    }

    .vendor-timeline-item {
        border: 1px solid rgba(31,42,34,0.08);
        border-radius: 6px;
        background: rgba(255,255,255,0.66);
        padding: 12px 13px;
        display: grid;
        gap: 4px;
    }

    .vendor-timeline-top {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        align-items: flex-start;
    }

    .vendor-timeline-time {
        color: var(--muted);
        font-size: 12px;
        white-space: nowrap;
    }

    .vendor-comment-body {
        white-space: pre-wrap;
        line-height: 1.5;
    }

    .vendor-request-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 6px;
    }

    .vendor-request-chip {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
        padding: 4px 8px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        border: 1px solid rgba(31,42,34,0.08);
        background: rgba(255,255,255,0.86);
    }

    .vendor-request-priority-urgent,
    .vendor-request-priority-high {
        color: #991b1b;
        background: rgba(239,68,68,0.1);
    }

    .vendor-request-priority-normal {
        color: #92400e;
        background: rgba(245,158,11,0.12);
    }

    .vendor-request-priority-low {
        color: #166534;
        background: rgba(34,197,94,0.12);
    }

    @media (max-width: 1200px) {
        .vendor-register-layout,
        .vendor-kpi-strip {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>

<section class="module-screen">
    <div class="surface-note">
        {{ __('Vendor reviews keep intake, current review posture, linked records, evidence, and approval decisions in one workspace. Use the register to browse vendors and open the current review you want to work on.') }}
    </div>

    @if (is_array($selected_vendor))
        @php
            $review = $selected_vendor['current_review'];
            $reviewStatePill = match($review['state']) {
                'prospective' => 'pill-prospective',
                'in-review' => 'pill-in-review',
                'approved' => 'pill-approved',
                'approved-with-conditions' => 'pill-approved-with-conditions',
                'rejected' => 'pill-rejected',
                default => '',
            };
        @endphp

        <div class="surface-card" style="padding:16px; display:grid; gap:16px;">
            <div class="surface-note">
                {{ __('Vendor Review keeps intake context, evidence, decision notes, linked internal records, and reviewer ownership in one workspace.') }}
            </div>

            <div class="row-between" style="align-items:flex-start;">
                <div>
                    <div class="eyebrow">{{ __('Vendor Review') }}</div>
                    <h2 class="screen-title" style="font-size:28px;">{{ $selected_vendor['legal_name'] }}</h2>
                    <div class="table-note">{{ $selected_vendor['id'] }}</div>
                    <div class="table-note">{{ $selected_vendor['service_summary'] }}</div>
                </div>
                <div class="action-cluster">
                    <span class="pill {{ $reviewStatePill }}">{{ $review['state_label'] }}</span>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
                <div class="metric-card"><div class="metric-label">{{ __('Tier') }}</div><div class="metric-value">{{ $selected_vendor['tier_label'] ?? ucfirst($selected_vendor['tier']) }}</div></div>
                <div class="metric-card"><div class="metric-label">{{ __('Inherent risk') }}</div><div class="metric-value">{{ $review['inherent_risk_label'] ?? ucfirst($review['inherent_risk']) }}</div></div>
                <div class="metric-card"><div class="metric-label">{{ __('Evidence') }}</div><div class="metric-value">{{ count($review['artifacts']) }}</div></div>
                <div class="metric-card"><div class="metric-label">{{ __('Questionnaire items') }}</div><div class="metric-value">{{ count($review['questionnaire_items']) }}</div><div class="meta-copy">{{ $review['questionnaire_template']['name'] ?? __('Manual only') }}</div></div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">{{ __('Overview') }}</div>
                    <div class="table-note" style="margin-top:10px;">{{ __('Current review: :title', ['title' => $review['title']]) }}</div>
                    <div class="table-note">{{ __('Contact: :name', ['name' => $selected_vendor['primary_contact_name'] !== '' ? $selected_vendor['primary_contact_name'] : __('None')]) }}@if($selected_vendor['primary_contact_email'] !== '') · {{ $selected_vendor['primary_contact_email'] }}@endif</div>
                    <div class="table-note">{{ __('Website: :value', ['value' => $selected_vendor['website'] !== '' ? $selected_vendor['website'] : __('Not set')]) }}</div>
                    <div class="table-note">{{ __('Scope: :value', ['value' => $selected_vendor['scope_id'] !== '' ? $selected_vendor['scope_id'] : __('Organization-wide')]) }}</div>
                    <div class="table-note">{{ __('Next review due: :value', ['value' => $review['next_review_due_on'] !== '' ? $review['next_review_due_on'] : __('Not scheduled')]) }}</div>
                    <div class="table-note" style="margin-top:10px;">{{ __('Review summary: :summary', ['summary' => $review['review_summary']]) }}</div>
                    <div class="table-note">{{ __('Decision notes: :notes', ['notes' => $review['decision_notes'] !== '' ? $review['decision_notes'] : __('None')]) }}</div>
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">{{ __('Workflow') }}</div>
                    @if ($review['transitions'] !== [])
                        <div class="action-cluster" style="margin-top:10px;">
                            @foreach ($review['transitions'] as $transition)
                                <form method="POST" action="{{ str_replace('__TRANSITION__', $transition, $review['transition_route']) }}">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.third-party-risk.root">
                                    <input type="hidden" name="vendor_id" value="{{ $selected_vendor['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <button class="button button-secondary" type="submit">{{ $review['transition_labels'][$transition] ?? ucfirst(str_replace('-', ' ', $transition)) }}</button>
                                </form>
                            @endforeach
                        </div>
                    @else
                        <div class="table-note" style="margin-top:10px;">{{ __('View-only access') }}</div>
                    @endif

                    <div class="data-stack" style="margin-top:12px;">
                        @forelse ($review['history'] as $history)
                            <div class="data-item">
                        <div class="entity-title">{{ $history->transitionKey }}</div>
                        <div class="table-note">{{ $history->fromState }} → {{ $history->toState }}</div>
                            </div>
                        @empty
                            <span class="muted-note">{{ __('No transitions recorded yet') }}</span>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">{{ __('Review profile') }}</div>
                    @if (is_array($review['review_profile'] ?? null))
                        <div class="entity-title" style="margin-top:10px;">{{ $review['review_profile']['name'] }}</div>
                        <div class="table-note">{{ $review['review_profile']['tier_label'] ?? ucfirst($review['review_profile']['tier']) }} {{ __('tier') }} · {{ __('default inherent risk') }} {{ $review['review_profile']['default_inherent_risk_label'] ?? ucfirst($review['review_profile']['default_inherent_risk']) }}</div>
                        <div class="table-note">{{ $review['review_profile']['review_interval_days'] !== '' ? $review['review_profile']['review_interval_days'].' '.__('day cadence') : __('No default cadence') }}</div>
                        <div class="table-note">{{ $review['review_profile']['summary'] !== '' ? $review['review_profile']['summary'] : __('No profile summary.') }}</div>
                    @else
                        <span class="muted-note" style="margin-top:10px;">{{ __('No review profile selected') }}</span>
                    @endif
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="row-between">
                        <div class="metric-label">{{ __('Questionnaire template') }}</div>
                        @if ($can_manage_vendors && ($review['questionnaire_template']['id'] ?? '') !== '')
                            <form method="POST" action="{{ $review['questionnaire_apply_template_route'] }}">
                                @csrf
                                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                <input type="hidden" name="menu" value="plugin.third-party-risk.root">
                                <input type="hidden" name="vendor_id" value="{{ $selected_vendor['id'] }}">
                                <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                <input type="hidden" name="questionnaire_template_id" value="{{ $review['questionnaire_template']['id'] }}">
                                <button class="button button-ghost" type="submit">{{ __('Apply template items') }}</button>
                            </form>
                        @endif
                    </div>
                    @if (is_array($review['questionnaire_template'] ?? null))
                        <div class="entity-title" style="margin-top:10px;">{{ $review['questionnaire_template']['name'] }}</div>
                        <div class="table-note">{{ $review['questionnaire_template']['summary'] !== '' ? $review['questionnaire_template']['summary'] : __('No template summary.') }}</div>
                    @else
                        <span class="muted-note" style="margin-top:10px;">{{ __('No questionnaire template selected') }}</span>
                    @endif
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="row-between">
                    <div class="metric-label">{{ __('Evidence') }}</div>
                        @if ($can_manage_vendors)
                            <details>
                                <summary class="button button-ghost" style="display:inline-flex;">{{ __('Attach evidence') }}</summary>
                                <form class="upload-form" method="POST" action="{{ $review['artifact_upload_route'] }}" enctype="multipart/form-data" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.third-party-risk.root">
                                    <input type="hidden" name="vendor_id" value="{{ $selected_vendor['id'] }}">
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
                        @forelse ($review['artifacts'] as $artifact)
                            <div class="data-item">
                                <div class="entity-title">{{ $artifact['label'] }}</div>
                                <div class="table-note">{{ $artifact['original_filename'] }}</div>
                            </div>
                        @empty
                            <span class="muted-note">{{ __('No evidence yet') }}</span>
                        @endforelse
                    </div>
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="row-between">
                    <div class="metric-label">{{ __('Questionnaire') }}</div>
                        @if ($can_manage_vendors)
                            <details>
                                <summary class="button button-ghost" style="display:inline-flex;">{{ __('Add question') }}</summary>
                                <form class="upload-form" method="POST" action="{{ $review['questionnaire_store_route'] }}" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.third-party-risk.root">
                                    <input type="hidden" name="vendor_id" value="{{ $selected_vendor['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <input class="field-input" type="text" name="section_title" placeholder="{{ __('Section title') }}">
                                    <input class="field-input" type="text" name="prompt" placeholder="{{ __('Question prompt') }}" required>
                                    <select class="field-select" name="response_type" required>
                                        @foreach ($questionnaire_response_type_options as $typeKey => $typeLabel)
                                            <option value="{{ $typeKey }}">{{ $typeLabel }}</option>
                                        @endforeach
                                    </select>
                                    <select class="field-select" name="attachment_mode">
                                        @foreach ($questionnaire_attachment_mode_options as $modeKey => $modeLabel)
                                            <option value="{{ $modeKey }}">{{ $modeLabel }}</option>
                                        @endforeach
                                    </select>
                                    <select class="field-select" name="attachment_upload_profile">
                                        <option value="">{{ __('Default attachment profile') }}</option>
                                        @foreach ($questionnaire_attachment_upload_profile_options as $profileKey => $profileLabel)
                                            <option value="{{ $profileKey }}">{{ $profileLabel }}</option>
                                        @endforeach
                                    </select>
                                    <select class="field-select" name="response_status">
                                        @foreach ($questionnaire_response_status_options as $statusKey => $statusLabel)
                                            <option value="{{ $statusKey }}">{{ $statusLabel }}</option>
                                        @endforeach
                                    </select>
                                    <input class="field-input" type="text" name="answer_text" placeholder="{{ __('Optional answer') }}">
                                    <input class="field-input" type="text" name="follow_up_notes" placeholder="{{ __('Optional follow-up') }}">
                                    <label class="table-note" style="display:flex; gap:8px; align-items:center;">
                                        <input type="checkbox" name="save_to_answer_library" value="1">
                                        <span>{{ __('Save this answer to the questionnaire library') }}</span>
                                    </label>
                                    <label class="table-note" style="display:flex; gap:8px; align-items:center;">
                                        <input type="checkbox" name="promote_attachments_to_evidence" value="1">
                                        <span>{{ __('Allow attachment promotion to evidence') }}</span>
                                    </label>
                                    <button class="button button-secondary" type="submit">{{ __('Save question') }}</button>
                                </form>
                            </details>
                        @endif
                    </div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($review['questionnaire_sections'] as $section)
                            <div class="surface-card" style="padding:14px;">
                                <div class="eyebrow">{{ $section['title'] }}</div>
                                <div class="data-stack" style="margin-top:10px;">
                                    @foreach ($section['items'] as $item)
                                        <div class="data-item">
                                            <div class="entity-title">{{ $item['position'] }}. {{ $item['prompt'] }}</div>
                                            <div class="table-note">{{ $item['response_type_label'] }} · {{ $item['response_status_label'] }}</div>
                                            <div class="table-note">{{ __('Attachment policy') }}: {{ $item['attachment_mode_label'] }} @if($item['supports_attachments']) · {{ $item['attachment_upload_profile_label'] }} @endif</div>
                                            <div class="table-note">{{ __('Answer') }}: {{ $item['answer_text'] !== '' ? $item['answer_text'] : __('No answer yet') }}</div>
                                            <div class="table-note">{{ __('Follow-up') }}: {{ $item['follow_up_notes'] !== '' ? $item['follow_up_notes'] : __('None') }}</div>
                                            <div class="table-note">{{ __('Review notes') }}: {{ $item['review_notes'] !== '' ? $item['review_notes'] : __('None') }}</div>
                                            @if ($item['reviewed_at'] !== '')
                                                <div class="table-note">{{ __('Reviewed at') }}: {{ $item['reviewed_at'] }}@if($item['reviewed_by_principal_id'] !== '') · {{ $item['reviewed_by_principal_id'] }}@endif</div>
                                            @endif
                                            @if (($item['answer_library_entries'] ?? []) !== [])
                                                <div class="surface-note" style="margin-top:8px;">
                                                    <strong>{{ __('Library suggestions') }}</strong>
                                                    @foreach ($item['answer_library_entries'] as $entry)
                                                        <div class="table-note" style="margin-top:6px;">
                                                            {{ $entry['answer_text'] }}
                                                            @if ($entry['notes'] !== '')
                                                                · {{ $entry['notes'] }}
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                            @if ($item['supports_attachments'])
                                                <div class="surface-note" style="margin-top:8px;">
                                                    <strong>{{ __('Question attachments') }}</strong>
                                                    <div class="table-note" style="margin-top:6px;">{{ __('Use this slot for files that specifically support this answer.') }}</div>
                                                    <div class="data-stack" style="margin-top:8px;">
                                                        @forelse ($item['artifacts'] as $artifact)
                                                            <div class="data-item">
                                                                <div class="entity-title">{{ $artifact['label'] }}</div>
                                                                <div class="table-note">{{ $artifact['original_filename'] }} · {{ $artifact['artifact_type'] }}</div>
                                                                @if ($can_manage_vendors && $can_manage_evidence && $item['promote_attachments_to_evidence'] === '1')
                                                                    <form method="POST" action="{{ $artifact['promote_route'] }}" style="margin-top:8px;">
                                                                        @csrf
                                                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                                                        <input type="hidden" name="scope_id" value="{{ $query['scope_id'] ?? '' }}">
                                                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                                                        <button class="button button-ghost" type="submit">{{ __('Promote to evidence') }}</button>
                                                                    </form>
                                                                @endif
                                                            </div>
                                                        @empty
                                                            <span class="muted-note">{{ __('No question attachments yet') }}</span>
                                                        @endforelse
                                                    </div>
                                                </div>
                                                @if ($can_manage_vendors)
                                                    <details style="margin-top:8px;">
                                                        <summary class="button button-ghost" style="display:inline-flex;">{{ __('Upload attachment') }}</summary>
                                                        <form class="upload-form" method="POST" action="{{ $item['artifact_upload_route'] }}" enctype="multipart/form-data" style="margin-top:10px;">
                                                            @csrf
                                                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                                            <input type="hidden" name="menu" value="plugin.third-party-risk.root">
                                                            <input type="hidden" name="vendor_id" value="{{ $selected_vendor['id'] }}">
                                                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                                            <input class="field-input" type="text" name="label" placeholder="{{ __('Attachment label') }}">
                                                            <input class="field-input" type="file" name="artifact" required>
                                                            <button class="button button-secondary" type="submit">{{ __('Upload attachment') }}</button>
                                                        </form>
                                                    </details>
                                                @endif
                                            @endif
                                            @if ($can_manage_vendors)
                                                <details style="margin-top:8px;">
                                                    <summary class="button button-ghost" style="display:inline-flex;">{{ __('Review response') }}</summary>
                                                    <form class="upload-form" method="POST" action="{{ $item['review_route'] }}" style="margin-top:10px;">
                                                        @csrf
                                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                                        <input type="hidden" name="menu" value="plugin.third-party-risk.root">
                                                        <input type="hidden" name="vendor_id" value="{{ $selected_vendor['id'] }}">
                                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                                        <select class="field-select" name="response_status" required>
                                                            <option value="under-review" @selected($item['response_status'] === 'under-review')>{{ __('Under review') }}</option>
                                                            <option value="accepted" @selected($item['response_status'] === 'accepted')>{{ __('Accepted') }}</option>
                                                            <option value="needs-follow-up" @selected($item['response_status'] === 'needs-follow-up')>{{ __('Needs follow-up') }}</option>
                                                        </select>
                                                        <input class="field-input" type="text" name="review_notes" value="{{ $item['review_notes'] }}" placeholder="{{ __('Review notes') }}">
                                                        <button class="button button-secondary" type="submit">{{ __('Save review') }}</button>
                                                    </form>
                                                </details>
                                                <details style="margin-top:8px;">
                                                    <summary class="button button-ghost" style="display:inline-flex;">{{ __('Update item') }}</summary>
                                                    <form class="upload-form" method="POST" action="{{ $item['update_route'] }}" style="margin-top:10px;">
                                                        @csrf
                                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                                        <input type="hidden" name="menu" value="plugin.third-party-risk.root">
                                                        <input type="hidden" name="vendor_id" value="{{ $selected_vendor['id'] }}">
                                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                                        <input class="field-input" type="text" name="section_title" value="{{ $item['section_title'] }}" placeholder="{{ __('Section title') }}">
                                                        <input class="field-input" type="text" name="prompt" value="{{ $item['prompt'] }}" required>
                                                        <select class="field-select" name="response_type" required>
                                                            @foreach ($questionnaire_response_type_options as $typeKey => $typeLabel)
                                                                <option value="{{ $typeKey }}" @selected($item['response_type'] === $typeKey)>{{ $typeLabel }}</option>
                                                            @endforeach
                                                        </select>
                                                        <select class="field-select" name="attachment_mode">
                                                            @foreach ($questionnaire_attachment_mode_options as $modeKey => $modeLabel)
                                                                <option value="{{ $modeKey }}" @selected($item['attachment_mode'] === $modeKey)>{{ $modeLabel }}</option>
                                                            @endforeach
                                                        </select>
                                                        <select class="field-select" name="attachment_upload_profile">
                                                            <option value="">{{ __('Default attachment profile') }}</option>
                                                            @foreach ($questionnaire_attachment_upload_profile_options as $profileKey => $profileLabel)
                                                                <option value="{{ $profileKey }}" @selected($item['attachment_upload_profile'] === $profileKey)>{{ $profileLabel }}</option>
                                                            @endforeach
                                                        </select>
                                                        <select class="field-select" name="response_status" required>
                                                            @foreach ($questionnaire_response_status_options as $statusKey => $statusLabel)
                                                                <option value="{{ $statusKey }}" @selected($item['response_status'] === $statusKey)>{{ $statusLabel }}</option>
                                                            @endforeach
                                                        </select>
                                                        <input class="field-input" type="text" name="answer_text" value="{{ $item['answer_text'] }}" placeholder="{{ __('Answer') }}">
                                                        <input class="field-input" type="text" name="follow_up_notes" value="{{ $item['follow_up_notes'] }}" placeholder="{{ __('Follow-up') }}">
                                                        <label class="table-note" style="display:flex; gap:8px; align-items:center;">
                                                            <input type="checkbox" name="save_to_answer_library" value="1">
                                                            <span>{{ __('Save this answer to the questionnaire library') }}</span>
                                                        </label>
                                                        <label class="table-note" style="display:flex; gap:8px; align-items:center;">
                                                            <input type="checkbox" name="promote_attachments_to_evidence" value="1" @checked($item['promote_attachments_to_evidence'] === '1')>
                                                            <span>{{ __('Allow attachment promotion to evidence') }}</span>
                                                        </label>
                                                        <button class="button button-secondary" type="submit">{{ __('Update question') }}</button>
                                                    </form>
                                                </details>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <span class="muted-note">{{ __('No questionnaire items yet') }}</span>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">{{ __('Linked records') }}</div>
                    <div class="table-note" style="margin-top:10px;">{{ __('Asset') }}: {{ $review['linked_asset_label'] ?? ($review['linked_asset_id'] !== '' ? $review['linked_asset_id'] : __('None')) }}</div>
                    <div class="table-note">{{ __('Control') }}: {{ $review['linked_control_label'] ?? ($review['linked_control_id'] !== '' ? $review['linked_control_id'] : __('None')) }}</div>
                    <div class="table-note">{{ __('Risk') }}: {{ $review['linked_risk_label'] ?? ($review['linked_risk_id'] !== '' ? $review['linked_risk_id'] : __('None')) }}</div>
                    <div class="table-note">{{ __('Finding') }}: {{ $review['linked_finding_label'] ?? ($review['linked_finding_id'] !== '' ? $review['linked_finding_id'] : __('None')) }}</div>

                    <div class="metric-label" style="margin-top:16px;">{{ __('Owners') }}</div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($review['owner_assignments'] as $owner)
                            <div class="data-item">
                                <div class="entity-title">{{ $owner['display_name'] }}</div>
                                <div class="table-note">{{ $owner['kind'] }}</div>
                                @if ($can_manage_vendors)
                                    <form method="POST" action="{{ str_replace('__ASSIGNMENT__', $owner['assignment_id'], $review['owner_remove_route']) }}" style="margin-top:8px;">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="menu" value="plugin.third-party-risk.root">
                                        <input type="hidden" name="vendor_id" value="{{ $selected_vendor['id'] }}">
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

                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">{{ __('Status') }}</div>
                    <div class="table-note" style="margin-top:10px;">{{ __('Vendor status: :status', ['status' => $selected_vendor['vendor_status_label'] ?? ucfirst($selected_vendor['vendor_status'])]) }}</div>
                    <div class="table-note">{{ __('Current review state: :state', ['state' => $review['state_label']]) }}</div>
                    <div class="table-note">{{ __('Questionnaire open items: :count', ['count' => $review['open_questionnaire_count']]) }}</div>
                    <div class="table-note">{{ __('Accepted answers: :count', ['count' => collect($review['questionnaire_items'])->where('response_status', 'accepted')->count()]) }}</div>
                    <div class="table-note">{{ __('Open remediation actions: :count', ['count' => $review['open_action_count']]) }}</div>
                    <div class="table-note">{{ __('External links: :count', ['count' => count($review['external_links'])]) }}</div>
                    <div class="table-note">{{ __('Reassessment posture') }}:
                        @if ($review['is_overdue'])
                            {{ __('Overdue') }}
                        @elseif ($review['is_due_soon'])
                            {{ __('Due soon') }}
                        @elseif ($review['next_review_due_on'] !== '')
                            {{ __('Scheduled') }}
                        @else
                            {{ __('Not scheduled') }}
                        @endif
                    </div>
                </div>
            </div>

            <div class="surface-card" style="padding:14px;">
                <div class="row-between">
                    <div class="metric-label">{{ __('Findings and remediation') }}</div>
                    @if (is_array($review['linked_finding'] ?? null))
                        <a class="button button-ghost" href="{{ $review['linked_finding']['open_url'] }}">{{ __('Open linked finding') }}</a>
                    @endif
                </div>

                @if (is_array($review['linked_finding'] ?? null))
                    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr)); margin-top:10px;">
                        <div class="surface-card" style="padding:14px;">
                            <div class="entity-title">{{ $review['linked_finding']['title'] }}</div>
                            <div class="table-note">{{ $review['linked_finding']['severity_label'] }} {{ __('severity') }} · {{ __('Due') }} {{ $review['linked_finding']['due_on'] !== '' ? $review['linked_finding']['due_on'] : __('Not set') }}</div>
                            <div class="table-note" style="margin-top:10px;">{{ $review['linked_finding']['description'] }}</div>
                            <div class="table-note" style="margin-top:10px;">{{ __('Open remediation actions: :count', ['count' => $review['linked_finding']['open_action_count']]) }}</div>
                        </div>
                        <div class="surface-card" style="padding:14px;">
                            <div class="metric-label">{{ __('Remediation actions') }}</div>
                            <div class="data-stack" style="margin-top:10px;">
                                @forelse ($review['linked_finding']['actions'] as $action)
                                    <div class="data-item">
                                        <div class="entity-title">{{ $action['title'] }}</div>
                                        <div class="table-note">{{ $action['status_label'] }}@if($action['due_on'] !== '') · {{ __('Due') }} {{ $action['due_on'] }}@endif</div>
                                        <div class="table-note">{{ $action['notes'] !== '' ? $action['notes'] : __('No action notes yet.') }}</div>
                                    </div>
                                @empty
                                    <span class="muted-note">{{ __('No remediation actions linked yet') }}</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @else
                    <span class="muted-note" style="margin-top:10px; display:block;">{{ __('No linked finding or remediation follow-up is connected to this review yet.') }}</span>
                @endif
            </div>

            <div class="surface-card" style="padding:14px;">
                <div class="row-between">
                    <div class="metric-label">{{ __('Brokered collection') }}</div>
                    <span class="table-note">{{ __('Internal collection without granting external portal access') }}</span>
                </div>

                @if ($can_manage_vendors)
                    <details style="margin-top:10px;">
                        <summary class="button button-ghost" style="display:inline-flex;">{{ __('Create brokered request') }}</summary>
                        <form class="upload-form" method="POST" action="{{ $review['brokered_request_issue_route'] }}" style="margin-top:10px;">
                            @csrf
                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                            <input type="hidden" name="menu" value="plugin.third-party-risk.root">
                            <input type="hidden" name="vendor_id" value="{{ $selected_vendor['id'] }}">
                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                                <div class="field">
                                    <label class="field-label">{{ __('Contact name') }}</label>
                                    <input class="field-input" name="contact_name" required>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Contact email') }}</label>
                                    <input class="field-input" type="email" name="contact_email">
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Collection channel') }}</label>
                                    <select class="field-select" name="collection_channel">
                                        <option value="email">{{ __('Email') }}</option>
                                        <option value="meeting">{{ __('Meeting') }}</option>
                                        <option value="call">{{ __('Call') }}</option>
                                        <option value="uploaded-docs">{{ __('Uploaded docs') }}</option>
                                        <option value="broker-note">{{ __('Broker note') }}</option>
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Broker principal') }}</label>
                                    <input class="field-input" name="broker_principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                </div>
                                <div class="field" style="grid-column:1 / -1;">
                                    <label class="field-label">{{ __('Instructions') }}</label>
                                    <textarea class="field-textarea" name="instructions" rows="4" placeholder="{{ __('Describe what should be collected off-platform and how the broker should confirm it.') }}"></textarea>
                                </div>
                            </div>
                            <div class="action-cluster" style="margin-top:14px;">
                                <button class="button button-secondary" type="submit">{{ __('Create brokered request') }}</button>
                            </div>
                        </form>
                    </details>
                @endif

                <div class="data-stack" style="margin-top:12px;">
                    @forelse ($review['brokered_requests'] as $brokeredRequest)
                        <div class="data-item">
                            <div class="row-between" style="align-items:flex-start; gap:12px;">
                                <div>
                                    <div class="entity-title">{{ $brokeredRequest['contact_name'] }}</div>
                                    @if ($brokeredRequest['contact_email'] !== '')
                                        <div class="table-note">{{ $brokeredRequest['contact_email'] }}</div>
                                    @endif
                                    <div class="table-note">{{ $brokeredRequest['collection_channel_label'] }} · {{ $brokeredRequest['collection_status_label'] }}</div>
                                    <div class="table-note">{{ __('Broker') }}: {{ $brokeredRequest['broker_principal_id'] !== '' ? $brokeredRequest['broker_principal_id'] : __('Not assigned') }}</div>
                                    <div class="table-note">{{ __('Requested') }}: {{ $brokeredRequest['requested_at'] !== '' ? $brokeredRequest['requested_at'] : __('Not recorded') }}</div>
                                    @if ($brokeredRequest['submitted_at'] !== '')
                                        <div class="table-note">{{ __('Submitted') }}: {{ $brokeredRequest['submitted_at'] }}</div>
                                    @endif
                                    @if ($brokeredRequest['completed_at'] !== '')
                                        <div class="table-note">{{ __('Completed') }}: {{ $brokeredRequest['completed_at'] }}</div>
                                    @endif
                                    @if ($brokeredRequest['cancelled_at'] !== '')
                                        <div class="table-note">{{ __('Cancelled') }}: {{ $brokeredRequest['cancelled_at'] }}</div>
                                    @endif
                                    @if ($brokeredRequest['instructions'] !== '')
                                        <div class="table-note">{{ __('Instructions') }}: {{ $brokeredRequest['instructions'] }}</div>
                                    @endif
                                    @if ($brokeredRequest['broker_notes'] !== '')
                                        <div class="table-note">{{ __('Broker notes') }}: {{ $brokeredRequest['broker_notes'] }}</div>
                                    @endif
                                </div>
                                @if ($can_manage_vendors)
                                    <form method="POST" action="{{ $brokeredRequest['update_route'] }}" style="min-width:240px;">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="menu" value="plugin.third-party-risk.root">
                                        <input type="hidden" name="vendor_id" value="{{ $selected_vendor['id'] }}">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <div class="field">
                                        <label class="field-label">{{ __('Status') }}</label>
                                            <select class="field-select" name="collection_status">
                                                @foreach (['queued' => __('Queued'), 'in-progress' => __('In progress'), 'submitted' => __('Submitted'), 'completed' => __('Completed'), 'cancelled' => __('Cancelled')] as $statusKey => $statusLabel)
                                                    <option value="{{ $statusKey }}" @selected($brokeredRequest['collection_status'] === $statusKey)>{{ $statusLabel }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field" style="margin-top:8px;">
                                            <label class="field-label">{{ __('Broker notes') }}</label>
                                            <textarea class="field-textarea" name="broker_notes" rows="3">{{ $brokeredRequest['broker_notes'] }}</textarea>
                                        </div>
                                        <div class="action-cluster" style="margin-top:10px;">
                                            <button class="button button-ghost" type="submit">{{ __('Save broker update') }}</button>
                                        </div>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <span class="muted-note">{{ __('No brokered collection requests yet') }}</span>
                    @endforelse
                </div>
            </div>

            <div class="surface-card" style="padding:14px;">
                <div class="row-between">
                    <div class="metric-label">{{ __('External collaboration') }}</div>
                    <span class="table-note">{{ __('Object-scoped portal access for this review only') }}</span>
                </div>

                @if (session('third_party_risk_external_portal_url'))
                    <div class="surface-note" style="margin-top:10px;">
                        {{ __('New portal link for :email:', ['email' => session('third_party_risk_external_portal_email')]) }}
                        <div style="margin-top:8px; word-break:break-all;">
                            <a href="{{ session('third_party_risk_external_portal_url') }}" target="_blank" rel="noreferrer">{{ session('third_party_risk_external_portal_url') }}</a>
                        </div>
                    </div>
                @endif

                @if ($can_manage_vendors)
                    <details style="margin-top:10px;">
                        <summary class="button button-ghost" style="display:inline-flex;">{{ __('Issue external review link') }}</summary>
                        <form class="upload-form" method="POST" action="{{ $review['external_link_issue_route'] }}" style="margin-top:10px;">
                            @csrf
                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                            <input type="hidden" name="menu" value="plugin.third-party-risk.root">
                            <input type="hidden" name="vendor_id" value="{{ $selected_vendor['id'] }}">
                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                                <div class="field">
                                    <label class="field-label">{{ __('Contact name') }}</label>
                                    <input class="field-input" name="contact_name">
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Contact email') }}</label>
                                    <input class="field-input" type="email" name="contact_email" required>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Expires at') }}</label>
                                    <input class="field-input" type="datetime-local" name="expires_at">
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Permissions') }}</label>
                                    <label style="display:flex; gap:8px; align-items:center; margin-top:6px;">
                                        <input type="checkbox" name="can_answer_questionnaire" value="1" checked>
                                        <span>{{ __('Answer questionnaire') }}</span>
                                    </label>
                                    <label style="display:flex; gap:8px; align-items:center; margin-top:6px;">
                                        <input type="checkbox" name="can_upload_artifacts" value="1" checked>
                                        <span>{{ __('Upload evidence') }}</span>
                                    </label>
                                    <label style="display:flex; gap:8px; align-items:center; margin-top:6px;">
                                        <input type="checkbox" name="send_email_invitation" value="1">
                                        <span>{{ __('Send email invitation now') }}</span>
                                    </label>
                                    <span class="table-note" style="display:block; margin-top:8px;">{{ __('Uses the organization SMTP connector from Notifications &amp; Delivery when enabled.') }}</span>
                                </div>
                            </div>
                            <div class="action-cluster" style="margin-top:14px;">
                                <button class="button button-secondary" type="submit">{{ __('Issue link') }}</button>
                            </div>
                        </form>
                    </details>
                @endif

                <div class="surface-card" style="padding:12px; margin-top:12px;">
                    <div class="row-between">
                        <div class="entity-title">{{ __('External collaborators') }}</div>
                        <span class="table-note">{{ __(':count tracked', ['count' => count($review['external_collaborators'])]) }}</span>
                    </div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($review['external_collaborators'] as $collaborator)
                            <div class="data-item">
                                <div class="row-between" style="align-items:flex-start; gap:12px;">
                                    <div>
                                        <div class="entity-title">{{ $collaborator['contact_name'] !== '' ? $collaborator['contact_name'] : $collaborator['contact_email'] }}</div>
                                        <div class="table-note">{{ $collaborator['contact_email'] }}</div>
                                        <div class="table-note">{{ __('Lifecycle') }}: {{ $collaborator['lifecycle_state_label'] }}</div>
                                        <div class="table-note">{{ __('Last link') }}: {{ $collaborator['last_link_issued_at'] !== '' ? $collaborator['last_link_issued_at'] : __('No links issued yet') }}</div>
                                        @if ($collaborator['blocked_at'] !== '')
                                            <div class="table-note">{{ __('Blocked at') }}: {{ $collaborator['blocked_at'] }}</div>
                                        @endif
                                    </div>
                                    @if ($can_manage_vendors)
                                        <form method="POST" action="{{ $collaborator['lifecycle_update_route'] }}" style="min-width:220px;">
                                            @csrf
                                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                            <input type="hidden" name="menu" value="plugin.third-party-risk.root">
                                            <input type="hidden" name="vendor_id" value="{{ $selected_vendor['id'] }}">
                                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                            <div class="field">
                                                <label class="field-label">{{ __('Lifecycle state') }}</label>
                                                <select class="field-select" name="lifecycle_state">
                                                    @foreach ($collaboration_collaborator_lifecycle_state_options as $stateKey => $stateLabel)
                                                        <option value="{{ $stateKey }}" @selected($collaborator['lifecycle_state'] === $stateKey)>{{ $stateLabel }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="action-cluster" style="margin-top:8px;">
                                                <button class="button button-ghost" type="submit">{{ __('Update state') }}</button>
                                            </div>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <span class="muted-note">{{ __('No external collaborators yet') }}</span>
                        @endforelse
                    </div>
                </div>

                <div class="data-stack" style="margin-top:12px;">
                    @forelse ($review['external_links'] as $externalLink)
                        @php
                            $deliveryLabel = [
                                'manual-only' => __('Manual only'),
                                'sent' => __('Email sent'),
                                'failed' => __('Email failed'),
                                'not-configured' => __('Email not configured'),
                            ][$externalLink['email_delivery_status']] ?? ucfirst(str_replace('-', ' ', $externalLink['email_delivery_status']));
                        @endphp
                        <div class="data-item">
                            <div class="row-between" style="align-items:flex-start; gap:12px;">
                                <div>
                                    <div class="entity-title">{{ $externalLink['contact_name'] !== '' ? $externalLink['contact_name'] : $externalLink['contact_email'] }}</div>
                                    <div class="table-note">{{ $externalLink['contact_email'] }}</div>
                                    <div class="table-note">
                                        {{ $externalLink['can_answer_questionnaire'] === '1' ? __('Questionnaire') : __('Read-only') }}
                                        @if ($externalLink['can_upload_artifacts'] === '1')
                                            · {{ __('Uploads') }}
                                        @endif
                                    </div>
                                    <div class="table-note">{{ __('Expires') }}: {{ $externalLink['expires_at'] !== '' ? $externalLink['expires_at'] : __('No expiry') }}</div>
                                    <div class="table-note">{{ __('Last accessed') }}: {{ $externalLink['last_accessed_at'] !== '' ? $externalLink['last_accessed_at'] : __('Never') }}</div>
                                    <div class="table-note">{{ __('Delivery') }}: {{ $deliveryLabel }}</div>
                                    <div class="table-note">{{ __('Email sent') }}: {{ $externalLink['email_sent_at'] !== '' ? $externalLink['email_sent_at'] : __('Not sent') }}</div>
                                    @if ($externalLink['email_delivery_error'] !== '')
                                        <div class="table-note">{{ __('Delivery error') }}: {{ $externalLink['email_delivery_error'] }}</div>
                                    @endif
                                    <div class="table-note">{{ __('Collaborator state') }}: {{ $externalLink['collaborator_lifecycle_state_label'] }}</div>
                                    <div class="table-note">{{ __('State') }}: {{ $externalLink['revoked_at'] === '' ? __('Active') : __('Revoked') }}</div>
                                </div>
                                @if ($can_manage_vendors && $externalLink['revoked_at'] === '')
                                    <form method="POST" action="{{ $externalLink['revoke_route'] }}">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="menu" value="plugin.third-party-risk.root">
                                        <input type="hidden" name="vendor_id" value="{{ $selected_vendor['id'] }}">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <button class="button button-ghost" type="submit">{{ __('Revoke') }}</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <span class="muted-note">{{ __('No external collaboration links yet') }}</span>
                    @endforelse
                </div>
            </div>

            <div class="surface-card" style="padding:14px;">
                <div class="row-between">
                    <div class="metric-label">{{ __('Timeline and activity') }}</div>
                    <span class="table-note">{{ __('Unified review activity across workflow, evidence, questionnaires, and external collaboration.') }}</span>
                </div>

                <div class="vendor-timeline" style="margin-top:12px;">
                    @forelse ($review['activity_timeline'] as $activity)
                        <div class="vendor-timeline-item">
                            <div class="vendor-timeline-top">
                                <div class="entity-title">{{ $activity['title'] }}</div>
                                <div class="vendor-timeline-time">{{ $activity['at'] }}</div>
                            </div>
                            @if ($activity['detail'] !== '')
                                <div class="table-note">{{ $activity['detail'] }}</div>
                            @endif
                        </div>
                    @empty
                        <span class="muted-note">{{ __('No review activity recorded yet.') }}</span>
                    @endforelse
                </div>
            </div>

            <div class="surface-card" style="padding:14px;">
                <div class="row-between">
                    <div class="metric-label">{{ __('Collaboration') }}</div>
                    <span class="table-note">{{ __('Discussion notes and tracked follow-up work for this review.') }}</span>
                </div>

                <div class="vendor-request-meta" style="margin-top:10px;">
                    <span class="vendor-request-chip">{{ __('Shared drafts') }}: {{ count($review['collaboration_drafts']) }}</span>
                    <span class="vendor-request-chip">{{ __('Draft assignment cues') }}: {{ $review['collaboration_draft_assignment_cue_count'] }}</span>
                    <span class="vendor-request-chip">{{ __('Draft mention cues') }}: {{ $review['collaboration_draft_mention_cue_count'] }}</span>
                    <span class="vendor-request-chip">{{ __('Assignment cues') }}: {{ $review['collaboration_assignment_cue_count'] }}</span>
                    <span class="vendor-request-chip">{{ __('Mention cues') }}: {{ $review['collaboration_mention_cue_count'] }}</span>
                </div>

                <div class="surface-card" style="padding:14px; margin-top:12px;">
                    <div class="row-between">
                        <div class="entity-title">{{ __('Shared drafts') }}</div>
                        <span class="table-note">{{ __('Shared continuity between users before publishing comments or follow-up requests.') }}</span>
                    </div>

                    @if ($can_manage_vendors)
                        <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr)); margin-top:12px;">
                            <div class="surface-card" style="padding:14px;">
                                <div class="entity-title">{{ __('Comment draft') }}</div>
                                <form class="upload-form" method="POST" action="{{ $review['collaboration_draft_store_route'] }}" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.third-party-risk.root">
                                    <input type="hidden" name="vendor_id" value="{{ $selected_vendor['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <input type="hidden" name="draft_type" value="comment">
                                    <div class="field">
                                        <label class="field-label">{{ __('Draft body') }}</label>
                                        <textarea class="field-textarea" name="body" rows="4" required></textarea>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Mention actors') }}</label>
                                        <select class="field-select" name="mentioned_actor_ids[]" multiple size="4">
                                            @foreach ($owner_actor_options as $actor)
                                                <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="action-cluster" style="margin-top:10px;">
                                        <button class="button button-ghost" type="submit">{{ __('Save shared draft') }}</button>
                                    </div>
                                </form>
                            </div>

                            <div class="surface-card" style="padding:14px;">
                                <div class="entity-title">{{ __('Follow-up draft') }}</div>
                                <form class="upload-form" method="POST" action="{{ $review['collaboration_draft_store_route'] }}" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.third-party-risk.root">
                                    <input type="hidden" name="vendor_id" value="{{ $selected_vendor['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <input type="hidden" name="draft_type" value="request">
                                    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                                        <div class="field" style="grid-column:1 / -1;">
                                            <label class="field-label">{{ __('Title') }}</label>
                                            <input class="field-input" name="title" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">{{ __('Priority') }}</label>
                                            <select class="field-select" name="priority">
                                                @foreach ($collaboration_request_priority_options as $priorityKey => $priorityLabel)
                                                    <option value="{{ $priorityKey }}">{{ $priorityLabel }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">{{ __('Handoff stage') }}</label>
                                            <select class="field-select" name="handoff_state">
                                                @foreach ($collaboration_request_handoff_state_options as $handoffStateKey => $handoffStateLabel)
                                                    <option value="{{ $handoffStateKey }}" @selected($handoffStateKey === 'review')>{{ $handoffStateLabel }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">{{ __('Mention actors') }}</label>
                                            <select class="field-select" name="mentioned_actor_ids[]" multiple size="4">
                                                @foreach ($owner_actor_options as $actor)
                                                    <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">{{ __('Assigned actor') }}</label>
                                            <select class="field-select" name="assigned_actor_id">
                                                <option value="">{{ __('No assignee') }}</option>
                                                @foreach ($owner_actor_options as $actor)
                                                    <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">{{ __('Due on') }}</label>
                                            <input class="field-input" type="date" name="due_on">
                                        </div>
                                        <div class="field" style="grid-column:1 / -1;">
                                            <label class="field-label">{{ __('Details') }}</label>
                                            <textarea class="field-textarea" name="details" rows="4"></textarea>
                                        </div>
                                    </div>
                                    <div class="action-cluster" style="margin-top:10px;">
                                        <button class="button button-ghost" type="submit">{{ __('Save shared draft') }}</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif

                    <div class="data-stack" style="margin-top:12px;">
                        @forelse ($review['collaboration_drafts'] as $draft)
                            @php $draftPriorityClass = 'vendor-request-priority-' . $draft['priority']; @endphp
                            <div class="data-item">
                                <div class="row-between" style="align-items:flex-start; gap:12px;">
                                    <div class="entity-title">{{ $draft['draft_type'] === 'request' ? ($draft['title'] !== '' ? $draft['title'] : $draft['draft_type_label']) : $draft['draft_type_label'] }}</div>
                                    <div class="table-note">{{ __('Last updated') }} {{ $draft['updated_at'] }}</div>
                                </div>
                                <div class="vendor-request-meta">
                                    <span class="vendor-request-chip">{{ $draft['draft_type_label'] }}</span>
                                    @if ($draft['draft_type'] === 'request')
                                        <span class="vendor-request-chip {{ $draftPriorityClass }}">{{ $draft['priority_label'] }}</span>
                                        <span class="vendor-request-chip">{{ __('Stage') }}: {{ $draft['handoff_state_label'] }}</span>
                                    @endif
                                    @if ($draft['has_assignment_cue'])
                                        <span class="vendor-request-chip">{{ __('Assigned to one of your actors') }}</span>
                                    @endif
                                    @if ($draft['has_mention_cue'])
                                        <span class="vendor-request-chip">{{ __('Mention cue') }}</span>
                                    @endif
                                    @if ($draft['assigned_actor_label'] !== '')
                                        <span class="vendor-request-chip">{{ __('Owner') }}: {{ $draft['assigned_actor_label'] }}</span>
                                    @endif
                                    @foreach ($draft['mentioned_actor_labels'] as $label)
                                        <span class="vendor-request-chip">{{ __('Mention') }}: {{ $label }}</span>
                                    @endforeach
                                    @if ($draft['due_on'] !== '')
                                        <span class="vendor-request-chip">{{ __('Due') }} {{ $draft['due_on'] }}</span>
                                    @endif
                                </div>
                                <div class="table-note" style="margin-top:8px;">{{ __('Edited by') }} {{ $draft['edited_by_principal_id'] !== '' ? $draft['edited_by_principal_id'] : __('System') }}</div>
                                @if ($draft['draft_type'] === 'comment')
                                    <div class="vendor-comment-body" style="margin-top:8px;">{{ $draft['body'] }}</div>
                                @elseif ($draft['details'] !== '')
                                    <div class="table-note" style="margin-top:8px;">{{ $draft['details'] }}</div>
                                @endif
                                @if ($can_manage_vendors)
                                    <div class="action-cluster" style="margin-top:10px;">
                                        @if ($draft['draft_type'] === 'comment')
                                            <form method="POST" action="{{ $draft['promote_comment_route'] }}">
                                                @csrf
                                                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                                <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                                <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                                <input type="hidden" name="menu" value="plugin.third-party-risk.root">
                                                <input type="hidden" name="vendor_id" value="{{ $selected_vendor['id'] }}">
                                                <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                                <button class="button button-secondary" type="submit">{{ __('Publish comment') }}</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ $draft['promote_request_route'] }}">
                                                @csrf
                                                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                                <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                                <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                                <input type="hidden" name="menu" value="plugin.third-party-risk.root">
                                                <input type="hidden" name="vendor_id" value="{{ $selected_vendor['id'] }}">
                                                <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                                <button class="button button-secondary" type="submit">{{ __('Publish follow-up request') }}</button>
                                            </form>
                                        @endif
                                    </div>
                                    <details style="margin-top:10px;">
                                        <summary class="button button-ghost" style="display:inline-flex;">{{ __('Update shared draft') }}</summary>
                                        <form class="upload-form" method="POST" action="{{ $draft['update_route'] }}" style="margin-top:10px;">
                                            @csrf
                                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                            <input type="hidden" name="menu" value="plugin.third-party-risk.root">
                                            <input type="hidden" name="vendor_id" value="{{ $selected_vendor['id'] }}">
                                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                            <input type="hidden" name="draft_type" value="{{ $draft['draft_type'] }}">
                                            @if ($draft['draft_type'] === 'comment')
                                                <div class="field">
                                                    <label class="field-label">{{ __('Draft body') }}</label>
                                                    <textarea class="field-textarea" name="body" rows="4" required>{{ $draft['body'] }}</textarea>
                                                </div>
                                            @else
                                                <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                                                    <div class="field" style="grid-column:1 / -1;">
                                                        <label class="field-label">{{ __('Title') }}</label>
                                                        <input class="field-input" name="title" value="{{ $draft['title'] }}" required>
                                                    </div>
                                                    <div class="field">
                                                        <label class="field-label">{{ __('Priority') }}</label>
                                                        <select class="field-select" name="priority">
                                                            @foreach ($collaboration_request_priority_options as $priorityKey => $priorityLabel)
                                                                <option value="{{ $priorityKey }}" @selected($draft['priority'] === $priorityKey)>{{ $priorityLabel }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="field">
                                                        <label class="field-label">{{ __('Handoff stage') }}</label>
                                                        <select class="field-select" name="handoff_state">
                                                            @foreach ($collaboration_request_handoff_state_options as $handoffStateKey => $handoffStateLabel)
                                                                <option value="{{ $handoffStateKey }}" @selected($draft['handoff_state'] === $handoffStateKey)>{{ $handoffStateLabel }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="field">
                                                        <label class="field-label">{{ __('Assigned actor') }}</label>
                                                        <select class="field-select" name="assigned_actor_id">
                                                            <option value="">{{ __('No assignee') }}</option>
                                                            @foreach ($owner_actor_options as $actor)
                                                                <option value="{{ $actor['id'] }}" @selected($draft['assigned_actor_id'] === $actor['id'])>{{ $actor['label'] }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="field">
                                                        <label class="field-label">{{ __('Due on') }}</label>
                                                        <input class="field-input" type="date" name="due_on" value="{{ $draft['due_on'] }}">
                                                    </div>
                                                    <div class="field" style="grid-column:1 / -1;">
                                                        <label class="field-label">{{ __('Details') }}</label>
                                                        <textarea class="field-textarea" name="details" rows="4">{{ $draft['details'] }}</textarea>
                                                    </div>
                                                </div>
                                            @endif
                                            <div class="field">
                                                <label class="field-label">{{ __('Mention actors') }}</label>
                                                <select class="field-select" name="mentioned_actor_ids[]" multiple size="4">
                                                    @foreach ($owner_actor_options as $actor)
                                                        <option value="{{ $actor['id'] }}" @selected(in_array($actor['id'], $draft['mentioned_actor_ids_list'], true))>{{ $actor['label'] }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="action-cluster" style="margin-top:10px;">
                                                <button class="button button-ghost" type="submit">{{ __('Save draft update') }}</button>
                                            </div>
                                        </form>
                                    </details>
                                @endif
                            </div>
                        @empty
                            <span class="muted-note">{{ __('No shared drafts yet') }}</span>
                        @endforelse
                    </div>
                </div>

                <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr)); margin-top:12px;">
                    <div class="surface-card" style="padding:14px;">
                        <div class="row-between">
                            <div class="entity-title">{{ __('Comments') }}</div>
                            <span class="table-note">{{ __(':count recorded', ['count' => count($review['collaboration_comments'])]) }}</span>
                        </div>

                        @if ($can_manage_vendors)
                            <details style="margin-top:10px;">
                                <summary class="button button-ghost" style="display:inline-flex;">{{ __('Add comment') }}</summary>
                                <form class="upload-form" method="POST" action="{{ $review['collaboration_comment_store_route'] }}" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.third-party-risk.root">
                                    <input type="hidden" name="vendor_id" value="{{ $selected_vendor['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <div class="field">
                                        <label class="field-label">{{ __('Comment') }}</label>
                                        <textarea class="field-textarea" name="body" rows="4" required></textarea>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Mention actors') }}</label>
                                        <select class="field-select" name="mentioned_actor_ids[]" multiple size="4">
                                            @foreach ($owner_actor_options as $actor)
                                                <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="action-cluster" style="margin-top:10px;">
                                        <button class="button button-secondary" type="submit">{{ __('Save comment') }}</button>
                                    </div>
                                </form>
                            </details>
                        @endif

                        <div class="data-stack" style="margin-top:12px;">
                            @forelse ($review['collaboration_comments'] as $comment)
                                <div class="data-item">
                                    <div class="row-between" style="align-items:flex-start; gap:12px;">
                                        <div class="table-note">{{ $comment['author_principal_id'] !== '' ? $comment['author_principal_id'] : __('System') }}</div>
                                        <div class="table-note">{{ $comment['created_at'] }}</div>
                                    </div>
                                    @if ($comment['has_mention_cue'])
                                        <div class="table-note" style="margin-top:6px;">{{ __('Mention cue for one of your linked actors.') }}</div>
                                    @endif
                                    @if ($comment['mentioned_actor_labels'] !== [])
                                        <div class="vendor-request-meta">
                                            @foreach ($comment['mentioned_actor_labels'] as $label)
                                                <span class="vendor-request-chip">{{ __('Mention') }}: {{ $label }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                    <div class="vendor-comment-body" style="margin-top:8px;">{{ $comment['body'] }}</div>
                                </div>
                            @empty
                                <span class="muted-note">{{ __('No collaboration comments yet') }}</span>
                            @endforelse
                        </div>
                    </div>

                    <div class="surface-card" style="padding:14px;">
                        <div class="row-between">
                            <div class="entity-title">{{ __('Follow-up requests') }}</div>
                            <span class="table-note">{{ __(':count tracked', ['count' => count($review['collaboration_requests'])]) }}</span>
                        </div>

                        @if ($can_manage_vendors)
                            <details style="margin-top:10px;">
                                <summary class="button button-ghost" style="display:inline-flex;">{{ __('Create follow-up request') }}</summary>
                                <form class="upload-form" method="POST" action="{{ $review['collaboration_request_store_route'] }}" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.third-party-risk.root">
                                    <input type="hidden" name="vendor_id" value="{{ $selected_vendor['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                                        <div class="field" style="grid-column:1 / -1;">
                                            <label class="field-label">{{ __('Title') }}</label>
                                            <input class="field-input" name="title" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">{{ __('Priority') }}</label>
                                            <select class="field-select" name="priority">
                                                @foreach ($collaboration_request_priority_options as $priorityKey => $priorityLabel)
                                                    <option value="{{ $priorityKey }}">{{ $priorityLabel }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">{{ __('Status') }}</label>
                                            <select class="field-select" name="status">
                                                @foreach ($collaboration_request_status_options as $statusKey => $statusLabel)
                                                    <option value="{{ $statusKey }}" @selected($statusKey === 'open')>{{ $statusLabel }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                                <label class="field-label">{{ __('Handoff stage') }}</label>
                                            <select class="field-select" name="handoff_state">
                                                @foreach ($collaboration_request_handoff_state_options as $handoffStateKey => $handoffStateLabel)
                                                    <option value="{{ $handoffStateKey }}" @selected($handoffStateKey === 'review')>{{ $handoffStateLabel }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                                <label class="field-label">{{ __('Mention actors') }}</label>
                                            <select class="field-select" name="mentioned_actor_ids[]" multiple size="4">
                                                @foreach ($owner_actor_options as $actor)
                                                    <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                                <label class="field-label">{{ __('Assigned actor') }}</label>
                                            <select class="field-select" name="assigned_actor_id">
                                                    <option value="">{{ __('No assignee') }}</option>
                                                @foreach ($owner_actor_options as $actor)
                                                    <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                                <label class="field-label">{{ __('Due on') }}</label>
                                            <input class="field-input" type="date" name="due_on">
                                        </div>
                                        <div class="field" style="grid-column:1 / -1;">
                                                <label class="field-label">{{ __('Details') }}</label>
                                            <textarea class="field-textarea" name="details" rows="4"></textarea>
                                        </div>
                                    </div>
                                    <div class="action-cluster" style="margin-top:10px;">
                                        <button class="button button-secondary" type="submit">{{ __('Create request') }}</button>
                                    </div>
                                </form>
                            </details>
                        @endif

                        <div class="data-stack" style="margin-top:12px;">
                            @forelse ($review['collaboration_requests'] as $collaborationRequest)
                                @php $requestPriorityClass = 'vendor-request-priority-' . $collaborationRequest['priority']; @endphp
                                <div class="data-item">
                                    <div class="entity-title">{{ $collaborationRequest['title'] }}</div>
                                    <div class="vendor-request-meta">
                                        <span class="vendor-request-chip {{ $requestPriorityClass }}">{{ $collaborationRequest['priority_label'] }}</span>
                                        <span class="vendor-request-chip">{{ $collaborationRequest['status_label'] }}</span>
                                        <span class="vendor-request-chip">{{ __('Stage') }}: {{ $collaborationRequest['handoff_state_label'] }}</span>
                                        @if ($collaborationRequest['has_assignment_cue'])
                                            <span class="vendor-request-chip">{{ __('Assigned to one of your actors') }}</span>
                                        @endif
                                        @if ($collaborationRequest['has_mention_cue'])
                                            <span class="vendor-request-chip">{{ __('Mention cue') }}</span>
                                        @endif
                                        @if ($collaborationRequest['assigned_actor_label'] !== '')
                                            <span class="vendor-request-chip">{{ __('Owner') }}: {{ $collaborationRequest['assigned_actor_label'] }}</span>
                                        @endif
                                        @foreach ($collaborationRequest['mentioned_actor_labels'] as $label)
                                            <span class="vendor-request-chip">{{ __('Mention') }}: {{ $label }}</span>
                                        @endforeach
                                        @if ($collaborationRequest['due_on'] !== '')
                                            <span class="vendor-request-chip">{{ __('Due') }} {{ $collaborationRequest['due_on'] }}</span>
                                        @endif
                                    </div>
                                    @if ($collaborationRequest['details'] !== '')
                                        <div class="table-note" style="margin-top:8px;">{{ $collaborationRequest['details'] }}</div>
                                    @endif
                                    <div class="table-note" style="margin-top:8px;">{{ __('Requested by') }} {{ $collaborationRequest['requested_by_principal_id'] !== '' ? $collaborationRequest['requested_by_principal_id'] : __('System') }} {{ __('on') }} {{ $collaborationRequest['created_at'] }}</div>
                                    @if ($collaborationRequest['completed_at'] !== '')
                                        <div class="table-note">{{ __('Completed at') }} {{ $collaborationRequest['completed_at'] }}</div>
                                    @endif
                                    @if ($collaborationRequest['cancelled_at'] !== '')
                                        <div class="table-note">{{ __('Cancelled at') }} {{ $collaborationRequest['cancelled_at'] }}</div>
                                    @endif
                                    @if ($can_manage_vendors)
                                        <details style="margin-top:10px;">
                                            <summary class="button button-ghost" style="display:inline-flex;">{{ __('Update follow-up request') }}</summary>
                                            <form class="upload-form" method="POST" action="{{ $collaborationRequest['update_route'] }}" style="margin-top:10px;">
                                                @csrf
                                                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                                <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                                <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                                <input type="hidden" name="menu" value="plugin.third-party-risk.root">
                                                <input type="hidden" name="vendor_id" value="{{ $selected_vendor['id'] }}">
                                                <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                                <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                                                    <div class="field" style="grid-column:1 / -1;">
                                                        <label class="field-label">{{ __('Title') }}</label>
                                                        <input class="field-input" name="title" value="{{ $collaborationRequest['title'] }}" required>
                                                    </div>
                                                    <div class="field">
                                                        <label class="field-label">{{ __('Priority') }}</label>
                                                        <select class="field-select" name="priority">
                                                            @foreach ($collaboration_request_priority_options as $priorityKey => $priorityLabel)
                                                                <option value="{{ $priorityKey }}" @selected($collaborationRequest['priority'] === $priorityKey)>{{ $priorityLabel }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="field">
                                                        <label class="field-label">{{ __('Status') }}</label>
                                                        <select class="field-select" name="status">
                                                            @foreach ($collaboration_request_status_options as $statusKey => $statusLabel)
                                                                <option value="{{ $statusKey }}" @selected($collaborationRequest['status'] === $statusKey)>{{ $statusLabel }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="field">
                                                        <label class="field-label">{{ __('Handoff stage') }}</label>
                                                        <select class="field-select" name="handoff_state">
                                                            @foreach ($collaboration_request_handoff_state_options as $handoffStateKey => $handoffStateLabel)
                                                                <option value="{{ $handoffStateKey }}" @selected($collaborationRequest['handoff_state'] === $handoffStateKey)>{{ $handoffStateLabel }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="field">
                                                        <label class="field-label">{{ __('Mention actors') }}</label>
                                                        <select class="field-select" name="mentioned_actor_ids[]" multiple size="4">
                                                            @foreach ($owner_actor_options as $actor)
                                                                <option value="{{ $actor['id'] }}" @selected(in_array($actor['id'], $collaborationRequest['mentioned_actor_ids_list'], true))>{{ $actor['label'] }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="field">
                                                        <label class="field-label">{{ __('Assigned actor') }}</label>
                                                        <select class="field-select" name="assigned_actor_id">
                                                            <option value="">{{ __('No assignee') }}</option>
                                                            @foreach ($owner_actor_options as $actor)
                                                                <option value="{{ $actor['id'] }}" @selected($collaborationRequest['assigned_actor_id'] === $actor['id'])>{{ $actor['label'] }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="field">
                                                        <label class="field-label">{{ __('Due on') }}</label>
                                                        <input class="field-input" type="date" name="due_on" value="{{ $collaborationRequest['due_on'] }}">
                                                    </div>
                                                    <div class="field" style="grid-column:1 / -1;">
                                                        <label class="field-label">{{ __('Details') }}</label>
                                                        <textarea class="field-textarea" name="details" rows="4">{{ $collaborationRequest['details'] }}</textarea>
                                                    </div>
                                                </div>
                                                <div class="action-cluster" style="margin-top:10px;">
                                                    <button class="button button-ghost" type="submit">{{ __('Save request update') }}</button>
                                                </div>
                                            </form>
                                        </details>
                                    @endif
                                </div>
                            @empty
                                <span class="muted-note">{{ __('No follow-up requests yet') }}</span>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            @if ($can_manage_vendors)
                <div class="surface-card" style="padding:14px;">
                    <details>
                        <summary class="button button-ghost" style="display:inline-flex; width:fit-content;">{{ __('Edit vendor and review') }}</summary>
                        <form class="upload-form" method="POST" action="{{ $selected_vendor['update_route'] }}" style="margin-top:14px;">
                            @csrf
                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                            <input type="hidden" name="menu" value="plugin.third-party-risk.root">
                            <input type="hidden" name="vendor_id" value="{{ $selected_vendor['id'] }}">
                            <input type="hidden" name="review_id" value="{{ $review['id'] }}">
                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                                <div class="field">
                                    <label class="field-label">{{ __('Vendor legal name') }}</label>
                                    <input class="field-input" name="legal_name" value="{{ $selected_vendor['legal_name'] }}" required>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Tier') }}</label>
                                    <select class="field-select" name="tier" required>
                                        @foreach (['low', 'medium', 'high', 'critical'] as $tier)
                                            <option value="{{ $tier }}" @selected($selected_vendor['tier'] === $tier)>{{ match ($tier) {
                                                'low' => __('Low'),
                                                'medium' => __('Medium'),
                                                'high' => __('High'),
                                                'critical' => __('Critical'),
                                            } }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field" style="grid-column:1 / -1;">
                                    <label class="field-label">{{ __('Service summary') }}</label>
                                    <input class="field-input" name="service_summary" value="{{ $selected_vendor['service_summary'] }}" required>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Website') }}</label>
                                    <input class="field-input" name="website" value="{{ $selected_vendor['website'] }}">
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Scope') }}</label>
                                    <select class="field-select" name="scope_id">
                                        <option value="">{{ __('Organization-wide') }}</option>
                                        @foreach ($scope_options as $scope)
                                            <option value="{{ $scope['id'] }}" @selected($selected_vendor['scope_id'] === $scope['id'])>{{ $scope['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Primary contact') }}</label>
                                    <input class="field-input" name="primary_contact_name" value="{{ $selected_vendor['primary_contact_name'] }}">
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Primary contact email') }}</label>
                                    <input class="field-input" name="primary_contact_email" type="email" value="{{ $selected_vendor['primary_contact_email'] }}">
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Review profile') }}</label>
                                    <select class="field-select" name="review_profile_id">
                                        <option value="">{{ __('No review profile') }}</option>
                                        @foreach ($review_profile_options as $profile)
                                            <option value="{{ $profile['id'] }}" @selected($review['review_profile_id'] === $profile['id'])>{{ $profile['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Questionnaire template') }}</label>
                                    <select class="field-select" name="questionnaire_template_id">
                                        <option value="">{{ __('No questionnaire template') }}</option>
                                        @foreach ($questionnaire_template_options as $template)
                                            <option value="{{ $template['id'] }}" @selected($review['questionnaire_template_id'] === $template['id'])>{{ $template['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Review title') }}</label>
                                    <input class="field-input" name="review_title" value="{{ $review['title'] }}" required>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Inherent risk') }}</label>
                                    <select class="field-select" name="inherent_risk" required>
                                        @foreach (['low', 'medium', 'high', 'critical'] as $risk)
                                            <option value="{{ $risk }}" @selected($review['inherent_risk'] === $risk)>{{ match ($risk) {
                                                'low' => __('Low'),
                                                'medium' => __('Medium'),
                                                'high' => __('High'),
                                                'critical' => __('Critical'),
                                            } }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field" style="grid-column:1 / -1;">
                                    <label class="field-label">{{ __('Review summary') }}</label>
                                    <input class="field-input" name="review_summary" value="{{ $review['review_summary'] }}" required>
                                </div>
                                <div class="field" style="grid-column:1 / -1;">
                                    <label class="field-label">{{ __('Decision notes') }}</label>
                                    <input class="field-input" name="decision_notes" value="{{ $review['decision_notes'] }}">
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Linked asset') }}</label>
                                    <select class="field-select" name="linked_asset_id">
                                        <option value="">{{ __('No linked asset') }}</option>
                                        @foreach ($asset_options as $asset)
                                            <option value="{{ $asset['id'] }}" @selected($review['linked_asset_id'] === $asset['id'])>{{ $asset['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Linked control') }}</label>
                                    <select class="field-select" name="linked_control_id">
                                        <option value="">{{ __('No linked control') }}</option>
                                        @foreach ($control_options as $control)
                                            <option value="{{ $control['id'] }}" @selected($review['linked_control_id'] === $control['id'])>{{ $control['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Linked risk') }}</label>
                                    <select class="field-select" name="linked_risk_id">
                                        <option value="">{{ __('No linked risk') }}</option>
                                        @foreach ($risk_options as $risk)
                                            <option value="{{ $risk['id'] }}" @selected($review['linked_risk_id'] === $risk['id'])>{{ $risk['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Linked finding') }}</label>
                                    <select class="field-select" name="linked_finding_id">
                                        <option value="">{{ __('No linked finding') }}</option>
                                        @foreach ($finding_options as $finding)
                                            <option value="{{ $finding['id'] }}" @selected($review['linked_finding_id'] === $finding['id'])>{{ $finding['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Next review due') }}</label>
                                    <input class="field-input" type="date" name="next_review_due_on" value="{{ $review['next_review_due_on'] }}">
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Add owner actor') }}</label>
                                    <select class="field-select" name="owner_actor_id">
                                        <option value="">{{ __('Do not add owner') }}</option>
                                        @foreach ($owner_actor_options as $actor)
                                            <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                                        @endforeach
                                    </select>
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
        @php
            $inReviewCount = collect($all_vendors)->filter(fn (array $vendor): bool => ($vendor['current_review']['state'] ?? null) === 'in-review')->count();
            $highExposureCount = collect($all_vendors)->filter(fn (array $vendor): bool => in_array(($vendor['tier'] ?? null), ['high', 'critical'], true))->count();
            $openQuestionnaireCount = collect($all_vendors)->sum(fn (array $vendor): int => (int) ($vendor['current_review']['open_questionnaire_count'] ?? 0));
            $openFollowUpCount = collect($all_vendors)->sum(fn (array $vendor): int => (int) (($vendor['current_review']['open_questionnaire_count'] ?? 0) + ($vendor['current_review']['open_action_count'] ?? 0)));
            $externalLinkCount = collect($all_vendors)->sum(fn (array $vendor): int => count($vendor['current_review']['external_links'] ?? []));
        @endphp

        @if ($can_manage_vendors)
            <div class="surface-card" id="vendor-editor" hidden>
                <div class="row-between" style="margin-bottom:14px;">
                    <div>
                        <div class="eyebrow">{{ __('Create') }}</div>
                        <div class="entity-title" style="font-size:24px;">{{ __('New vendor review') }}</div>
                    </div>
                </div>

                <form class="upload-form" method="POST" action="{{ $create_route }}">
                    @csrf
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                    <input type="hidden" name="menu" value="plugin.third-party-risk.root">
                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                        <div class="field">
                            <label class="field-label">{{ __('Vendor legal name') }}</label>
                            <input class="field-input" name="legal_name" required>
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('Tier') }}</label>
                            <select class="field-select" name="tier" required>
                                @foreach (['low', 'medium', 'high', 'critical'] as $tier)
                                    <option value="{{ $tier }}">{{ match ($tier) {
                                        'low' => __('Low'),
                                        'medium' => __('Medium'),
                                        'high' => __('High'),
                                        'critical' => __('Critical'),
                                    } }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field" style="grid-column:1 / -1;">
                            <label class="field-label">{{ __('Service summary') }}</label>
                            <input class="field-input" name="service_summary" required>
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('Website') }}</label>
                            <input class="field-input" name="website">
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('Scope') }}</label>
                            <select class="field-select" name="scope_id">
                                <option value="">{{ __('Organization-wide') }}</option>
                                @foreach ($scope_options as $scope)
                                    <option value="{{ $scope['id'] }}">{{ $scope['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('Primary contact') }}</label>
                            <input class="field-input" name="primary_contact_name">
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('Primary contact email') }}</label>
                            <input class="field-input" type="email" name="primary_contact_email">
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('Review profile') }}</label>
                            <select class="field-select" name="review_profile_id">
                                <option value="">{{ __('No review profile') }}</option>
                                @foreach ($review_profile_options as $profile)
                                    <option value="{{ $profile['id'] }}">{{ $profile['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('Questionnaire template') }}</label>
                            <select class="field-select" name="questionnaire_template_id">
                                <option value="">{{ __('No questionnaire template') }}</option>
                                @foreach ($questionnaire_template_options as $template)
                                    <option value="{{ $template['id'] }}">{{ $template['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('Review title') }}</label>
                            <input class="field-input" name="review_title" required>
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('Inherent risk') }}</label>
                            <select class="field-select" name="inherent_risk" required>
                                @foreach (['low', 'medium', 'high', 'critical'] as $risk)
                                    <option value="{{ $risk }}">{{ match ($risk) {
                                        'low' => __('Low'),
                                        'medium' => __('Medium'),
                                        'high' => __('High'),
                                        'critical' => __('Critical'),
                                    } }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field" style="grid-column:1 / -1;">
                            <label class="field-label">{{ __('Review summary') }}</label>
                            <input class="field-input" name="review_summary" required>
                        </div>
                        <div class="field" style="grid-column:1 / -1;">
                            <label class="field-label">{{ __('Decision notes') }}</label>
                            <input class="field-input" name="decision_notes">
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('Linked asset') }}</label>
                            <select class="field-select" name="linked_asset_id">
                                <option value="">{{ __('No linked asset') }}</option>
                                @foreach ($asset_options as $asset)
                                    <option value="{{ $asset['id'] }}">{{ $asset['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('Linked control') }}</label>
                            <select class="field-select" name="linked_control_id">
                                <option value="">{{ __('No linked control') }}</option>
                                @foreach ($control_options as $control)
                                    <option value="{{ $control['id'] }}">{{ $control['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('Linked risk') }}</label>
                            <select class="field-select" name="linked_risk_id">
                                <option value="">{{ __('No linked risk') }}</option>
                                @foreach ($risk_options as $risk)
                                    <option value="{{ $risk['id'] }}">{{ $risk['label'] }}</option>
                                @endforeach
                            </select>
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
                            <label class="field-label">{{ __('Next review due') }}</label>
                            <input class="field-input" type="date" name="next_review_due_on">
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('Owner actor') }}</label>
                            <select class="field-select" name="owner_actor_id">
                                <option value="">{{ __('No owner') }}</option>
                                @foreach ($owner_actor_options as $actor)
                                    <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="action-cluster" style="margin-top:14px;">
                        <button class="button button-primary" type="submit">{{ __('Create vendor review') }}</button>
                    </div>
                </form>
            </div>
        @endif

        <div class="vendor-register-grid">
            <div class="vendor-filter-strip">
                @foreach ($register_filters as $filter)
                    <a class="vendor-filter-link @if($register_filter === $filter['id']) is-active @endif" href="{{ $filter['url'] }}">
                        <span>{{ $filter['label'] }}</span>
                        <span class="vendor-filter-count">{{ $filter['count'] }}</span>
                    </a>
                @endforeach
            </div>

            <div class="vendor-kpi-strip">
                <div class="vendor-kpi">
                    <div class="metric-label">{{ __('Vendors') }}</div>
                    <div class="vendor-kpi-value">{{ count($vendors) }}</div>
                    <div class="vendor-kpi-copy">{{ __('Tracked third parties in the current register filter.') }}</div>
                </div>
                <div class="vendor-kpi">
                    <div class="metric-label">{{ __('In review') }}</div>
                    <div class="vendor-kpi-value">{{ $inReviewCount }}</div>
                    <div class="vendor-kpi-copy">{{ __('Reviews currently waiting on due diligence, evidence, or decision work.') }}</div>
                </div>
                <div class="vendor-kpi">
                    <div class="metric-label">{{ __('High exposure') }}</div>
                    <div class="vendor-kpi-value">{{ $highExposureCount }}</div>
                    <div class="vendor-kpi-copy">{{ __('High and critical vendors that usually deserve the most attention.') }}</div>
                </div>
                <div class="vendor-kpi">
                    <div class="metric-label">{{ __('Open follow-up') }}</div>
                    <div class="vendor-kpi-value">{{ $openFollowUpCount }}</div>
                    <div class="vendor-kpi-copy">{{ __('Questionnaire and remediation follow-up still open across the review set.') }}</div>
                </div>
            </div>

            <div class="vendor-register-layout">
                <div class="vendor-panel">
                    <div class="vendor-panel-header">
                        <div>
                            <div class="eyebrow">{{ __('Register') }}</div>
                            <h3>{{ __('Vendor register list') }}</h3>
                            <div class="table-note" style="margin-top:6px;">{{ __('This list stays focused on tier, current review, vendor status, owner summary, and Open.') }}</div>
                        </div>
                    </div>

                    <div>
                        <table class="vendor-register-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Vendor') }}</th>
                                    <th>{{ __('Tier') }}</th>
                                    <th>{{ __('Current review') }}</th>
                                    <th>{{ __('Vendor status') }}</th>
                                    <th>{{ __('Attention') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($vendors as $vendor)
                                    @php
                                        $review = $vendor['current_review'];
                                        $tierClass = 'vendor-tier-'.$vendor['tier'];
                                        $statusClass = 'vendor-status-'.str_replace(' ', '-', strtolower((string) $vendor['vendor_status']));
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="entity-title">{{ $vendor['legal_name'] }}</div>
                                            <div class="table-note">{{ $vendor['service_summary'] }}</div>
                                            <div class="table-note">{{ $vendor['primary_contact_email'] !== '' ? $vendor['primary_contact_email'] : __('No external contact yet') }}</div>
                                        </td>
                                        <td><span class="vendor-tier {{ $tierClass }}">{{ $vendor['tier_label'] ?? ucfirst($vendor['tier']) }}</span></td>
                                        <td>
                                            <div class="entity-title" style="font-size:18px;">{{ $review['title'] }}</div>
                                            <div class="table-note">{{ $review['state_label'] }} · {{ $review['inherent_risk_label'] ?? ucfirst($review['inherent_risk']) }} {{ __('inherent risk') }}</div>
                                            <div class="table-note">{{ __('Next due: :date', ['date' => $review['next_review_due_on'] !== '' ? $review['next_review_due_on'] : __('Not scheduled')]) }}</div>
                                            <div class="table-note">
                                                @if ($review['is_overdue'])
                                                    {{ __('Overdue reassessment') }}
                                                @elseif ($review['is_due_soon'])
                                                    {{ __('Due soon') }}
                                                @elseif ($review['is_decision_pending'])
                                                    {{ __('Decision pending') }}
                                                @else
                                                    {{ __('Review cadence on track') }}
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <span class="vendor-status-badge {{ $statusClass }}">{{ $vendor['vendor_status_label'] ?? ucfirst($vendor['vendor_status']) }}</span>
                                        </td>
                                        <td>
                                            <div class="table-note">{{ count($review['owner_assignments']) }} {{ __('Owners') }}</div>
                                            <div class="table-note">{{ count($review['artifacts']) }} {{ __('Evidence') }}</div>
                                            <div class="table-note">{{ $review['open_questionnaire_count'] }} {{ __('Questionnaire items') }}</div>
                                            <div class="table-note">{{ $review['open_action_count'] }} {{ __('Remediation actions') }}</div>
                                        </td>
                                        <td><a class="button button-ghost" href="{{ $vendor['open_url'] }}">{{ __('Open') }}</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="table-note">{{ __('No vendors yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <aside class="vendor-panel">
                    <div class="vendor-panel-header">
                        <div>
                            <div class="eyebrow">{{ __('Review load') }}</div>
                            <h3>{{ __('Attention rail') }}</h3>
                            <div class="table-note" style="margin-top:6px;">{{ __('A quick side view of where third-party review work is accumulating.') }}</div>
                        </div>
                    </div>

                    <div class="vendor-mini-stack">
                        <div class="vendor-mini-item">
                            <div class="vendor-mini-top">
                                <div class="entity-title">{{ __('External collaboration') }}</div>
                                <strong>{{ $externalLinkCount }}</strong>
                            </div>
                            <div class="vendor-mini-meta">{{ __('Issued review links currently tracked across vendor workspaces.') }}</div>
                        </div>
                        <div class="vendor-mini-item">
                            <div class="vendor-mini-top">
                                <div class="entity-title">{{ __('Approved posture') }}</div>
                                <strong>{{ collect($all_vendors)->filter(fn (array $vendor): bool => in_array(($vendor['current_review']['state'] ?? null), ['approved', 'approved-with-conditions'], true))->count() }}</strong>
                            </div>
                            <div class="vendor-mini-meta">{{ __('Reviews that already reached a decision and can move into follow-up or cadence monitoring.') }}</div>
                        </div>
                        <div class="vendor-mini-item">
                            <div class="vendor-mini-top">
                                <div class="entity-title">{{ __('Open follow-up') }}</div>
                                <strong>{{ $openFollowUpCount }}</strong>
                            </div>
                            <div class="vendor-mini-meta">{{ __('Questionnaire and remediation items still awaiting closure.') }}</div>
                        </div>
                    </div>

                    <div class="metric-label">{{ __('Current review posture') }}</div>
                    <div class="vendor-rail-list">
                        @forelse (collect($vendors)->sortByDesc(fn (array $vendor): int => (int) (($vendor['current_review']['open_questionnaire_count'] ?? 0) + ($vendor['current_review']['open_action_count'] ?? 0) + (($vendor['current_review']['is_overdue'] ?? false) ? 1 : 0) + (($vendor['current_review']['is_due_soon'] ?? false) ? 1 : 0)))->take(4) as $vendor)
                            @php
                                $review = $vendor['current_review'];
                            @endphp
                            <div class="vendor-mini-item">
                                <div class="vendor-mini-top">
                                    <div class="entity-title">{{ $vendor['legal_name'] }}</div>
                                    <span class="pill {{
                                        match($review['state']) {
                                            'prospective' => 'pill-prospective',
                                            'in-review' => 'pill-in-review',
                                            'approved' => 'pill-approved',
                                            'approved-with-conditions' => 'pill-approved-with-conditions',
                                            'rejected' => 'pill-rejected',
                                            default => '',
                                        }
                                    }}">{{ $review['state_label'] }}</span>
                                </div>
                                <div class="vendor-mini-meta">{{ $review['title'] }}</div>
                                <div class="vendor-mini-meta">{{ count($review['artifacts']) }} {{ __('Evidence') }} · {{ $review['open_questionnaire_count'] }} {{ __('Questionnaire follow-up') }} · {{ $review['open_action_count'] }} {{ __('Remediation actions') }}</div>
                                <div class="vendor-mini-meta">
                                    @if ($review['is_overdue'])
                                        {{ __('Overdue reassessment') }}
                                    @elseif ($review['is_due_soon'])
                                        {{ __('Due soon on :date', ['date' => $review['next_review_due_on']]) }}
                                    @elseif ($review['is_decision_pending'])
                                        {{ __('Decision pending') }}
                                    @else
                                        {{ __('Next due: :date', ['date' => $review['next_review_due_on'] !== '' ? $review['next_review_due_on'] : __('Not scheduled')]) }}
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="muted-note">{{ __('No vendor reviews available yet.') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif
</section>
