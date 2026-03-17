<section class="module-screen">
    @if (is_array($selected_row))
        @php($selectedMembership = $selected_row['membership'])
        <div class="surface-card" style="padding:16px; display:grid; gap:16px;">
            <div class="row-between" style="align-items:flex-start;">
                <div>
                    <div class="eyebrow">Access</div>
                    <h2 class="screen-title" style="font-size:28px;">{{ $selected_row['user']['display_name'] ?? $selectedMembership['principal_id'] }}</h2>
                    <div class="table-note">{{ $selectedMembership['id'] }}</div>
                    <div class="table-note">{{ $selected_row['user']['email'] ?? 'No local profile' }}</div>
                </div>
                <div class="action-cluster">
                    <a class="button button-ghost" href="{{ $memberships_list_url }}">Back to access</a>
                    <span class="pill">{{ $selectedMembership['is_active'] ? 'active' : 'inactive' }}</span>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
                <div class="metric-card"><div class="metric-label">Role sets</div><div class="metric-value">{{ count($selectedMembership['roles']) }}</div></div>
                <div class="metric-card"><div class="metric-label">Scopes</div><div class="metric-value">{{ count($selectedMembership['scope_ids']) }}</div></div>
                <div class="metric-card"><div class="metric-label">Principal</div><div class="metric-value" style="font-size:18px;">{{ $selectedMembership['principal_id'] }}</div></div>
                <div class="metric-card"><div class="metric-label">Organization</div><div class="metric-value" style="font-size:18px;">{{ $organization_id }}</div></div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Role sets</div>
                    <div class="data-stack" style="margin-top:10px;">
                        @if ($selectedMembership['roles'] === [])
                            <span class="muted-note">No role sets yet</span>
                        @else
                            @foreach ($role_option_groups as $group)
                                @php($groupRoles = array_values(array_filter($selectedMembership['roles'], static fn (string $roleKey): bool => in_array($roleKey, array_column($group['roles'], 'key'), true))))
                                @if ($groupRoles !== [])
                                    <div class="data-item">
                                        <div class="entity-title">{{ $group['label'] }}</div>
                                        <div class="table-note">{{ implode(', ', $groupRoles) }}</div>
                                    </div>
                                @endif
                            @endforeach
                        @endif
                    </div>
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Scopes</div>
                    <div class="data-stack" style="margin-top:10px;">
                        @if ($selectedMembership['scope_ids'] === [])
                            <span class="muted-note">All organization scopes</span>
                        @else
                            @foreach ($selectedMembership['scope_ids'] as $scopeId)
                                <div class="data-item">{{ $scopeId }}</div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>

            @if ($can_manage_memberships)
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Edit access</div>
                    <form class="upload-form" method="POST" action="{{ route('plugin.identity-local.memberships.update', ['membershipId' => $selectedMembership['id']]) }}" style="margin-top:10px;">
                        @csrf
                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                        <input type="hidden" name="organization_id" value="{{ $organization_id }}">
                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                        <input type="hidden" name="menu" value="plugin.identity-local.memberships">
                        <input type="hidden" name="selected_membership_id" value="{{ $selectedMembership['id'] }}">
                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                        <div class="field">
                            <label class="field-label">Person</label>
                            <select class="field-select" name="subject_principal_id" required>
                                @foreach ($user_options as $user)
                                    <option value="{{ $user['principal_id'] }}" @selected($selectedMembership['principal_id'] === $user['principal_id'])>{{ $user['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">Role sets</label>
                            <div class="overview-grid" style="grid-template-columns:repeat({{ max(1, count($role_option_groups)) }}, minmax(0, 1fr)); gap:12px;">
                                @foreach ($role_option_groups as $group)
                                    <div class="surface-card" style="padding:12px;">
                                        <div class="entity-title">{{ $group['label'] }}</div>
                                        <div class="table-note" style="margin:4px 0 10px;">{{ $group['description'] }}</div>
                                        <select class="field-select" name="role_keys[]" multiple size="{{ max(3, count($group['roles'])) }}">
                                            @foreach ($group['roles'] as $role)
                                                <option value="{{ $role['key'] }}" @selected(in_array($role['key'], $selectedMembership['roles'], true))>{{ $role['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="field">
                            <label class="field-label">Scope access</label>
                            <select class="field-select" name="scope_ids[]" multiple size="{{ max(3, count($scope_options)) }}">
                                @foreach ($scope_options as $scope)
                                    <option value="{{ $scope['id'] }}" @selected(in_array($scope['id'], $selectedMembership['scope_ids'], true))>{{ $scope['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label class="field-label" style="display:flex; gap:8px; align-items:center;">
                            <input type="checkbox" name="is_active" value="1" @checked($selectedMembership['is_active'])>
                            Membership is active
                        </label>
                        <div class="action-cluster">
                            <button class="button button-secondary" type="submit">Save access</button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    @else
        @if ($can_manage_memberships)
            <div class="surface-card" id="identity-membership-editor" hidden>
                <div class="row-between" style="margin-bottom:14px;">
                    <div>
                        <div class="eyebrow">Access</div>
                        <div class="entity-title" style="font-size:24px;">Grant organization access</div>
                    </div>
                </div>

                <form class="upload-form" method="POST" action="{{ $create_route }}">
                    @csrf
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                    <input type="hidden" name="organization_id" value="{{ $organization_id }}">
                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                    <input type="hidden" name="menu" value="plugin.identity-local.memberships">
                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                        <div class="field">
                            <label class="field-label" for="identity-membership-user">Person</label>
                            <select class="field-select" id="identity-membership-user" name="subject_principal_id" required>
                                <option value="">Select a person</option>
                                @foreach ($user_options as $user)
                                    <option value="{{ $user['principal_id'] }}">{{ $user['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="identity-membership-scopes">Scope access</label>
                            <select class="field-select" id="identity-membership-scopes" name="scope_ids[]" multiple size="{{ max(3, count($scope_options)) }}">
                                @foreach ($scope_options as $scope)
                                    <option value="{{ $scope['id'] }}">{{ $scope['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field" style="grid-column:1 / -1;">
                            <label class="field-label">Role sets</label>
                            <div class="overview-grid" style="grid-template-columns:repeat({{ max(1, count($role_option_groups)) }}, minmax(0, 1fr)); gap:12px;">
                                @foreach ($role_option_groups as $group)
                                    <div class="surface-card" style="padding:12px;">
                                        <div class="entity-title">{{ $group['label'] }}</div>
                                        <div class="table-note" style="margin:4px 0 10px;">{{ $group['description'] }}</div>
                                        <select class="field-select" name="role_keys[]" multiple size="{{ max(3, count($group['roles'])) }}">
                                            @foreach ($group['roles'] as $role)
                                                <option value="{{ $role['key'] }}">{{ $role['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="action-cluster" style="margin-top:14px;"><button class="button button-primary" type="submit">Grant access</button></div>
                </form>
            </div>
        @endif

        <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
            <div class="metric-card"><div class="metric-label">Memberships</div><div class="metric-value">{{ count($rows) }}</div></div>
            <div class="metric-card"><div class="metric-label">Active</div><div class="metric-value">{{ collect($rows)->where('membership.is_active', true)->count() }}</div></div>
            <div class="metric-card"><div class="metric-label">Role grants</div><div class="metric-value">{{ collect($rows)->sum(fn ($row) => count($row['membership']['roles'])) }}</div></div>
            <div class="metric-card"><div class="metric-label">Scoped access</div><div class="metric-value">{{ collect($rows)->filter(fn ($row) => $row['membership']['scope_ids'] !== [])->count() }}</div></div>
        </div>

        <div class="table-card">
            <table class="entity-table">
                <thead>
                    <tr>
                        <th>Person</th>
                        <th>Membership</th>
                        <th>Role sets</th>
                        <th>Scopes</th>
                        <th>Status</th>
                        <th>{{ $can_manage_memberships ? 'Actions' : 'Access' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td><div class="entity-title">{{ $row['user']['display_name'] ?? $row['membership']['principal_id'] }}</div><div class="table-note">{{ $row['user']['email'] ?? 'No local profile' }}</div></td>
                            <td><div>{{ $row['membership']['principal_id'] }}</div><div class="entity-id">{{ $row['membership']['id'] }}</div></td>
                            <td>@if ($row['membership']['roles'] === [])<span class="muted-note">No role sets yet</span>@else<div class="table-note">{{ count($row['membership']['roles']) }} roles</div>@endif</td>
                            <td>@if ($row['membership']['scope_ids'] === [])<span class="muted-note">All organization scopes</span>@else<div class="table-note">{{ count($row['membership']['scope_ids']) }} scopes</div>@endif</td>
                            <td><span class="pill">{{ $row['membership']['is_active'] ? 'active' : 'inactive' }}</span></td>
                            <td><a class="button button-secondary" href="{{ $row['open_url'] }}">Open</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
