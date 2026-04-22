<style>
    .pill-active  { background: rgba(34,197,94,0.14);  color: #166534; }
    .pill-review  { background: rgba(245,158,11,0.14); color: #92400e; }
    .pill-draft   { background: rgba(31,42,34,0.06);   color: var(--muted); }
    .pill-archived{ background: rgba(31,42,34,0.06);   color: var(--muted); }

    details > summary { cursor: pointer; list-style: none; }
    details > summary::-webkit-details-marker { display: none; }
</style>

<section class="module-screen">
    <div class="surface-note">
        {{ __('Impact tiers and dependency kinds are business-managed catalog values from `Reference catalogs`. Continuity workflow states are system-controlled.') }}
    </div>

    @if (is_array($selected_service))
        <div class="surface-card" style="padding:16px; display:grid; gap:16px;">
            <div class="surface-note">
                {{ __('Continuity Service Detail keeps recovery plans, dependencies, documents, workflow, ownership, and service maintenance in one workspace. Use the service register to browse services and open the one you want to work on.') }}
            </div>

            <div class="row-between" style="align-items:flex-start;">
                <div>
                    <div class="eyebrow">{{ __('Continuity Service Detail') }}</div>
                    <h2 class="screen-title" style="font-size:28px;">{{ $selected_service['title'] }}</h2>
                    <div class="table-note">{{ $selected_service['id'] }}</div>
                    <div class="table-note">{{ $selected_service['impact_tier_label'] }}</div>
                </div>
                <div class="action-cluster">
                    @php $svcStatePill = match($selected_service['state']) { 'active' => 'pill-active', 'review' => 'pill-review', 'draft' => 'pill-draft', 'archived' => 'pill-archived', default => '' }; @endphp
                    <span class="pill {{ $svcStatePill }}">{{ __($selected_service['state']) }}</span>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(5, minmax(0, 1fr));">
                <div class="metric-card"><div class="metric-label">{{ __('RTO') }}</div><div class="metric-value">{{ $selected_service['recovery_time_objective_hours'] }}h</div></div>
                <div class="metric-card"><div class="metric-label">{{ __('RPO') }}</div><div class="metric-value">{{ $selected_service['recovery_point_objective_hours'] }}h</div></div>
                <div class="metric-card"><div class="metric-label">{{ __('Recovery plans') }}</div><div class="metric-value">{{ $selected_service['plan_count'] }}</div></div>
                <div class="metric-card"><div class="metric-label">{{ __('Dependencies') }}</div><div class="metric-value">{{ count($selected_service['dependencies']) }}</div></div>
                <div class="metric-card"><div class="metric-label">{{ __('Documents') }}</div><div class="metric-value">{{ count($selected_service['artifacts']) }}</div></div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">{{ __('Overview') }}</div>
                    <div class="table-note" style="margin-top:10px;">{{ __('Owners: :count', ['count' => count($selected_service['owner_assignments'])]) }}</div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($selected_service['owner_assignments'] as $owner)
                            <div class="data-item">
                                <div class="entity-title">{{ $owner['display_name'] }}</div>
                                <div class="table-note">{{ $owner['kind'] }}</div>
                                @if ($can_manage_continuity)
                                    <form method="POST" action="{{ str_replace('__ASSIGNMENT__', $owner['assignment_id'], $selected_service['owner_remove_route']) }}" style="margin-top:8px;">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="menu" value="plugin.continuity-bcm.root">
                                        <input type="hidden" name="service_id" value="{{ $selected_service['id'] }}">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <button class="button button-ghost" type="submit">{{ __('Remove owner') }}</button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <span class="muted-note">{{ __('No owner assigned') }}</span>
                        @endforelse
                    </div>
                    <div class="table-note">{{ __('Asset: :value', ['value' => $selected_service['linked_asset_label'] ?? ($selected_service['linked_asset_id'] !== '' ? $selected_service['linked_asset_id'] : __('None'))]) }}</div>
                    <div class="table-note">{{ __('Risk: :value', ['value' => $selected_service['linked_risk_id'] !== '' ? $selected_service['linked_risk_id'] : __('None')]) }}</div>
                    <div class="table-note">{{ __('Scope: :value', ['value' => $selected_service['scope_id'] !== '' ? $selected_service['scope_id'] : __('Organization-wide')]) }}</div>
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">{{ __('Workflow') }}</div>
                    @if ($selected_service['transitions'] !== [])
                        <div class="action-cluster" style="margin-top:10px;">
                            @foreach ($selected_service['transitions'] as $transition)
                                <form method="POST" action="{{ str_replace('__TRANSITION__', $transition, $selected_service['transition_route']) }}">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.continuity-bcm.root">
                                    <input type="hidden" name="service_id" value="{{ $selected_service['id'] }}">
                                    <input type="hidden" name="scope_id" value="{{ $selected_service['scope_id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <button class="button button-secondary" type="submit">{{ __($transition) }}</button>
                                </form>
                            @endforeach
                        </div>
                    @else
                        <div class="table-note" style="margin-top:10px;">{{ __('View-only access') }}</div>
                    @endif

                    @if ($can_manage_continuity)
                        <details style="margin-top:12px;">
                            <summary class="button button-ghost" style="display:inline-flex;">{{ __('Edit service') }}</summary>
                            <form class="upload-form" method="POST" action="{{ $selected_service['update_route'] }}" style="margin-top:10px;">
                                @csrf
                                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                <input type="hidden" name="menu" value="plugin.continuity-bcm.root">
                                <input type="hidden" name="service_id" value="{{ $selected_service['id'] }}">
                                <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                <div class="field">
                                    <label class="field-label">{{ __('Title') }}</label>
                                    <input class="field-input" name="title" value="{{ $selected_service['title'] }}" required>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Impact tier') }}</label>
                                    <select class="field-select" name="impact_tier" required>
                                        @foreach ($impact_tier_options as $option)
                                            <option value="{{ $option['id'] }}" @selected($selected_service['impact_tier'] === $option['id'])>{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('RTO hours') }}</label>
                                    <input class="field-input" name="recovery_time_objective_hours" type="number" min="0" max="8760" value="{{ $selected_service['recovery_time_objective_hours'] }}" required>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('RPO hours') }}</label>
                                    <input class="field-input" name="recovery_point_objective_hours" type="number" min="0" max="8760" value="{{ $selected_service['recovery_point_objective_hours'] }}" required>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Linked asset') }}</label>
                                    <select class="field-select" name="linked_asset_id">
                                        <option value="">{{ __('No linked asset') }}</option>
                                        @foreach ($asset_options as $asset)
                                            <option value="{{ $asset['id'] }}" @selected($selected_service['linked_asset_id'] === $asset['id'])>{{ $asset['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Linked risk') }}</label>
                                    <select class="field-select" name="linked_risk_id">
                                        <option value="">{{ __('No linked risk') }}</option>
                                        @foreach ($risk_options as $risk)
                                            <option value="{{ $risk['id'] }}" @selected($selected_service['linked_risk_id'] === $risk['id'])>{{ $risk['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Scope') }}</label>
                                    <select class="field-select" name="scope_id">
                                        <option value="">{{ __('Organization-wide') }}</option>
                                        @foreach ($scope_options as $scope)
                                            <option value="{{ $scope['id'] }}" @selected($selected_service['scope_id'] === $scope['id'])>{{ $scope['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field-label">{{ __('Add owner actor') }}</label>
                                    <select class="field-select" name="owner_actor_id">
                                        <option value="">{{ __('Do not add owner') }}</option>
                                        @foreach ($owner_actor_options as $actor)
                                            <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <div class="table-note">{{ __('Selecting an actor adds another owner instead of replacing the current set.') }}</div>
                                </div>
                                @if (($selected_service['owner_assignments'] ?? []) !== [])
                                    <div class="field" style="grid-column:1 / -1;">
                                        <label class="field-label">{{ __('Current owners') }}</label>
                                        <div class="data-stack">
                                            @foreach ($selected_service['owner_assignments'] as $owner)
                                                <div class="data-item">
                                                    <div class="entity-title">{{ $owner['display_name'] }}</div>
                                                    <div class="table-note">{{ $owner['kind'] }}</div>
                                                    <form method="POST" action="{{ str_replace('__ASSIGNMENT__', $owner['assignment_id'], $selected_service['owner_remove_route']) }}" style="margin-top:8px;">
                                                        @csrf
                                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                                        <input type="hidden" name="menu" value="plugin.continuity-bcm.root">
                                                        <input type="hidden" name="service_id" value="{{ $selected_service['id'] }}">
                                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                                        <button class="button button-ghost" type="submit">{{ __('Remove owner') }}</button>
                                                    </form>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                                <div class="action-cluster">
                                    <button class="button button-secondary" type="submit">{{ __('Save changes') }}</button>
                                </div>
                            </form>
                        </details>
                    @endif
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(3, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="row-between">
                        <div class="metric-label">{{ __('Recovery plans') }}</div>
                        @if ($can_manage_continuity)
                            <details>
                                <summary class="button button-ghost" style="display:inline-flex;">{{ __('Add recovery plan') }}</summary>
                                <form class="upload-form" method="POST" action="{{ $selected_service['plan_store_route'] }}" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.continuity-bcm.root">
                                    <input type="hidden" name="service_id" value="{{ $selected_service['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <input type="hidden" name="scope_id" value="{{ $selected_service['scope_id'] }}">
                                    <div class="field">
                                        <label class="field-label">{{ __('Plan title') }}</label>
                                        <input class="field-input" name="title" placeholder="{{ __('Recovery plan title') }}" required>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Strategy summary') }}</label>
                                        <input class="field-input" name="strategy_summary" placeholder="{{ __('How this service recovers') }}" required>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Test due') }}</label>
                                        <input class="field-input" name="test_due_on" type="date">
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Linked policy') }}</label>
                                        <select class="field-select" name="linked_policy_id">
                                            <option value="">{{ __('No linked policy') }}</option>
                                            @foreach ($policy_options as $policy)
                                                <option value="{{ $policy['id'] }}">{{ $policy['label'] }}</option>
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
                                        <label class="field-label">{{ __('Initial owner actor') }}</label>
                                        <select class="field-select" name="owner_actor_id">
                                            <option value="">{{ __('No owner') }}</option>
                                            @foreach ($owner_actor_options as $actor)
                                                <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="action-cluster">
                                        <button class="button button-secondary" type="submit">{{ __('Create recovery plan') }}</button>
                                    </div>
                                </form>
                            </details>
                        @endif
                    </div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($selected_service['plans'] as $plan)
                            <div class="data-item">
                                <div class="row-between" style="gap:10px; align-items:flex-start;">
                                    <div>
                                        <div class="entity-title">{{ $plan['title'] }}</div>
                                        <div class="table-note">{{ $plan['strategy_summary'] }}</div>
                                        <div class="table-note">{{ $plan['test_due_on'] !== '' ? __('Test due: :date', ['date' => $plan['test_due_on']]) : __('No test date') }}</div>
                                    </div>
                                    <a class="button button-secondary" href="{{ $plan['open_url'] }}">{{ __('Open') }}</a>
                                </div>
                            </div>
                        @empty
                            <span class="muted-note">{{ __('No recovery plans yet') }}</span>
                        @endforelse
                    </div>
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="row-between">
                        <div class="metric-label">{{ __('Dependencies') }}</div>
                        @if ($can_manage_continuity)
                            <details>
                                <summary class="button button-ghost" style="display:inline-flex;">{{ __('Add dependency') }}</summary>
                                <form class="upload-form" method="POST" action="{{ $selected_service['dependency_store_route'] }}" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.continuity-bcm.root">
                                    <input type="hidden" name="service_id" value="{{ $selected_service['id'] }}">
                                    <input type="hidden" name="scope_id" value="{{ $selected_service['scope_id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <select class="field-select" name="depends_on_service_id" required>
                                        <option value="">{{ __('Depends on...') }}</option>
                                        @foreach ($service_options as $option)
                                            @if ($option['id'] !== $selected_service['id'])
                                                <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                    <select class="field-select" name="dependency_kind">
                                        @foreach ($dependency_kind_options as $option)
                                            <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <input class="field-input" type="text" name="recovery_notes" placeholder="{{ __('Recovery note') }}">
                                    <button class="button button-secondary" type="submit">{{ __('Save dependency') }}</button>
                                </form>
                            </details>
                        @endif
                    </div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($selected_service['dependencies'] as $dependency)
                            <div class="data-item">
                                <div class="entity-title">{{ $dependency['depends_on_service_title'] }}</div>
                                <div class="table-note">{{ __(':kind dependency', ['kind' => $dependency['dependency_kind_label']]) }}</div>
                                @if ($dependency['recovery_notes'] !== '')
                                    <div class="table-note">{{ $dependency['recovery_notes'] }}</div>
                                @endif
                            </div>
                        @empty
                            <span class="muted-note">{{ __('No dependencies mapped') }}</span>
                        @endforelse
                    </div>
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="row-between">
                        <div class="metric-label">{{ __('Documents') }}</div>
                        @if ($can_manage_continuity)
                            <details>
                                <summary class="button button-ghost" style="display:inline-flex;">{{ __('Attach document') }}</summary>
                                <form class="upload-form" method="POST" action="{{ $selected_service['artifact_upload_route'] }}" enctype="multipart/form-data" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.continuity-bcm.root">
                                    <input type="hidden" name="service_id" value="{{ $selected_service['id'] }}">
                                    <input type="hidden" name="scope_id" value="{{ $selected_service['scope_id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <input type="hidden" name="artifact_type" value="continuity-record">
                                    <input class="field-input" type="text" name="label" placeholder="{{ __('Document label') }}">
                                    <input class="field-input" type="file" name="artifact" required>
                                    <button class="button button-secondary" type="submit">{{ __('Upload document') }}</button>
                                </form>
                            </details>
                        @endif
                    </div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($selected_service['artifacts'] as $artifact)
                            <div class="data-item">
                                <div class="row-between" style="align-items:flex-start; gap:12px;">
                                    <div>
                                        <div class="entity-title">{{ $artifact['label'] }}</div>
                                        <div class="table-note">{{ $artifact['original_filename'] }}</div>
                                    </div>
                                    <form method="POST" action="{{ route('plugin.evidence-management.promote', ['artifactId' => $artifact['id']]) }}">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                        <input type="hidden" name="scope_id" value="{{ $selected_service['scope_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <button class="button button-ghost" type="submit">{{ __('Promote to evidence') }}</button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <span class="muted-note">{{ __('No documents yet') }}</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @else
        @if ($can_manage_continuity)
            <div class="surface-card" id="continuity-service-editor" hidden>
                <div class="row-between" style="margin-bottom:14px;">
                    <div>
                        <div class="eyebrow">{{ __('Continuity') }}</div>
                        <div class="entity-title" style="font-size:24px;">{{ __('New service') }}</div>
                    </div>
                </div>

                <form class="upload-form" method="POST" action="{{ $create_route }}">
                    @csrf
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                    <input type="hidden" name="menu" value="plugin.continuity-bcm.root">
                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">

                    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                        <div class="field">
                            <label class="field-label" for="service-title">{{ __('Title') }}</label>
                            <input class="field-input" id="service-title" name="title" required>
                        </div>
                        <div class="field">
                            <label class="field-label" for="service-impact">{{ __('Impact tier') }}</label>
                            <select class="field-select" id="service-impact" name="impact_tier" required>
                                <option value="">{{ __('Choose impact tier') }}</option>
                                @foreach ($impact_tier_options as $option)
                                    <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="service-rto">{{ __('RTO hours') }}</label>
                            <input class="field-input" id="service-rto" name="recovery_time_objective_hours" type="number" min="0" max="8760" required>
                        </div>
                        <div class="field">
                            <label class="field-label" for="service-rpo">{{ __('RPO hours') }}</label>
                            <input class="field-input" id="service-rpo" name="recovery_point_objective_hours" type="number" min="0" max="8760" required>
                        </div>
                        <div class="field">
                            <label class="field-label" for="service-asset">{{ __('Linked asset') }}</label>
                            <select class="field-select" id="service-asset" name="linked_asset_id">
                                <option value="">{{ __('No linked asset') }}</option>
                                @foreach ($asset_options as $asset)
                                    <option value="{{ $asset['id'] }}">{{ $asset['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="service-risk">{{ __('Linked risk') }}</label>
                            <select class="field-select" id="service-risk" name="linked_risk_id">
                                <option value="">{{ __('No linked risk') }}</option>
                                @foreach ($risk_options as $risk)
                                    <option value="{{ $risk['id'] }}">{{ $risk['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="service-scope">{{ __('Scope') }}</label>
                            <select class="field-select" id="service-scope" name="scope_id">
                                <option value="">{{ __('Organization-wide') }}</option>
                                @foreach ($scope_options as $scope)
                                    <option value="{{ $scope['id'] }}" @selected(($query['scope_id'] ?? null) === $scope['id'])>{{ $scope['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="service-owner">{{ __('Initial owner actor') }}</label>
                            <select class="field-select" id="service-owner" name="owner_actor_id">
                                <option value="">{{ __('No owner') }}</option>
                                @foreach ($owner_actor_options as $actor)
                                    <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="action-cluster" style="margin-top:14px;">
                        <button class="button button-primary" type="submit">{{ __('Create service') }}</button>
                    </div>
                </form>
            </div>
        @endif

        <div class="overview-grid" style="grid-template-columns:repeat(5, minmax(0, 1fr));">
            <div class="metric-card"><div class="metric-label">{{ __('Services') }}</div><div class="metric-value">{{ count($services) }}</div></div>
            <div class="metric-card"><div class="metric-label">{{ __('Active') }}</div><div class="metric-value">{{ collect($services)->where('state', 'active')->count() }}</div></div>
            <div class="metric-card"><div class="metric-label">{{ __('Under Review') }}</div><div class="metric-value">{{ collect($services)->where('state', 'review')->count() }}</div></div>
            <div class="metric-card"><div class="metric-label">{{ __('Recovery Plans') }}</div><div class="metric-value">{{ collect($services)->sum('plan_count') }}</div></div>
            <div class="metric-card"><div class="metric-label">{{ __('Dependencies') }}</div><div class="metric-value">{{ collect($services)->sum(fn ($service) => count($service['dependencies'])) }}</div></div>
        </div>

        <div class="surface-card">
            <div class="entity-title">{{ __('Continuity service list') }}</div>
            <div class="table-note" style="margin-top:6px;">{{ __('This list stays focused on impact summary, owner summary, linked records, state, and Open. Use Continuity Service Detail to manage recovery plans, dependencies, documents, ownership, and workflow.') }}</div>
        </div>

        <div class="table-card" id="continuity-service-plans">
            <table class="entity-table">
                <thead>
                    <tr>
                        <th>{{ __('Service') }}</th>
                        <th>{{ __('Recovery Objectives') }}</th>
                        <th>{{ __('Owner') }}</th>
                        <th>{{ __('Assets') }}</th>
                        <th>{{ __('Risks') }}</th>
                        <th>{{ __('State') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($services as $service)
                        <tr>
                            <td>
                                <div class="entity-title">{{ $service['title'] }}</div>
                                <div class="entity-id">{{ $service['id'] }} · {{ $service['impact_tier_label'] }}</div>
                                <div class="table-note">{{ __(':count recovery plans', ['count' => $service['plan_count']]) }}</div>
                            </td>
                            <td>
                                <div>{{ __('RTO') }}: {{ $service['recovery_time_objective_hours'] }}h</div>
                                <div>{{ __('RPO') }}: {{ $service['recovery_point_objective_hours'] }}h</div>
                            </td>
                            <td>
                                @if (($service['owner_assignments'] ?? []) !== [])
                                    <div>{{ $service['owner_assignments'][0]['display_name'] }}</div>
                                    @if (count($service['owner_assignments']) > 1)
                                        @php $extraOwners = count($service['owner_assignments']) - 1; @endphp
                                        <div class="table-note">+{{ $extraOwners }} {{ __($extraOwners === 1 ? 'more owner' : 'more owners') }}</div>
                                    @else
                                        <div class="table-note">{{ $service['owner_assignments'][0]['kind'] }}</div>
                                    @endif
                                @else
                                    <span class="muted-note">{{ __('No owner assigned') }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="data-stack">
                                    @forelse ($service['linked_assets'] as $asset)
                                        <a href="{{ $asset['url'] }}">{{ $asset['label'] }}</a>
                                    @empty
                                        <span class="muted-note">{{ __('No linked assets') }}</span>
                                    @endforelse
                                </div>
                            </td>
                            <td>
                                <div class="data-stack">
                                    @forelse ($service['linked_risks'] as $risk)
                                        <a href="{{ $risk['url'] }}">{{ $risk['label'] }}</a>
                                    @empty
                                        <span class="muted-note">{{ __('No linked risks') }}</span>
                                    @endforelse
                                </div>
                            </td>
                            <td>
                                @php $sSvcPill = match($service['state']) { 'active' => 'pill-active', 'review' => 'pill-review', 'draft' => 'pill-draft', 'archived' => 'pill-archived', default => '' }; @endphp
                                <span class="pill {{ $sSvcPill }}">{{ __($service['state']) }}</span>
                            </td>
                            <td>
                                <a class="button button-secondary" href="{{ $service['open_url'] }}&{{ http_build_query(['context_label' => __('Services'), 'context_back_url' => $services_list_url]) }}">{{ __('Open') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
