<section class="module-screen">
    <div class="overview-grid">
        <div class="metric-card"><div class="metric-label">Roles</div><div class="metric-value">{{ count($roles) }}</div></div>
        <div class="metric-card"><div class="metric-label">Grants</div><div class="metric-value">{{ count($grants) }}</div></div>
        <div class="metric-card"><div class="metric-label">Platform Grants</div><div class="metric-value">{{ collect($grants)->where('context_type', 'platform')->count() }}</div></div>
        <div class="metric-card"><div class="metric-label">Organization Grants</div><div class="metric-value">{{ collect($grants)->where('context_type', 'organization')->count() }}</div></div>
    </div>

    <div class="surface-card" id="role-editor">
        <div class="row-between" style="margin-bottom:14px;">
            <div>
                <div class="eyebrow">Create</div>
                <div class="screen-title" style="font-size:26px;">New Role</div>
            </div>
        </div>

        <form class="upload-form" method="POST" action="{{ $role_store_route }}">
            @csrf
            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
            <input type="hidden" name="menu" value="core.roles">

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="field">
                    <label class="field-label" for="role-key">Role key</label>
                    <input class="field-input" id="role-key" name="key" required>
                </div>
                <div class="field">
                    <label class="field-label" for="role-label">Label</label>
                    <input class="field-input" id="role-label" name="label" required>
                </div>
                <div class="field" style="grid-column:1 / -1;">
                    <label class="field-label">Permissions</label>
                    <div class="overview-grid" style="grid-template-columns:repeat(3, minmax(0, 1fr)); gap:8px;">
                        @foreach ($permission_options as $permission)
                            <label class="context-form" style="align-items:center;">
                                <input type="checkbox" name="permissions[]" value="{{ $permission['key'] }}">
                                <span class="meta-copy">{{ $permission['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="action-cluster" style="margin-top:14px;">
                <button class="button button-primary" type="submit">Create Role</button>
            </div>
        </form>
    </div>

    <div class="surface-card" id="grant-editor">
        <div class="row-between" style="margin-bottom:14px;">
            <div>
                <div class="eyebrow">Assign</div>
                <div class="screen-title" style="font-size:26px;">New Grant</div>
            </div>
        </div>

        <form class="upload-form" method="POST" action="{{ $grant_store_route }}">
            @csrf
            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
            <input type="hidden" name="menu" value="core.roles">

            <div class="overview-grid" style="grid-template-columns:repeat(3, minmax(0, 1fr));">
                <div class="field">
                    <label class="field-label" for="grant-target-type">Target type</label>
                    <select class="field-select" id="grant-target-type" name="target_type" required>
                        <option value="principal">Principal</option>
                        <option value="membership" selected>Membership</option>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="grant-target-id">Target id</label>
                    <input class="field-input" id="grant-target-id" name="target_id" list="grant-target-options" required>
                </div>
                <div class="field">
                    <label class="field-label" for="grant-grant-type">Grant type</label>
                    <select class="field-select" id="grant-grant-type" name="grant_type" required>
                        <option value="role" selected>Role</option>
                        <option value="permission">Permission</option>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="grant-value">Value</label>
                    <input class="field-input" id="grant-value" name="value" list="grant-value-options" required>
                </div>
                <div class="field">
                    <label class="field-label" for="grant-context-type">Context type</label>
                    <select class="field-select" id="grant-context-type" name="context_type" required>
                        <option value="platform">Platform</option>
                        <option value="organization" selected>Organization</option>
                        <option value="scope">Scope</option>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="grant-organization-id">Organization</label>
                    <select class="field-select" id="grant-organization-id" name="organization_id">
                        <option value="">Not scoped</option>
                        @foreach ($organization_options as $organization)
                            <option value="{{ $organization['id'] }}">{{ $organization['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="grant-scope-id">Scope</label>
                    <select class="field-select" id="grant-scope-id" name="scope_id">
                        <option value="">Not scoped</option>
                        @foreach ($scope_options as $scope)
                            <option value="{{ $scope['id'] }}">{{ $scope['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

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

            <div class="action-cluster" style="margin-top:14px;">
                <button class="button button-primary" type="submit">Create Grant</button>
            </div>
        </form>
    </div>

    <div class="table-card">
        <table class="entity-table">
            <thead>
                <tr>
                    <th>Role</th>
                    <th>Permissions</th>
                    <th>Source</th>
                    <th>Update</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($roles as $role)
                    <tr>
                        <td>
                            <div class="entity-title">{{ $role['label'] }}</div>
                            <div class="entity-id">{{ $role['key'] }}</div>
                        </td>
                        <td>
                            <div class="table-note">{{ implode(', ', $role['permissions']) }}</div>
                        </td>
                        <td><span class="pill">{{ ($role['is_system'] ?? false) ? 'system' : 'custom' }}</span></td>
                        <td>
                            <details>
                                <summary class="button button-ghost" style="display:inline-flex;">Edit</summary>
                                <form class="upload-form" method="POST" action="{{ $role_store_route }}" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="core.roles">
                                    <div class="field">
                                        <label class="field-label">Role key</label>
                                        <input class="field-input" name="key" value="{{ $role['key'] }}" required @disabled($role['is_system'] ?? false)>
                                    </div>
                                    @if ($role['is_system'] ?? false)
                                        <input type="hidden" name="key" value="{{ $role['key'] }}">
                                    @endif
                                    <div class="field">
                                        <label class="field-label">Label</label>
                                        <input class="field-input" name="label" value="{{ $role['label'] }}" required>
                                    </div>
                                    <div class="overview-grid" style="grid-template-columns:repeat(3, minmax(0, 1fr)); gap:8px;">
                                        @foreach ($permission_options as $permission)
                                            <label class="context-form" style="align-items:center;">
                                                <input type="checkbox" name="permissions[]" value="{{ $permission['key'] }}" @checked(in_array($permission['key'], $role['permissions'], true))>
                                                <span class="meta-copy">{{ $permission['label'] }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <div class="action-cluster">
                                        <button class="button button-secondary" type="submit">Save Role</button>
                                    </div>
                                </form>
                            </details>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="table-card">
        <table class="entity-table">
            <thead>
                <tr>
                    <th>Grant</th>
                    <th>Target</th>
                    <th>Context</th>
                    <th>Source</th>
                    <th>Update</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($grants as $grant)
                    <tr>
                        <td>
                            <div class="entity-title">{{ $grant['grant_type'] }}: {{ $grant['value'] }}</div>
                            <div class="entity-id">{{ $grant['id'] }}</div>
                        </td>
                        <td>
                            <div>{{ $grant['target_type'] }}</div>
                            <div class="table-note">{{ $grant['target_id'] }}</div>
                        </td>
                        <td>
                            <div>{{ $grant['context_type'] }}</div>
                            <div class="table-note">{{ $grant['organization_id'] ?? 'platform' }}{{ ($grant['scope_id'] ?? null) !== null ? ' / '.$grant['scope_id'] : '' }}</div>
                        </td>
                        <td><span class="pill">{{ ($grant['is_system'] ?? false) ? 'system' : 'custom' }}</span></td>
                        <td>
                            <details>
                                <summary class="button button-ghost" style="display:inline-flex;">Edit</summary>
                                <form class="upload-form" method="POST" action="{{ route('core.grants.update', ['grantId' => $grant['id']]) }}" style="margin-top:10px;">
                                    @csrf
                                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                    <input type="hidden" name="menu" value="core.roles">
                                    <div class="field">
                                        <label class="field-label">Target type</label>
                                        <select class="field-select" name="target_type" required>
                                            <option value="principal" @selected($grant['target_type'] === 'principal')>Principal</option>
                                            <option value="membership" @selected($grant['target_type'] === 'membership')>Membership</option>
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Target id</label>
                                        <input class="field-input" name="target_id" value="{{ $grant['target_id'] }}" list="grant-target-options" required>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Grant type</label>
                                        <select class="field-select" name="grant_type" required>
                                            <option value="role" @selected($grant['grant_type'] === 'role')>Role</option>
                                            <option value="permission" @selected($grant['grant_type'] === 'permission')>Permission</option>
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Value</label>
                                        <input class="field-input" name="value" value="{{ $grant['value'] }}" list="grant-value-options" required>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Context type</label>
                                        <select class="field-select" name="context_type" required>
                                            <option value="platform" @selected($grant['context_type'] === 'platform')>Platform</option>
                                            <option value="organization" @selected($grant['context_type'] === 'organization')>Organization</option>
                                            <option value="scope" @selected($grant['context_type'] === 'scope')>Scope</option>
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Organization</label>
                                        <select class="field-select" name="organization_id">
                                            <option value="">Not scoped</option>
                                            @foreach ($organization_options as $organization)
                                                <option value="{{ $organization['id'] }}" @selected(($grant['organization_id'] ?? null) === $organization['id'])>{{ $organization['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Scope</label>
                                        <select class="field-select" name="scope_id">
                                            <option value="">Not scoped</option>
                                            @foreach ($scope_options as $scope)
                                                <option value="{{ $scope['id'] }}" @selected(($grant['scope_id'] ?? null) === $scope['id'])>{{ $scope['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="action-cluster">
                                        <button class="button button-secondary" type="submit">Save Grant</button>
                                    </div>
                                </form>
                            </details>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
