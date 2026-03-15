<section class="module-screen">
    @if (is_array($selected_role))
        <div class="surface-card" style="padding:16px; display:grid; gap:16px;">
            <div class="row-between" style="align-items:flex-start;">
                <div>
                    <div class="eyebrow">Role</div>
                    <h2 class="screen-title" style="font-size:28px;">{{ $selected_role['label'] }}</h2>
                    <div class="table-note">{{ $selected_role['key'] }}</div>
                    <div class="table-note">{{ $selected_role['category_label'] }}</div>
                </div>
                <div class="action-cluster">
                    <a class="button button-ghost" href="{{ $roles_list_url }}">Back to roles</a>
                    <span class="pill">{{ ($selected_role['is_system'] ?? false) ? 'system' : 'custom' }}</span>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
                <div class="metric-card"><div class="metric-label">Permissions</div><div class="metric-value">{{ count($selected_role['permissions']) }}</div></div>
                <div class="metric-card"><div class="metric-label">Category</div><div class="metric-value" style="font-size:20px;">{{ $selected_role['category_label'] }}</div></div>
                <div class="metric-card"><div class="metric-label">Source</div><div class="metric-value" style="font-size:20px;">{{ ($selected_role['is_system'] ?? false) ? 'System' : 'Custom' }}</div></div>
                <div class="metric-card"><div class="metric-label">Usage</div><div class="metric-value">{{ collect($grants)->where('grant_type', 'role')->where('value', $selected_role['key'])->count() }}</div></div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Category</div>
                    <div class="table-note" style="margin-top:10px;">{{ $selected_role['category_description'] }}</div>
                    <div class="data-stack" style="margin-top:12px;">
                        @foreach ($selected_role['permissions'] as $permission)
                            <div class="data-item">{{ $permission }}</div>
                        @endforeach
                    </div>
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Edit role</div>
                    <form class="upload-form" method="POST" action="{{ $role_store_route }}" style="margin-top:10px;">
                        @csrf
                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                        <input type="hidden" name="menu" value="core.roles">
                        <input type="hidden" name="role_key" value="{{ $selected_role['key'] }}">
                        <div class="field">
                            <label class="field-label">Role key</label>
                            <input class="field-input" name="key" value="{{ $selected_role['key'] }}" required @disabled($selected_role['is_system'] ?? false)>
                        </div>
                        @if ($selected_role['is_system'] ?? false)
                            <input type="hidden" name="key" value="{{ $selected_role['key'] }}">
                        @endif
                        <div class="field">
                            <label class="field-label">Label</label>
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
                            <button class="button button-secondary" type="submit">Save role</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @elseif (is_array($selected_grant))
        <div class="surface-card" style="padding:16px; display:grid; gap:16px;">
            <div class="row-between" style="align-items:flex-start;">
                <div>
                    <div class="eyebrow">Grant</div>
                    <h2 class="screen-title" style="font-size:28px;">{{ $selected_grant['grant_type'] }}: {{ $selected_grant['value'] }}</h2>
                    <div class="table-note">{{ $selected_grant['id'] }}</div>
                    <div class="table-note">{{ $selected_grant['target_type'] }} · {{ $selected_grant['target_id'] }}</div>
                </div>
                <div class="action-cluster">
                    <a class="button button-ghost" href="{{ $roles_list_url }}">Back to grants</a>
                    <span class="pill">{{ ($selected_grant['is_system'] ?? false) ? 'system' : 'custom' }}</span>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
                <div class="metric-card"><div class="metric-label">Target</div><div class="metric-value" style="font-size:18px;">{{ $selected_grant['target_type'] }}</div></div>
                <div class="metric-card"><div class="metric-label">Context</div><div class="metric-value" style="font-size:18px;">{{ $selected_grant['context_type'] }}</div></div>
                <div class="metric-card"><div class="metric-label">Organization</div><div class="metric-value" style="font-size:18px;">{{ $selected_grant['organization_id'] ?? 'platform' }}</div></div>
                <div class="metric-card"><div class="metric-label">Scope</div><div class="metric-value" style="font-size:18px;">{{ $selected_grant['scope_id'] ?? 'not scoped' }}</div></div>
            </div>

            <div class="surface-card" style="padding:14px;">
                <div class="metric-label">Edit grant</div>
                <form class="upload-form" method="POST" action="{{ route('core.grants.update', ['grantId' => $selected_grant['id']]) }}" style="margin-top:10px;">
                    @csrf
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                    <input type="hidden" name="menu" value="core.roles">
                    <input type="hidden" name="grant_id" value="{{ $selected_grant['id'] }}">
                    <div class="overview-grid" style="grid-template-columns:repeat(3, minmax(0, 1fr));">
                        <div class="field">
                            <label class="field-label">Target type</label>
                            <select class="field-select" name="target_type" required>
                                <option value="principal" @selected($selected_grant['target_type'] === 'principal')>Principal</option>
                                <option value="membership" @selected($selected_grant['target_type'] === 'membership')>Membership</option>
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">Target id</label>
                            <input class="field-input" name="target_id" value="{{ $selected_grant['target_id'] }}" list="grant-target-options" required>
                        </div>
                        <div class="field">
                            <label class="field-label">Grant type</label>
                            <select class="field-select" name="grant_type" required>
                                <option value="role" @selected($selected_grant['grant_type'] === 'role')>Role</option>
                                <option value="permission" @selected($selected_grant['grant_type'] === 'permission')>Permission</option>
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">Value</label>
                            <input class="field-input" name="value" value="{{ $selected_grant['value'] }}" list="grant-value-options" required>
                        </div>
                        <div class="field">
                            <label class="field-label">Context type</label>
                            <select class="field-select" name="context_type" required>
                                <option value="platform" @selected($selected_grant['context_type'] === 'platform')>Platform</option>
                                <option value="organization" @selected($selected_grant['context_type'] === 'organization')>Organization</option>
                                <option value="scope" @selected($selected_grant['context_type'] === 'scope')>Scope</option>
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">Organization</label>
                            <select class="field-select" name="organization_id">
                                <option value="">Not scoped</option>
                                @foreach ($organization_options as $organization)
                                    <option value="{{ $organization['id'] }}" @selected(($selected_grant['organization_id'] ?? null) === $organization['id'])>{{ $organization['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">Scope</label>
                            <select class="field-select" name="scope_id">
                                <option value="">Not scoped</option>
                                @foreach ($scope_options as $scope)
                                    <option value="{{ $scope['id'] }}" @selected(($selected_grant['scope_id'] ?? null) === $scope['id'])>{{ $scope['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="action-cluster">
                        <button class="button button-secondary" type="submit">Save grant</button>
                    </div>
                </form>
            </div>
        </div>
    @else
        <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
            <div class="metric-card"><div class="metric-label">Roles</div><div class="metric-value">{{ count($roles) }}</div></div>
            <div class="metric-card"><div class="metric-label">Grants</div><div class="metric-value">{{ count($grants) }}</div></div>
            <div class="metric-card"><div class="metric-label">Platform grants</div><div class="metric-value">{{ collect($grants)->where('context_type', 'platform')->count() }}</div></div>
            <div class="metric-card"><div class="metric-label">Organization grants</div><div class="metric-value">{{ collect($grants)->where('context_type', 'organization')->count() }}</div></div>
        </div>

        <div class="surface-note">
            Platform permissions belong to <strong>/admin</strong>. Day-to-day organization responsibilities should usually be granted through operational workspace role sets, with identity roles reserved for access governance.
        </div>

        <div class="surface-card" id="role-editor" hidden>
            <div class="row-between" style="margin-bottom:14px;">
                <div><div class="eyebrow">Create</div><div class="entity-title" style="font-size:24px;">New role</div></div>
            </div>
            <form class="upload-form" method="POST" action="{{ $role_store_route }}">
                @csrf
                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                <input type="hidden" name="menu" value="core.roles">
                <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                    <div class="field"><label class="field-label" for="role-key">Role key</label><input class="field-input" id="role-key" name="key" required></div>
                    <div class="field"><label class="field-label" for="role-label">Label</label><input class="field-input" id="role-label" name="label" required></div>
                    <div class="field" style="grid-column:1 / -1;">
                        <label class="field-label">Permissions</label>
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
                <div class="action-cluster" style="margin-top:14px;"><button class="button button-primary" type="submit">Create role</button></div>
            </form>
        </div>

        <div class="surface-card" id="grant-editor" hidden>
            <div class="row-between" style="margin-bottom:14px;">
                <div><div class="eyebrow">Assign</div><div class="entity-title" style="font-size:24px;">New grant</div></div>
            </div>
            <form class="upload-form" method="POST" action="{{ $grant_store_route }}">
                @csrf
                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                <input type="hidden" name="menu" value="core.roles">
                <div class="overview-grid" style="grid-template-columns:repeat(3, minmax(0, 1fr));">
                    <div class="field"><label class="field-label" for="grant-target-type">Target type</label><select class="field-select" id="grant-target-type" name="target_type" required><option value="principal">Principal</option><option value="membership" selected>Membership</option></select></div>
                    <div class="field"><label class="field-label" for="grant-target-id">Target id</label><input class="field-input" id="grant-target-id" name="target_id" list="grant-target-options" required></div>
                    <div class="field"><label class="field-label" for="grant-grant-type">Grant type</label><select class="field-select" id="grant-grant-type" name="grant_type" required><option value="role" selected>Role</option><option value="permission">Permission</option></select></div>
                    <div class="field"><label class="field-label" for="grant-value">Value</label><input class="field-input" id="grant-value" name="value" list="grant-value-options" required></div>
                    <div class="field"><label class="field-label" for="grant-context-type">Context type</label><select class="field-select" id="grant-context-type" name="context_type" required><option value="platform">Platform</option><option value="organization" selected>Organization</option><option value="scope">Scope</option></select></div>
                    <div class="field"><label class="field-label" for="grant-organization-id">Organization</label><select class="field-select" id="grant-organization-id" name="organization_id"><option value="">Not scoped</option>@foreach ($organization_options as $organization)<option value="{{ $organization['id'] }}">{{ $organization['label'] }}</option>@endforeach</select></div>
                    <div class="field"><label class="field-label" for="grant-scope-id">Scope</label><select class="field-select" id="grant-scope-id" name="scope_id"><option value="">Not scoped</option>@foreach ($scope_options as $scope)<option value="{{ $scope['id'] }}">{{ $scope['label'] }}</option>@endforeach</select></div>
                </div>
                <datalist id="grant-target-options">@foreach ($principal_options as $principal)<option value="{{ $principal }}">{{ $principal }}</option>@endforeach @foreach ($membership_options as $membership)<option value="{{ $membership['id'] }}">{{ $membership['label'] }}</option>@endforeach</datalist>
                <datalist id="grant-value-options">@foreach ($roles as $role)<option value="{{ $role['key'] }}">{{ $role['label'] }}</option>@endforeach @foreach ($permission_options as $permission)<option value="{{ $permission['key'] }}">{{ $permission['label'] }}</option>@endforeach</datalist>
                <div class="action-cluster" style="margin-top:14px;"><button class="button button-primary" type="submit">Create grant</button></div>
            </form>
        </div>

        <div class="table-card">
            <table class="entity-table">
                <thead>
                    <tr><th>Role</th><th>Category</th><th>Permissions</th><th>Source</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @foreach ($roles as $role)
                        <tr>
                            <td><div class="entity-title">{{ $role['label'] }}</div><div class="entity-id">{{ $role['key'] }}</div></td>
                            <td><div>{{ $role['category_label'] }}</div><div class="table-note">{{ $role['category_description'] }}</div></td>
                            <td><div class="table-note">{{ implode(', ', $role['permissions']) }}</div></td>
                            <td><span class="pill">{{ ($role['is_system'] ?? false) ? 'system' : 'custom' }}</span></td>
                            <td><a class="button button-secondary" href="{{ $role['open_url'] }}">Edit details</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="table-card">
            <table class="entity-table">
                <thead>
                    <tr><th>Grant</th><th>Target</th><th>Context</th><th>Source</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @foreach ($grants as $grant)
                        <tr>
                            <td><div class="entity-title">{{ $grant['grant_type'] }}: {{ $grant['value'] }}</div><div class="entity-id">{{ $grant['id'] }}</div></td>
                            <td><div>{{ $grant['target_type'] }}</div><div class="table-note">{{ $grant['target_id'] }}</div></td>
                            <td><div>{{ $grant['context_type'] }}</div><div class="table-note">{{ $grant['organization_id'] ?? 'platform' }}{{ ($grant['scope_id'] ?? null) !== null ? ' / '.$grant['scope_id'] : '' }}</div></td>
                            <td><span class="pill">{{ ($grant['is_system'] ?? false) ? 'system' : 'custom' }}</span></td>
                            <td><a class="button button-secondary" href="{{ $grant['open_url'] }}">Edit details</a></td>
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
