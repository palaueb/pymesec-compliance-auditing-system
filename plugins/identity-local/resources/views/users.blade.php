<section class="module-screen">
    @if ($can_manage_users)
        <div class="surface-card" id="identity-user-editor">
            <div class="row-between" style="margin-bottom:14px;">
                <div>
                    <div class="eyebrow">People</div>
                    <div class="screen-title" style="font-size:26px;">Add person</div>
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
                    <div class="field">
                        <label class="field-label" for="identity-username">Username</label>
                        <input class="field-input" id="identity-username" name="username" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="identity-display-name">Full name</label>
                        <input class="field-input" id="identity-display-name" name="display_name" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="identity-email">Work email</label>
                        <input class="field-input" id="identity-email" name="email" type="email" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="identity-job-title">Role or team</label>
                        <input class="field-input" id="identity-job-title" name="job_title">
                    </div>
                    <div class="field">
                        <label class="field-label" for="identity-password">Password</label>
                        <input class="field-input" id="identity-password" name="password" type="password">
                    </div>
                    <div class="field">
                        <label class="field-label" for="identity-password-confirmation">Confirm password</label>
                        <input class="field-input" id="identity-password-confirmation" name="password_confirmation" type="password">
                    </div>
                    <div class="field">
                        <label class="field-label" for="identity-actor">Responsible profile</label>
                        <select class="field-select" id="identity-actor" name="actor_id">
                            <option value="">No linked profile yet</option>
                            @foreach ($owner_actor_options as $actor)
                                <option value="{{ $actor['id'] }}">{{ $actor['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="action-cluster" style="margin-top:14px;">
                    <label class="field-label" style="display:flex; gap:8px; align-items:center;">
                        <input type="checkbox" name="password_enabled" value="1">
                        Allow password sign-in
                    </label>
                    <label class="field-label" style="display:flex; gap:8px; align-items:center;">
                        <input type="checkbox" name="magic_link_enabled" value="1" checked>
                        Allow email sign-in link
                    </label>
                </div>

                <div class="action-cluster" style="margin-top:14px;">
                    <button class="button button-primary" type="submit">Add person</button>
                </div>
            </form>
        </div>
    @endif

    <div class="overview-grid">
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
                        <td>
                            <div class="entity-title">{{ $row['user']['display_name'] }}</div>
                            <div class="table-note">{{ $row['user']['job_title'] !== '' ? $row['user']['job_title'] : 'No role defined yet' }}</div>
                        </td>
                        <td>
                            <div>{{ $row['user']['username'] }}</div>
                            <div>{{ $row['user']['email'] }}</div>
                            <div class="entity-id">{{ $row['user']['principal_id'] }}</div>
                            <div class="table-note">
                                @if ($row['user']['password_enabled'])
                                    Password + email code
                                @elseif ($row['user']['magic_link_enabled'])
                                    Email sign-in link
                                @else
                                    Access not enabled
                                @endif
                            </div>
                        </td>
                        <td>
                            @if ($row['memberships'] === [])
                                <span class="muted-note">No workspace access yet</span>
                            @else
                                <div class="data-stack">
                                    @foreach ($row['memberships'] as $membership)
                                        <div class="data-item">
                                            <div>{{ count($membership['roles']) }} role {{ count($membership['roles']) === 1 ? 'set' : 'sets' }}</div>
                                            <div class="table-note">{{ $membership['id'] }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td>
                            @if ($row['linked_actors'] === [])
                                <span class="muted-note">No linked business owner</span>
                            @else
                                <div class="data-stack">
                                    @foreach ($row['linked_actors'] as $actor)
                                        <div class="data-item">
                                            <div class="entity-title">{{ $actor['display_name'] }}</div>
                                            <div class="table-note">{{ $actor['kind'] }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td>
                            <div class="table-note" style="margin-bottom:8px;">
                                {{ $row['user']['auth_provider'] === 'local' ? 'Local person' : 'LDAP cached person' }}
                            </div>

                            @if ($row['workspace_url'] !== null)
                                <a class="button button-secondary" href="{{ $row['workspace_url'] }}">Open workspace</a>
                            @else
                                <span class="muted-note">Workspace not ready</span>
                            @endif

                            @if ($can_manage_users)
                                <details style="margin-top:10px;">
                                    <summary class="button button-ghost" style="display:inline-flex;">Edit</summary>
                                    <form class="upload-form" method="POST" action="{{ route('plugin.identity-local.users.update', ['userId' => $row['user']['id']]) }}" style="margin-top:10px;">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                        <input type="hidden" name="organization_id" value="{{ $organization_id }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="menu" value="plugin.identity-local.users">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <div class="field">
                                            <label class="field-label">Username</label>
                                            <input class="field-input" name="username" value="{{ $row['user']['username'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Full name</label>
                                            <input class="field-input" name="display_name" value="{{ $row['user']['display_name'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Work email</label>
                                            <input class="field-input" name="email" type="email" value="{{ $row['user']['email'] }}" required>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Role or team</label>
                                            <input class="field-input" name="job_title" value="{{ $row['user']['job_title'] }}">
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
                                        <label class="field-label" style="display:flex; gap:8px; align-items:center;">
                                            <input type="checkbox" name="password_enabled" value="1" @checked($row['user']['password_enabled'])>
                                            Allow password sign-in
                                        </label>
                                        <label class="field-label" style="display:flex; gap:8px; align-items:center;">
                                            <input type="checkbox" name="magic_link_enabled" value="1" @checked($row['user']['magic_link_enabled'])>
                                            Allow email sign-in link
                                        </label>
                                        <label class="field-label" style="display:flex; gap:8px; align-items:center;">
                                            <input type="checkbox" name="is_active" value="1" @checked($row['user']['is_active'])>
                                            Active workspace person
                                        </label>
                                        <div class="action-cluster">
                                            <button class="button button-secondary" type="submit">Save person</button>
                                        </div>
                                    </form>
                                </details>

                                @if ($row['user']['auth_provider'] === 'local' && $row['user']['principal_id'] !== ($query['principal_id'] ?? null))
                                    <form class="upload-form" method="POST" action="{{ route('plugin.identity-local.users.delete', ['userId' => $row['user']['id']]) }}" style="margin-top:10px;">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                                        <input type="hidden" name="organization_id" value="{{ $organization_id }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                                        <input type="hidden" name="menu" value="plugin.identity-local.users">
                                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                                        <button class="button button-ghost" type="submit" onclick="return confirm('Remove this local person and all of their organization access?')">Delete</button>
                                    </form>
                                @elseif ($row['user']['auth_provider'] !== 'local')
                                    <div class="muted-note" style="margin-top:10px;">LDAP people are refreshed from directory sync.</div>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
