<style>
    .pill-draft    { background: rgba(31,42,34,0.06);   color: var(--muted); }
    .pill-review   { background: rgba(245,158,11,0.14); color: #92400e; }
    .pill-approved { background: rgba(34,197,94,0.14);  color: #166534; }
    .pill-archived { background: rgba(31,42,34,0.06);   color: var(--muted); }

    details > summary { cursor: pointer; list-style: none; }
    details > summary::-webkit-details-marker { display: none; }
</style>

@php
    $hasFrameworks = $frameworks !== [];
@endphp

<section class="module-screen">
    <div class="overview-grid" style="grid-template-columns:repeat(6, minmax(0, 1fr));">
        <div class="metric-card"><div class="metric-label">Frameworks</div><div class="metric-value">{{ count($frameworks) }}</div></div>
        <div class="metric-card"><div class="metric-label">Adopted</div><div class="metric-value">{{ collect($frameworks)->where('adoption_status', 'active')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">Onboarding</div><div class="metric-value">{{ collect($frameworks)->where('adoption_status', 'in-progress')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">Requirements</div><div class="metric-value">{{ count($requirements) }}</div></div>
        <div class="metric-card"><div class="metric-label">Linked controls</div><div class="metric-value">{{ collect($frameworks)->sum('linked_control_count') }}</div></div>
        <div class="metric-card"><div class="metric-label">Mandates</div><div class="metric-value">{{ collect($frameworks)->filter(fn ($framework) => is_array($framework['mandate_document'] ?? null))->count() }}</div></div>
    </div>

    <div class="surface-card">
        <div class="surface-note">
            Activate each framework from its own card. When adoption becomes active, upload the signed mandate document from company leadership so the request is traceable by framework, date, and scope.
        </div>
    </div>

    @if ($can_manage_controls)
        <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
            <div class="surface-card">
                <div class="row-between" style="margin-bottom:12px;">
                    <div>
                        <div class="eyebrow">Frameworks</div>
                        <div class="entity-title" style="font-size:20px;">Add framework</div>
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
                        <label class="field-label" for="framework-code">Code</label>
                        <input class="field-input" id="framework-code" name="code" placeholder="ISO 27001" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="framework-name">Name</label>
                        <input class="field-input" id="framework-name" name="name" placeholder="ISO 27001:2022" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="framework-description">What it covers</label>
                        <input class="field-input" id="framework-description" name="description" placeholder="Security management baseline">
                    </div>

                    <div class="action-cluster" style="margin-top:12px;">
                        <button class="button button-secondary" type="submit">Add framework</button>
                    </div>
                </form>
            </div>

            <div class="surface-card">
                <div class="row-between" style="margin-bottom:12px;">
                    <div>
                        <div class="eyebrow">Requirements</div>
                        <div class="entity-title" style="font-size:20px;">Add requirement</div>
                    </div>
                </div>

                <div class="surface-note" style="margin-bottom:12px;">
                    Add requirements directly inside a framework card when you want them already scoped to that framework. Use this global form only when starting from scratch.
                </div>

                <form class="upload-form" method="POST" action="{{ $create_requirement_route }}">
                    @csrf
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                    <input type="hidden" name="menu" value="plugin.controls-catalog.framework-adoption">
                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">

                    <div class="field">
                        <label class="field-label" for="requirement-framework">Framework</label>
                        <select class="field-select" id="requirement-framework" name="framework_id" @if ($hasFrameworks) required @endif>
                            <option value="">{{ $hasFrameworks ? 'Select a framework' : 'Create a framework first' }}</option>
                            @foreach ($frameworks as $framework)
                                <option value="{{ $framework['id'] }}">{{ $framework['code'] }} · {{ $framework['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="requirement-code">Requirement code</label>
                        <input class="field-input" id="requirement-code" name="code" placeholder="A.5.18" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="requirement-title">Title</label>
                        <input class="field-input" id="requirement-title" name="title" placeholder="Access rights" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="requirement-description">What to verify</label>
                        <input class="field-input" id="requirement-description" name="description" placeholder="Review who gets access and why">
                    </div>

                    <div class="action-cluster" style="margin-top:12px;">
                        <button class="button button-secondary" type="submit" @disabled(! $hasFrameworks)>Add requirement</button>
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
                        <span class="pill {{ $adoptionPill }}">{{ str_replace('-', ' ', $framework['adoption_status']) }}</span>
                        <span class="table-note">{{ $framework['adoption_scope_label'] }}</span>
                    </div>
                </div>

                <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
                    <div class="metric-card"><div class="metric-label">Requirements</div><div class="metric-value">{{ $framework['requirement_count'] }}</div></div>
                    <div class="metric-card"><div class="metric-label">Mapped</div><div class="metric-value">{{ $framework['mapped_requirement_count'] }}</div></div>
                    <div class="metric-card"><div class="metric-label">Controls</div><div class="metric-value">{{ $framework['linked_control_count'] }}</div></div>
                    <div class="metric-card"><div class="metric-label">Coverage</div><div class="metric-value">{{ $framework['coverage_percent'] }}%</div></div>
                </div>

                @if ($framework['description'] !== '')
                    <div class="surface-note" style="margin-top:12px;">{{ $framework['description'] }}</div>
                @endif

                <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr)); margin-top:16px;">
                    <div class="surface-card" style="padding:16px;">
                        <div class="field-label">Adoption record</div>
                        <div class="data-stack" style="margin-top:10px;">
                            <div class="data-item">
                                <div class="entity-title">Workspace scope</div>
                                <div class="table-note">{{ $framework['adoption_scope_label'] }}</div>
                            </div>
                            <div class="data-item">
                                <div class="entity-title">Adopted on</div>
                                <div class="table-note">{{ $framework['adopted_at'] !== '' ? $framework['adopted_at'] : 'Not set' }}</div>
                            </div>
                            <div class="data-item">
                                <div class="entity-title">Target level</div>
                                <div class="table-note">{{ $framework['target_level'] !== '' ? ucfirst($framework['target_level']) : 'Not set' }}</div>
                            </div>
                        </div>

                        @if ($mandateDocument !== null)
                            <div class="data-item" style="margin-top:12px;">
                                <div class="entity-title">{{ $mandateDocument['label'] }}</div>
                                <div class="table-note">{{ $mandateDocument['original_filename'] }}</div>
                                <div class="table-note">Uploaded {{ $mandateDocument['created_at'] }}</div>
                            </div>
                        @else
                            <div class="surface-note" style="margin-top:12px;">
                                No signed mandate document uploaded yet.
                            </div>
                        @endif
                    </div>

                    <div class="surface-card" style="padding:16px;">
                        <div class="field-label">Requirements library</div>
                        <div class="table-note" style="margin-top:6px;">
                            {{ count($framework['requirements']) }} requirements are currently attached to this framework.
                        </div>

                        <details style="margin-top:12px;">
                            <summary class="button button-ghost" style="display:inline-flex;">Browse requirements</summary>
                            <div class="data-stack" style="margin-top:12px;">
                                @forelse ($framework['requirements'] as $requirement)
                                    <div class="data-item">
                                        <div class="entity-title">{{ $requirement['code'] }} · {{ $requirement['title'] }}</div>
                                        @if ($requirement['description'] !== '')
                                            <div class="table-note">{{ $requirement['description'] }}</div>
                                        @endif
                                    </div>
                                @empty
                                    <span class="muted-note">No requirements yet.</span>
                                @endforelse
                            </div>
                        </details>

                        @if ($can_manage_controls)
                            <details style="margin-top:12px;">
                                <summary class="button button-secondary" style="display:inline-flex;">Add requirement to {{ $framework['code'] }}</summary>
                                <form class="upload-form" method="POST" action="{{ $create_requirement_route }}" style="margin-top:12px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.controls-catalog.framework-adoption">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <input type="hidden" name="framework_id" value="{{ $framework['id'] }}">

                                    <div class="field">
                                        <label class="field-label">Requirement code</label>
                                        <input class="field-input" name="code" placeholder="A.5.18" required>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Title</label>
                                        <input class="field-input" name="title" placeholder="Access rights" required>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">What to verify</label>
                                        <input class="field-input" name="description" placeholder="Review who gets access and why">
                                    </div>

                                    <div class="action-cluster" style="margin-top:12px;">
                                        <button class="button button-secondary" type="submit">Add requirement</button>
                                    </div>
                                </form>
                            </details>
                        @endif
                    </div>
                </div>

                @if ($can_manage_controls)
                    <div class="surface-card" style="padding:16px; margin-top:16px;">
                        <div class="field-label">Manage adoption</div>
                        <div class="table-note" style="margin-top:6px;">
                            Upload the signed mandate document when the framework becomes active. If a mandate is already on file, you can update the adoption status without uploading it again.
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
                                    <label class="field-label">Scope</label>
                                    <select class="field-select" name="scope_id">
                                        <option value="">Organization-wide</option>
                                        @foreach ($scope_options as $scope)
                                            <option value="{{ $scope['id'] }}" @selected($framework['adoption_scope_id'] === $scope['id'])>{{ $scope['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">Adoption status</label>
                                    <select class="field-select" name="status" required>
                                        @foreach ($framework_adoption_status_options as $statusValue => $statusLabel)
                                            <option value="{{ $statusValue }}" @selected($framework['adoption_status'] === $statusValue)>{{ $statusLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">Target level</label>
                                    <select class="field-select" name="target_level">
                                        <option value="">Not set</option>
                                        @foreach ($framework_target_level_options as $levelValue => $levelLabel)
                                            <option value="{{ $levelValue }}" @selected($framework['target_level'] === $levelValue)>{{ $levelLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">Adopted on</label>
                                    <input class="field-input" type="date" name="adopted_at" value="{{ $framework['adopted_at'] !== '' ? substr($framework['adopted_at'], 0, 10) : '' }}">
                                </div>
                                <div class="field" style="grid-column:1 / -1;">
                                    <label class="field-label">Signed mandate document</label>
                                    <input class="field-input" type="file" name="mandate_document" @if ($framework['adoption_status'] === 'active' && $mandateDocument === null) required @endif>
                                    <div class="table-note" style="margin-top:6px;">
                                        Upload the signed document from company leadership that records who requested the framework adoption, when it was approved, and under which mandate.
                                    </div>
                                </div>
                            </div>

                            <div class="action-cluster" style="margin-top:12px;">
                                <button class="button button-secondary" type="submit">Save adoption</button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        @empty
            <div class="surface-card">
                <span class="muted-note">No frameworks yet.</span>
            </div>
        @endforelse
    </div>
</section>
