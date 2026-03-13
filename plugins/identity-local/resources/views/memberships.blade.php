<section class="module-screen">
    @if ($can_manage_memberships)
        <div class="surface-card" id="identity-membership-editor">
            <div class="row-between" style="margin-bottom:14px;">
                <div>
                    <div class="eyebrow">Access</div>
                    <div class="screen-title" style="font-size:26px;">Grant organization access</div>
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
                        <label class="field-label" for="identity-membership-roles">Role sets</label>
                        <select class="field-select" id="identity-membership-roles" name="role_keys[]" multiple size="{{ max(4, count($role_options)) }}">
                            @foreach ($role_options as $role)
                                <option value="{{ $role['key'] }}">{{ $role['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="action-cluster" style="margin-top:14px;">
                    <button class="button button-primary" type="submit">Grant access</button>
                </div>
            </form>
        </div>
    @endif

    <div class="overview-grid">
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
                        <td>
                            <div class="entity-title">{{ $row['user']['display_name'] ?? $row['membership']['principal_id'] }}</div>
                            <div class="table-note">{{ $row['user']['email'] ?? 'No local profile' }}</div>
                        </td>
                        <td>
                            <div>{{ $row['membership']['principal_id'] }}</div>
                            <div class="entity-id">{{ $row['membership']['id'] }}</div>
                        </td>
                        <td>
                            @if ($row['membership']['roles'] === [])
                                <span class="muted-note">No role sets yet</span>
                            @else
                                <div class="data-stack">
                                    @foreach ($row['membership']['roles'] as $role)
                                        <div class="data-item">{{ $role }}</div>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td>
                            @if ($row['membership']['scope_ids'] === [])
                                <span class="muted-note">All organization scopes</span>
                            @else
                                <div class="data-stack">
                                    @foreach ($row['membership']['scope_ids'] as $scopeId)
                                        <div class="data-item">{{ $scopeId }}</div>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td><span class="pill">{{ $row['membership']['is_active'] ? 'active' : 'inactive' }}</span></td>
                        <td>
                            @if (! $can_manage_memberships)
                                <span class="muted-note">View-only access</span>
                            @else
                                <details>
                                    <summary class="button button-ghost" style="display:inline-flex;">Edit</summary>
                                    <form class="upload-form" method="POST" action="{{ route('plugin.identity-local.memberships.update', ['membershipId' => $row['membership']['id']]) }}" style="margin-top:10px;">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                        <input type="hidden" name="organization_id" value="{{ $organization_id }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="menu" value="plugin.identity-local.memberships">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <div class="field">
                                            <label class="field-label">Person</label>
                                            <select class="field-select" name="subject_principal_id" required>
                                                @foreach ($user_options as $user)
                                                    <option value="{{ $user['principal_id'] }}" @selected($row['membership']['principal_id'] === $user['principal_id'])>{{ $user['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Role sets</label>
                                            <select class="field-select" name="role_keys[]" multiple size="{{ max(4, count($role_options)) }}">
                                                @foreach ($role_options as $role)
                                                    <option value="{{ $role['key'] }}" @selected(in_array($role['key'], $row['membership']['roles'], true))>{{ $role['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Scope access</label>
                                            <select class="field-select" name="scope_ids[]" multiple size="{{ max(3, count($scope_options)) }}">
                                                @foreach ($scope_options as $scope)
                                                    <option value="{{ $scope['id'] }}" @selected(in_array($scope['id'], $row['membership']['scope_ids'], true))>{{ $scope['name'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <label class="field-label" style="display:flex; gap:8px; align-items:center;">
                                            <input type="checkbox" name="is_active" value="1" @checked($row['membership']['is_active'])>
                                            Membership is active
                                        </label>
                                        <div class="action-cluster">
                                            <button class="button button-secondary" type="submit">Save access</button>
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
