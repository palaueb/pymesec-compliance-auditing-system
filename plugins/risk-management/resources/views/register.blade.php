<style>
    .pill-assessing  { background: rgba(245,158,11,0.14); color: #92400e; }
    .pill-accepted   { background: rgba(34,197,94,0.14);  color: #166534; }
    .pill-closed     { background: rgba(31,42,34,0.06);   color: var(--muted); }
    .pill-archived   { background: rgba(31,42,34,0.06);   color: var(--muted); }

    details > summary { cursor: pointer; list-style: none; }
    details > summary::-webkit-details-marker { display: none; }
</style>

<section class="module-screen">
    <div class="surface-note">
        {{ __('Risk categories are business-managed catalog values from `Reference catalogs`. Risk workflow states such as `assessing`, `accepted`, or `closed` are system-controlled.') }}
    </div>

    @if (is_array($selected_risk))
        <div class="surface-card" style="padding:16px; display:grid; gap:16px;">
            <div class="surface-note">
                {{ __('Risk Detail keeps evidence, workflow, linked records, ownership, and treatment maintenance in one workspace. Use the risk register to browse risks and open the one you want to work on.') }}
            </div>

            <div class="row-between" style="align-items:flex-start;">
                <div>
                    <div class="eyebrow">{{ __('Risk Detail') }}</div>
                    <h2 class="screen-title" style="font-size:28px;">{{ $selected_risk['title'] }}</h2>
                    <div class="table-note">{{ $selected_risk['id'] }}</div>
                    <div class="table-note">{{ $selected_risk['category_label'] }}</div>
                </div>
                <div class="action-cluster">
                    @php $riskStatePill = match($selected_risk['state']) { 'assessing' => 'pill-assessing', 'accepted' => 'pill-accepted', 'closed' => 'pill-closed', 'archived' => 'pill-archived', default => '' }; @endphp
                    <span class="pill {{ $riskStatePill }}">{{ $selected_risk['state'] }}</span>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
                <div class="metric-card"><div class="metric-label">{{ __('Inherent') }}</div><div class="metric-value">{{ $selected_risk['inherent_score'] }}</div></div>
                <div class="metric-card"><div class="metric-label">{{ __('Residual') }}</div><div class="metric-value">{{ $selected_risk['residual_score'] }}</div></div>
                <div class="metric-card"><div class="metric-label">{{ __('Evidence') }}</div><div class="metric-value">{{ count($selected_risk['artifacts']) }}</div></div>
                <div class="metric-card"><div class="metric-label">{{ __('Scope') }}</div><div class="metric-value" style="font-size:20px;">{{ $selected_risk['scope_id'] !== '' ? $selected_risk['scope_id'] : __('Organization-wide') }}</div></div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">{{ __('Overview') }}</div>
                    <div class="table-note" style="margin-top:10px;">{{ __('Treatment: :treatment', ['treatment' => $selected_risk['treatment']]) }}</div>
                    <div class="table-note">{{ __('Owners: :count', ['count' => count($selected_risk['owner_assignments'])]) }}</div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($selected_risk['owner_assignments'] as $owner)
                            <div class="data-item">
                                <div class="entity-title">{{ $owner['display_name'] }}</div>
                                <div class="table-note">{{ $owner['kind'] }}</div>
                                @if ($can_manage_risks)
                                    <form method="POST" action="{{ str_replace('__ASSIGNMENT__', $owner['assignment_id'], $selected_risk['owner_remove_route']) }}" style="margin-top:8px;">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                        <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="menu" value="plugin.risk-management.root">
                                        <input type="hidden" name="risk_id" value="{{ $selected_risk['id'] }}">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <button class="button button-ghost" type="submit">{{ __('Remove owner') }}</button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <span class="muted-note">{{ __('No owner assigned') }}</span>
                        @endforelse
                    </div>
                    <div class="table-note">
                        {{ __('Asset') }}:
                        @if ($selected_risk['linked_asset_url'] !== null)
                            <a href="{{ $selected_risk['linked_asset_url'] }}">{{ $selected_risk['linked_asset_label'] ?? $selected_risk['linked_asset_id'] }}</a>
                        @else
                            {{ $selected_risk['linked_asset_id'] !== '' ? $selected_risk['linked_asset_id'] : __('None') }}
                        @endif
                    </div>
                    <div class="table-note">
                        {{ __('Control') }}:
                        @if ($selected_risk['linked_control_url'] !== null)
                            <a href="{{ $selected_risk['linked_control_url'] }}">{{ $selected_risk['linked_control_label'] ?? $selected_risk['linked_control_id'] }}</a>
                        @else
                            {{ $selected_risk['linked_control_id'] !== '' ? $selected_risk['linked_control_id'] : __('None') }}
                        @endif
                    </div>
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">{{ __('Workflow') }}</div>
                    @if ($selected_risk['transitions'] !== [])
                        <div class="action-cluster" style="margin-top:10px;">
                            @foreach ($selected_risk['transitions'] as $transition)
                                <form method="POST" action="{{ str_replace('__TRANSITION__', $transition, $selected_risk['transition_route']) }}">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.risk-management.root">
                                    <input type="hidden" name="risk_id" value="{{ $selected_risk['id'] }}">
                                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                    <button class="button button-secondary" type="submit">{{ ucwords(str_replace('-', ' ', $transition)) }}</button>
                                </form>
                            @endforeach
                        </div>
                    @else
                        <div class="table-note" style="margin-top:10px;">{{ __('View-only access') }}</div>
                    @endif

                    <div class="data-stack" style="margin-top:12px;">
                        @forelse ($selected_risk['history'] as $history)
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
                    <div class="row-between">
                        <div class="metric-label">{{ __('Evidence') }}</div>
                        @if ($can_manage_risks)
                            <details>
                                <summary class="button button-ghost" style="display:inline-flex;">{{ __('Attach evidence') }}</summary>
                                <form class="upload-form" method="POST" action="{{ $selected_risk['artifact_upload_route'] }}" enctype="multipart/form-data" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="plugin.risk-management.root">
                                    <input type="hidden" name="risk_id" value="{{ $selected_risk['id'] }}">
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
                        @forelse ($selected_risk['artifacts'] as $artifact)
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
                                        <input type="hidden" name="scope_id" value="{{ $selected_risk['scope_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <button class="button button-ghost" type="submit">{{ __('Promote to evidence') }}</button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <span class="muted-note">{{ __('No evidence yet') }}</span>
                        @endforelse
                    </div>
                </div>

                @if ($can_manage_risks)
                    <div class="surface-card" style="padding:14px;">
                        <hr style="border:none; border-top:1px solid rgba(31,42,34,0.07); margin:0;">
                        <details>
                            <summary class="button button-ghost" style="display:inline-flex; width:fit-content;">{{ __('Edit risk details') }}</summary>
                            <form class="upload-form" method="POST" action="{{ $selected_risk['update_route'] }}" style="margin-top:14px;">
                                @csrf
                                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                                <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                <input type="hidden" name="menu" value="plugin.risk-management.root">
                                <input type="hidden" name="risk_id" value="{{ $selected_risk['id'] }}">
                                <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                                    <div class="field">
                                        <label class="field-label">{{ __('Title') }}</label>
                                        <input class="field-input" name="title" value="{{ $selected_risk['title'] }}" required>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Category') }}</label>
                                        <select class="field-select" name="category" required>
                                            @foreach ($risk_category_options as $option)
                                                <option value="{{ $option['id'] }}" @selected($selected_risk['category'] === $option['id'])>{{ $option['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Inherent score') }}</label>
                                        <input class="field-input" name="inherent_score" type="number" min="0" max="100" value="{{ $selected_risk['inherent_score'] }}" required>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Residual score') }}</label>
                                        <input class="field-input" name="residual_score" type="number" min="0" max="100" value="{{ $selected_risk['residual_score'] }}" required>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Linked asset') }}</label>
                                        <select class="field-select" name="linked_asset_id">
                                            <option value="">{{ __('No linked asset') }}</option>
                                            @foreach ($asset_options as $asset)
                                                <option value="{{ $asset['id'] }}" @selected($selected_risk['linked_asset_id'] === $asset['id'])>{{ $asset['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Linked control') }}</label>
                                        <select class="field-select" name="linked_control_id">
                                            <option value="">{{ __('No linked control') }}</option>
                                            @foreach ($control_options as $control)
                                                <option value="{{ $control['id'] }}" @selected($selected_risk['linked_control_id'] === $control['id'])>{{ $control['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Scope') }}</label>
                                        <select class="field-select" name="scope_id">
                                            <option value="">{{ __('Organization-wide') }}</option>
                                            @foreach ($scope_options as $scope)
                                                <option value="{{ $scope['id'] }}" @selected($selected_risk['scope_id'] === $scope['id'])>{{ $scope['name'] }}</option>
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
                                    <div class="field" style="grid-column:1 / -1;">
                                        <label class="field-label">{{ __('Treatment summary') }}</label>
                                        <input class="field-input" name="treatment" value="{{ $selected_risk['treatment'] }}" required>
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
        </div>
    @else
        @if ($can_manage_risks)
            <div class="surface-card" id="risk-editor" hidden>
                <div class="row-between" style="margin-bottom:14px;">
                    <div>
                        <div class="eyebrow">{{ __('Create') }}</div>
                        <div class="entity-title" style="font-size:24px;">{{ __('New risk') }}</div>
                    </div>
                </div>

                <form class="upload-form" method="POST" action="{{ $create_route }}">
                    @csrf
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                    <input type="hidden" name="organization_id" value="{{ $query['organization_id'] }}">
                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                    <input type="hidden" name="menu" value="plugin.risk-management.root">
                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">

                    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                        <div class="field">
                            <label class="field-label" for="risk-title">{{ __('Title') }}</label>
                            <input class="field-input" id="risk-title" name="title" required>
                        </div>
                        <div class="field">
                            <label class="field-label" for="risk-category">{{ __('Category') }}</label>
                            <select class="field-select" id="risk-category" name="category" required>
                                @foreach ($risk_category_options as $option)
                                    <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="risk-inherent">{{ __('Inherent score') }}</label>
                            <input class="field-input" id="risk-inherent" name="inherent_score" type="number" min="0" max="100" required>
                        </div>
                        <div class="field">
                            <label class="field-label" for="risk-residual">{{ __('Residual score') }}</label>
                            <input class="field-input" id="risk-residual" name="residual_score" type="number" min="0" max="100" required>
                        </div>
                        <div class="field">
                            <label class="field-label" for="risk-asset">{{ __('Linked asset') }}</label>
                            <select class="field-select" id="risk-asset" name="linked_asset_id">
                                <option value="">{{ __('No linked asset') }}</option>
                                @foreach ($asset_options as $asset)
                                    <option value="{{ $asset['id'] }}">{{ $asset['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="risk-control">{{ __('Linked control') }}</label>
                            <select class="field-select" id="risk-control" name="linked_control_id">
                                <option value="">{{ __('No linked control') }}</option>
                                @foreach ($control_options as $control)
                                    <option value="{{ $control['id'] }}">{{ $control['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="risk-scope">{{ __('Scope') }}</label>
                            <select class="field-select" id="risk-scope" name="scope_id">
                                <option value="">{{ __('Organization-wide') }}</option>
                                @foreach ($scope_options as $scope)
                                    <option value="{{ $scope['id'] }}" @selected(($query['scope_id'] ?? null) === $scope['id'])>{{ $scope['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="risk-owner">{{ __('Initial owner actor') }}</label>
                            <select class="field-select" id="risk-owner" name="owner_actor_id">
                                <option value="">{{ __('No owner') }}</option>
                                @foreach ($owner_actor_options as $actor)
                                    <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field" style="grid-column:1 / -1;">
                            <label class="field-label" for="risk-treatment">{{ __('Treatment summary') }}</label>
                            <input class="field-input" id="risk-treatment" name="treatment" required>
                        </div>
                    </div>

                    <div class="action-cluster" style="margin-top:14px;">
                        <button class="button button-primary" type="submit">{{ __('Create risk') }}</button>
                    </div>
                </form>
            </div>
        @endif

        <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
            <div class="metric-card"><div class="metric-label">{{ __('Risks') }}</div><div class="metric-value">{{ count($risks) }}</div></div>
            <div class="metric-card"><div class="metric-label">{{ __('Assessing') }}</div><div class="metric-value">{{ collect($risks)->where('state', 'assessing')->count() }}</div></div>
            <div class="metric-card"><div class="metric-label">{{ __('Accepted') }}</div><div class="metric-value">{{ collect($risks)->where('state', 'accepted')->count() }}</div></div>
            <div class="metric-card"><div class="metric-label">{{ __('Evidence') }}</div><div class="metric-value">{{ collect($risks)->sum(fn ($risk) => count($risk['artifacts'])) }}</div></div>
        </div>

        <div class="surface-card">
            <div class="entity-title">{{ __('Risk register list') }}</div>
            <div class="table-note" style="margin-top:6px;">{{ __('This list stays focused on category, score, owner summary, linked records, state, and Open. Use Risk Detail to manage evidence, workflow, and treatment maintenance.') }}</div>
        </div>

        <div class="table-card">
            <table class="entity-table">
                <thead>
                    <tr>
                        <th>{{ __('Risk') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Scores') }}</th>
                        <th>{{ __('Owner') }}</th>
                        <th>{{ __('Asset') }}</th>
                        <th>{{ __('Control') }}</th>
                        <th>{{ __('State') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($risks as $risk)
                        <tr>
                            <td>
                                <div class="entity-title">{{ $risk['title'] }}</div>
                                <div class="entity-id">{{ $risk['id'] }}</div>
                                <div class="table-note">{{ $risk['treatment'] }}</div>
                            </td>
                            <td>{{ $risk['category_label'] }}</td>
                            <td>
                                <div><strong style="{{ $risk['inherent_score'] >= 70 ? 'color:#991b1b;' : ($risk['inherent_score'] >= 40 ? 'color:#92400e;' : 'color:#166534;') }}">{{ __('Inherent') }}:</strong> {{ $risk['inherent_score'] }}</div>
                                <div><strong style="{{ $risk['residual_score'] >= 70 ? 'color:#991b1b;' : ($risk['residual_score'] >= 40 ? 'color:#92400e;' : 'color:#166534;') }}">{{ __('Residual') }}:</strong> {{ $risk['residual_score'] }}</div>
                            </td>
                            <td>
                                @if (($risk['owner_assignments'] ?? []) !== [])
                                    <div>{{ $risk['owner_assignments'][0]['display_name'] }}</div>
                                    @if (count($risk['owner_assignments']) > 1)
                                        <div class="table-note">+{{ count($risk['owner_assignments']) - 1 }} {{ __('additional owner(s)') }}</div>
                                    @else
                                        <div class="table-note">{{ $risk['owner_assignments'][0]['kind'] }}</div>
                                    @endif
                                @else
                                    <span class="muted-note">{{ __('No owner assigned') }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($risk['linked_asset_url'] !== null)
                                    <a href="{{ $risk['linked_asset_url'] }}">{{ $risk['linked_asset_label'] ?? $risk['linked_asset_id'] }}</a>
                                @else
                                    <span class="muted-note">{{ $risk['linked_asset_id'] !== '' ? $risk['linked_asset_id'] : __('No linked asset') }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($risk['linked_control_url'] !== null)
                                    <a href="{{ $risk['linked_control_url'] }}">{{ $risk['linked_control_label'] ?? $risk['linked_control_id'] }}</a>
                                @else
                                    <span class="muted-note">{{ $risk['linked_control_id'] !== '' ? $risk['linked_control_id'] : __('No linked control') }}</span>
                                @endif
                            </td>
                            <td>
                                @php $sRiskPill = match($risk['state']) { 'assessing' => 'pill-assessing', 'accepted' => 'pill-accepted', 'closed' => 'pill-closed', 'archived' => 'pill-archived', default => '' }; @endphp
                                <span class="pill {{ $sRiskPill }}">{{ $risk['state'] }}</span>
                            </td>
                            <td>
                                <a class="button button-secondary" href="{{ $risk['open_url'] }}&{{ http_build_query(['context_label' => 'Risks', 'context_back_url' => $risks_list_url]) }}">{{ __('Open') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
