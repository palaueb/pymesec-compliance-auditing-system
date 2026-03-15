<section class="module-screen">
    @if (is_array($selected_row))
        @php($selectedUser = $selected_row['user'])
        <div class="surface-card" style="padding:16px; display:grid; gap:16px;">
            <div class="row-between" style="align-items:flex-start;">
                <div>
                    <div class="eyebrow">Person</div>
                    <h2 class="screen-title" style="font-size:28px;">{{ $selectedUser['display_name'] }}</h2>
                    <div class="table-note">{{ $selectedUser['job_title'] !== '' ? $selectedUser['job_title'] : 'No role defined yet' }}</div>
                    <div class="table-note">{{ $selectedUser['principal_id'] }}</div>
                </div>
                <div class="action-cluster">
                    <a class="button button-ghost" href="{{ $users_list_url }}">Back to people</a>
                    <span class="pill">{{ $selectedUser['is_active'] ? 'active' : 'inactive' }}</span>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
                <div class="metric-card"><div class="metric-label">Memberships</div><div class="metric-value">{{ count($selected_row['memberships']) }}</div></div>
                <div class="metric-card"><div class="metric-label">Profiles</div><div class="metric-value">{{ count($selected_row['linked_actors']) }}</div></div>
                <div class="metric-card"><div class="metric-label">Access mode</div><div class="metric-value" style="font-size:20px;">@if ($selectedUser['password_enabled']) Password @elseif ($selectedUser['magic_link_enabled']) Email link @else Disabled @endif</div></div>
                <div class="metric-card"><div class="metric-label">Directory</div><div class="metric-value" style="font-size:20px;">{{ $selectedUser['auth_provider'] === 'local' ? 'Local' : 'LDAP' }}</div></div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Access profile</div>
                    <div class="table-note" style="margin-top:10px;">{{ $selectedUser['username'] }}</div>
                    <div class="table-note">{{ $selectedUser['email'] }}</div>
                    <div class="table-note">
                        @if ($selectedUser['password_enabled'])
                            Password + email code
                        @elseif ($selectedUser['magic_link_enabled'])
                            Email sign-in link
                        @else
                            Access not enabled
                        @endif
                    </div>
                    @if ($selected_row['workspace_url'] !== null)
                        <div class="action-cluster" style="margin-top:12px;">
                            <a class="button button-secondary" href="{{ $selected_row['workspace_url'] }}">Open workspace</a>
                        </div>
                    @endif
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Responsible profiles</div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($selected_row['linked_actors'] as $actor)
                            <div class="data-item">
                                <div class="entity-title">{{ $actor['display_name'] }}</div>
                                <div class="table-note">{{ $actor['kind'] }}</div>
                            </div>
                        @empty
                            <span class="muted-note">No linked business owner</span>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="surface-card" style="padding:14px;">
                <div class="metric-label">Organization access</div>
                <div class="data-stack" style="margin-top:10px;">
                    @forelse ($selected_row['memberships'] as $membership)
                        <div class="data-item">
                            <div class="entity-title">{{ $membership['id'] }}</div>
                            <div class="table-note">{{ count($membership['roles']) }} role {{ count($membership['roles']) === 1 ? 'set' : 'sets' }}</div>
                        </div>
                    @empty
                        <span class="muted-note">No workspace access yet</span>
                    @endforelse
                </div>
            </div>

            @if ($can_manage_users)
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Edit person</div>
                    <form class="upload-form" method="POST" action="{{ route('plugin.identity-local.users.update', ['userId' => $selectedUser['id']]) }}" style="margin-top:10px;">
                        @csrf
                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                        <input type="hidden" name="organization_id" value="{{ $organization_id }}">
                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                        <input type="hidden" name="menu" value="plugin.identity-local.users">
                        <input type="hidden" name="user_id" value="{{ $selectedUser['id'] }}">
                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                        <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                            <div class="field">
                                <label class="field-label">Username</label>
                                <input class="field-input" name="username" value="{{ $selectedUser['username'] }}" required>
                            </div>
                            <div class="field">
                                <label class="field-label">Full name</label>
                                <input class="field-input" name="display_name" value="{{ $selectedUser['display_name'] }}" required>
                            </div>
                            <div class="field">
                                <label class="field-label">Work email</label>
                                <input class="field-input" name="email" type="email" value="{{ $selectedUser['email'] }}" required>
                            </div>
                            <div class="field">
                                <label class="field-label">Role or team</label>
                                <input class="field-input" name="job_title" value="{{ $selectedUser['job_title'] }}">
                            </div>
                            <div class="field">
                                <label class="field-label">New password</label>
                                <input class="field-input" name="password" type="password">
                            </div>
                            <div class="field">
                                <label class="field-label">Confirm password</label>
                                <input class="field-input" name="password_confirmation" type="password">
                            </div>
                            <div class="field">
                                <label class="field-label">Responsible profile</label>
                                <select class="field-select" name="actor_id">
                                    <option value="">Keep current link</option>
                                    @foreach ($owner_actor_options as $actor)
                                        <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="action-cluster" style="margin-top:14px;">
                            <label class="field-label" style="display:flex; gap:8px; align-items:center;">
                                <input type="checkbox" name="password_enabled" value="1" @checked($selectedUser['password_enabled'])>
                                Allow password sign-in
                            </label>
                            <label class="field-label" style="display:flex; gap:8px; align-items:center;">
                                <input type="checkbox" name="magic_link_enabled" value="1" @checked($selectedUser['magic_link_enabled'])>
                                Allow email sign-in link
                            </label>
                            <label class="field-label" style="display:flex; gap:8px; align-items:center;">
                                <input type="checkbox" name="is_active" value="1" @checked($selectedUser['is_active'])>
                                Active workspace person
                            </label>
                        </div>
                        <div class="action-cluster" style="margin-top:14px;">
                            <button class="button button-secondary" type="submit">Save person</button>
                            @if ($selectedUser['auth_provider'] === 'local' && $selectedUser['principal_id'] !== ($query['principal_id'] ?? null))
                                <button class="button button-ghost" formaction="{{ route('plugin.identity-local.users.delete', ['userId' => $selectedUser['id']]) }}" onclick="return confirm('Remove this local person and all of their organization access?')">Delete</button>
                            @endif
                        </div>
                    </form>
                    @if ($selectedUser['auth_provider'] !== 'local')
                        <div class="muted-note" style="margin-top:10px;">LDAP people are refreshed from directory sync.</div>
                    @endif
                </div>
            @endif
        </div>
    @else
        @if ($can_manage_users)
            <div class="surface-card" id="identity-user-editor" hidden>
                <div class="row-between" style="margin-bottom:14px;">
                    <div>
                        <div class="eyebrow">People</div>
                        <div class="entity-title" style="font-size:24px;">Add person</div>
                    </div>
                </div>

                <form class="upload-form" method="POST" action="{{ $create_route }}">
                    @csrf
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                    <input type="hidden" name="organization_id" value="{{ $organization_id }}">
                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                    <input type="hidden" name="menu" value="plugin.identity-local.users">
                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                        <div class="field"><label class="field-label" for="identity-username">Username</label><input class="field-input" id="identity-username" name="username" required></div>
                        <div class="field"><label class="field-label" for="identity-display-name">Full name</label><input class="field-input" id="identity-display-name" name="display_name" required></div>
                        <div class="field"><label class="field-label" for="identity-email">Work email</label><input class="field-input" id="identity-email" name="email" type="email" required></div>
                        <div class="field"><label class="field-label" for="identity-job-title">Role or team</label><input class="field-input" id="identity-job-title" name="job_title"></div>
                        <div class="field"><label class="field-label" for="identity-password">Password</label><input class="field-input" id="identity-password" name="password" type="password"></div>
                        <div class="field"><label class="field-label" for="identity-password-confirmation">Confirm password</label><input class="field-input" id="identity-password-confirmation" name="password_confirmation" type="password"></div>
                        <div class="field"><label class="field-label" for="identity-actor">Responsible profile</label><select class="field-select" id="identity-actor" name="actor_id"><option value="">No linked profile yet</option>@foreach ($owner_actor_options as $actor)<option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>@endforeach</select></div>
                    </div>
                    <div class="action-cluster" style="margin-top:14px;">
                        <label class="field-label" style="display:flex; gap:8px; align-items:center;"><input type="checkbox" name="password_enabled" value="1">Allow password sign-in</label>
                        <label class="field-label" style="display:flex; gap:8px; align-items:center;"><input type="checkbox" name="magic_link_enabled" value="1" checked>Allow email sign-in link</label>
                    </div>
                    <div class="action-cluster" style="margin-top:14px;"><button class="button button-primary" type="submit">Add person</button></div>
                </form>
            </div>
        @endif

        <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
            <div class="metric-card"><div class="metric-label">People</div><div class="metric-value">{{ count($rows) }}</div></div>
            <div class="metric-card"><div class="metric-label">Ready for workspace</div><div class="metric-value">{{ collect($rows)->filter(fn ($row) => $row['workspace_url'] !== null)->count() }}</div></div>
            <div class="metric-card"><div class="metric-label">Responsible profiles</div><div class="metric-value">{{ collect($rows)->sum(fn ($row) => count($row['linked_actors'])) }}</div></div>
            <div class="metric-card"><div class="metric-label">Inactive</div><div class="metric-value">{{ collect($rows)->where('user.is_active', false)->count() }}</div></div>
        </div>

        <div class="table-card">
            <table class="entity-table">
                <thead>
                    <tr>
                        <th>Person</th>
                        <th>Access profile</th>
                        <th>Organization access</th>
                        <th>Responsible profile</th>
                        <th>{{ $can_manage_users ? 'Actions' : 'Workspace' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td><div class="entity-title">{{ $row['user']['display_name'] }}</div><div class="table-note">{{ $row['user']['job_title'] !== '' ? $row['user']['job_title'] : 'No role defined yet' }}</div></td>
                            <td><div>{{ $row['user']['username'] }}</div><div>{{ $row['user']['email'] }}</div><div class="entity-id">{{ $row['user']['principal_id'] }}</div></td>
                            <td>@if ($row['memberships'] === [])<span class="muted-note">No workspace access yet</span>@else<div class="table-note">{{ count($row['memberships']) }} memberships</div>@endif</td>
                            <td>@if ($row['linked_actors'] === [])<span class="muted-note">No linked business owner</span>@else<div class="table-note">{{ count($row['linked_actors']) }} linked profiles</div>@endif</td>
                            <td><a class="button button-secondary" href="{{ $row['open_url'] }}">Edit details</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
