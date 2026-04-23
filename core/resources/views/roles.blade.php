<section class="module-screen">
    <div class="surface-note">{{ __('core.roles.summary') }}</div>

    @if (is_array($selected_role))
        <div class="surface-card" style="padding:16px; display:grid; gap:16px;">
            <div class="surface-note">{{ __('core.roles.role_detail.summary') }}</div>

            <div class="row-between" style="align-items:flex-start;">
                <div>
                    <div class="eyebrow">{{ __('core.roles.role_detail.eyebrow') }}</div>
                    <h2 class="screen-title" style="font-size:28px;">{{ $selected_role['label'] }}</h2>
                    <div class="table-note">{{ $selected_role['key'] }}</div>
                    <div class="table-note">{{ $selected_role['category_label'] }}</div>
                </div>
                <div class="action-cluster">
                    <a class="button button-ghost" href="{{ $roles_list_url }}">{{ __('core.roles.back_to_roles') }}</a>
                    <span class="pill">{{ ($selected_role['is_system'] ?? false) ? __('core.status.system') : __('core.status.custom') }}</span>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
                <div class="metric-card"><div class="metric-label">{{ __('core.roles.metric.permissions') }}</div><div class="metric-value">{{ count($selected_role['permissions']) }}</div></div>
                <div class="metric-card"><div class="metric-label">{{ __('core.roles.metric.category') }}</div><div class="metric-value" style="font-size:20px;">{{ $selected_role['category_label'] }}</div></div>
                <div class="metric-card"><div class="metric-label">{{ __('core.roles.metric.source') }}</div><div class="metric-value" style="font-size:20px;">{{ ($selected_role['is_system'] ?? false) ? __('core.status.system') : __('core.status.custom') }}</div></div>
                <div class="metric-card"><div class="metric-label">{{ __('core.roles.metric.usage') }}</div><div class="metric-value">{{ collect($grants)->where('grant_type', 'role')->where('value', $selected_role['key'])->count() }}</div></div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">{{ __('core.roles.category.title') }}</div>
                    <div class="table-note" style="margin-top:10px;">{{ $selected_role['category_description'] }}</div>
                    <div class="data-stack" style="margin-top:12px;">
                        @foreach ($selected_role['permissions'] as $permission)
                            <div class="data-item">{{ $permission }}</div>
                        @endforeach
                    </div>
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">{{ __('core.roles.edit.title') }}</div>
                    <form class="upload-form" method="POST" action="{{ $role_store_route }}" style="margin-top:10px;">
                        @csrf
                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                        <input type="hidden" name="menu" value="core.roles">
                        <input type="hidden" name="role_key" value="{{ $selected_role['key'] }}">
                        <div class="field">
                            <label class="field-label">{{ __('core.roles.edit.role_key') }}</label>
                            <input class="field-input" name="key" value="{{ $selected_role['key'] }}" required @disabled($selected_role['is_system'] ?? false)>
                        </div>
                        @if ($selected_role['is_system'] ?? false)
                            <input type="hidden" name="key" value="{{ $selected_role['key'] }}">
                        @endif
                        <div class="field">
                            <label class="field-label">{{ __('core.roles.edit.label') }}</label>
                            <input class="field-input" name="label" value="{{ $selected_role['label'] }}" required>
                        </div>
                        <div class="overview-grid" style="grid-template-columns:repeat({{ max(1, count($permission_groups)) }}, minmax(0, 1fr)); gap:12px;">
                            @foreach ($permission_groups as $group)
                                <div class="surface-card" style="padding:12px;">
                                    <div class="entity-title">{{ $group['label'] }}</div>
                                    <div class="table-note" style="margin:4px 0 10px;">{{ $group['description'] }}</div>
                                    <div class="overview-grid" style="grid-template-columns:minmax(0, 1fr); gap:8px;">
                                        @foreach ($group['permissions'] as $permission)
                                            <label class="context-form" style="align-items:center;">
                                                <input type="checkbox" name="permissions[]" value="{{ $permission['key'] }}" @checked(in_array($permission['key'], $selected_role['permissions'], true))>
                                                <span class="meta-copy">{{ $permission['label'] }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="action-cluster">
                            <button class="button button-secondary" type="submit">{{ __('core.roles.edit.save_button') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @elseif (is_array($selected_grant))
        <div class="surface-card" style="padding:16px; display:grid; gap:16px;">
            <div class="surface-note">{{ __('core.roles.grant_detail.summary') }}</div>

            <div class="row-between" style="align-items:flex-start;">
                <div>
                    <div class="eyebrow">{{ __('core.roles.grant_detail.eyebrow') }}</div>
                    <h2 class="screen-title" style="font-size:28px;">{{ $selected_grant['grant_type'] }}: {{ $selected_grant['value'] }}</h2>
                    <div class="table-note">{{ $selected_grant['id'] }}</div>
                    <div class="table-note">{{ $selected_grant['target_type'] }} · {{ $selected_grant['target_id'] }}</div>
                </div>
                <div class="action-cluster">
                    <a class="button button-ghost" href="{{ $roles_list_url }}">{{ __('core.roles.back_to_grants') }}</a>
                    <span class="pill">{{ ($selected_grant['is_system'] ?? false) ? __('core.status.system') : __('core.status.custom') }}</span>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
                <div class="metric-card"><div class="metric-label">{{ __('core.roles.grant.metric.target') }}</div><div class="metric-value" style="font-size:18px;">{{ $selected_grant['target_type'] }}</div></div>
                <div class="metric-card"><div class="metric-label">{{ __('core.roles.grant.metric.context') }}</div><div class="metric-value" style="font-size:18px;">{{ $selected_grant['context_type'] }}</div></div>
                <div class="metric-card"><div class="metric-label">{{ __('core.roles.grant.metric.organization') }}</div><div class="metric-value" style="font-size:18px;">{{ $selected_grant['organization_id'] ?? __('core.roles.grant.platform') }}</div></div>
                <div class="metric-card"><div class="metric-label">{{ __('core.roles.grant.metric.scope') }}</div><div class="metric-value" style="font-size:18px;">{{ $selected_grant['scope_id'] ?? __('core.roles.grant.not_scoped') }}</div></div>
            </div>

            <div class="surface-card" style="padding:14px;">
                <div class="metric-label">{{ __('core.roles.grant.edit.title') }}</div>
                <form class="upload-form" method="POST" action="{{ route('core.grants.update', ['grantId' => $selected_grant['id']]) }}" style="margin-top:10px;">
                    @csrf
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                    <input type="hidden" name="menu" value="core.roles">
                    <input type="hidden" name="grant_id" value="{{ $selected_grant['id'] }}">
                    <div class="overview-grid" style="grid-template-columns:repeat(3, minmax(0, 1fr));">
                        <div class="field">
                            <label class="field-label">{{ __('core.roles.grant.edit.target_type') }}</label>
                            <select class="field-select" name="target_type" required>
                                <option value="principal" @selected($selected_grant['target_type'] === 'principal')>{{ __('core.roles.grant.edit.principal') }}</option>
                                <option value="membership" @selected($selected_grant['target_type'] === 'membership')>{{ __('core.roles.grant.edit.membership') }}</option>
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('core.roles.grant.edit.target_id') }}</label>
                            <input class="field-input" name="target_id" value="{{ $selected_grant['target_id'] }}" list="grant-target-options" required>
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('core.roles.grant.edit.grant_type') }}</label>
                            <select class="field-select" name="grant_type" required>
                                <option value="role" @selected($selected_grant['grant_type'] === 'role')>{{ __('core.roles.grant.edit.role') }}</option>
                                <option value="permission" @selected($selected_grant['grant_type'] === 'permission')>{{ __('core.roles.grant.edit.permission') }}</option>
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('core.roles.grant.edit.value') }}</label>
                            <input class="field-input" name="value" value="{{ $selected_grant['value'] }}" list="grant-value-options" required>
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('core.roles.grant.edit.context_type') }}</label>
                            <select class="field-select" name="context_type" required>
                                <option value="platform" @selected($selected_grant['context_type'] === 'platform')>{{ __('core.roles.grant.edit.platform') }}</option>
                                <option value="organization" @selected($selected_grant['context_type'] === 'organization')>{{ __('core.roles.grant.edit.organization') }}</option>
                                <option value="scope" @selected($selected_grant['context_type'] === 'scope')>{{ __('core.roles.grant.edit.scope') }}</option>
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('core.roles.grant.edit.organization_label') }}</label>
                            <select class="field-select" name="organization_id">
                                <option value="">{{ __('core.roles.grant.not_scoped') }}</option>
                                @foreach ($organization_options as $organization)
                                    <option value="{{ $organization['id'] }}" @selected(($selected_grant['organization_id'] ?? null) === $organization['id'])>{{ $organization['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('core.roles.grant.edit.scope_label') }}</label>
                            <select class="field-select" name="scope_id">
                                <option value="">{{ __('core.roles.grant.not_scoped') }}</option>
                                @foreach ($scope_options as $scope)
                                    <option value="{{ $scope['id'] }}" @selected(($selected_grant['scope_id'] ?? null) === $scope['id'])>{{ $scope['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="action-cluster">
                        <button class="button button-secondary" type="submit">{{ __('core.roles.grant.edit.save_button') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @else
        <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
            <div class="metric-card"><div class="metric-label">{{ __('core.roles.metric.roles') }}</div><div class="metric-value">{{ count($roles) }}</div></div>
            <div class="metric-card"><div class="metric-label">{{ __('core.roles.metric.grants') }}</div><div class="metric-value">{{ count($grants) }}</div></div>
            <div class="metric-card"><div class="metric-label">{{ __('core.roles.metric.platform_grants') }}</div><div class="metric-value">{{ collect($grants)->where('context_type', 'platform')->count() }}</div></div>
            <div class="metric-card"><div class="metric-label">{{ __('core.roles.metric.organization_grants') }}</div><div class="metric-value">{{ collect($grants)->where('context_type', 'organization')->count() }}</div></div>
        </div>

        <div class="surface-note">{{ __('core.roles.platform_note') }}</div>

        <div class="surface-card" id="role-editor" hidden>
            <div class="row-between" style="margin-bottom:14px;">
                <div><div class="eyebrow">{{ __('core.roles.create.eyebrow') }}</div><div class="entity-title" style="font-size:24px;">{{ __('core.roles.create.title') }}</div></div>
            </div>
            <form class="upload-form" method="POST" action="{{ $role_store_route }}">
                @csrf
                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                <input type="hidden" name="menu" value="core.roles">
                <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                    <div class="field"><label class="field-label" for="role-key">{{ __('core.roles.create.role_key') }}</label><input class="field-input" id="role-key" name="key" required></div>
                    <div class="field"><label class="field-label" for="role-label">{{ __('core.roles.create.label') }}</label><input class="field-input" id="role-label" name="label" required></div>
                    <div class="field" style="grid-column:1 / -1;">
                        <label class="field-label">{{ __('core.roles.create.permissions') }}</label>
                        <div class="overview-grid" style="grid-template-columns:repeat({{ max(1, count($permission_groups)) }}, minmax(0, 1fr)); gap:12px;">
                            @foreach ($permission_groups as $group)
                                <div class="surface-card" style="padding:12px;">
                                    <div class="entity-title">{{ $group['label'] }}</div>
                                    <div class="table-note" style="margin:4px 0 10px;">{{ $group['description'] }}</div>
                                    <div class="overview-grid" style="grid-template-columns:minmax(0, 1fr); gap:8px;">
                                        @foreach ($group['permissions'] as $permission)
                                            <label class="context-form" style="align-items:center;"><input type="checkbox" name="permissions[]" value="{{ $permission['key'] }}"><span class="meta-copy">{{ $permission['label'] }}</span></label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="action-cluster" style="margin-top:14px;"><button class="button button-primary" type="submit">{{ __('core.roles.create.button') }}</button></div>
            </form>
        </div>

        <div class="surface-card" id="grant-editor" hidden>
            <div class="row-between" style="margin-bottom:14px;">
                <div><div class="eyebrow">{{ __('core.roles.assign.eyebrow') }}</div><div class="entity-title" style="font-size:24px;">{{ __('core.roles.assign.title') }}</div></div>
            </div>
            <form class="upload-form" method="POST" action="{{ $grant_store_route }}">
                @csrf
                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                <input type="hidden" name="menu" value="core.roles">
                <div class="overview-grid" style="grid-template-columns:repeat(3, minmax(0, 1fr));">
                    <div class="field"><label class="field-label" for="grant-target-type">{{ __('core.roles.grant.edit.target_type') }}</label><select class="field-select" id="grant-target-type" name="target_type" required><option value="principal">{{ __('core.roles.grant.edit.principal') }}</option><option value="membership" selected>{{ __('core.roles.grant.edit.membership') }}</option></select></div>
                    <div class="field"><label class="field-label" for="grant-target-id">{{ __('core.roles.grant.edit.target_id') }}</label><input class="field-input" id="grant-target-id" name="target_id" list="grant-target-options" required></div>
                    <div class="field"><label class="field-label" for="grant-grant-type">{{ __('core.roles.grant.edit.grant_type') }}</label><select class="field-select" id="grant-grant-type" name="grant_type" required><option value="role" selected>{{ __('core.roles.grant.edit.role') }}</option><option value="permission">{{ __('core.roles.grant.edit.permission') }}</option></select></div>
                    <div class="field"><label class="field-label" for="grant-value">{{ __('core.roles.grant.edit.value') }}</label><input class="field-input" id="grant-value" name="value" list="grant-value-options" required></div>
                    <div class="field"><label class="field-label" for="grant-context-type">{{ __('core.roles.grant.edit.context_type') }}</label><select class="field-select" id="grant-context-type" name="context_type" required><option value="platform">{{ __('core.roles.grant.edit.platform') }}</option><option value="organization" selected>{{ __('core.roles.grant.edit.organization') }}</option><option value="scope">{{ __('core.roles.grant.edit.scope') }}</option></select></div>
                    <div class="field"><label class="field-label" for="grant-organization-id">{{ __('core.roles.grant.edit.organization_label') }}</label><select class="field-select" id="grant-organization-id" name="organization_id"><option value="">{{ __('core.roles.grant.not_scoped') }}</option>@foreach ($organization_options as $organization)<option value="{{ $organization['id'] }}">{{ $organization['label'] }}</option>@endforeach</select></div>
                    <div class="field"><label class="field-label" for="grant-scope-id">{{ __('core.roles.grant.edit.scope_label') }}</label><select class="field-select" id="grant-scope-id" name="scope_id"><option value="">{{ __('core.roles.grant.not_scoped') }}</option>@foreach ($scope_options as $scope)<option value="{{ $scope['id'] }}">{{ $scope['label'] }}</option>@endforeach</select></div>
                </div>
                <datalist id="grant-target-options">@foreach ($principal_options as $principal)<option value="{{ $principal }}">{{ $principal }}</option>@endforeach @foreach ($membership_options as $membership)<option value="{{ $membership['id'] }}">{{ $membership['label'] }}</option>@endforeach</datalist>
                <datalist id="grant-value-options">@foreach ($roles as $role)<option value="{{ $role['key'] }}">{{ $role['label'] }}</option>@endforeach @foreach ($permission_options as $permission)<option value="{{ $permission['key'] }}">{{ $permission['label'] }}</option>@endforeach</datalist>
                <div class="action-cluster" style="margin-top:14px;"><button class="button button-primary" type="submit">{{ __('core.roles.create.grant_button') }}</button></div>
            </form>
        </div>

        <div class="table-card">
            <div class="screen-header">
                <div>
                    <h2 class="screen-title" style="font-size:24px;">{{ __('core.roles.list.roles_title') }}</h2>
                    <p class="screen-subtitle">{{ __('core.roles.list.roles_subtitle') }}</p>
                </div>
            </div>
            <table class="entity-table">
                <thead>
                    <tr><th>{{ __('core.roles.list.role') }}</th><th>{{ __('core.roles.list.category') }}</th><th>{{ __('core.roles.list.permissions') }}</th><th>{{ __('core.roles.list.source') }}</th><th>{{ __('core.roles.list.actions') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($roles as $role)
                        <tr>
                            <td><div class="entity-title">{{ $role['label'] }}</div><div class="entity-id">{{ $role['key'] }}</div></td>
                            <td><div>{{ $role['category_label'] }}</div><div class="table-note">{{ $role['category_description'] }}</div></td>
                            <td><div class="table-note">{{ implode(', ', $role['permissions']) }}</div></td>
                            <td><span class="pill">{{ ($role['is_system'] ?? false) ? __('core.status.system') : __('core.status.custom') }}</span></td>
                            <td><a class="button button-secondary" href="{{ $role['open_url'] }}">{{ __('core.actions.open') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="table-card">
            <div class="screen-header">
                <div>
                    <h2 class="screen-title" style="font-size:24px;">{{ __('core.roles.list.grants_title') }}</h2>
                    <p class="screen-subtitle">{{ __('core.roles.list.grants_subtitle') }}</p>
                </div>
            </div>
            <table class="entity-table">
                <thead>
                    <tr><th>{{ __('core.roles.list.grant') }}</th><th>{{ __('core.roles.list.target') }}</th><th>{{ __('core.roles.list.context') }}</th><th>{{ __('core.roles.list.source') }}</th><th>{{ __('core.roles.list.actions') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($grants as $grant)
                        <tr>
                            <td><div class="entity-title">{{ $grant['grant_type'] }}: {{ $grant['value'] }}</div><div class="entity-id">{{ $grant['id'] }}</div></td>
                            <td><div>{{ $grant['target_type'] }}</div><div class="table-note">{{ $grant['target_id'] }}</div></td>
                            <td><div>{{ $grant['context_type'] }}</div><div class="table-note">{{ $grant['organization_id'] ?? __('core.roles.grant.platform') }}{{ ($grant['scope_id'] ?? null) !== null ? ' / '.$grant['scope_id'] : '' }}</div></td>
                            <td><span class="pill">{{ ($grant['is_system'] ?? false) ? __('core.status.system') : __('core.status.custom') }}</span></td>
                            <td><a class="button button-secondary" href="{{ $grant['open_url'] }}">{{ __('core.actions.open') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <datalist id="grant-target-options">
        @foreach ($principal_options as $principal)
            <option value="{{ $principal }}">{{ $principal }}</option>
        @endforeach
        @foreach ($membership_options as $membership)
            <option value="{{ $membership['id'] }}">{{ $membership['label'] }}</option>
        @endforeach
    </datalist>

    <datalist id="grant-value-options">
        @foreach ($roles as $role)
            <option value="{{ $role['key'] }}">{{ $role['label'] }}</option>
        @endforeach
        @foreach ($permission_options as $permission)
            <option value="{{ $permission['key'] }}">{{ $permission['label'] }}</option>
        @endforeach
    </datalist>
</section>
