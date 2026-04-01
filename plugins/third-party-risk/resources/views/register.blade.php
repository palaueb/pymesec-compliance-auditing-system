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

    @media (max-width: 1200px) {
        .vendor-register-layout,
        .vendor-kpi-strip {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>

<section class="module-screen">
    <div class="surface-note">
        Vendor reviews keep intake, current review posture, linked records, evidence, and approval decisions in one workspace. Use the register to browse vendors and open the current review you want to work on.
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
                Vendor Review keeps intake context, evidence, decision notes, linked internal records, and reviewer ownership in one workspace.
            </div>

            <div class="row-between" style="align-items:flex-start;">
                <div>
                    <div class="eyebrow">Vendor Review</div>
                    <h2 class="screen-title" style="font-size:28px;">{{ $selected_vendor['legal_name'] }}</h2>
                    <div class="table-note">{{ $selected_vendor['id'] }}</div>
                    <div class="table-note">{{ $selected_vendor['service_summary'] }}</div>
                </div>
                <div class="action-cluster">
                    <span class="pill {{ $reviewStatePill }}">{{ $review['state_label'] }}</span>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
                <div class="metric-card"><div class="metric-label">Tier</div><div class="metric-value">{{ ucfirst($selected_vendor['tier']) }}</div></div>
                <div class="metric-card"><div class="metric-label">Inherent risk</div><div class="metric-value">{{ ucfirst($review['inherent_risk']) }}</div></div>
                <div class="metric-card"><div class="metric-label">Evidence</div><div class="metric-value">{{ count($review['artifacts']) }}</div></div>
                <div class="metric-card"><div class="metric-label">Questionnaire items</div><div class="metric-value">{{ count($review['questionnaire_items']) }}</div><div class="meta-copy">{{ $review['questionnaire_template']['name'] ?? 'Manual only' }}</div></div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Overview</div>
                    <div class="table-note" style="margin-top:10px;">Current review: {{ $review['title'] }}</div>
                    <div class="table-note">Contact: {{ $selected_vendor['primary_contact_name'] !== '' ? $selected_vendor['primary_contact_name'] : 'None' }}@if($selected_vendor['primary_contact_email'] !== '') · {{ $selected_vendor['primary_contact_email'] }}@endif</div>
                    <div class="table-note">Website: {{ $selected_vendor['website'] !== '' ? $selected_vendor['website'] : 'Not set' }}</div>
                    <div class="table-note">Scope: {{ $selected_vendor['scope_id'] !== '' ? $selected_vendor['scope_id'] : 'Org-wide' }}</div>
                    <div class="table-note">Next review due: {{ $review['next_review_due_on'] !== '' ? $review['next_review_due_on'] : 'Not scheduled' }}</div>
                    <div class="table-note" style="margin-top:10px;">Review summary: {{ $review['review_summary'] }}</div>
                    <div class="table-note">Decision notes: {{ $review['decision_notes'] !== '' ? $review['decision_notes'] : 'None' }}</div>
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Workflow</div>
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
                                    <button class="button button-secondary" type="submit">{{ ucwords(str_replace('-', ' ', $transition)) }}</button>
                                </form>
                            @endforeach
                        </div>
                    @else
                        <div class="table-note" style="margin-top:10px;">View-only access</div>
                    @endif

                    <div class="data-stack" style="margin-top:12px;">
                        @forelse ($review['history'] as $history)
                            <div class="data-item">
                                <div class="entity-title">{{ $history->transitionKey }}</div>
                                <div class="table-note">{{ $history->fromState }} → {{ $history->toState }}</div>
                            </div>
                        @empty
                            <span class="muted-note">No transitions recorded yet</span>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Review profile</div>
                    @if (is_array($review['review_profile'] ?? null))
                        <div class="entity-title" style="margin-top:10px;">{{ $review['review_profile']['name'] }}</div>
                        <div class="table-note">{{ ucfirst($review['review_profile']['tier']) }} tier · default inherent risk {{ ucfirst($review['review_profile']['default_inherent_risk']) }}</div>
                        <div class="table-note">{{ $review['review_profile']['review_interval_days'] !== '' ? $review['review_profile']['review_interval_days'].' day cadence' : 'No default cadence' }}</div>
                        <div class="table-note">{{ $review['review_profile']['summary'] !== '' ? $review['review_profile']['summary'] : 'No profile summary.' }}</div>
                    @else
                        <span class="muted-note" style="margin-top:10px;">No review profile selected</span>
                    @endif
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="row-between">
                        <div class="metric-label">Questionnaire template</div>
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
                                <button class="button button-ghost" type="submit">Apply template items</button>
                            </form>
                        @endif
                    </div>
                    @if (is_array($review['questionnaire_template'] ?? null))
                        <div class="entity-title" style="margin-top:10px;">{{ $review['questionnaire_template']['name'] }}</div>
                        <div class="table-note">{{ $review['questionnaire_template']['summary'] !== '' ? $review['questionnaire_template']['summary'] : 'No template summary.' }}</div>
                    @else
                        <span class="muted-note" style="margin-top:10px;">No questionnaire template selected</span>
                    @endif
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="row-between">
                        <div class="metric-label">Evidence</div>
                        @if ($can_manage_vendors)
                            <details>
                                <summary class="button button-ghost" style="display:inline-flex;">Attach evidence</summary>
                                <form class="upload-form" method="POST" action="{{ $review['artifact_upload_route'] }}" enctype="multipart/form-data" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.third-party-risk.root">
                                    <input type="hidden" name="vendor_id" value="{{ $selected_vendor['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <input type="hidden" name="artifact_type" value="evidence">
                                    <input class="field-input" type="text" name="label" placeholder="Evidence label">
                                    <input class="field-input" type="file" name="artifact" required>
                                    <button class="button button-secondary" type="submit">Upload evidence</button>
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
                            <span class="muted-note">No evidence yet</span>
                        @endforelse
                    </div>
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="row-between">
                        <div class="metric-label">Questionnaire</div>
                        @if ($can_manage_vendors)
                            <details>
                                <summary class="button button-ghost" style="display:inline-flex;">Add question</summary>
                                <form class="upload-form" method="POST" action="{{ $review['questionnaire_store_route'] }}" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.third-party-risk.root">
                                    <input type="hidden" name="vendor_id" value="{{ $selected_vendor['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <input class="field-input" type="text" name="prompt" placeholder="Question prompt" required>
                                    <select class="field-select" name="response_type" required>
                                        <option value="yes-no">Yes / no</option>
                                        <option value="long-text">Long text</option>
                                        <option value="date">Date</option>
                                        <option value="evidence-list">Evidence list</option>
                                    </select>
                                    <select class="field-select" name="response_status">
                                        <option value="draft">Draft</option>
                                        <option value="sent">Sent</option>
                                        <option value="submitted">Submitted</option>
                                        <option value="under-review">Under review</option>
                                        <option value="accepted">Accepted</option>
                                        <option value="needs-follow-up">Needs follow-up</option>
                                    </select>
                                    <input class="field-input" type="text" name="answer_text" placeholder="Optional answer">
                                    <input class="field-input" type="text" name="follow_up_notes" placeholder="Optional follow-up">
                                    <button class="button button-secondary" type="submit">Save question</button>
                                </form>
                            </details>
                        @endif
                    </div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($review['questionnaire_items'] as $item)
                            <div class="data-item">
                                <div class="entity-title">{{ $item['position'] }}. {{ $item['prompt'] }}</div>
                                <div class="table-note">{{ ucwords(str_replace('-', ' ', $item['response_type'])) }} · {{ ucwords(str_replace('-', ' ', $item['response_status'])) }}</div>
                                <div class="table-note">Answer: {{ $item['answer_text'] !== '' ? $item['answer_text'] : 'No answer yet' }}</div>
                                <div class="table-note">Follow-up: {{ $item['follow_up_notes'] !== '' ? $item['follow_up_notes'] : 'None' }}</div>
                                @if ($can_manage_vendors)
                                    <details style="margin-top:8px;">
                                        <summary class="button button-ghost" style="display:inline-flex;">Update item</summary>
                                        <form class="upload-form" method="POST" action="{{ $item['update_route'] }}" style="margin-top:10px;">
                                            @csrf
                                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                            <input type="hidden" name="menu" value="plugin.third-party-risk.root">
                                            <input type="hidden" name="vendor_id" value="{{ $selected_vendor['id'] }}">
                                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                            <input class="field-input" type="text" name="prompt" value="{{ $item['prompt'] }}" required>
                                            <select class="field-select" name="response_type" required>
                                                @foreach (['yes-no' => 'Yes / no', 'long-text' => 'Long text', 'date' => 'Date', 'evidence-list' => 'Evidence list'] as $typeKey => $typeLabel)
                                                    <option value="{{ $typeKey }}" @selected($item['response_type'] === $typeKey)>{{ $typeLabel }}</option>
                                                @endforeach
                                            </select>
                                            <select class="field-select" name="response_status" required>
                                                @foreach (['draft', 'sent', 'submitted', 'under-review', 'accepted', 'needs-follow-up'] as $status)
                                                    <option value="{{ $status }}" @selected($item['response_status'] === $status)>{{ ucwords(str_replace('-', ' ', $status)) }}</option>
                                                @endforeach
                                            </select>
                                            <input class="field-input" type="text" name="answer_text" value="{{ $item['answer_text'] }}" placeholder="Answer">
                                            <input class="field-input" type="text" name="follow_up_notes" value="{{ $item['follow_up_notes'] }}" placeholder="Follow-up">
                                            <button class="button button-secondary" type="submit">Update question</button>
                                        </form>
                                    </details>
                                @endif
                            </div>
                        @empty
                            <span class="muted-note">No questionnaire items yet</span>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Linked records</div>
                    <div class="table-note" style="margin-top:10px;">Asset: {{ $review['linked_asset_label'] ?? ($review['linked_asset_id'] !== '' ? $review['linked_asset_id'] : 'None') }}</div>
                    <div class="table-note">Control: {{ $review['linked_control_label'] ?? ($review['linked_control_id'] !== '' ? $review['linked_control_id'] : 'None') }}</div>
                    <div class="table-note">Risk: {{ $review['linked_risk_label'] ?? ($review['linked_risk_id'] !== '' ? $review['linked_risk_id'] : 'None') }}</div>
                    <div class="table-note">Finding: {{ $review['linked_finding_label'] ?? ($review['linked_finding_id'] !== '' ? $review['linked_finding_id'] : 'None') }}</div>

                    <div class="metric-label" style="margin-top:16px;">Owners</div>
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
                                        <button class="button button-ghost" type="submit">Remove owner</button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <span class="muted-note">No owner assigned</span>
                        @endforelse
                    </div>
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Status</div>
                    <div class="table-note" style="margin-top:10px;">Vendor status: {{ ucfirst($selected_vendor['vendor_status']) }}</div>
                    <div class="table-note">Current review state: {{ $review['state_label'] }}</div>
                    <div class="table-note">Questionnaire open items: {{ collect($review['questionnaire_items'])->whereIn('response_status', ['draft', 'sent', 'submitted', 'under-review', 'needs-follow-up'])->count() }}</div>
                    <div class="table-note">Accepted answers: {{ collect($review['questionnaire_items'])->where('response_status', 'accepted')->count() }}</div>
                    <div class="table-note">External links: {{ count($review['external_links']) }}</div>
                </div>
            </div>

            <div class="surface-card" style="padding:14px;">
                <div class="row-between">
                    <div class="metric-label">External collaboration</div>
                    <span class="table-note">Object-scoped portal access for this review only</span>
                </div>

                @if (session('third_party_risk_external_portal_url'))
                    <div class="surface-note" style="margin-top:10px;">
                        New portal link for {{ session('third_party_risk_external_portal_email') }}:
                        <div style="margin-top:8px; word-break:break-all;">
                            <a href="{{ session('third_party_risk_external_portal_url') }}" target="_blank" rel="noreferrer">{{ session('third_party_risk_external_portal_url') }}</a>
                        </div>
                    </div>
                @endif

                @if ($can_manage_vendors)
                    <details style="margin-top:10px;">
                        <summary class="button button-ghost" style="display:inline-flex;">Issue external review link</summary>
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
                                    <label class="field-label">Contact name</label>
                                    <input class="field-input" name="contact_name">
                                </div>
                                <div class="field">
                                    <label class="field-label">Contact email</label>
                                    <input class="field-input" type="email" name="contact_email" required>
                                </div>
                                <div class="field">
                                    <label class="field-label">Expires at</label>
                                    <input class="field-input" type="datetime-local" name="expires_at">
                                </div>
                                <div class="field">
                                    <label class="field-label">Permissions</label>
                                    <label style="display:flex; gap:8px; align-items:center; margin-top:6px;">
                                        <input type="checkbox" name="can_answer_questionnaire" value="1" checked>
                                        <span>Answer questionnaire</span>
                                    </label>
                                    <label style="display:flex; gap:8px; align-items:center; margin-top:6px;">
                                        <input type="checkbox" name="can_upload_artifacts" value="1" checked>
                                        <span>Upload evidence</span>
                                    </label>
                                    <label style="display:flex; gap:8px; align-items:center; margin-top:6px;">
                                        <input type="checkbox" name="send_email_invitation" value="1">
                                        <span>Send email invitation now</span>
                                    </label>
                                    <span class="table-note" style="display:block; margin-top:8px;">Uses the organization SMTP connector from Notifications &amp; Delivery when enabled.</span>
                                </div>
                            </div>
                            <div class="action-cluster" style="margin-top:14px;">
                                <button class="button button-secondary" type="submit">Issue link</button>
                            </div>
                        </form>
                    </details>
                @endif

                <div class="data-stack" style="margin-top:12px;">
                    @forelse ($review['external_links'] as $externalLink)
                        @php
                            $deliveryLabel = [
                                'manual-only' => 'Manual only',
                                'sent' => 'Email sent',
                                'failed' => 'Email failed',
                                'not-configured' => 'Email not configured',
                            ][$externalLink['email_delivery_status']] ?? ucfirst(str_replace('-', ' ', $externalLink['email_delivery_status']));
                        @endphp
                        <div class="data-item">
                            <div class="row-between" style="align-items:flex-start; gap:12px;">
                                <div>
                                    <div class="entity-title">{{ $externalLink['contact_name'] !== '' ? $externalLink['contact_name'] : $externalLink['contact_email'] }}</div>
                                    <div class="table-note">{{ $externalLink['contact_email'] }}</div>
                                    <div class="table-note">
                                        {{ $externalLink['can_answer_questionnaire'] === '1' ? 'Questionnaire' : 'Read-only' }}
                                        @if ($externalLink['can_upload_artifacts'] === '1')
                                            · Uploads
                                        @endif
                                    </div>
                                    <div class="table-note">Expires: {{ $externalLink['expires_at'] !== '' ? $externalLink['expires_at'] : 'No expiry' }}</div>
                                    <div class="table-note">Last accessed: {{ $externalLink['last_accessed_at'] !== '' ? $externalLink['last_accessed_at'] : 'Never' }}</div>
                                    <div class="table-note">Delivery: {{ $deliveryLabel }}</div>
                                    <div class="table-note">Email sent: {{ $externalLink['email_sent_at'] !== '' ? $externalLink['email_sent_at'] : 'Not sent' }}</div>
                                    @if ($externalLink['email_delivery_error'] !== '')
                                        <div class="table-note">Delivery error: {{ $externalLink['email_delivery_error'] }}</div>
                                    @endif
                                    <div class="table-note">State: {{ $externalLink['revoked_at'] === '' ? 'Active' : 'Revoked' }}</div>
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
                                        <button class="button button-ghost" type="submit">Revoke</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <span class="muted-note">No external collaboration links yet</span>
                    @endforelse
                </div>
            </div>

            @if ($can_manage_vendors)
                <div class="surface-card" style="padding:14px;">
                    <details>
                        <summary class="button button-ghost" style="display:inline-flex; width:fit-content;">Edit vendor and review</summary>
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
                                    <label class="field-label">Vendor legal name</label>
                                    <input class="field-input" name="legal_name" value="{{ $selected_vendor['legal_name'] }}" required>
                                </div>
                                <div class="field">
                                    <label class="field-label">Tier</label>
                                    <select class="field-select" name="tier" required>
                                        @foreach (['low', 'medium', 'high', 'critical'] as $tier)
                                            <option value="{{ $tier }}" @selected($selected_vendor['tier'] === $tier)>{{ ucfirst($tier) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field" style="grid-column:1 / -1;">
                                    <label class="field-label">Service summary</label>
                                    <input class="field-input" name="service_summary" value="{{ $selected_vendor['service_summary'] }}" required>
                                </div>
                                <div class="field">
                                    <label class="field-label">Website</label>
                                    <input class="field-input" name="website" value="{{ $selected_vendor['website'] }}">
                                </div>
                                <div class="field">
                                    <label class="field-label">Scope</label>
                                    <select class="field-select" name="scope_id">
                                        <option value="">Organization-wide</option>
                                        @foreach ($scope_options as $scope)
                                            <option value="{{ $scope['id'] }}" @selected($selected_vendor['scope_id'] === $scope['id'])>{{ $scope['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">Primary contact</label>
                                    <input class="field-input" name="primary_contact_name" value="{{ $selected_vendor['primary_contact_name'] }}">
                                </div>
                                <div class="field">
                                    <label class="field-label">Primary contact email</label>
                                    <input class="field-input" name="primary_contact_email" type="email" value="{{ $selected_vendor['primary_contact_email'] }}">
                                </div>
                                <div class="field">
                                    <label class="field-label">Review profile</label>
                                    <select class="field-select" name="review_profile_id">
                                        <option value="">No review profile</option>
                                        @foreach ($review_profile_options as $profile)
                                            <option value="{{ $profile['id'] }}" @selected($review['review_profile_id'] === $profile['id'])>{{ $profile['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">Questionnaire template</label>
                                    <select class="field-select" name="questionnaire_template_id">
                                        <option value="">No questionnaire template</option>
                                        @foreach ($questionnaire_template_options as $template)
                                            <option value="{{ $template['id'] }}" @selected($review['questionnaire_template_id'] === $template['id'])>{{ $template['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">Review title</label>
                                    <input class="field-input" name="review_title" value="{{ $review['title'] }}" required>
                                </div>
                                <div class="field">
                                    <label class="field-label">Inherent risk</label>
                                    <select class="field-select" name="inherent_risk" required>
                                        @foreach (['low', 'medium', 'high', 'critical'] as $risk)
                                            <option value="{{ $risk }}" @selected($review['inherent_risk'] === $risk)>{{ ucfirst($risk) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field" style="grid-column:1 / -1;">
                                    <label class="field-label">Review summary</label>
                                    <input class="field-input" name="review_summary" value="{{ $review['review_summary'] }}" required>
                                </div>
                                <div class="field" style="grid-column:1 / -1;">
                                    <label class="field-label">Decision notes</label>
                                    <input class="field-input" name="decision_notes" value="{{ $review['decision_notes'] }}">
                                </div>
                                <div class="field">
                                    <label class="field-label">Linked asset</label>
                                    <select class="field-select" name="linked_asset_id">
                                        <option value="">No linked asset</option>
                                        @foreach ($asset_options as $asset)
                                            <option value="{{ $asset['id'] }}" @selected($review['linked_asset_id'] === $asset['id'])>{{ $asset['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">Linked control</label>
                                    <select class="field-select" name="linked_control_id">
                                        <option value="">No linked control</option>
                                        @foreach ($control_options as $control)
                                            <option value="{{ $control['id'] }}" @selected($review['linked_control_id'] === $control['id'])>{{ $control['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">Linked risk</label>
                                    <select class="field-select" name="linked_risk_id">
                                        <option value="">No linked risk</option>
                                        @foreach ($risk_options as $risk)
                                            <option value="{{ $risk['id'] }}" @selected($review['linked_risk_id'] === $risk['id'])>{{ $risk['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">Linked finding</label>
                                    <select class="field-select" name="linked_finding_id">
                                        <option value="">No linked finding</option>
                                        @foreach ($finding_options as $finding)
                                            <option value="{{ $finding['id'] }}" @selected($review['linked_finding_id'] === $finding['id'])>{{ $finding['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">Next review due</label>
                                    <input class="field-input" type="date" name="next_review_due_on" value="{{ $review['next_review_due_on'] }}">
                                </div>
                                <div class="field">
                                    <label class="field-label">Add owner actor</label>
                                    <select class="field-select" name="owner_actor_id">
                                        <option value="">Do not add owner</option>
                                        @foreach ($owner_actor_options as $actor)
                                            <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="action-cluster" style="margin-top:14px;">
                                <button class="button button-secondary" type="submit">Save changes</button>
                            </div>
                        </form>
                    </details>
                </div>
            @endif
        </div>
    @else
        @php
            $inReviewCount = collect($vendors)->filter(fn (array $vendor): bool => ($vendor['current_review']['state'] ?? null) === 'in-review')->count();
            $highExposureCount = collect($vendors)->filter(fn (array $vendor): bool => in_array(($vendor['tier'] ?? null), ['high', 'critical'], true))->count();
            $openQuestionnaireCount = collect($vendors)->sum(
                fn (array $vendor): int => collect($vendor['current_review']['questionnaire_items'] ?? [])->whereIn('response_status', ['draft', 'sent', 'submitted', 'under-review', 'needs-follow-up'])->count()
            );
            $externalLinkCount = collect($vendors)->sum(fn (array $vendor): int => count($vendor['current_review']['external_links'] ?? []));
        @endphp

        @if ($can_manage_vendors)
            <div class="surface-card" id="vendor-editor" hidden>
                <div class="row-between" style="margin-bottom:14px;">
                    <div>
                        <div class="eyebrow">Create</div>
                        <div class="entity-title" style="font-size:24px;">New vendor review</div>
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
                            <label class="field-label">Vendor legal name</label>
                            <input class="field-input" name="legal_name" required>
                        </div>
                        <div class="field">
                            <label class="field-label">Tier</label>
                            <select class="field-select" name="tier" required>
                                @foreach (['low', 'medium', 'high', 'critical'] as $tier)
                                    <option value="{{ $tier }}">{{ ucfirst($tier) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field" style="grid-column:1 / -1;">
                            <label class="field-label">Service summary</label>
                            <input class="field-input" name="service_summary" required>
                        </div>
                        <div class="field">
                            <label class="field-label">Website</label>
                            <input class="field-input" name="website">
                        </div>
                        <div class="field">
                            <label class="field-label">Scope</label>
                            <select class="field-select" name="scope_id">
                                <option value="">Organization-wide</option>
                                @foreach ($scope_options as $scope)
                                    <option value="{{ $scope['id'] }}">{{ $scope['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">Primary contact</label>
                            <input class="field-input" name="primary_contact_name">
                        </div>
                        <div class="field">
                            <label class="field-label">Primary contact email</label>
                            <input class="field-input" type="email" name="primary_contact_email">
                        </div>
                        <div class="field">
                            <label class="field-label">Review profile</label>
                            <select class="field-select" name="review_profile_id">
                                <option value="">No review profile</option>
                                @foreach ($review_profile_options as $profile)
                                    <option value="{{ $profile['id'] }}">{{ $profile['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">Questionnaire template</label>
                            <select class="field-select" name="questionnaire_template_id">
                                <option value="">No questionnaire template</option>
                                @foreach ($questionnaire_template_options as $template)
                                    <option value="{{ $template['id'] }}">{{ $template['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">Review title</label>
                            <input class="field-input" name="review_title" required>
                        </div>
                        <div class="field">
                            <label class="field-label">Inherent risk</label>
                            <select class="field-select" name="inherent_risk" required>
                                @foreach (['low', 'medium', 'high', 'critical'] as $risk)
                                    <option value="{{ $risk }}">{{ ucfirst($risk) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field" style="grid-column:1 / -1;">
                            <label class="field-label">Review summary</label>
                            <input class="field-input" name="review_summary" required>
                        </div>
                        <div class="field" style="grid-column:1 / -1;">
                            <label class="field-label">Decision notes</label>
                            <input class="field-input" name="decision_notes">
                        </div>
                        <div class="field">
                            <label class="field-label">Linked asset</label>
                            <select class="field-select" name="linked_asset_id">
                                <option value="">No linked asset</option>
                                @foreach ($asset_options as $asset)
                                    <option value="{{ $asset['id'] }}">{{ $asset['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">Linked control</label>
                            <select class="field-select" name="linked_control_id">
                                <option value="">No linked control</option>
                                @foreach ($control_options as $control)
                                    <option value="{{ $control['id'] }}">{{ $control['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">Linked risk</label>
                            <select class="field-select" name="linked_risk_id">
                                <option value="">No linked risk</option>
                                @foreach ($risk_options as $risk)
                                    <option value="{{ $risk['id'] }}">{{ $risk['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">Linked finding</label>
                            <select class="field-select" name="linked_finding_id">
                                <option value="">No linked finding</option>
                                @foreach ($finding_options as $finding)
                                    <option value="{{ $finding['id'] }}">{{ $finding['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">Next review due</label>
                            <input class="field-input" type="date" name="next_review_due_on">
                        </div>
                        <div class="field">
                            <label class="field-label">Owner actor</label>
                            <select class="field-select" name="owner_actor_id">
                                <option value="">No owner</option>
                                @foreach ($owner_actor_options as $actor)
                                    <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="action-cluster" style="margin-top:14px;">
                        <button class="button button-primary" type="submit">Create vendor review</button>
                    </div>
                </form>
            </div>
        @endif

        <div class="vendor-register-grid">
            <div class="vendor-kpi-strip">
                <div class="vendor-kpi">
                    <div class="metric-label">Vendors</div>
                    <div class="vendor-kpi-value">{{ count($vendors) }}</div>
                    <div class="vendor-kpi-copy">Tracked third parties with an active review workspace.</div>
                </div>
                <div class="vendor-kpi">
                    <div class="metric-label">In Review</div>
                    <div class="vendor-kpi-value">{{ $inReviewCount }}</div>
                    <div class="vendor-kpi-copy">Reviews currently waiting on due diligence, evidence, or decision work.</div>
                </div>
                <div class="vendor-kpi">
                    <div class="metric-label">High Exposure</div>
                    <div class="vendor-kpi-value">{{ $highExposureCount }}</div>
                    <div class="vendor-kpi-copy">High and critical vendors that usually deserve the most attention.</div>
                </div>
                <div class="vendor-kpi">
                    <div class="metric-label">Open Follow-up</div>
                    <div class="vendor-kpi-value">{{ $openQuestionnaireCount }}</div>
                    <div class="vendor-kpi-copy">Questionnaire items still open across the current review set.</div>
                </div>
            </div>

            <div class="vendor-register-layout">
                <div class="vendor-panel">
                    <div class="vendor-panel-header">
                        <div>
                            <div class="eyebrow">Register</div>
                            <h3>Vendor register list</h3>
                            <div class="table-note" style="margin-top:6px;">This list stays focused on tier, current review, vendor status, owner summary, and Open.</div>
                        </div>
                    </div>

                    <div>
                        <table class="vendor-register-table">
                            <thead>
                                <tr>
                                    <th>Vendor</th>
                                    <th>Tier</th>
                                    <th>Current review</th>
                                    <th>Vendor status</th>
                                    <th>Attention</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($vendors as $vendor)
                                    @php
                                        $review = $vendor['current_review'];
                                        $tierClass = 'vendor-tier-'.$vendor['tier'];
                                        $statusClass = 'vendor-status-'.str_replace(' ', '-', strtolower((string) $vendor['vendor_status']));
                                        $openItems = collect($review['questionnaire_items'] ?? [])->whereIn('response_status', ['draft', 'sent', 'submitted', 'under-review', 'needs-follow-up'])->count();
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="entity-title">{{ $vendor['legal_name'] }}</div>
                                            <div class="table-note">{{ $vendor['service_summary'] }}</div>
                                            <div class="table-note">{{ $vendor['primary_contact_email'] !== '' ? $vendor['primary_contact_email'] : 'No external contact yet' }}</div>
                                        </td>
                                        <td><span class="vendor-tier {{ $tierClass }}">{{ ucfirst($vendor['tier']) }}</span></td>
                                        <td>
                                            <div class="entity-title" style="font-size:18px;">{{ $review['title'] }}</div>
                                            <div class="table-note">{{ $review['state_label'] }} · {{ ucfirst($review['inherent_risk']) }} inherent risk</div>
                                            <div class="table-note">Next due: {{ $review['next_review_due_on'] !== '' ? $review['next_review_due_on'] : 'Not scheduled' }}</div>
                                        </td>
                                        <td>
                                            <span class="vendor-status-badge {{ $statusClass }}">{{ ucfirst($vendor['vendor_status']) }}</span>
                                        </td>
                                        <td>
                                            <div class="table-note">{{ count($review['owner_assignments']) }} owners</div>
                                            <div class="table-note">{{ count($review['artifacts']) }} evidence files</div>
                                            <div class="table-note">{{ $openItems }} open items</div>
                                        </td>
                                        <td><a class="button button-ghost" href="{{ $vendor['open_url'] }}">Open</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="table-note">No vendors yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <aside class="vendor-panel">
                    <div class="vendor-panel-header">
                        <div>
                            <div class="eyebrow">Review Load</div>
                            <h3>Attention rail</h3>
                            <div class="table-note" style="margin-top:6px;">A quick side view of where third-party review work is accumulating.</div>
                        </div>
                    </div>

                    <div class="vendor-mini-stack">
                        <div class="vendor-mini-item">
                            <div class="vendor-mini-top">
                                <div class="entity-title">External collaboration</div>
                                <strong>{{ $externalLinkCount }}</strong>
                            </div>
                            <div class="vendor-mini-meta">Issued review links currently tracked across vendor workspaces.</div>
                        </div>
                        <div class="vendor-mini-item">
                            <div class="vendor-mini-top">
                                <div class="entity-title">Approved posture</div>
                                <strong>{{ collect($vendors)->filter(fn (array $vendor): bool => in_array(($vendor['current_review']['state'] ?? null), ['approved', 'approved-with-conditions'], true))->count() }}</strong>
                            </div>
                            <div class="vendor-mini-meta">Reviews that already reached a decision and can move into follow-up or cadence monitoring.</div>
                        </div>
                    </div>

                    <div class="metric-label">Current review posture</div>
                    <div class="vendor-rail-list">
                        @forelse (collect($vendors)->sortByDesc(fn (array $vendor): int => collect($vendor['current_review']['questionnaire_items'] ?? [])->whereIn('response_status', ['draft', 'sent', 'submitted', 'under-review', 'needs-follow-up'])->count())->take(4) as $vendor)
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
                                <div class="vendor-mini-meta">{{ count($review['artifacts']) }} evidence · {{ collect($review['questionnaire_items'] ?? [])->whereIn('response_status', ['draft', 'sent', 'submitted', 'under-review', 'needs-follow-up'])->count() }} open items</div>
                                <div class="vendor-mini-meta">Next due: {{ $review['next_review_due_on'] !== '' ? $review['next_review_due_on'] : 'Not scheduled' }}</div>
                            </div>
                        @empty
                            <div class="muted-note">No vendor reviews available yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif
</section>
