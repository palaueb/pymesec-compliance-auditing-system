@php
    $connection = is_array($connection ?? null) ? $connection : null;
@endphp

<div class="module-screen compact">
    <div class="surface-note">
        The external directory remains the source of identity data, while the local copy keeps sessions, fallback access and assigned work context available.
    </div>

    @if (session('status'))
        <div class="surface-note">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="surface-note" style="border-left-color: var(--warning);">{{ session('error') }}</div>
    @endif

    <div class="overview-grid" style="grid-template-columns:repeat(3, minmax(0, 1fr));">
        <div class="metric-card">
            <div class="metric-label">Connector</div>
            <div class="metric-value">{{ $connection['name'] ?? 'Not configured' }}</div>
            <div class="meta-copy">{{ $connection['host'] ?? 'No external directory linked yet.' }}</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Sync status</div>
            <div class="metric-value">{{ $connection['last_sync_status'] ?? 'idle' }}</div>
            <div class="meta-copy">{{ $connection['last_sync_message'] ?? 'No sync has run yet.' }}</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Cached people</div>
            <div class="metric-value">{{ count($cached_users) }}</div>
            <div class="meta-copy">Directory users kept locally for fallback access.</div>
        </div>
    </div>

    @if ($can_manage_directory)
        <div class="table-card">
            <div class="screen-header">
                <div>
                    <h2 class="screen-title" style="font-size:24px;">Directory connector</h2>
                    <p class="screen-subtitle">One external directory per organization. The local copy stays responsible for access context and fallback sign-in.</p>
                </div>
                @if ($connection !== null)
                    <form id="ldap-sync-form" method="POST" action="{{ $sync_route }}">
                        @csrf
                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                        <input type="hidden" name="organization_id" value="{{ $organization_id }}">
                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                        @foreach (($query['membership_ids'] ?? []) as $membershipId)
                            <input type="hidden" name="membership_ids[]" value="{{ $membershipId }}">
                        @endforeach
                        <button class="button button-primary" type="submit">Sync now</button>
                    </form>
                @endif
            </div>

            @if ($connection !== null)
                <div class="overview-grid" style="grid-template-columns:repeat(3, minmax(0, 1fr));">
                    <div class="surface-card" style="padding:16px;">
                        <div class="field-label">Connection</div>
                        <div class="body-copy" style="margin-top:8px;">{{ $connection['host'] }}:{{ $connection['port'] }}</div>
                        <div class="table-note" style="margin-top:8px;">Base DN: {{ $connection['base_dn'] }}</div>
                    </div>
                    <div class="surface-card" style="padding:16px;">
                        <div class="field-label">Sign-in mode</div>
                        <div class="body-copy" style="margin-top:8px;">{{ ($connection['login_mode'] ?? 'username') === 'email' ? 'Email' : 'Username' }}</div>
                        <div class="table-note" style="margin-top:8px;">Fallback email access {{ ($connection['fallback_email_enabled'] ?? false) ? 'enabled' : 'disabled' }}.</div>
                    </div>
                    <div class="surface-card" style="padding:16px;">
                        <div class="field-label">Sync cadence</div>
                        <div class="body-copy" style="margin-top:8px;">Every {{ $connection['sync_interval_minutes'] ?? 60 }} minutes</div>
                        <div class="table-note" style="margin-top:8px;">Connector {{ ($connection['is_enabled'] ?? false) ? 'active' : 'disabled' }}.</div>
                    </div>
                </div>
            @endif

            <div id="ldap-connection-editor" class="editor-panel" @if ($connection !== null) hidden @endif>
                <div class="row-between">
                    <div>
                        <div class="entity-title">{{ $connection === null ? 'Set up directory connector' : 'Edit directory connector' }}</div>
                        <div class="table-note">Adjust the external directory connection without cluttering the working view.</div>
                    </div>
                    <button class="button button-ghost" type="button" data-editor-toggle="ldap-connection-editor">Close</button>
                </div>

                <form class="upload-form" method="POST" action="{{ $save_connection_route }}">
                    @csrf
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                    <input type="hidden" name="organization_id" value="{{ $organization_id }}">
                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                    @foreach (($query['membership_ids'] ?? []) as $membershipId)
                        <input type="hidden" name="membership_ids[]" value="{{ $membershipId }}">
                    @endforeach

                    <div class="overview-grid" style="grid-template-columns:repeat(3, minmax(0, 1fr));">
                        <div class="field">
                            <label class="field-label" for="ldap-name">Directory label</label>
                            <input class="field-input" id="ldap-name" name="name" value="{{ $connection['name'] ?? '' }}" required>
                        </div>
                        <div class="field">
                            <label class="field-label" for="ldap-host">Host</label>
                            <input class="field-input" id="ldap-host" name="host" value="{{ $connection['host'] ?? '' }}" placeholder="ldap.company.test" required>
                        </div>
                        <div class="field">
                            <label class="field-label" for="ldap-port">Port</label>
                            <input class="field-input" id="ldap-port" name="port" type="number" min="1" max="65535" value="{{ $connection['port'] ?? 389 }}" required>
                        </div>
                        <div class="field">
                            <label class="field-label" for="ldap-base-dn">Base DN</label>
                            <input class="field-input" id="ldap-base-dn" name="base_dn" value="{{ $connection['base_dn'] ?? '' }}" required>
                        </div>
                        <div class="field">
                            <label class="field-label" for="ldap-bind-dn">Bind DN</label>
                            <input class="field-input" id="ldap-bind-dn" name="bind_dn" value="{{ $connection['bind_dn'] ?? '' }}">
                        </div>
                        <div class="field">
                            <label class="field-label" for="ldap-bind-password">Bind password</label>
                            <input class="field-input" id="ldap-bind-password" name="bind_password" type="password" placeholder="{{ $connection !== null ? 'Leave blank to keep current value' : '' }}">
                        </div>
                        <div class="field">
                            <label class="field-label" for="ldap-user-attribute">Username attribute</label>
                            <input class="field-input" id="ldap-user-attribute" name="user_dn_attribute" value="{{ $connection['user_dn_attribute'] ?? 'uid' }}">
                        </div>
                        <div class="field">
                            <label class="field-label" for="ldap-mail-attribute">Email attribute</label>
                            <input class="field-input" id="ldap-mail-attribute" name="mail_attribute" value="{{ $connection['mail_attribute'] ?? 'mail' }}">
                        </div>
                        <div class="field">
                            <label class="field-label" for="ldap-display-attribute">Display name attribute</label>
                            <input class="field-input" id="ldap-display-attribute" name="display_name_attribute" value="{{ $connection['display_name_attribute'] ?? 'cn' }}">
                        </div>
                        <div class="field">
                            <label class="field-label" for="ldap-job-attribute">Job title attribute</label>
                            <input class="field-input" id="ldap-job-attribute" name="job_title_attribute" value="{{ $connection['job_title_attribute'] ?? 'title' }}">
                        </div>
                        <div class="field">
                            <label class="field-label" for="ldap-group-attribute">Group attribute</label>
                            <input class="field-input" id="ldap-group-attribute" name="group_attribute" value="{{ $connection['group_attribute'] ?? 'memberOf' }}">
                        </div>
                        <div class="field">
                            <label class="field-label" for="ldap-login-mode">Directory login mode</label>
                            <select class="field-select" id="ldap-login-mode" name="login_mode">
                                <option value="username" @selected(($connection['login_mode'] ?? 'username') === 'username')>Username</option>
                                <option value="email" @selected(($connection['login_mode'] ?? '') === 'email')>Email</option>
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="ldap-sync-interval">Sync interval (minutes)</label>
                            <input class="field-input" id="ldap-sync-interval" name="sync_interval_minutes" type="number" min="5" max="10080" value="{{ $connection['sync_interval_minutes'] ?? 60 }}">
                        </div>
                        <div class="field">
                            <label class="field-label" for="ldap-user-filter">User filter</label>
                            <input class="field-input" id="ldap-user-filter" name="user_filter" value="{{ $connection['user_filter'] ?? '' }}" placeholder="(objectClass=person)">
                        </div>
                        <div class="field" style="align-content:start; gap:10px;">
                            <label class="field-label" style="display:flex; gap:10px; align-items:center; min-height:40px;">
                                <input type="checkbox" name="fallback_email_enabled" value="1" @checked($connection === null || ($connection['fallback_email_enabled'] ?? false))>
                                Enable cached email fallback
                            </label>
                            <label class="field-label" style="display:flex; gap:10px; align-items:center; min-height:40px;">
                                <input type="checkbox" name="is_enabled" value="1" @checked($connection === null || ($connection['is_enabled'] ?? false))>
                                Connector active
                            </label>
                        </div>
                    </div>

                    <div class="action-cluster">
                        <button class="button button-primary" type="submit">Save connector</button>
                    </div>
                </form>
            </div>
        </div>

        @if ($connection !== null)
            <div class="table-card">
                <div class="screen-header">
                    <div>
                        <h2 class="screen-title" style="font-size:24px;">Group mappings</h2>
                        <p class="screen-subtitle">Groups decide inherited roles and scopes. Unmatched people remain cached until you assign access.</p>
                    </div>
                </div>

                <div id="ldap-mapping-editor" class="editor-panel" hidden>
                    <div class="row-between">
                        <div>
                            <div class="entity-title">Add group mapping</div>
                            <div class="table-note">Map one external group to internal access rules and scope limits.</div>
                        </div>
                        <button class="button button-ghost" type="button" data-editor-toggle="ldap-mapping-editor">Close</button>
                    </div>

                    <form class="upload-form" method="POST" action="{{ $save_mapping_route }}">
                        @csrf
                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] }}">
                        <input type="hidden" name="organization_id" value="{{ $organization_id }}">
                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                        @foreach (($query['membership_ids'] ?? []) as $membershipId)
                            <input type="hidden" name="membership_ids[]" value="{{ $membershipId }}">
                        @endforeach

                        <div class="field">
                            <label class="field-label" for="ldap-group">LDAP group DN</label>
                            <input class="field-input" id="ldap-group" name="ldap_group" placeholder="cn=it-services,ou=Groups,dc=company,dc=test" required>
                        </div>

                        <div class="overview-grid" style="grid-template-columns:repeat(3, minmax(0, 1fr));">
                            <div class="field">
                                <label class="field-label" for="ldap-role-keys">Roles</label>
                                <select class="field-select" id="ldap-role-keys" name="role_keys[]" multiple size="8">
                                    @foreach ($role_options as $role)
                                        <option value="{{ $role['key'] }}">{{ $role['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label class="field-label" for="ldap-scope-ids">Scopes</label>
                                <select class="field-select" id="ldap-scope-ids" name="scope_ids[]" multiple size="8">
                                    @foreach ($scope_options as $scope)
                                        <option value="{{ $scope['id'] }}">{{ $scope['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field" style="align-content:start;">
                                <label class="field-label" style="display:flex; gap:10px; align-items:center; min-height:40px;">
                                    <input type="checkbox" name="is_active" value="1" checked>
                                    Mapping active
                                </label>
                            </div>
                        </div>

                        <div class="action-cluster">
                            <button class="button button-secondary" type="submit">Save mapping</button>
                        </div>
                    </form>
                </div>

                <table class="entity-table">
                    <thead>
                        <tr>
                            <th>LDAP group</th>
                            <th>Roles</th>
                            <th>Scopes</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($mappings as $mapping)
                            <tr>
                                <td><div class="entity-title">{{ $mapping['ldap_group'] }}</div></td>
                                <td>{{ $mapping['role_keys'] !== [] ? implode(', ', $mapping['role_keys']) : 'Unassigned' }}</td>
                                <td>{{ $mapping['scope_ids'] !== [] ? implode(', ', $mapping['scope_ids']) : 'All scopes' }}</td>
                                <td>{{ $mapping['is_active'] ? 'Active' : 'Disabled' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="muted-note">No group mappings yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    @endif

    <div class="table-card">
        <div class="screen-header">
            <div>
                <h2 class="screen-title" style="font-size:24px;">Cached directory people</h2>
                <p class="screen-subtitle">The local copy powers fallback email access and scoped sessions.</p>
            </div>
        </div>
        <table class="entity-table">
            <thead>
                <tr>
                    <th>Person</th>
                    <th>Username</th>
                    <th>Groups</th>
                    <th>Synced</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cached_users as $user)
                    <tr>
                        <td>
                            <div class="entity-title">{{ $user['display_name'] }}</div>
                            <div class="entity-id">{{ $user['email'] }}</div>
                        </td>
                        <td>{{ $user['username'] }}</td>
                        <td>{{ $user['directory_groups'] !== [] ? implode(', ', $user['directory_groups']) : 'Unassigned' }}</td>
                        <td>{{ $user['directory_synced_at'] ?? 'n/a' }}</td>
                        <td>{{ $user['is_active'] ? 'Active' : 'Disabled' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted-note">No directory-backed people cached for this organization yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
