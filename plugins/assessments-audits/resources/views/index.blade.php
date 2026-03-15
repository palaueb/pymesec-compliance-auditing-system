<section class="module-screen">
    @if ($can_manage_assessments)
        <details class="surface-card" id="assessment-editor">
            <summary class="button button-primary" style="display:inline-flex;">New assessment</summary>

            <form class="upload-form" method="POST" action="{{ $create_route }}" style="margin-top:14px;">
                @csrf
                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                <input type="hidden" name="menu" value="plugin.assessments-audits.root">
                <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">

                <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                    <div class="field">
                        <label class="field-label" for="assessment-title">Title</label>
                        <input class="field-input" id="assessment-title" name="title" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="assessment-framework">Framework</label>
                        <select class="field-select" id="assessment-framework" name="framework_id">
                            <option value="">Any framework</option>
                            @foreach ($framework_options as $framework)
                                <option value="{{ $framework['id'] }}">{{ $framework['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="assessment-scope">Scope</label>
                        <select class="field-select" id="assessment-scope" name="scope_id">
                            <option value="">Organization-wide</option>
                            @foreach ($scope_options as $scope)
                                <option value="{{ $scope['id'] }}" @selected(($query['scope_id'] ?? null) === $scope['id'])>{{ $scope['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="assessment-status">Status</label>
                        <select class="field-select" id="assessment-status" name="status">
                            @foreach ($status_options as $statusValue => $statusLabel)
                                <option value="{{ $statusValue }}">{{ $statusLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="assessment-starts">Starts on</label>
                        <input class="field-input" id="assessment-starts" name="starts_on" type="date" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="assessment-ends">Ends on</label>
                        <input class="field-input" id="assessment-ends" name="ends_on" type="date" required>
                    </div>
                    <div class="field" style="grid-column:1 / -1;">
                        <label class="field-label" for="assessment-summary">Summary</label>
                        <input class="field-input" id="assessment-summary" name="summary" required>
                    </div>
                    <div class="field" style="grid-column:1 / -1;">
                        <label class="field-label" for="assessment-controls">Checklist controls</label>
                        <select class="field-select" id="assessment-controls" name="control_ids[]" multiple size="{{ min(max(count($control_options), 3), 8) }}">
                            @foreach ($control_options as $control)
                                <option value="{{ $control['id'] }}">{{ $control['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="action-cluster" style="margin-top:14px;">
                    <button class="button button-primary" type="submit">Create assessment</button>
                </div>
            </form>
        </details>
    @endif

    <div class="overview-grid">
        <div class="metric-card"><div class="metric-label">Assessments</div><div class="metric-value">{{ count($campaigns) }}</div></div>
        <div class="metric-card"><div class="metric-label">Pass</div><div class="metric-value">{{ collect($campaigns)->sum(fn ($campaign) => $campaign['review_summary']['pass']) }}</div></div>
        <div class="metric-card"><div class="metric-label">Partial</div><div class="metric-value">{{ collect($campaigns)->sum(fn ($campaign) => $campaign['review_summary']['partial']) }}</div></div>
        <div class="metric-card"><div class="metric-label">Fail</div><div class="metric-value">{{ collect($campaigns)->sum(fn ($campaign) => $campaign['review_summary']['fail']) }}</div></div>
        <div class="metric-card"><div class="metric-label">Findings</div><div class="metric-value">{{ collect($campaigns)->sum(fn ($campaign) => $campaign['review_summary']['linked_findings']) }}</div></div>
        <div class="metric-card"><div class="metric-label">Workpapers</div><div class="metric-value">{{ collect($campaigns)->sum(fn ($campaign) => $campaign['review_summary']['artifacts']) }}</div></div>
    </div>

    <div class="table-card">
        <table class="entity-table">
            <thead>
                <tr>
                    <th>Assessment</th>
                    <th>Perimeter</th>
                    <th>Checklist</th>
                    <th>Results</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($campaigns as $campaign)
                    <tr>
                        <td>
                            <div class="entity-title">{{ $campaign['title'] }}</div>
                            <div class="entity-id">{{ $campaign['id'] }}</div>
                            <div class="table-note">{{ $campaign['summary'] }}</div>
                            <div class="table-note">Status: {{ $campaign['status'] }}</div>
                        </td>
                        <td>
                            <div>{{ $campaign['scope_name'] }}</div>
                            <div class="table-note">{{ $campaign['framework_name'] }}</div>
                            <div class="table-note">{{ $campaign['starts_on'] }} -> {{ $campaign['ends_on'] }}</div>
                        </td>
                        <td>
                            <div class="entity-title">{{ count($campaign['reviews']) }} controls</div>
                            <div class="table-note">{{ collect($campaign['reviews'])->sum(fn ($review) => count($review['requirements'])) }} mapped requirements</div>
                            <div class="table-note">{{ $campaign['review_summary']['artifacts'] }} workpapers · {{ $campaign['review_summary']['linked_findings'] }} findings</div>
                        </td>
                        <td>
                            <div class="data-stack">
                                <div class="data-item">Pass: {{ $campaign['review_summary']['pass'] }}</div>
                                <div class="data-item">Partial: {{ $campaign['review_summary']['partial'] }}</div>
                                <div class="data-item">Fail: {{ $campaign['review_summary']['fail'] }}</div>
                                <div class="data-item">Not tested: {{ $campaign['review_summary']['not-tested'] }}</div>
                            </div>
                        </td>
                        <td>
                            <div class="action-cluster">
                                <a class="button button-ghost" href="{{ $campaign['report_route'] }}">Export summary</a>

                                <details>
                                    <summary class="button button-ghost" style="display:inline-flex;">Review checklist</summary>

                                    <div class="surface-card" style="margin-top:12px; padding:14px; display:grid; gap:12px;">
                                        @foreach ($campaign['reviews'] as $review)
                                            <section class="surface-card" style="padding:12px; display:grid; gap:12px;">
                                                <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                                                    <div>
                                                        <div class="entity-title">{{ $review['control_name'] }}</div>
                                                        <div class="table-note">{{ $review['control_framework'] }} · {{ $review['control_domain'] }}</div>
                                                        <div class="table-note">{{ $review['control_evidence'] }}</div>
                                                    </div>
                                                    <span class="pill">{{ $result_options[$review['result']] ?? $review['result'] }}</span>
                                                </div>

                                                <div class="overview-grid" style="grid-template-columns:repeat(3, minmax(0, 1fr));">
                                                    <div class="surface-card" style="padding:10px;">
                                                        <div class="metric-label">Requirements</div>
                                                        <div class="data-stack" style="margin-top:8px;">
                                                            @forelse ($review['requirements'] as $requirement)
                                                                <div class="data-item">
                                                                    <div class="entity-title">{{ $requirement['framework_code'] }} · {{ $requirement['requirement_code'] }}</div>
                                                                    <div class="table-note">{{ $requirement['requirement_title'] }}</div>
                                                                    <div class="table-note">Coverage: {{ ucfirst($requirement['coverage']) }}</div>
                                                                </div>
                                                            @empty
                                                                <span class="muted-note">No mapped requirements</span>
                                                            @endforelse
                                                        </div>
                                                    </div>

                                                    <div class="surface-card" style="padding:10px;">
                                                        <div class="metric-label">Review notes</div>
                                                        <div class="table-note" style="margin-top:8px;">
                                                            {{ $review['test_notes'] !== '' ? $review['test_notes'] : 'No test notes recorded yet.' }}
                                                        </div>
                                                        <div class="metric-label" style="margin-top:10px;">Conclusion</div>
                                                        <div class="table-note" style="margin-top:8px;">
                                                            {{ $review['conclusion'] !== '' ? $review['conclusion'] : 'No conclusion recorded yet.' }}
                                                        </div>
                                                        @if ($review['reviewed_on'] !== '')
                                                            <div class="table-note" style="margin-top:10px;">Reviewed on {{ $review['reviewed_on'] }}</div>
                                                        @endif
                                                    </div>

                                                    <div class="surface-card" style="padding:10px;">
                                                        <div class="metric-label">Evidence and findings</div>
                                                        <div class="data-stack" style="margin-top:8px;">
                                                            @forelse ($review['artifacts'] as $artifact)
                                                                <div class="data-item">
                                                                    <div class="entity-title">{{ $artifact['label'] }}</div>
                                                                    <div class="table-note">{{ $artifact['original_filename'] }}</div>
                                                                </div>
                                                            @empty
                                                                <span class="muted-note">No workpapers uploaded</span>
                                                            @endforelse
                                                        </div>
                                                        @if (is_array($review['linked_finding'] ?? null))
                                                            <div class="metric-label" style="margin-top:10px;">Linked finding</div>
                                                            <div class="entity-title" style="margin-top:8px;">{{ $review['linked_finding']['title'] }}</div>
                                                            <div class="table-note">{{ ucfirst($review['linked_finding']['severity']) }}</div>
                                                            <div class="table-note">{{ $review['linked_finding']['description'] }}</div>
                                                        @else
                                                            <div class="table-note" style="margin-top:10px;">No finding linked</div>
                                                        @endif
                                                    </div>
                                                </div>

                                                @if ($can_manage_assessments)
                                                    <div class="action-cluster">
                                                        <details>
                                                            <summary class="button button-ghost" style="display:inline-flex;">Record review</summary>
                                                            <form class="upload-form" method="POST" action="{{ $review['review_update_route'] }}" style="margin-top:10px;">
                                                                @csrf
                                                                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                                                <input type="hidden" name="organization_id" value="{{ $campaign['organization_id'] }}">
                                                                <input type="hidden" name="scope_id" value="{{ $campaign['scope_id'] }}">
                                                                <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                                                <input type="hidden" name="menu" value="plugin.assessments-audits.root">
                                                                <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">

                                                                <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                                                                    <div class="field">
                                                                        <label class="field-label">Result</label>
                                                                        <select class="field-select" name="result">
                                                                            @foreach ($result_options as $resultValue => $resultLabel)
                                                                                <option value="{{ $resultValue }}" @selected($review['result'] === $resultValue)>{{ $resultLabel }}</option>
                                                                            @endforeach
                                                                        </select>
                                                                    </div>
                                                                    <div class="field">
                                                                        <label class="field-label">Reviewed on</label>
                                                                        <input class="field-input" type="date" name="reviewed_on" value="{{ $review['reviewed_on'] }}">
                                                                    </div>
                                                                    <div class="field" style="grid-column:1 / -1;">
                                                                        <label class="field-label">Test notes</label>
                                                                        <textarea class="field-input" name="test_notes" rows="4">{{ $review['test_notes'] }}</textarea>
                                                                    </div>
                                                                    <div class="field" style="grid-column:1 / -1;">
                                                                        <label class="field-label">Conclusion</label>
                                                                        <textarea class="field-input" name="conclusion" rows="3">{{ $review['conclusion'] }}</textarea>
                                                                    </div>
                                                                </div>

                                                                <div class="action-cluster" style="margin-top:12px;">
                                                                    <button class="button button-secondary" type="submit">Save review</button>
                                                                </div>
                                                            </form>
                                                        </details>

                                                        <details>
                                                            <summary class="button button-ghost" style="display:inline-flex;">Add workpaper</summary>
                                                            <form class="upload-form" method="POST" action="{{ $review['artifact_upload_route'] }}" enctype="multipart/form-data" style="margin-top:10px;">
                                                                @csrf
                                                                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                                                <input type="hidden" name="organization_id" value="{{ $campaign['organization_id'] }}">
                                                                <input type="hidden" name="scope_id" value="{{ $campaign['scope_id'] }}">
                                                                <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                                                <input type="hidden" name="menu" value="plugin.assessments-audits.root">
                                                                <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                                                <input type="hidden" name="artifact_type" value="workpaper">

                                                                <div class="field">
                                                                    <label class="field-label">Label</label>
                                                                    <input class="field-input" name="label" value="Assessment workpaper">
                                                                </div>
                                                                <div class="field">
                                                                    <label class="field-label">File</label>
                                                                    <input type="file" name="artifact" required>
                                                                </div>

                                                                <div class="action-cluster" style="margin-top:12px;">
                                                                    <button class="button button-secondary" type="submit">Upload workpaper</button>
                                                                </div>
                                                            </form>
                                                        </details>

                                                        @if (! is_array($review['linked_finding'] ?? null))
                                                            <details>
                                                                <summary class="button button-ghost" style="display:inline-flex;">Create finding</summary>
                                                                <form class="upload-form" method="POST" action="{{ $review['finding_store_route'] }}" style="margin-top:10px;">
                                                                    @csrf
                                                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                                                    <input type="hidden" name="organization_id" value="{{ $campaign['organization_id'] }}">
                                                                    <input type="hidden" name="scope_id" value="{{ $campaign['scope_id'] }}">
                                                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                                                    <input type="hidden" name="menu" value="plugin.assessments-audits.root">
                                                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">

                                                                    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                                                                        <div class="field">
                                                                            <label class="field-label">Title</label>
                                                                            <input class="field-input" name="title" value="{{ $review['control_name'] }} finding" required>
                                                                        </div>
                                                                        <div class="field">
                                                                            <label class="field-label">Severity</label>
                                                                            <select class="field-select" name="severity">
                                                                                <option value="low">Low</option>
                                                                                <option value="medium" selected>Medium</option>
                                                                                <option value="high">High</option>
                                                                                <option value="critical">Critical</option>
                                                                            </select>
                                                                        </div>
                                                                        <div class="field">
                                                                            <label class="field-label">Due on</label>
                                                                            <input class="field-input" type="date" name="due_on">
                                                                        </div>
                                                                        <div class="field" style="grid-column:1 / -1;">
                                                                            <label class="field-label">Description</label>
                                                                            <textarea class="field-input" name="description" rows="4" required>{{ $review['conclusion'] !== '' ? $review['conclusion'] : 'Assessment review identified a gap that requires remediation.' }}</textarea>
                                                                        </div>
                                                                    </div>

                                                                    <div class="action-cluster" style="margin-top:12px;">
                                                                        <button class="button button-secondary" type="submit">Create finding</button>
                                                                    </div>
                                                                </form>
                                                            </details>
                                                        @endif
                                                    </div>
                                                @endif
                                            </section>
                                        @endforeach
                                    </div>
                                </details>

                                @if ($can_manage_assessments)
                                    <details>
                                        <summary class="button button-ghost" style="display:inline-flex;">Edit</summary>
                                        <form class="upload-form" method="POST" action="{{ $campaign['update_route'] }}" style="margin-top:10px;">
                                            @csrf
                                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                            <input type="hidden" name="organization_id" value="{{ $campaign['organization_id'] }}">
                                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                            <input type="hidden" name="menu" value="plugin.assessments-audits.root">
                                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">

                                            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                                                <div class="field">
                                                    <label class="field-label">Title</label>
                                                    <input class="field-input" name="title" value="{{ $campaign['title'] }}" required>
                                                </div>
                                                <div class="field">
                                                    <label class="field-label">Framework</label>
                                                    <select class="field-select" name="framework_id">
                                                        <option value="">Any framework</option>
                                                        @foreach ($framework_options as $framework)
                                                            <option value="{{ $framework['id'] }}" @selected($campaign['framework_id'] === $framework['id'])>{{ $framework['label'] }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="field">
                                                    <label class="field-label">Scope</label>
                                                    <select class="field-select" name="scope_id">
                                                        <option value="">Organization-wide</option>
                                                        @foreach ($scope_options as $scope)
                                                            <option value="{{ $scope['id'] }}" @selected($campaign['scope_id'] === $scope['id'])>{{ $scope['name'] }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="field">
                                                    <label class="field-label">Status</label>
                                                    <select class="field-select" name="status">
                                                        @foreach ($status_options as $statusValue => $statusLabel)
                                                            <option value="{{ $statusValue }}" @selected($campaign['status'] === $statusValue)>{{ $statusLabel }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="field">
                                                    <label class="field-label">Starts on</label>
                                                    <input class="field-input" name="starts_on" type="date" value="{{ $campaign['starts_on'] }}" required>
                                                </div>
                                                <div class="field">
                                                    <label class="field-label">Ends on</label>
                                                    <input class="field-input" name="ends_on" type="date" value="{{ $campaign['ends_on'] }}" required>
                                                </div>
                                                <div class="field" style="grid-column:1 / -1;">
                                                    <label class="field-label">Summary</label>
                                                    <input class="field-input" name="summary" value="{{ $campaign['summary'] }}" required>
                                                </div>
                                                <div class="field" style="grid-column:1 / -1;">
                                                    <label class="field-label">Checklist controls</label>
                                                    <select class="field-select" name="control_ids[]" multiple size="{{ min(max(count($control_options), 3), 8) }}">
                                                        @foreach ($control_options as $control)
                                                            <option value="{{ $control['id'] }}" @selected(collect($campaign['controls'])->pluck('id')->contains($control['id']))>{{ $control['label'] }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="action-cluster" style="margin-top:12px;">
                                                <button class="button button-secondary" type="submit">Save changes</button>
                                            </div>
                                        </form>
                                    </details>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
