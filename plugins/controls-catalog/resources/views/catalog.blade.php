<section class="module-screen">
    @php
        $hasFrameworks = $framework_options !== [];
        $hasRequirements = $requirement_options !== [];
    @endphp

    @if ($can_manage_controls)
        <details class="surface-card" id="control-editor">
            <summary class="button button-primary" style="display:inline-flex;">New control</summary>

            <form class="upload-form" method="POST" action="{{ $create_route }}" style="margin-top:14px;">
                @csrf
                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
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
                        <label class="field-label" for="control-owner">Owner actor</label>
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
        </details>
    @endif

    <div class="overview-grid">
        <div class="metric-card"><div class="metric-label">Controls</div><div class="metric-value">{{ count($controls) }}</div></div>
        <div class="metric-card"><div class="metric-label">Frameworks</div><div class="metric-value">{{ count($frameworks) }}</div></div>
        <div class="metric-card"><div class="metric-label">Requirements</div><div class="metric-value">{{ count($requirements) }}</div></div>
        <div class="metric-card"><div class="metric-label">In Review</div><div class="metric-value">{{ collect($controls)->where('state', 'review')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">Artifacts</div><div class="metric-value">{{ collect($controls)->sum(fn ($control) => count($control['artifacts'])) }}</div></div>
        <div class="metric-card"><div class="metric-label">Approved</div><div class="metric-value">{{ collect($controls)->where('state', 'approved')->count() }}</div></div>
    </div>

    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
        <div class="surface-card">
            <div class="row-between" style="margin-bottom:12px;">
                <div>
                    <div class="eyebrow">Frameworks</div>
                    <div class="screen-title" style="font-size:22px;">Reusable frameworks</div>
                </div>
            </div>

            <div class="data-stack" style="margin-bottom:14px;">
                @forelse ($frameworks as $framework)
                    <div class="data-item">
                        <div class="entity-title">{{ $framework['code'] }} · {{ $framework['name'] }}</div>
                        @if ($framework['description'] !== '')
                            <div class="table-note">{{ $framework['description'] }}</div>
                        @endif
                    </div>
                @empty
                    <span class="muted-note">No frameworks yet.</span>
                @endforelse
            </div>

            @if ($can_manage_controls)
                <details>
                    <summary class="button button-secondary" style="display:inline-flex;">Add framework</summary>
                    <form class="upload-form" method="POST" action="{{ $create_framework_route }}" style="margin-top:12px;">
                        @csrf
                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                        <input type="hidden" name="menu" value="plugin.controls-catalog.root">
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
                </details>
            @endif
        </div>

        <div class="surface-card">
            <div class="row-between" style="margin-bottom:12px;">
                <div>
                    <div class="eyebrow">Requirements</div>
                    <div class="screen-title" style="font-size:22px;">Coverage library</div>
                </div>
            </div>

            <div class="data-stack" style="margin-bottom:14px;">
                @forelse ($requirements as $requirement)
                    <div class="data-item">
                        <div class="entity-title">{{ $requirement['code'] }} · {{ $requirement['title'] }}</div>
                        <div class="table-note">{{ $requirement['framework_name'] }}</div>
                        @if ($requirement['description'] !== '')
                            <div class="table-note">{{ $requirement['description'] }}</div>
                        @endif
                    </div>
                @empty
                    <span class="muted-note">No requirements yet.</span>
                @endforelse
            </div>

            @if ($can_manage_controls)
                <details>
                    <summary class="button button-secondary" style="display:inline-flex;">Add requirement</summary>
                    <form class="upload-form" method="POST" action="{{ $create_requirement_route }}" style="margin-top:12px;">
                        @csrf
                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                        <input type="hidden" name="menu" value="plugin.controls-catalog.root">
                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">

                        <div class="field">
                            <label class="field-label" for="requirement-framework">Framework</label>
                            <select class="field-select" id="requirement-framework" name="framework_id" @if ($hasFrameworks) required @endif>
                                <option value="">{{ $hasFrameworks ? 'Select a framework' : 'Create a framework first' }}</option>
                                @foreach ($framework_options as $framework)
                                    <option value="{{ $framework['id'] }}">{{ $framework['label'] }}</option>
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
                </details>
            @endif
        </div>
    </div>

    <div class="table-card">
        <table class="entity-table">
            <thead>
                <tr>
                    <th>Control</th>
                    <th>Framework</th>
                    <th>Coverage</th>
                    <th>Owner</th>
                    <th>Evidence</th>
                    <th>State</th>
                    <th>{{ $can_manage_controls ? 'Transitions' : 'Access' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($controls as $control)
                    <tr>
                        <td>
                            <div class="entity-title">{{ $control['name'] }}</div>
                            <div class="entity-id">{{ $control['id'] }}</div>
                            <div class="table-note">{{ $control['domain'] }}</div>
                        </td>
                        <td>{{ $control['framework'] }}</td>
                        <td>
                            <div class="data-stack">
                                @forelse ($control['requirements'] as $requirement)
                                    <div class="data-item">
                                        <div class="entity-title">{{ $requirement['requirement_code'] }} · {{ $requirement['requirement_title'] }}</div>
                                        <div class="table-note">{{ $requirement['framework_name'] }} · {{ ucfirst($requirement['coverage']) }}</div>
                                        @if ($requirement['notes'] !== '')
                                            <div class="table-note">{{ $requirement['notes'] }}</div>
                                        @endif
                                    </div>
                                @empty
                                    <span class="muted-note">No requirements linked</span>
                                @endforelse
                            </div>

                            @if ($can_manage_controls)
                                <form class="upload-form" method="POST" action="{{ $control['attach_requirement_route'] }}" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.controls-catalog.root">
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
                            @endif
                        </td>
                        <td>
                            @if ($control['owner_assignment'] !== null)
                                <div>{{ $control['owner_assignment']['display_name'] }}</div>
                                <div class="table-note">{{ $control['owner_assignment']['kind'] }}</div>
                            @else
                                <span class="muted-note">No owner assigned</span>
                            @endif
                        </td>
                        <td>
                            <div>{{ $control['evidence'] }}</div>
                            <div class="data-stack" style="margin-top:8px;">
                                @forelse ($control['artifacts'] as $artifact)
                                    <div class="data-item">
                                        <div class="entity-title">{{ $artifact['label'] }}</div>
                                        <div class="table-note">{{ $artifact['original_filename'] }} · {{ $artifact['artifact_type'] }}</div>
                                    </div>
                                @empty
                                    <span class="muted-note">No artifacts yet</span>
                                @endforelse
                            </div>
                            @if ($can_manage_controls)
                                <form class="upload-form" method="POST" action="{{ $control['artifact_upload_route'] }}" enctype="multipart/form-data" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.controls-catalog.root">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <input type="hidden" name="artifact_type" value="evidence">
                                    <input type="text" name="label" placeholder="Evidence label">
                                    <input type="file" name="artifact" required>
                                    <button class="button button-secondary" type="submit">Attach Evidence</button>
                                </form>
                            @endif
                        </td>
                        <td><span class="pill">{{ $control['state'] }}</span></td>
                        <td>
                            @if ($control['transitions'] !== [])
                                <div class="action-cluster">
                                    @foreach ($control['transitions'] as $transition)
                                        <form method="POST" action="{{ str_replace('__TRANSITION__', $transition, $control['transition_route']) }}">
                                            @csrf
                                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                            <input type="hidden" name="menu" value="plugin.controls-catalog.root">
                                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                            <button class="button button-secondary" type="submit">{{ ucwords(str_replace('-', ' ', $transition)) }}</button>
                                        </form>
                                    @endforeach
                                </div>
                            @else
                                <span class="muted-note">View-only access</span>
                            @endif

                            @if ($can_manage_controls)
                                <details style="margin-top:10px;">
                                    <summary class="button button-ghost" style="display:inline-flex;">Edit</summary>
                                    <form class="upload-form" method="POST" action="{{ $control['update_route'] }}" style="margin-top:10px;">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="menu" value="plugin.controls-catalog.root">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <div class="field">
                                            <label class="field-label">Name</label>
                                            <input class="field-input" name="name" value="{{ $control['name'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Framework</label>
                                            <select class="field-select" name="framework_id" @if ($hasFrameworks) required @endif>
                                                <option value="">{{ $hasFrameworks ? 'Select a framework' : 'Create a framework first' }}</option>
                                                @foreach ($framework_options as $framework)
                                                    <option value="{{ $framework['id'] }}" @selected($control['framework_id'] === $framework['id'])>{{ $framework['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Domain</label>
                                            <input class="field-input" name="domain" value="{{ $control['domain'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Scope</label>
                                            <select class="field-select" name="scope_id">
                                                <option value="">Organization-wide</option>
                                                @foreach ($scope_options as $scope)
                                                    <option value="{{ $scope['id'] }}" @selected($control['scope_id'] === $scope['id'])>{{ $scope['name'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Evidence summary</label>
                                            <input class="field-input" name="evidence" value="{{ $control['evidence'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Owner actor</label>
                                            <select class="field-select" name="owner_actor_id">
                                                <option value="">Keep current owner</option>
                                                @foreach ($owner_actor_options as $actor)
                                                    <option value="{{ $actor['id'] }}" @selected(($control['owner_assignment']['id'] ?? null) === $actor['id'])>{{ $actor['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="action-cluster">
                                            <button class="button button-secondary" type="submit" @disabled(! $hasFrameworks)>Save changes</button>
                                        </div>
                                    </form>
                                </details>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
