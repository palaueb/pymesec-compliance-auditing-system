<section class="module-screen">
    <div class="surface-note">
        Automation packs define installable compliance automations. Use this catalog to register packs, control lifecycle state, and track operational posture.
    </div>

    <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr)); margin-top:12px;">
        <div class="metric-card">
            <div class="metric-label">Packs</div>
            <div class="metric-value">{{ count($packs) }}</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Enabled</div>
            <div class="metric-value">{{ count(array_filter($packs, static fn(array $pack): bool => $pack['is_enabled'] === '1')) }}</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Installed</div>
            <div class="metric-value">{{ count(array_filter($packs, static fn(array $pack): bool => $pack['is_installed'] === '1')) }}</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Failing</div>
            <div class="metric-value">{{ count(array_filter($packs, static fn(array $pack): bool => $pack['health_state'] === 'failing')) }}</div>
        </div>
    </div>

    <div class="surface-card" style="margin-top:14px; padding:14px;">
        <div class="row-between">
            <div class="entity-title">Automation pack catalog</div>
            <span class="table-note">Install/enable/disable packs and track health posture</span>
        </div>

        <div style="overflow-x:auto; margin-top:10px;">
            <table class="entity-table" style="min-width:1180px; margin-top:0;">
                <thead>
                    <tr>
                        <th style="min-width:420px;">Pack</th>
                        <th style="min-width:140px;">Lifecycle</th>
                        <th style="min-width:130px;">Health</th>
                        <th style="min-width:120px;">Version</th>
                        <th style="min-width:140px;">Scope</th>
                        <th style="min-width:100px;">Open</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($packs as $pack)
                        <tr>
                            <td style="min-width:420px; vertical-align:top;">
                                <div class="entity-title">{{ $pack['name'] }}</div>
                                <div class="table-note" style="overflow-wrap:anywhere;">{{ $pack['pack_key'] }}</div>
                                <div class="table-note" style="overflow-wrap:anywhere;">{{ $pack['summary'] !== '' ? $pack['summary'] : 'No summary' }}</div>
                            </td>
                            <td style="min-width:140px; vertical-align:top;">{{ $pack['lifecycle_state_label'] }}</td>
                            <td style="min-width:130px; vertical-align:top;">{{ $pack['health_state_label'] }}</td>
                            <td style="min-width:120px; vertical-align:top;">{{ $pack['version'] !== '' ? $pack['version'] : 'Not set' }}</td>
                            <td style="min-width:140px; vertical-align:top;">{{ $pack['scope_id'] !== '' ? $pack['scope_id'] : 'Org-wide' }}</td>
                            <td style="min-width:100px; vertical-align:top;"><a class="button button-ghost" href="{{ $pack['open_url'] }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No automation packs registered yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if (is_array($selected_pack))
        <div class="surface-card" style="margin-top:14px; padding:14px;">
            <div class="row-between">
                <div>
                    <div class="eyebrow">Automation pack</div>
                    <h2 class="screen-title" style="font-size:26px;">{{ $selected_pack['name'] }}</h2>
                    <div class="table-note">{{ $selected_pack['pack_key'] }}</div>
                </div>
                <div class="action-cluster">
                    <span class="pill">{{ $selected_pack['lifecycle_state_label'] }}</span>
                    <span class="pill">{{ $selected_pack['health_state_label'] }}</span>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(3, minmax(0, 1fr)); margin-top:12px;">
                <div class="metric-card">
                    <div class="metric-label">Installed</div>
                    <div class="metric-value">{{ $selected_pack['is_installed'] === '1' ? 'Yes' : 'No' }}</div>
                    <div class="meta-copy">{{ $selected_pack['installed_at'] !== '' ? $selected_pack['installed_at'] : 'Not recorded' }}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Enabled</div>
                    <div class="metric-value">{{ $selected_pack['is_enabled'] === '1' ? 'Yes' : 'No' }}</div>
                    <div class="meta-copy">{{ $selected_pack['enabled_at'] !== '' ? $selected_pack['enabled_at'] : 'Not recorded' }}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Last run</div>
                    <div class="metric-value">{{ $selected_pack['last_run_at'] !== '' ? $selected_pack['last_run_at'] : 'Never' }}</div>
                    <div class="meta-copy">Failure: {{ $selected_pack['last_failure_at'] !== '' ? $selected_pack['last_failure_at'] : 'None' }}</div>
                </div>
            </div>

            <div class="table-note" style="margin-top:12px;">
                Provider {{ ucfirst($selected_pack['provider_type']) }} · Provenance {{ ucfirst($selected_pack['provenance_type']) }} · Owner {{ $selected_pack['owner_principal_id'] !== '' ? $selected_pack['owner_principal_id'] : 'Not set' }}
            </div>
            <div class="table-note">
                Source:
                @if ($selected_pack['source_ref'] !== '')
                    <a href="{{ $selected_pack['source_ref'] }}" target="_blank" rel="noreferrer">{{ $selected_pack['source_ref'] }}</a>
                @else
                    Not set
                @endif
            </div>
            @if ($selected_pack['last_failure_reason'] !== '')
                <div class="table-note">Last failure reason: {{ $selected_pack['last_failure_reason'] }}</div>
            @endif

            @if ($can_manage_packs)
                <div class="action-cluster" style="margin-top:12px;">
                    <form method="POST" action="{{ route('plugin.automation-catalog.install', ['packId' => $selected_pack['id']]) }}">
                        @csrf
                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                        <input type="hidden" name="scope_id" value="{{ $query['scope_id'] ?? '' }}">
                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                        <input type="hidden" name="menu" value="plugin.automation-catalog.root">
                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                        <button class="button button-ghost" type="submit">Install</button>
                    </form>
                    <form method="POST" action="{{ route('plugin.automation-catalog.enable', ['packId' => $selected_pack['id']]) }}">
                        @csrf
                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                        <input type="hidden" name="scope_id" value="{{ $query['scope_id'] ?? '' }}">
                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                        <input type="hidden" name="menu" value="plugin.automation-catalog.root">
                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                        <button class="button button-secondary" type="submit">Enable</button>
                    </form>
                    <form method="POST" action="{{ route('plugin.automation-catalog.disable', ['packId' => $selected_pack['id']]) }}">
                        @csrf
                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                        <input type="hidden" name="scope_id" value="{{ $query['scope_id'] ?? '' }}">
                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                        <input type="hidden" name="menu" value="plugin.automation-catalog.root">
                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                        <button class="button button-ghost" type="submit">Disable</button>
                    </form>
                    <form method="POST" action="{{ route('plugin.automation-catalog.uninstall', ['packId' => $selected_pack['id']]) }}">
                        @csrf
                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                        <input type="hidden" name="scope_id" value="{{ $query['scope_id'] ?? '' }}">
                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                        <input type="hidden" name="menu" value="plugin.automation-catalog.root">
                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                        <button class="button button-ghost" type="submit">Uninstall</button>
                    </form>
                </div>

                <form class="upload-form" method="POST" action="{{ route('plugin.automation-catalog.health.update', ['packId' => $selected_pack['id']]) }}" style="margin-top:12px;">
                    @csrf
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                    <input type="hidden" name="scope_id" value="{{ $query['scope_id'] ?? '' }}">
                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                    <input type="hidden" name="menu" value="plugin.automation-catalog.root">
                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                        <div class="field">
                            <label class="field-label">Health state</label>
                            <select class="field-select" name="health_state" required>
                                @foreach ($health_state_options as $state => $label)
                                    <option value="{{ $state }}" @selected($selected_pack['health_state'] === $state)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">Failure reason (if degraded/failing)</label>
                            <input class="field-input" name="last_failure_reason" value="{{ $selected_pack['last_failure_reason'] }}">
                        </div>
                    </div>
                    <div class="action-cluster" style="margin-top:10px;">
                        <button class="button button-ghost" type="submit">Update health</button>
                    </div>
                </form>

                <div class="surface-card" style="margin-top:14px; padding:14px;">
                    <div class="row-between">
                        <div class="entity-title">Automation output mappings</div>
                        <span class="table-note">Map outputs into evidence refresh and workflow transitions.</span>
                    </div>

                    <table class="entity-table" style="margin-top:10px;">
                        <thead>
                            <tr>
                                <th>Mapping</th>
                                <th>Target</th>
                                <th>Last delivery</th>
                                <th>Run</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($selected_pack_output_mappings as $mapping)
                                <tr>
                                    <td>
                                        <div class="entity-title">{{ $mapping['mapping_label'] }}</div>
                                        <div class="table-note">{{ $mapping['mapping_kind_label'] }} · {{ $mapping['is_active'] === '1' ? 'Active' : 'Inactive' }}</div>
                                        @if ($mapping['workflow_key'] !== '' || $mapping['transition_key'] !== '')
                                            <div class="table-note">{{ $mapping['workflow_key'] !== '' ? $mapping['workflow_key'] : 'No workflow' }} · {{ $mapping['transition_key'] !== '' ? $mapping['transition_key'] : 'No transition' }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="table-note">{{ $mapping['target_subject_type'] !== '' ? $mapping['target_subject_type'] : 'No subject type' }}</div>
                                        <div class="entity-title" style="font-size:14px;">{{ $mapping['target_subject_id'] !== '' ? $mapping['target_subject_id'] : 'No subject id' }}</div>
                                    </td>
                                    <td>
                                        <div class="table-note">{{ $mapping['last_status_label'] }}</div>
                                        <div class="table-note">{{ $mapping['last_applied_at'] !== '' ? $mapping['last_applied_at'] : 'Never executed' }}</div>
                                        @if ($mapping['last_message'] !== '')
                                            <div class="table-note">{{ $mapping['last_message'] }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <form class="upload-form" method="POST" action="{{ $mapping['apply_route'] }}" enctype="multipart/form-data">
                                            @csrf
                                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                            <input type="hidden" name="scope_id" value="{{ $query['scope_id'] ?? '' }}">
                                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                            <input type="hidden" name="menu" value="plugin.automation-catalog.root">
                                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">

                                            @if ($mapping['mapping_kind'] === 'evidence-refresh')
                                                <div class="field" style="margin-bottom:8px;">
                                                    <label class="field-label">Output file</label>
                                                    <input class="field-input" type="file" name="output_file">
                                                </div>
                                                <div class="field" style="margin-bottom:8px;">
                                                    <label class="field-label">Or existing artifact id</label>
                                                    <input class="field-input" name="existing_artifact_id" placeholder="01H...">
                                                </div>
                                                <div class="field" style="margin-bottom:8px;">
                                                    <label class="field-label">Evidence kind</label>
                                                    <select class="field-select" name="evidence_kind">
                                                        @foreach ($evidence_kind_options as $kind => $label)
                                                            <option value="{{ $kind }}">{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            @endif

                                            <button class="button button-secondary" type="submit">Run mapping</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                            <tr><td colspan="4">No output mappings for this pack yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>

                    @if (is_string($output_mapping_store_route))
                        <details style="margin-top:12px;">
                            <summary class="button button-ghost" style="display:inline-flex;">Add output mapping</summary>
                            <form class="upload-form" method="POST" action="{{ $output_mapping_store_route }}" style="margin-top:12px;">
                                @csrf
                                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                <input type="hidden" name="scope_id" value="{{ $query['scope_id'] ?? '' }}">
                                <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                <input type="hidden" name="menu" value="plugin.automation-catalog.root">
                                <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                <input type="hidden" name="is_active" value="1">

                                <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                                    <div class="field">
                                        <label class="field-label">Mapping label</label>
                                        <input class="field-input" name="mapping_label" placeholder="AWS baseline evidence refresh" required>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Mapping kind</label>
                                        <select class="field-select" name="mapping_kind" required>
                                            @foreach ($mapping_kind_options as $kind => $label)
                                                <option value="{{ $kind }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Target subject type</label>
                                        <select class="field-select" name="target_subject_type" required>
                                            @foreach ($subject_type_options as $subjectType => $label)
                                                <option value="{{ $subjectType }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Target subject id</label>
                                        <input class="field-input" name="target_subject_id" placeholder="control-access-review" required>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Workflow key (for workflow mappings)</label>
                                        <input class="field-input" name="workflow_key" list="automation-workflow-keys" placeholder="plugin.controls-catalog.control-lifecycle">
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Transition key (for workflow mappings)</label>
                                        <input class="field-input" name="transition_key" list="automation-transition-keys" placeholder="submit-review">
                                    </div>
                                </div>

                                <div class="action-cluster" style="margin-top:10px;">
                                    <button class="button button-secondary" type="submit">Save output mapping</button>
                                </div>
                            </form>
                        </details>
                    @endif

                    <datalist id="automation-workflow-keys">
                        @foreach ($automation_workflow_catalog as $workflow)
                            <option value="{{ $workflow['key'] }}">{{ $workflow['label'] }}</option>
                        @endforeach
                    </datalist>
                    <datalist id="automation-transition-keys">
                        @foreach ($automation_workflow_catalog as $workflow)
                            @foreach ($workflow['transitions'] as $transition)
                                <option value="{{ $transition['key'] }}">{{ $workflow['key'] }} → {{ $transition['to_state'] }}</option>
                            @endforeach
                        @endforeach
                    </datalist>
                </div>
            @endif
        </div>
    @endif

    @if ($can_manage_packs && $show_pack_editor)
        <div id="automation-pack-editor" class="surface-card" style="margin-top:14px; padding:14px;">
            <div class="row-between">
                <div class="entity-title">Register local pack</div>
            </div>
            <form class="upload-form" method="POST" action="{{ $pack_store_route }}" style="margin-top:12px;">
                @csrf
                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                <input type="hidden" name="menu" value="plugin.automation-catalog.root">
                <input type="hidden" name="automation_panel" value="pack-editor">
                <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                    <div class="field">
                        <label class="field-label">Pack key</label>
                        <input class="field-input" name="pack_key" placeholder="connector.aws.config-baseline" required>
                    </div>
                    <div class="field">
                        <label class="field-label">Name</label>
                        <input class="field-input" name="name" placeholder="AWS Config Baseline Collector" required>
                    </div>
                    <div class="field">
                        <label class="field-label">Version</label>
                        <input class="field-input" name="version" placeholder="0.1.0">
                    </div>
                    <div class="field">
                        <label class="field-label">Scope</label>
                        <select class="field-select" name="scope_id">
                            <option value="">Org-wide</option>
                            @foreach ($scope_options as $scope)
                                <option value="{{ $scope['id'] }}">{{ $scope['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label">Provider type</label>
                        <select class="field-select" name="provider_type" required>
                            @foreach ($provider_type_options as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label">Provenance type</label>
                        <select class="field-select" name="provenance_type" required>
                            @foreach ($provenance_type_options as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field" style="grid-column:1 / -1;">
                        <label class="field-label">Source URL</label>
                        <input class="field-input" type="url" name="source_ref" placeholder="https://github.com/example/automation-pack">
                    </div>
                    <div class="field" style="grid-column:1 / -1;">
                        <label class="field-label">Summary</label>
                        <textarea class="field-textarea" name="summary" rows="3"></textarea>
                    </div>
                </div>
                <div class="action-cluster" style="margin-top:12px;">
                    <button class="button button-secondary" type="submit">Save pack</button>
                </div>
            </form>
        </div>
    @endif

    @if ($show_repository_panel)
        <div class="surface-card" style="margin-top:14px; padding:14px;">
            <div class="row-between">
                <div>
                    <div class="entity-title">External package repositories</div>
                    <span class="table-note">Register signed repository indexes and refresh discovered packs.</span>
                </div>
                @if ($can_manage_packs)
                    <form method="POST" action="{{ $official_repository_install_route }}">
                        @csrf
                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                        <input type="hidden" name="scope_id" value="{{ $query['scope_id'] ?? '' }}">
                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                        <input type="hidden" name="menu" value="plugin.automation-catalog.root">
                        <input type="hidden" name="automation_panel" value="repository-editor">
                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                        <button class="button button-secondary" type="submit">Install PymeSec Official Repository</button>
                    </form>
                @endif
            </div>

            @if ($can_manage_packs)
                <form class="upload-form" method="POST" action="{{ $repository_store_route }}" style="margin-top:12px;">
                        @csrf
                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                        <input type="hidden" name="menu" value="plugin.automation-catalog.root">
                        <input type="hidden" name="automation_panel" value="repository-editor">
                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                        <input type="hidden" name="is_enabled" value="1">
                        <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                            <div class="field">
                                <label class="field-label">Label</label>
                                <input class="field-input" name="label" placeholder="PymeSec Community Packs" required>
                            </div>
                            <div class="field">
                                <label class="field-label">Scope</label>
                                <select class="field-select" name="scope_id">
                                    <option value="">Org-wide</option>
                                    @foreach ($scope_options as $scope)
                                        <option value="{{ $scope['id'] }}">{{ $scope['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field" style="grid-column:1 / -1;">
                                <label class="field-label">Repository index URL</label>
                                <input class="field-input" type="url" name="repository_url" placeholder="https://packages.example.org/deploy/repository.json" required>
                            </div>
                            <div class="field" style="grid-column:1 / -1;">
                                <label class="field-label">Repository signature URL</label>
                                <input class="field-input" type="url" name="repository_sign_url" placeholder="https://packages.example.org/deploy/repository.sign">
                            </div>
                            <div class="field">
                                <label class="field-label">Trust tier</label>
                                <select class="field-select" name="trust_tier" required>
                                    @foreach ($trust_tier_options as $trustTier => $trustLabel)
                                        <option value="{{ $trustTier }}">{{ $trustLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field" style="grid-column:1 / -1;">
                                <label class="field-label">Public key (PEM)</label>
                                <textarea class="field-textarea" rows="8" name="public_key_pem" placeholder="-----BEGIN PUBLIC KEY-----" required></textarea>
                            </div>
                        </div>
                        <div class="action-cluster" style="margin-top:12px;">
                            <button class="button button-secondary" type="submit">Save repository</button>
                        </div>
                </form>
            @endif

            <div style="overflow-x:auto; margin-top:10px;">
                <table class="entity-table" style="min-width:1080px; margin-top:0;">
                    <thead>
                        <tr>
                            <th>Repository</th>
                            <th>Trust tier</th>
                            <th>Last sync</th>
                            <th>Status</th>
                            <th>Open packs</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($repositories as $repo)
                            <tr>
                                <td style="min-width:360px; vertical-align:top;">
                                    <div class="entity-title">{{ $repo['label'] }}</div>
                                    <div class="table-note" style="overflow-wrap:anywhere;">{{ $repo['repository_url'] }}</div>
                                    <div class="table-note">Scope: {{ $repo['scope_id'] !== '' ? $repo['scope_id'] : 'Org-wide' }}</div>
                                </td>
                                <td style="min-width:160px; vertical-align:top;">{{ $trust_tier_options[$repo['trust_tier']] ?? $repo['trust_tier'] }}</td>
                                <td style="min-width:170px; vertical-align:top;">{{ $repo['last_refreshed_at'] !== '' ? $repo['last_refreshed_at'] : 'Never' }}</td>
                                <td style="min-width:340px; vertical-align:top;">
                                    <div class="table-note">{{ $repo['last_status_label'] }}</div>
                                    @if ($repo['last_error'] !== '')
                                        <div class="table-note" style="overflow-wrap:anywhere;">{{ $repo['last_error'] }}</div>
                                    @endif
                                </td>
                                <td style="min-width:120px; vertical-align:top;">{{ $repo['is_enabled'] === '1' ? 'Enabled' : 'Disabled' }}</td>
                                <td style="min-width:120px; vertical-align:top;">
                                    @if ($can_manage_packs)
                                        <form method="POST" action="{{ $repo['refresh_route'] }}">
                                            @csrf
                                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                            <input type="hidden" name="scope_id" value="{{ $query['scope_id'] ?? '' }}">
                                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                            <input type="hidden" name="menu" value="plugin.automation-catalog.root">
                                            <input type="hidden" name="automation_panel" value="repository-editor">
                                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                            <button class="button button-ghost" type="submit">Refresh</button>
                                        </form>
                                    @else
                                        <span class="table-note">No actions</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6">No repositories registered yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="surface-card" style="margin-top:14px; padding:14px;">
            <div class="row-between">
                <div class="entity-title">External catalog (latest releases)</div>
                <span class="table-note">Discovered latest pack versions from enabled repositories.</span>
            </div>

            <div style="overflow-x:auto; margin-top:10px;">
                <table class="entity-table" style="min-width:980px; margin-top:0;">
                    <thead>
                        <tr>
                            <th>Pack</th>
                            <th>Repository</th>
                            <th>Latest version</th>
                            <th>Versions</th>
                            <th>Artifact</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($external_catalog_rows as $row)
                            <tr>
                                <td style="min-width:320px; vertical-align:top;">
                                    <div class="entity-title">{{ $row['pack_name'] }}</div>
                                    <div class="table-note" style="overflow-wrap:anywhere;">{{ $row['pack_key'] }}</div>
                                    @if ($row['pack_description'] !== '')
                                        <div class="table-note" style="overflow-wrap:anywhere;">{{ $row['pack_description'] }}</div>
                                    @endif
                                </td>
                                <td style="min-width:190px; vertical-align:top;">
                                    <div class="table-note" style="overflow-wrap:anywhere;">{{ $row['repository_label'] }}</div>
                                    <div class="table-note">{{ $row['repository_last_status'] === 'success' ? 'Synced' : ($row['repository_last_status'] === 'failed' ? 'Failed' : 'Never') }}</div>
                                </td>
                                <td style="min-width:120px; vertical-align:top;">{{ $row['latest_version'] }}</td>
                                <td style="min-width:100px; vertical-align:top;">{{ $row['versions_available'] }}</td>
                                <td style="min-width:250px; vertical-align:top;">
                                    <a href="{{ $row['artifact_url'] }}" target="_blank" rel="noreferrer">Artifact</a>
                                    @if ($row['artifact_signature_url'] !== '')
                                        <div class="table-note" style="overflow-wrap:anywhere;"><a href="{{ $row['artifact_signature_url'] }}" target="_blank" rel="noreferrer">Signature</a></div>
                                    @endif
                                    @if ($row['artifact_sha256'] !== '')
                                        <div class="table-note" style="overflow-wrap:anywhere;">SHA256: {{ $row['artifact_sha256'] }}</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5">No external releases discovered yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</section>
