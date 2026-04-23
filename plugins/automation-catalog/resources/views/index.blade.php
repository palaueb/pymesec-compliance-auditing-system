<section class="module-screen">
    <div class="surface-note">
        {{ __('Automation packs define installable compliance automations. Use this catalog to register packs, control lifecycle state, and track operational posture.') }}
    </div>

    @if ($show_catalog_chrome)
        <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr)); margin-top:12px;">
            <div class="metric-card">
                <div class="metric-label">{{ __('Installed packs') }}</div>
                <div class="metric-value">{{ count($installed_packs) }}</div>
            </div>
            <div class="metric-card">
                <div class="metric-label">{{ __('Enabled') }}</div>
                <div class="metric-value">{{ count(array_filter($installed_packs, static fn(array $pack): bool => $pack['is_enabled'] === '1')) }}</div>
            </div>
            <div class="metric-card">
                <div class="metric-label">{{ __('Failing') }}</div>
                <div class="metric-value">{{ count(array_filter($installed_packs, static fn(array $pack): bool => $pack['health_state'] === 'failing')) }}</div>
            </div>
            <div class="metric-card">
                <div class="metric-label">{{ __('External latest') }}</div>
                <div class="metric-value">{{ count($external_catalog_rows) }}</div>
            </div>
        </div>

        <div class="surface-card" style="margin-top:14px; padding:14px;">
            <div class="row-between">
                <div class="entity-title">{{ __('Automation pack catalog') }}</div>
                <span class="table-note">{{ __('Installed automations available in this workspace.') }}</span>
            </div>

            <div style="overflow-x:auto; margin-top:10px;">
                <table class="entity-table" style="min-width:1080px; margin-top:0;">
                    <thead>
                        <tr>
                            <th style="min-width:360px;">{{ __('Pack') }}</th>
                            <th style="min-width:140px;">{{ __('Lifecycle') }}</th>
                            <th style="min-width:130px;">{{ __('Health') }}</th>
                            <th style="min-width:120px;">{{ __('Version') }}</th>
                            <th style="min-width:140px;">{{ __('Scope') }}</th>
                            <th style="min-width:100px;">{{ __('Open') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($installed_packs as $pack)
                            <tr>
                                <td style="min-width:360px; vertical-align:top;">
                                    <div class="entity-title">{{ $pack['name'] }}</div>
                                    <div class="table-note" style="overflow-wrap:anywhere;">{{ $pack['pack_key'] }}</div>
                                    <div class="table-note" style="overflow-wrap:anywhere;">{{ $pack['summary'] !== '' ? $pack['summary'] : __('No summary') }}</div>
                                </td>
                                <td style="min-width:140px; vertical-align:top;">{{ $pack['lifecycle_state_label'] }}</td>
                                <td style="min-width:130px; vertical-align:top;">{{ $pack['health_state_label'] }}</td>
                                <td style="min-width:120px; vertical-align:top;">{{ $pack['version'] !== '' ? $pack['version'] : __('Not set') }}</td>
                                <td style="min-width:140px; vertical-align:top;">{{ $pack['scope_id'] !== '' ? $pack['scope_id'] : __('Org-wide') }}</td>
                                <td style="min-width:100px; vertical-align:top;"><a class="button button-ghost" href="{{ $pack['open_url'] }}">{{ __('Open') }}</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    @if ($show_external_catalog)
                                        {{ __('No automation packs installed yet. Install a pack from the external catalog below.') }}
                                    @else
                                        {{ __('No automation packs installed yet.') }}
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if (is_array($selected_pack))
        <div class="surface-card" style="margin-top:14px; padding:14px;">
            <div class="row-between">
                <div>
                    <a class="button button-ghost" href="{{ $packs_list_url }}" style="margin-bottom:10px;">{{ __('Back to automations') }}</a>
                    <div class="eyebrow">{{ __('Automation pack') }}</div>
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
                    <div class="metric-label">{{ __('Installed') }}</div>
                    <div class="metric-value">{{ $selected_pack['is_installed'] === '1' ? __('Yes') : __('No') }}</div>
                    <div class="meta-copy">{{ $selected_pack['installed_at'] !== '' ? $selected_pack['installed_at'] : __('Not recorded') }}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">{{ __('Enabled') }}</div>
                    <div class="metric-value">{{ $selected_pack['is_enabled'] === '1' ? __('Yes') : __('No') }}</div>
                    <div class="meta-copy">{{ $selected_pack['enabled_at'] !== '' ? $selected_pack['enabled_at'] : __('Not recorded') }}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">{{ __('Last run') }}</div>
                    <div class="metric-value">{{ $selected_pack['last_run_at'] !== '' ? $selected_pack['last_run_at'] : __('Never') }}</div>
                    <div class="meta-copy">{{ __('Failure:') }} {{ $selected_pack['last_failure_at'] !== '' ? $selected_pack['last_failure_at'] : __('None') }}</div>
                </div>
            </div>

            <div class="table-note" style="margin-top:12px;">
                {{ __('Provider') }} {{ ucfirst($selected_pack['provider_type']) }} · {{ __('Provenance') }} {{ ucfirst($selected_pack['provenance_type']) }} · {{ __('Owner') }} {{ $selected_pack['owner_principal_id'] !== '' ? $selected_pack['owner_principal_id'] : __('Not set') }}
            </div>
            <div class="table-note">
                {{ __('Source:') }}
                @if ($selected_pack['source_ref'] !== '')
                    <a href="{{ $selected_pack['source_ref'] }}" target="_blank" rel="noreferrer">{{ $selected_pack['source_ref'] }}</a>
                @else
                    {{ __('Not set') }}
                @endif
            </div>
            @if ($selected_pack['last_failure_reason'] !== '')
                <div class="table-note">{{ __('Last failure reason:') }} {{ $selected_pack['last_failure_reason'] }}</div>
            @endif
            <div class="table-note">
                {{ __('Runtime schedule:') }}
                @if ($selected_pack['runtime_schedule_enabled'] === '1')
                    {{ __('Enabled') }} · {{ $selected_pack['runtime_schedule_cron'] !== '' ? $selected_pack['runtime_schedule_cron'] : __('No cron') }} · {{ $selected_pack['runtime_schedule_timezone'] !== '' ? $selected_pack['runtime_schedule_timezone'] : __('UTC') }}
                @else
                    {{ __('Disabled') }}
                @endif
            </div>

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
                        <button class="button button-ghost" type="submit">{{ __('Install') }}</button>
                    </form>
                    <form method="POST" action="{{ route('plugin.automation-catalog.enable', ['packId' => $selected_pack['id']]) }}">
                        @csrf
                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                        <input type="hidden" name="scope_id" value="{{ $query['scope_id'] ?? '' }}">
                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                        <input type="hidden" name="menu" value="plugin.automation-catalog.root">
                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                        <button class="button button-secondary" type="submit">{{ __('Enable') }}</button>
                    </form>
                    <form method="POST" action="{{ route('plugin.automation-catalog.disable', ['packId' => $selected_pack['id']]) }}">
                        @csrf
                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                        <input type="hidden" name="scope_id" value="{{ $query['scope_id'] ?? '' }}">
                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                        <input type="hidden" name="menu" value="plugin.automation-catalog.root">
                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                        <button class="button button-ghost" type="submit">{{ __('Disable') }}</button>
                    </form>
                    <form method="POST" action="{{ route('plugin.automation-catalog.uninstall', ['packId' => $selected_pack['id']]) }}">
                        @csrf
                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                        <input type="hidden" name="scope_id" value="{{ $query['scope_id'] ?? '' }}">
                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                        <input type="hidden" name="menu" value="plugin.automation-catalog.root">
                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                        <button class="button button-ghost" type="submit">{{ __('Uninstall') }}</button>
                    </form>
                    @if (is_string($runtime_run_route) && $selected_pack['is_installed'] === '1' && $selected_pack['is_enabled'] === '1')
                        <form method="POST" action="{{ $runtime_run_route }}">
                            @csrf
                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                            <input type="hidden" name="scope_id" value="{{ $query['scope_id'] ?? '' }}">
                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                            <input type="hidden" name="menu" value="plugin.automation-catalog.root">
                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                    <button class="button button-secondary" type="submit">{{ __('Run now') }}</button>
                        </form>
                    @endif
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
                            <label class="field-label">{{ __('Health state') }}</label>
                            <select class="field-select" name="health_state" required>
                                @foreach ($health_state_options as $state => $label)
                                    <option value="{{ $state }}" @selected($selected_pack['health_state'] === $state)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('Failure reason (if degraded/failing)') }}</label>
                            <input class="field-input" name="last_failure_reason" value="{{ $selected_pack['last_failure_reason'] }}">
                        </div>
                    </div>
                    <div class="action-cluster" style="margin-top:10px;">
                        <button class="button button-ghost" type="submit">{{ __('Update health') }}</button>
                    </div>
                </form>

                <form class="upload-form" method="POST" action="{{ route('plugin.automation-catalog.schedule.update', ['packId' => $selected_pack['id']]) }}" style="margin-top:12px;">
                    @csrf
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                    <input type="hidden" name="scope_id" value="{{ $query['scope_id'] ?? '' }}">
                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                    <input type="hidden" name="menu" value="plugin.automation-catalog.root">
                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                    <div class="overview-grid" style="grid-template-columns:repeat(3, minmax(0, 1fr));">
                        <div class="field">
                            <label class="field-label">{{ __('Enable schedule') }}</label>
                            <select class="field-select" name="runtime_schedule_enabled">
                            <option value="0" @selected($selected_pack['runtime_schedule_enabled'] !== '1')>{{ __('Disabled') }}</option>
                            <option value="1" @selected($selected_pack['runtime_schedule_enabled'] === '1')>{{ __('Enabled') }}</option>
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('Cron expression') }}</label>
                            <input class="field-input" name="runtime_schedule_cron" placeholder="*/15 * * * *" value="{{ $selected_pack['runtime_schedule_cron'] }}">
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('Timezone') }}</label>
                            <input class="field-input" name="runtime_schedule_timezone" placeholder="UTC" value="{{ $selected_pack['runtime_schedule_timezone'] }}">
                        </div>
                    </div>
                    <div class="action-cluster" style="margin-top:10px;">
                        <button class="button button-ghost" type="submit">{{ __('Update schedule') }}</button>
                    </div>
                </form>

                <div class="surface-card" style="margin-top:14px; padding:14px;">
                    <div class="row-between">
                        <div class="entity-title">{{ __('Automation output mappings') }}</div>
                        <span class="table-note">{{ __('Map outputs into evidence refresh and workflow transitions.') }}</span>
                    </div>

                    <table class="entity-table" style="margin-top:10px;">
                        <thead>
                            <tr>
                                <th>{{ __('Mapping') }}</th>
                                <th>{{ __('Target') }}</th>
                                <th>{{ __('Last delivery') }}</th>
                                <th>{{ __('Run') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($selected_pack_output_mappings as $mapping)
                                <tr>
                                    <td>
                                        <div class="entity-title">{{ $mapping['mapping_label'] }}</div>
                                        <div class="table-note">{{ $mapping['mapping_kind_label'] }} · {{ $mapping['is_active'] === '1' ? __('Active') : __('Inactive') }}</div>
                                        @if ($mapping['workflow_key'] !== '' || $mapping['transition_key'] !== '')
                                            <div class="table-note">{{ $mapping['workflow_key'] !== '' ? $mapping['workflow_key'] : __('No workflow') }} · {{ $mapping['transition_key'] !== '' ? $mapping['transition_key'] : __('No transition') }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="table-note">{{ $mapping['target_subject_type'] !== '' ? $mapping['target_subject_type'] : __('No subject type') }} · {{ $mapping['target_binding_mode_label'] }}</div>
                                        <div class="entity-title" style="font-size:14px;">{{ $mapping['target_subject_id'] !== '' ? $mapping['target_subject_id'] : __('No subject id') }}</div>
                                        @if ($mapping['target_scope_id'] !== '')
                                            <div class="table-note">{{ __('Scope resolver:') }} {{ $mapping['target_scope_id'] }}</div>
                                        @endif
                                        @if ($mapping['target_selector_tags'] !== '')
                                            <div class="table-note">{{ __('Tags:') }} {{ $mapping['target_selector_tags'] }}</div>
                                        @endif
                                        <div class="table-note">{{ __('Execution mode:') }} {{ $mapping['execution_mode_label'] }}</div>
                                        <div class="table-note">{{ __('Posture policy:') }} {{ $mapping['posture_propagation_policy_label'] }}</div>
                                        <div class="table-note">{{ __('On fail:') }} {{ $mapping['on_fail_policy_label'] }}</div>
                                        <div class="table-note">{{ __('Evidence policy:') }} {{ $mapping['evidence_policy_label'] }}</div>
                                        <div class="table-note">{{ __('Retry:') }} {{ $mapping['runtime_retry_max_attempts'] }} {{ __('max') }} · {{ $mapping['runtime_retry_backoff_ms'] }} {{ __('ms backoff') }}</div>
                                        <div class="table-note">{{ __('Guardrails:') }} {{ $mapping['runtime_max_targets'] }} {{ __('max targets') }} · {{ $mapping['runtime_payload_max_kb'] }} {{ __('KB payload') }}</div>
                                    </td>
                                    <td>
                                        <div class="table-note">{{ $mapping['last_status_label'] }}</div>
                                        <div class="table-note">{{ $mapping['last_applied_at'] !== '' ? $mapping['last_applied_at'] : __('Never executed') }}</div>
                                        @if ($mapping['last_message'] !== '')
                                            <div class="table-note">{{ $mapping['last_message'] }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if (($mapping['target_binding_mode'] ?? 'explicit') === 'scope')
                                            <div class="table-note">{{ __('Scope resolver mapping.') }}</div>
                                            <div class="table-note">{{ __('Use') }} <strong>{{ __('Run now') }}</strong> {{ __('on the pack to execute all resolved targets.') }}</div>
                                        @elseif (($mapping['execution_mode'] ?? 'both') === 'runtime-only')
                                            <div class="table-note">{{ __('Runtime-only mapping.') }}</div>
                                            <div class="table-note">{{ __('Use') }} <strong>{{ __('Run now') }}</strong> {{ __('on the pack.') }}</div>
                                        @else
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
                                                        <label class="field-label">{{ __('Output file') }}</label>
                                                        <input class="field-input" type="file" name="output_file">
                                                    </div>
                                                    <div class="field" style="margin-bottom:8px;">
                                                        <label class="field-label">{{ __('Or existing artifact id') }}</label>
                                                        <input class="field-input" name="existing_artifact_id" placeholder="01H...">
                                                    </div>
                                                    <div class="field" style="margin-bottom:8px;">
                                                        <label class="field-label">{{ __('Evidence kind') }}</label>
                                                        <select class="field-select" name="evidence_kind">
                                                            @foreach ($evidence_kind_options as $kind => $label)
                                                                <option value="{{ $kind }}">{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                @endif

                                                <button class="button button-secondary" type="submit">{{ __('Run mapping') }}</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                            <tr><td colspan="4">{{ __('No output mappings for this pack yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>

                    @if (is_string($output_mapping_store_route))
                        <details style="margin-top:12px;">
                            <summary class="button button-ghost" style="display:inline-flex;">{{ __('Add output mapping') }}</summary>
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
                                        <label class="field-label">{{ __('Mapping label') }}</label>
                                        <input class="field-input" name="mapping_label" placeholder="{{ __('AWS baseline evidence refresh') }}" required>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Mapping kind') }}</label>
                                        <select class="field-select" name="mapping_kind" required>
                                            @foreach ($mapping_kind_options as $kind => $label)
                                                <option value="{{ $kind }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Target subject type') }}</label>
                                        <select class="field-select" name="target_subject_type" required>
                                            @foreach ($subject_type_options as $subjectType => $label)
                                                <option value="{{ $subjectType }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Binding mode') }}</label>
                                        <select class="field-select" name="target_binding_mode">
                                            <option value="explicit">{{ __('Explicit object id') }}</option>
                                            <option value="scope">{{ __('Scope resolver (asset/risk)') }}</option>
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Target subject id') }}</label>
                                        <input class="field-input" name="target_subject_id" placeholder="{{ __('control-access-review') }}">
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Resolver scope id (optional)') }}</label>
                                        <input class="field-input" name="target_scope_id" placeholder="{{ __('scope-eu') }}">
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Resolver tags (optional)') }}</label>
                                        <input class="field-input" name="target_tags" placeholder="{{ __('criticality:high,classification:confidential') }}">
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Posture propagation policy') }}</label>
                                        <select class="field-select" name="posture_propagation_policy">
                                            <option value="disabled">{{ __('Disabled') }}</option>
                                            <option value="status-only">{{ __('Status only (asset/risk)') }}</option>
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Execution mode') }}</label>
                                        <select class="field-select" name="execution_mode">
                                            <option value="both">{{ __('Both (manual + runtime)') }}</option>
                                            <option value="runtime-only">{{ __('Runtime only') }}</option>
                                            <option value="manual-only">{{ __('Manual only') }}</option>
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('On fail policy') }}</label>
                                        <select class="field-select" name="on_fail_policy">
                                            <option value="no-op">{{ __('No-op') }}</option>
                                            <option value="raise-finding">{{ __('Raise finding') }}</option>
                                            <option value="raise-finding-and-action">{{ __('Raise finding + action') }}</option>
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Evidence policy') }}</label>
                                        <select class="field-select" name="evidence_policy">
                                            <option value="always">{{ __('Always') }}</option>
                                            <option value="on-fail">{{ __('On fail') }}</option>
                                            <option value="on-change">{{ __('On change') }}</option>
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Runtime retry max attempts') }}</label>
                                        <input class="field-input" type="number" min="0" max="5" name="runtime_retry_max_attempts" value="0">
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Runtime retry backoff (ms)') }}</label>
                                        <input class="field-input" type="number" min="0" max="60000" name="runtime_retry_backoff_ms" value="0">
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Runtime max targets') }}</label>
                                        <input class="field-input" type="number" min="1" max="2000" name="runtime_max_targets" value="200">
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Runtime payload max (KB)') }}</label>
                                        <input class="field-input" type="number" min="0" max="10240" name="runtime_payload_max_kb" value="512">
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Workflow key (for workflow mappings)') }}</label>
                                        <input class="field-input" name="workflow_key" list="automation-workflow-keys" placeholder="{{ __('plugin.controls-catalog.control-lifecycle') }}">
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Transition key (for workflow mappings)') }}</label>
                                        <input class="field-input" name="transition_key" list="automation-transition-keys" placeholder="{{ __('submit-review') }}">
                                    </div>
                                </div>

                                <div class="action-cluster" style="margin-top:10px;">
                                    <button class="button button-secondary" type="submit">{{ __('Save output mapping') }}</button>
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

                <div class="surface-card" style="margin-top:14px; padding:14px;">
                    <div class="row-between">
                        <div class="entity-title">{{ __('Runtime executions') }}</div>
                        <span class="table-note">{{ __('Manual and scheduled runtime history for this pack.') }}</span>
                    </div>
                    <table class="entity-table" style="margin-top:10px;">
                        <thead>
                            <tr>
                                <th>{{ __('Started') }}</th>
                                <th>{{ __('Trigger') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Mappings') }}</th>
                                <th>{{ __('Duration') }}</th>
                                <th>{{ __('Message') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($selected_pack_runs as $run)
                                <tr>
                                    <td>{{ $run['started_at'] }}</td>
                                    <td>{{ $run['trigger_mode_label'] }}</td>
                                    <td>{{ $run['status_label'] }}</td>
                                    <td>
                                        <div class="table-note">{{ __('Total') }} {{ $run['total_mappings'] }}</div>
                                        <div class="table-note">{{ __('OK') }} {{ $run['success_count'] }} · {{ __('Fail') }} {{ $run['failed_count'] }} · {{ __('Skip') }} {{ $run['skipped_count'] }}</div>
                                    </td>
                                    <td>{{ $run['duration_ms'] !== '' ? $run['duration_ms'].' ms' : __('N/A') }}</td>
                                    <td>
                                        @if ($run['failure_reason'] !== '')
                                            <div class="table-note" style="overflow-wrap:anywhere;">{{ $run['failure_reason'] }}</div>
                                        @else
                                            <div class="table-note">{{ $run['finished_at'] !== '' ? __('Completed') : __('Running') }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6">{{ __('No runtime executions yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="surface-card" style="margin-top:14px; padding:14px;">
                    <div class="row-between">
                        <div class="entity-title">{{ __('Latest check results') }}</div>
                        <span class="table-note">{{ __('Per-target runtime outcomes persisted for diagnostics and reporting.') }}</span>
                    </div>
                    <table class="entity-table" style="margin-top:10px;">
                        <thead>
                            <tr>
                                <th>{{ __('Checked') }}</th>
                                <th>{{ __('Target') }}</th>
                                <th>{{ __('Mapping') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Outcome') }}</th>
                                <th>{{ __('Attempts') }}</th>
                                <th>{{ __('Evidence') }}</th>
                                <th>{{ __('Finding') }}</th>
                                <th>{{ __('Action') }}</th>
                                <th>{{ __('Message') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($selected_pack_check_results as $result)
                                <tr>
                                    <td>{{ $result['checked_at'] }}</td>
                                    <td>
                                        <div class="table-note">{{ $result['target_subject_type'] !== '' ? $result['target_subject_type'] : __('No subject type') }}</div>
                                        <div class="entity-title" style="font-size:14px;">{{ $result['target_subject_id'] !== '' ? $result['target_subject_id'] : __('No subject id') }}</div>
                                    </td>
                                    <td>
                                        <div class="table-note">{{ $result['mapping_kind'] !== '' ? $result['mapping_kind'] : __('No mapping kind') }}</div>
                                        <div class="table-note">{{ __('Trigger') }} {{ $result['trigger_mode'] }}</div>
                                    </td>
                                    <td>{{ $result['status_label'] }}</td>
                                    <td>{{ $result['outcome_label'] }}</td>
                                    <td>
                                        <div class="table-note">{{ $result['attempt_count'] ?? __('1') }} {{ __('tries') }}</div>
                                        @if (($result['retry_count'] ?? '0') !== '0')
                                            <div class="table-note">{{ __('Retries') }} {{ $result['retry_count'] }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($result['evidence_open_url'] !== '')
                                            <a href="{{ $result['evidence_open_url'] }}">{{ __('Open evidence') }}</a>
                                        @elseif ($result['artifact_id'] !== '')
                                            <span class="table-note">{{ __('Artifact') }} {{ $result['artifact_id'] }}</span>
                                        @else
                                            <span class="table-note">{{ __('None') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($result['finding_open_url'] !== '')
                                            <a href="{{ $result['finding_open_url'] }}">{{ __('Open finding') }}</a>
                                        @else
                                            <span class="table-note">{{ __('None') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($result['action_open_url'] !== '')
                                            <a href="{{ $result['action_open_url'] }}">{{ __('Open action') }}</a>
                                        @else
                                            <span class="table-note">{{ __('None') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="table-note" style="overflow-wrap:anywhere;">{{ $result['message'] !== '' ? $result['message'] : __('No message') }}</div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="10">{{ __('No check results yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    @if ($can_manage_packs && $show_pack_editor)
        <div id="automation-pack-editor" class="surface-card" style="margin-top:14px; padding:14px;">
            <div class="row-between">
                <div class="entity-title">{{ __('Register local pack') }}</div>
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
                        <label class="field-label">{{ __('Pack key') }}</label>
                        <input class="field-input" name="pack_key" placeholder="{{ __('connector.aws.config-baseline') }}" required>
                    </div>
                    <div class="field">
                        <label class="field-label">{{ __('Name') }}</label>
                        <input class="field-input" name="name" placeholder="{{ __('AWS Config Baseline Collector') }}" required>
                    </div>
                    <div class="field">
                        <label class="field-label">{{ __('Version') }}</label>
                        <input class="field-input" name="version" placeholder="{{ __('0.1.0') }}">
                    </div>
                    <div class="field">
                        <label class="field-label">{{ __('Scope') }}</label>
                        <select class="field-select" name="scope_id">
                            <option value="">{{ __('Org-wide') }}</option>
                            @foreach ($scope_options as $scope)
                                <option value="{{ $scope['id'] }}">{{ $scope['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label">{{ __('Provider type') }}</label>
                        <select class="field-select" name="provider_type" required>
                            @foreach ($provider_type_options as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label">{{ __('Provenance type') }}</label>
                        <select class="field-select" name="provenance_type" required>
                            @foreach ($provenance_type_options as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field" style="grid-column:1 / -1;">
                        <label class="field-label">{{ __('Source URL') }}</label>
                        <input class="field-input" type="url" name="source_ref" placeholder="{{ __('https://github.com/example/automation-pack') }}">
                    </div>
                    <div class="field" style="grid-column:1 / -1;">
                        <label class="field-label">{{ __('Summary') }}</label>
                        <textarea class="field-textarea" name="summary" rows="3"></textarea>
                    </div>
                </div>
                <div class="action-cluster" style="margin-top:12px;">
                    <button class="button button-secondary" type="submit">{{ __('Save pack') }}</button>
                </div>
            </form>
        </div>
    @endif

    @if ($show_repository_panel)
        <div class="surface-card" style="margin-top:14px; padding:14px;">
            @if ($show_repository_onboarding)
                <div class="entity-title">{{ __('Install your first package repository') }}</div>
                <div class="table-note" style="margin-top:4px;">{{ __('Choose a source to unlock external automation packs.') }}</div>

                <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr)); margin-top:12px;">
                    <div class="surface-card" style="padding:12px;">
                        <div class="entity-title">{{ __('PymeSec Official Repository') }}</div>
                        <div class="table-note" style="margin-top:6px;">{{ __('Recommended default source with signature validation enabled.') }}</div>
                        @if ($can_manage_packs)
                            <form method="POST" action="{{ $official_repository_install_route }}" style="margin-top:12px;">
                                @csrf
                                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                <input type="hidden" name="scope_id" value="{{ $query['scope_id'] ?? '' }}">
                                <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                <input type="hidden" name="menu" value="plugin.automation-catalog.root">
                                <input type="hidden" name="automation_panel" value="repository-editor">
                                <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                <button class="button button-secondary" type="submit">{{ __('Activate official repository') }}</button>
                            </form>
                        @endif
                    </div>

                    <div class="surface-card" style="padding:12px;">
                        <div class="entity-title">{{ __('Custom repository') }}</div>
                        <div class="table-note" style="margin-top:6px;">{{ __('Register your own signed repository endpoint.') }}</div>
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

                                <div class="field">
                                    <label class="field-label">{{ __('Label') }}</label>
                                    <input class="field-input" name="label" placeholder="{{ __('PymeSec Community Packs') }}" required>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Repository index URL') }}</label>
                                    <input class="field-input" type="url" name="repository_url" placeholder="{{ __('https://packages.example.org/deploy/repository.json') }}" required>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Repository signature URL') }}</label>
                                    <input class="field-input" type="url" name="repository_sign_url" placeholder="{{ __('https://packages.example.org/deploy/repository.sign') }}">
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Scope') }}</label>
                                    <select class="field-select" name="scope_id">
                                        <option value="">{{ __('Org-wide') }}</option>
                                        @foreach ($scope_options as $scope)
                                            <option value="{{ $scope['id'] }}">{{ $scope['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Trust tier') }}</label>
                                    <select class="field-select" name="trust_tier" required>
                                        @foreach ($trust_tier_options as $trustTier => $trustLabel)
                                            <option value="{{ $trustTier }}">{{ $trustLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Public key (PEM)') }}</label>
                                    <textarea class="field-textarea" rows="7" name="public_key_pem" placeholder="{{ __('-----BEGIN PUBLIC KEY-----') }}" required></textarea>
                                </div>

                                <div class="action-cluster" style="margin-top:12px;">
                                    <button class="button button-ghost" type="submit">{{ __('Save repository') }}</button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            @else
                <div class="row-between">
                    <div>
                        <div class="entity-title">{{ __('External package repositories') }}</div>
                        <span class="table-note">{{ __('Register signed repository indexes and refresh discovered packs.') }}</span>
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
                            <button class="button button-secondary" type="submit">{{ __('Install PymeSec Official Repository') }}</button>
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
                                <label class="field-label">{{ __('Label') }}</label>
                                <input class="field-input" name="label" placeholder="{{ __('PymeSec Community Packs') }}" required>
                            </div>
                            <div class="field">
                                <label class="field-label">{{ __('Scope') }}</label>
                                <select class="field-select" name="scope_id">
                                    <option value="">{{ __('Org-wide') }}</option>
                                    @foreach ($scope_options as $scope)
                                        <option value="{{ $scope['id'] }}">{{ $scope['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field" style="grid-column:1 / -1;">
                                <label class="field-label">{{ __('Repository index URL') }}</label>
                                <input class="field-input" type="url" name="repository_url" placeholder="{{ __('https://packages.example.org/deploy/repository.json') }}" required>
                            </div>
                            <div class="field" style="grid-column:1 / -1;">
                                <label class="field-label">{{ __('Repository signature URL') }}</label>
                                <input class="field-input" type="url" name="repository_sign_url" placeholder="{{ __('https://packages.example.org/deploy/repository.sign') }}">
                            </div>
                            <div class="field">
                                <label class="field-label">{{ __('Trust tier') }}</label>
                                <select class="field-select" name="trust_tier" required>
                                    @foreach ($trust_tier_options as $trustTier => $trustLabel)
                                        <option value="{{ $trustTier }}">{{ $trustLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field" style="grid-column:1 / -1;">
                                <label class="field-label">{{ __('Public key (PEM)') }}</label>
                                <textarea class="field-textarea" rows="8" name="public_key_pem" placeholder="{{ __('-----BEGIN PUBLIC KEY-----') }}" required></textarea>
                            </div>
                        </div>
                        <div class="action-cluster" style="margin-top:12px;">
                            <button class="button button-secondary" type="submit">{{ __('Save repository') }}</button>
                        </div>
                    </form>
                @endif

                <div style="overflow-x:auto; margin-top:10px;">
                    <table class="entity-table" style="min-width:1080px; margin-top:0;">
                        <thead>
                            <tr>
                                <th>{{ __('Repository') }}</th>
                                <th>{{ __('Trust tier') }}</th>
                                <th>{{ __('Last sync') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Open packs') }}</th>
                                <th>{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($repositories as $repo)
                                <tr>
                                    <td style="min-width:360px; vertical-align:top;">
                                        <div class="entity-title">{{ $repo['label'] }}</div>
                                        <div class="table-note" style="overflow-wrap:anywhere;">{{ $repo['repository_url'] }}</div>
                                        <div class="table-note">{{ __('Scope:') }} {{ $repo['scope_id'] !== '' ? $repo['scope_id'] : __('Org-wide') }}</div>
                                    </td>
                                    <td style="min-width:160px; vertical-align:top;">{{ $trust_tier_options[$repo['trust_tier']] ?? $repo['trust_tier'] }}</td>
                                    <td style="min-width:170px; vertical-align:top;">{{ $repo['last_refreshed_at'] !== '' ? $repo['last_refreshed_at'] : __('Never') }}</td>
                                    <td style="min-width:340px; vertical-align:top;">
                                        <div class="table-note">{{ $repo['last_status_label'] }}</div>
                                        @if ($repo['last_error'] !== '')
                                            <div class="table-note" style="overflow-wrap:anywhere;">{{ $repo['last_error'] }}</div>
                                        @endif
                                    </td>
                                    <td style="min-width:120px; vertical-align:top;">{{ $repo['is_enabled'] === '1' ? __('Enabled') : __('Disabled') }}</td>
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
                                                <button class="button button-ghost" type="submit">{{ __('Refresh') }}</button>
                                            </form>
                                        @else
                                            <span class="table-note">{{ __('No actions') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6">{{ __('No repositories registered yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    @if ($show_external_catalog)
        <div class="surface-card" style="margin-top:14px; padding:14px;">
            <div class="row-between">
                <div class="entity-title">{{ __('External catalog (latest releases)') }}</div>
                <span class="table-note">{{ __('Discovered latest pack versions from enabled repositories.') }}</span>
            </div>

            <div style="overflow-x:auto; margin-top:10px;">
                <table class="entity-table" style="min-width:1120px; margin-top:0;">
                    <thead>
                        <tr>
                            <th>{{ __('Pack') }}</th>
                            <th>{{ __('Repository') }}</th>
                            <th>{{ __('Latest version') }}</th>
                            <th>{{ __('Versions') }}</th>
                            <th>{{ __('Artifact') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($external_catalog_rows as $row)
                            <tr>
                                <td style="min-width:300px; vertical-align:top;">
                                    <div class="entity-title">{{ $row['pack_name'] }}</div>
                                    <div class="table-note" style="overflow-wrap:anywhere;">{{ $row['pack_key'] }}</div>
                                    @if ($row['pack_description'] !== '')
                                        <div class="table-note" style="overflow-wrap:anywhere;">{{ $row['pack_description'] }}</div>
                                    @endif
                                </td>
                                <td style="min-width:170px; vertical-align:top;">
                                    <div class="table-note" style="overflow-wrap:anywhere;">{{ $row['repository_label'] }}</div>
                                    <div class="table-note">{{ $row['repository_last_status'] === 'success' ? __('Synced') : ($row['repository_last_status'] === 'failed' ? __('Failed') : __('Never')) }}</div>
                                </td>
                                <td style="min-width:110px; vertical-align:top;">{{ $row['latest_version'] }}</td>
                                <td style="min-width:90px; vertical-align:top;">{{ $row['versions_available'] }}</td>
                                <td style="min-width:250px; vertical-align:top;">
                                    <a href="{{ $row['artifact_url'] }}" target="_blank" rel="noreferrer">{{ __('Artifact') }}</a>
                                    @if ($row['artifact_signature_url'] !== '')
                                        <div class="table-note" style="overflow-wrap:anywhere;"><a href="{{ $row['artifact_signature_url'] }}" target="_blank" rel="noreferrer">{{ __('Signature') }}</a></div>
                                    @endif
                                    @if ($row['artifact_sha256'] !== '')
                                        <div class="table-note" style="overflow-wrap:anywhere;">{{ __('SHA256:') }} {{ $row['artifact_sha256'] }}</div>
                                    @endif
                                </td>
                                <td style="min-width:160px; vertical-align:top;">
                                    @if ($row['local_pack_installed'] === '1' && $row['open_url'] !== '')
                                        <a class="button button-ghost" href="{{ $row['open_url'] }}">{{ __('Open') }}</a>
                                    @elseif ($row['can_install'] === '1' && $row['install_route'] !== '')
                                        <form method="POST" action="{{ $row['install_route'] }}">
                                            @csrf
                                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                            <input type="hidden" name="scope_id" value="{{ $query['scope_id'] ?? '' }}">
                                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                            <input type="hidden" name="menu" value="plugin.automation-catalog.root">
                                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                            <button class="button button-secondary" type="submit">{{ __('Install') }}</button>
                                        </form>
                                    @elseif ($row['local_pack_id'] !== '')
                                        <span class="table-note">{{ __('Discovered only') }}</span>
                                    @else
                                        <span class="table-note">{{ __('Not available') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6">{{ __('No external releases discovered yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</section>
