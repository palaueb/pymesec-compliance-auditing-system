<style>
    .pill-draft    { background: rgba(31,42,34,0.06);   color: var(--muted); }
    .pill-review   { background: rgba(245,158,11,0.14); color: #92400e; }
    .pill-approved { background: rgba(34,197,94,0.14);  color: #166534; }
    .pill-archived { background: rgba(31,42,34,0.06);   color: var(--muted); }

    details > summary { cursor: pointer; list-style: none; }
    details > summary::-webkit-details-marker { display: none; }
</style>

@php
    $hasFrameworks = $framework_options !== [];
    $hasRequirements = $requirement_options !== [];
    $selectedControl = is_array($selected_control ?? null) ? $selected_control : null;
    $isDetailMode = $selectedControl !== null;
@endphp

<section class="module-screen">
    @if ($can_manage_controls && ! $isDetailMode)
        <div class="surface-card" id="control-editor" hidden>
            <div class="row-between" style="margin-bottom:14px;">
                <div>
                    <div class="eyebrow">Create</div>
                    <div class="entity-title" style="font-size:24px;">New control</div>
                </div>
            </div>

            <form class="upload-form" method="POST" action="{{ $create_route }}">
                @csrf
                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                <input type="hidden" name="menu" value="plugin.controls-catalog.root">
                <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">

                <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                    <div class="field">
                        <label class="field-label" for="control-name">Name</label>
                        <input class="field-input" id="control-name" name="name" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="control-framework">Framework</label>
                        <select class="field-select" id="control-framework" name="framework_id" @if ($hasFrameworks) required @endif>
                            <option value="">{{ $hasFrameworks ? 'Select a framework' : 'Create a framework first' }}</option>
                            @foreach ($framework_options as $framework)
                                <option value="{{ $framework['id'] }}">{{ $framework['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="control-domain">Domain</label>
                        <input class="field-input" id="control-domain" name="domain" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="control-scope">Scope</label>
                        <select class="field-select" id="control-scope" name="scope_id">
                            <option value="">Organization-wide</option>
                            @foreach ($scope_options as $scope)
                                <option value="{{ $scope['id'] }}" @selected(($query['scope_id'] ?? null) === $scope['id'])>{{ $scope['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field" style="grid-column:1 / -1;">
                        <label class="field-label" for="control-evidence">Evidence summary</label>
                        <input class="field-input" id="control-evidence" name="evidence" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="control-owner">Initial owner actor</label>
                        <select class="field-select" id="control-owner" name="owner_actor_id">
                            <option value="">No owner</option>
                            @foreach ($owner_actor_options as $actor)
                                <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="action-cluster" style="margin-top:14px;">
                    <button class="button button-primary" type="submit" @disabled(! $hasFrameworks)>Create control</button>
                    @if (! $hasFrameworks)
                        <span class="muted-note">Add a framework before adding controls.</span>
                    @endif
                </div>
            </form>
        </div>
    @endif

    <div class="overview-grid" style="grid-template-columns:repeat(6, minmax(0, 1fr));">
        <div class="metric-card"><div class="metric-label">Controls</div><div class="metric-value">{{ count($controls) }}</div></div>
        <div class="metric-card"><div class="metric-label">Frameworks</div><div class="metric-value">{{ count($frameworks) }}</div></div>
        <div class="metric-card"><div class="metric-label">Adopted</div><div class="metric-value">{{ collect($frameworks)->where('adoption_status', 'active')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">Onboarding</div><div class="metric-value">{{ collect($frameworks)->where('adoption_status', 'in-progress')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">Approved</div><div class="metric-value">{{ collect($controls)->where('state', 'approved')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">In Review</div><div class="metric-value">{{ collect($controls)->where('state', 'review')->count() }}</div></div>
    </div>


    @if ($isDetailMode)
        <div class="table-card">
            <div class="surface-note" style="margin-bottom:16px;">
                Control Detail keeps requirement mappings, ownership, evidence, workflow transitions, and control editing in one workspace. Use the control list to browse the catalog and open the control you want to work on.
            </div>

            <div class="screen-header">
                <div>
                    <div class="eyebrow">Control Detail</div>
                    <h2 class="screen-title" style="font-size:24px;">{{ $selectedControl['name'] }}</h2>
                    <p class="screen-subtitle">{{ $selectedControl['framework'] }} · {{ $selectedControl['domain'] }} · {{ $selectedControl['id'] }}</p>
                </div>
                <div class="action-cluster">
                    @php $ctrlStatePill = match($selectedControl['state']) { 'draft' => 'pill-draft', 'review' => 'pill-review', 'approved' => 'pill-approved', 'archived' => 'pill-archived', default => '' }; @endphp
                    <span class="pill {{ $ctrlStatePill }}">{{ $selectedControl['state'] }}</span>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
                <div class="metric-card">
                    <div class="metric-label">Framework</div>
                    <div class="metric-value">{{ $selectedControl['framework'] }}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Domain</div>
                    <div class="metric-value">{{ $selectedControl['domain'] }}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Owners</div>
                    <div class="metric-value">{{ count($selectedControl['owner_assignments']) }}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Artifacts</div>
                    <div class="metric-value">{{ count($selectedControl['artifacts']) }}</div>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:16px;">
                    <div class="field-label">Coverage</div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($selectedControl['requirements'] as $requirement)
                            <div class="data-item">
                                <div class="entity-title">{{ $requirement['requirement_code'] }} · {{ $requirement['requirement_title'] }}</div>
                                <div class="table-note">{{ $requirement['framework_name'] }} · {{ ucfirst($requirement['coverage']) }}</div>
                                @if ($requirement['notes'] !== '')
                                    <div class="table-note">{{ $requirement['notes'] }}</div>
                                @endif
                            </div>
                        @empty
                            <span class="muted-note">No requirements linked.</span>
                        @endforelse
                    </div>

                    @if ($can_manage_controls)
                        <details style="margin-top:12px;">
                            <summary class="button button-secondary" style="display:inline-flex;">Link requirement</summary>
                            <form class="upload-form" method="POST" action="{{ $selectedControl['attach_requirement_route'] }}" style="margin-top:12px;">
                                @csrf
                                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                <input type="hidden" name="menu" value="plugin.controls-catalog.root">
                                <input type="hidden" name="control_id" value="{{ $selectedControl['id'] }}">
                                <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                <select class="field-select" name="requirement_id" @if ($hasRequirements) required @endif>
                                    <option value="">{{ $hasRequirements ? 'Link a requirement' : 'Add a requirement first' }}</option>
                                    @foreach ($requirement_options as $requirement)
                                        <option value="{{ $requirement['id'] }}">{{ $requirement['label'] }}</option>
                                    @endforeach
                                </select>
                                <select class="field-select" name="coverage">
                                    <option value="supports">Supports</option>
                                    <option value="partial">Partial</option>
                                    <option value="full">Full</option>
                                </select>
                                <input type="text" name="notes" placeholder="Coverage note">
                                <button class="button button-secondary" type="submit" @disabled(! $hasRequirements)>Link requirement</button>
                            </form>
                        </details>
                    @endif
                </div>

                <div class="surface-card" style="padding:16px;">
                    <div class="field-label">Owners</div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($selectedControl['owner_assignments'] as $owner)
                            <div class="data-item">
                                <div class="entity-title">{{ $owner['display_name'] }}</div>
                                <div class="table-note">{{ $owner['kind'] }}</div>
                                @if ($can_manage_controls)
                                    <form method="POST" action="{{ str_replace('__ASSIGNMENT__', $owner['assignment_id'], $selectedControl['owner_remove_route']) }}" style="margin-top:8px;">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="menu" value="plugin.controls-catalog.root">
                                        <input type="hidden" name="control_id" value="{{ $selectedControl['id'] }}">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <button class="button button-ghost" type="submit">Remove owner</button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <span class="muted-note">No owner assigned.</span>
                        @endforelse
                    </div>
                </div>

                <div class="surface-card" style="padding:16px;">
                    <div class="field-label">Evidence</div>
                    <div class="body-copy" style="margin-top:10px;">{{ $selectedControl['evidence'] }}</div>
                    <div class="data-stack" style="margin-top:12px;">
                        @forelse ($selectedControl['artifacts'] as $artifact)
                            <div class="data-item">
                                <div class="row-between" style="align-items:flex-start; gap:12px;">
                                    <div>
                                        <div class="entity-title">{{ $artifact['label'] }}</div>
                                        <div class="table-note">{{ $artifact['original_filename'] }} · {{ $artifact['artifact_type'] }}</div>
                                    </div>
                                    <form method="POST" action="{{ route('plugin.evidence-management.promote', ['artifactId' => $artifact['id']]) }}">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                        <input type="hidden" name="scope_id" value="{{ $selectedControl['scope_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <button class="button button-ghost" type="submit">Promote to evidence</button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <span class="muted-note">No artifacts yet.</span>
                        @endforelse
                    </div>

                    @if ($can_manage_controls)
                        <details style="margin-top:12px;">
                            <summary class="button button-secondary" style="display:inline-flex;">Attach evidence</summary>
                            <form class="upload-form" method="POST" action="{{ $selectedControl['artifact_upload_route'] }}" enctype="multipart/form-data" style="margin-top:12px;">
                                @csrf
                                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                <input type="hidden" name="menu" value="plugin.controls-catalog.root">
                                <input type="hidden" name="control_id" value="{{ $selectedControl['id'] }}">
                                <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                <input type="hidden" name="artifact_type" value="evidence">
                                <input type="text" name="label" placeholder="Evidence label">
                                <input type="file" name="artifact" required>
                                <button class="button button-secondary" type="submit">Attach evidence</button>
                            </form>
                        </details>
                    @endif
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:16px;">
                    <div class="field-label">Workflow</div>
                    @if ($selectedControl['transitions'] !== [])
                        <div class="action-cluster" style="margin-top:12px;">
                            @foreach ($selectedControl['transitions'] as $transition)
                                <form method="POST" action="{{ str_replace('__TRANSITION__', $transition, $selectedControl['transition_route']) }}">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.controls-catalog.root">
                                    <input type="hidden" name="control_id" value="{{ $selectedControl['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <button class="button button-secondary" type="submit">{{ ucwords(str_replace('-', ' ', $transition)) }}</button>
                                </form>
                            @endforeach
                        </div>
                    @else
                        <div class="body-copy" style="margin-top:10px;">View-only access.</div>
                    @endif
                </div>

                @if ($can_manage_controls)
                    <div class="surface-card" style="padding:16px;">
                        <details>
                            <summary class="button button-ghost" style="display:inline-flex; width:fit-content;">Edit control details</summary>
                            <form class="upload-form" method="POST" action="{{ $selectedControl['update_route'] }}" style="margin-top:14px;">
                                @csrf
                                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                <input type="hidden" name="menu" value="plugin.controls-catalog.root">
                                <input type="hidden" name="control_id" value="{{ $selectedControl['id'] }}">
                                <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                <div class="field">
                                    <label class="field-label">Name</label>
                                    <input class="field-input" name="name" value="{{ $selectedControl['name'] }}" required>
                                </div>
                                <div class="field">
                                    <label class="field-label">Framework</label>
                                    <select class="field-select" name="framework_id" @if ($hasFrameworks) required @endif>
                                        <option value="">{{ $hasFrameworks ? 'Select a framework' : 'Create a framework first' }}</option>
                                        @foreach ($framework_options as $framework)
                                            <option value="{{ $framework['id'] }}" @selected($selectedControl['framework_id'] === $framework['id'])>{{ $framework['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">Domain</label>
                                    <input class="field-input" name="domain" value="{{ $selectedControl['domain'] }}" required>
                                </div>
                                <div class="field">
                                    <label class="field-label">Scope</label>
                                    <select class="field-select" name="scope_id">
                                        <option value="">Organization-wide</option>
                                        @foreach ($scope_options as $scope)
                                            <option value="{{ $scope['id'] }}" @selected($selectedControl['scope_id'] === $scope['id'])>{{ $scope['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">Evidence summary</label>
                                    <input class="field-input" name="evidence" value="{{ $selectedControl['evidence'] }}" required>
                                </div>
                                <div class="field">
                                    <label class="field-label">Add owner actor</label>
                                    <select class="field-select" name="owner_actor_id">
                                        <option value="">Do not add owner</option>
                                        @foreach ($owner_actor_options as $actor)
                                            <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <div class="table-note">Selecting an actor adds another owner instead of replacing the current set.</div>
                                </div>
                                <div class="action-cluster">
                                    <button class="button button-secondary" type="submit" @disabled(! $hasFrameworks)>Save changes</button>
                                </div>
                            </form>
                        </details>
                    </div>
                @endif
            </div>
        </div>
    @else
        <div class="surface-card">
            <div class="entity-title">Control list</div>
            <div class="table-note" style="margin-top:6px;">This list stays focused on catalog browsing, framework context, owner summary, and Open. Use Control Detail to manage requirement mappings, evidence, workflow, and control maintenance.</div>
        </div>

        <div class="table-card">
            <table class="entity-table">
                <thead>
                    <tr>
                        <th>Control</th>
                        <th>Framework</th>
                        <th>Owner</th>
                        <th>Evidence</th>
                        <th>State</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($controls as $control)
                        <tr>
                            <td>
                                <div class="entity-title">{{ $control['name'] }}</div>
                                <div class="entity-id">{{ $control['id'] }}</div>
                                <div class="table-note">{{ $control['domain'] }}</div>
                            </td>
                            <td>{{ $control['framework'] }}</td>
                            <td>
                                @if (($control['owner_assignments'] ?? []) !== [])
                                    <div>{{ $control['owner_assignments'][0]['display_name'] }}</div>
                                    @if (count($control['owner_assignments']) > 1)
                                        <div class="table-note">+{{ count($control['owner_assignments']) - 1 }} more owner{{ count($control['owner_assignments']) > 2 ? 's' : '' }}</div>
                                    @else
                                        <div class="table-note">{{ $control['owner_assignments'][0]['kind'] }}</div>
                                    @endif
                                @else
                                    <span class="muted-note">No owner assigned</span>
                                @endif
                            </td>
                            <td>{{ $control['evidence'] }}</td>
                            <td>
                                @php $sCtrlPill = match($control['state']) { 'draft' => 'pill-draft', 'review' => 'pill-review', 'approved' => 'pill-approved', 'archived' => 'pill-archived', default => '' }; @endphp
                                <span class="pill {{ $sCtrlPill }}">{{ $control['state'] }}</span>
                            </td>
                            <td>
                                <div class="action-cluster">
                                    <a class="button button-secondary" href="{{ $control['open_url'] }}&{{ http_build_query(['context_label' => 'Controls', 'context_back_url' => $controls_list_url]) }}">Open</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center; padding:28px;">
                                <span class="muted-note">No controls yet.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</section>
