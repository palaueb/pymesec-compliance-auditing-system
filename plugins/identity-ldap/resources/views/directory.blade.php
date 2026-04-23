@php
    $connection = is_array($connection ?? null) ? $connection : null;
@endphp

<div class="module-screen compact">
    <div class="surface-note">{{ __('identity-ldap.summary') }}</div>

    <div class="surface-note">{{ __('identity-ldap.governance_note') }}</div>

    @if (session('status'))
        <div class="surface-note">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="surface-note" style="border-left-color: var(--warning);">{{ session('error') }}</div>
    @endif

    <div class="overview-grid" style="grid-template-columns:repeat(3, minmax(0, 1fr));">
        <div class="metric-card">
            <div class="metric-label">{{ __('identity-ldap.metric.connector') }}</div>
            <div class="metric-value">{{ $connection['name'] ?? __('identity-ldap.not_configured') }}</div>
            <div class="meta-copy">{{ $connection['host'] ?? __('identity-ldap.no_directory_linked') }}</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">{{ __('identity-ldap.metric.sync_status') }}</div>
            <div class="metric-value">{{ $connection['last_sync_status'] ?? __('identity-ldap.sync.idle') }}</div>
            <div class="meta-copy">{{ $connection['last_sync_message'] ?? __('identity-ldap.no_sync_yet') }}</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">{{ __('identity-ldap.metric.cached_people') }}</div>
            <div class="metric-value">{{ count($cached_users) }}</div>
            <div class="meta-copy">{{ __('identity-ldap.cached_people_copy') }}</div>
        </div>
    </div>

    @if ($can_manage_directory)
        <div class="table-card">
            <div class="screen-header">
                <div>
                    <h2 class="screen-title" style="font-size:24px;">{{ __('identity-ldap.connector.title') }}</h2>
                    <p class="screen-subtitle">{{ __('identity-ldap.connector.subtitle') }}</p>
                </div>
                @if ($connection !== null)
                    <form id="ldap-sync-form" method="POST" action="{{ $sync_route }}">
                        @csrf
                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                        <input type="hidden" name="organization_id" value="{{ $organization_id }}">
                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                        @foreach (($query['membership_ids'] ?? []) as $membershipId)
                            <input type="hidden" name="membership_ids[]" value="{{ $membershipId }}">
                        @endforeach
                        <button class="button button-primary" type="submit">{{ __('identity-ldap.connector.sync_now') }}</button>
                    </form>
                @endif
            </div>

            @if ($connection !== null)
                <div class="overview-grid" style="grid-template-columns:repeat(3, minmax(0, 1fr));">
                    <div class="surface-card" style="padding:16px;">
                        <div class="field-label">{{ __('identity-ldap.connector.connection') }}</div>
                        <div class="body-copy" style="margin-top:8px;">{{ $connection['host'] }}:{{ $connection['port'] }}</div>
                        <div class="table-note" style="margin-top:8px;">{{ __('identity-ldap.connector.base_dn', ['value' => $connection['base_dn']]) }}</div>
                    </div>
                    <div class="surface-card" style="padding:16px;">
                        <div class="field-label">{{ __('identity-ldap.connector.sign_in_mode') }}</div>
                        <div class="body-copy" style="margin-top:8px;">{{ ($connection['login_mode'] ?? 'username') === 'email' ? __('identity-ldap.connector.mode.email') : __('identity-ldap.connector.mode.username') }}</div>
                        <div class="table-note" style="margin-top:8px;">{{ __('identity-ldap.connector.fallback_email', ['state' => ($connection['fallback_email_enabled'] ?? false) ? __('core.status.enabled') : __('core.status.disabled')]) }}</div>
                    </div>
                    <div class="surface-card" style="padding:16px;">
                        <div class="field-label">{{ __('identity-ldap.connector.sync_cadence') }}</div>
                        <div class="body-copy" style="margin-top:8px;">{{ __('identity-ldap.connector.every_minutes', ['value' => $connection['sync_interval_minutes'] ?? 60]) }}</div>
                        <div class="table-note" style="margin-top:8px;">{{ __('identity-ldap.connector.active_state', ['state' => ($connection['is_enabled'] ?? false) ? __('core.status.active') : __('core.status.disabled')]) }}</div>
                    </div>
                </div>
            @endif

            <div id="ldap-connection-editor" class="editor-panel" @if ($connection !== null) hidden @endif>
                <div class="row-between">
                    <div>
                        <div class="entity-title">{{ $connection === null ? __('identity-ldap.connector.setup_title') : __('identity-ldap.connector.edit_title') }}</div>
                        <div class="table-note">{{ __('identity-ldap.connector.editor_copy') }}</div>
                    </div>
                    <button class="button button-ghost" type="button" data-editor-toggle="ldap-connection-editor">{{ __('Close') }}</button>
                </div>

                <form class="upload-form" method="POST" action="{{ $save_connection_route }}">
                    @csrf
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                    <input type="hidden" name="organization_id" value="{{ $organization_id }}">
                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                    @foreach (($query['membership_ids'] ?? []) as $membershipId)
                        <input type="hidden" name="membership_ids[]" value="{{ $membershipId }}">
                    @endforeach

                    <div class="overview-grid" style="grid-template-columns:repeat(3, minmax(0, 1fr));">
                        <div class="field">
                            <label class="field-label" for="ldap-name">{{ __('identity-ldap.form.directory_label') }}</label>
                            <input class="field-input" id="ldap-name" name="name" value="{{ $connection['name'] ?? '' }}" required>
                        </div>
                        <div class="field">
                            <label class="field-label" for="ldap-host">{{ __('identity-ldap.form.host') }}</label>
                            <input class="field-input" id="ldap-host" name="host" value="{{ $connection['host'] ?? '' }}" placeholder="ldap.company.test" required>
                        </div>
                        <div class="field">
                            <label class="field-label" for="ldap-port">{{ __('identity-ldap.form.port') }}</label>
                            <input class="field-input" id="ldap-port" name="port" type="number" min="1" max="65535" value="{{ $connection['port'] ?? 389 }}" required>
                        </div>
                        <div class="field">
                            <label class="field-label" for="ldap-base-dn">{{ __('identity-ldap.form.base_dn') }}</label>
                            <input class="field-input" id="ldap-base-dn" name="base_dn" value="{{ $connection['base_dn'] ?? '' }}" required>
                        </div>
                        <div class="field">
                            <label class="field-label" for="ldap-bind-dn">{{ __('identity-ldap.form.bind_dn') }}</label>
                            <input class="field-input" id="ldap-bind-dn" name="bind_dn" value="{{ $connection['bind_dn'] ?? '' }}">
                        </div>
                        <div class="field">
                            <label class="field-label" for="ldap-bind-password">{{ __('identity-ldap.form.bind_password') }}</label>
                            <input class="field-input" id="ldap-bind-password" name="bind_password" type="password" placeholder="{{ $connection !== null ? __('identity-ldap.form.keep_current') : '' }}">
                        </div>
                        <div class="field">
                            <label class="field-label" for="ldap-user-attribute">{{ __('identity-ldap.form.user_attribute') }}</label>
                            <input class="field-input" id="ldap-user-attribute" name="user_dn_attribute" value="{{ $connection['user_dn_attribute'] ?? 'uid' }}">
                        </div>
                        <div class="field">
                            <label class="field-label" for="ldap-mail-attribute">{{ __('identity-ldap.form.mail_attribute') }}</label>
                            <input class="field-input" id="ldap-mail-attribute" name="mail_attribute" value="{{ $connection['mail_attribute'] ?? 'mail' }}">
                        </div>
                        <div class="field">
                            <label class="field-label" for="ldap-display-attribute">{{ __('identity-ldap.form.display_name_attribute') }}</label>
                            <input class="field-input" id="ldap-display-attribute" name="display_name_attribute" value="{{ $connection['display_name_attribute'] ?? 'cn' }}">
                        </div>
                        <div class="field">
                            <label class="field-label" for="ldap-job-attribute">{{ __('identity-ldap.form.job_title_attribute') }}</label>
                            <input class="field-input" id="ldap-job-attribute" name="job_title_attribute" value="{{ $connection['job_title_attribute'] ?? 'title' }}">
                        </div>
                        <div class="field">
                            <label class="field-label" for="ldap-group-attribute">{{ __('identity-ldap.form.group_attribute') }}</label>
                            <input class="field-input" id="ldap-group-attribute" name="group_attribute" value="{{ $connection['group_attribute'] ?? 'memberOf' }}">
                        </div>
                        <div class="field">
                            <label class="field-label" for="ldap-login-mode">{{ __('identity-ldap.form.login_mode') }}</label>
                            <select class="field-select" id="ldap-login-mode" name="login_mode">
                                <option value="username" @selected(($connection['login_mode'] ?? 'username') === 'username')>{{ __('identity-ldap.connector.mode.username') }}</option>
                                <option value="email" @selected(($connection['login_mode'] ?? '') === 'email')>{{ __('identity-ldap.connector.mode.email') }}</option>
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="ldap-sync-interval">{{ __('identity-ldap.form.sync_interval') }}</label>
                            <input class="field-input" id="ldap-sync-interval" name="sync_interval_minutes" type="number" min="5" max="10080" value="{{ $connection['sync_interval_minutes'] ?? 60 }}">
                        </div>
                        <div class="field">
                            <label class="field-label" for="ldap-user-filter">{{ __('identity-ldap.form.user_filter') }}</label>
                            <input class="field-input" id="ldap-user-filter" name="user_filter" value="{{ $connection['user_filter'] ?? '' }}" placeholder="(objectClass=person)">
                        </div>
                        <div class="field" style="align-content:start; gap:10px;">
                            <label class="field-label" style="display:flex; gap:10px; align-items:center; min-height:40px;">
                                <input type="checkbox" name="fallback_email_enabled" value="1" @checked($connection === null || ($connection['fallback_email_enabled'] ?? false))>
                                {{ __('identity-ldap.form.enable_fallback') }}
                            </label>
                            <label class="field-label" style="display:flex; gap:10px; align-items:center; min-height:40px;">
                                <input type="checkbox" name="is_enabled" value="1" @checked($connection === null || ($connection['is_enabled'] ?? false))>
                                {{ __('identity-ldap.form.connector_active') }}
                            </label>
                        </div>
                    </div>

                    <div class="action-cluster">
                        <button class="button button-primary" type="submit">{{ __('identity-ldap.form.save_connector') }}</button>
                    </div>
                </form>
            </div>
        </div>

        @if ($connection !== null)
            <div class="table-card">
                <div class="screen-header">
                    <div>
                        <h2 class="screen-title" style="font-size:24px;">{{ __('identity-ldap.mappings.title') }}</h2>
                        <p class="screen-subtitle">{{ __('identity-ldap.mappings.subtitle') }}</p>
                    </div>
                </div>

                <div id="ldap-mapping-editor" class="editor-panel" hidden>
                    <div class="row-between">
                        <div>
                            <div class="entity-title">{{ __('identity-ldap.mappings.add_title') }}</div>
                            <div class="table-note">{{ __('identity-ldap.mappings.add_copy') }}</div>
                        </div>
                        <button class="button button-ghost" type="button" data-editor-toggle="ldap-mapping-editor">{{ __('Close') }}</button>
                    </div>

                    <form class="upload-form" method="POST" action="{{ $save_mapping_route }}">
                        @csrf
                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                        <input type="hidden" name="organization_id" value="{{ $organization_id }}">
                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                        @foreach (($query['membership_ids'] ?? []) as $membershipId)
                            <input type="hidden" name="membership_ids[]" value="{{ $membershipId }}">
                        @endforeach

                        <div class="field">
                            <label class="field-label" for="ldap-group">{{ __('identity-ldap.mappings.group_dn') }}</label>
                            <input class="field-input" id="ldap-group" name="ldap_group" placeholder="cn=it-services,ou=Groups,dc=company,dc=test" required>
                        </div>

                        <div class="overview-grid" style="grid-template-columns:repeat(3, minmax(0, 1fr));">
                            <div class="field">
                                <label class="field-label" for="ldap-role-keys">{{ __('identity-ldap.mappings.roles') }}</label>
                                <select class="field-select" id="ldap-role-keys" name="role_keys[]" multiple size="8">
                                    @foreach ($role_options as $role)
                                        <option value="{{ $role['key'] }}">{{ $role['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label class="field-label" for="ldap-scope-ids">{{ __('identity-ldap.mappings.scopes') }}</label>
                                <select class="field-select" id="ldap-scope-ids" name="scope_ids[]" multiple size="8">
                                    @foreach ($scope_options as $scope)
                                        <option value="{{ $scope['id'] }}">{{ $scope['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field" style="align-content:start;">
                                <label class="field-label" style="display:flex; gap:10px; align-items:center; min-height:40px;">
                                    <input type="checkbox" name="is_active" value="1" checked>
                                    {{ __('identity-ldap.mappings.active') }}
                                </label>
                            </div>
                        </div>

                        <div class="action-cluster">
                            <button class="button button-secondary" type="submit">{{ __('identity-ldap.mappings.save_button') }}</button>
                        </div>
                    </form>
                </div>

                <table class="entity-table">
                    <thead>
                        <tr>
                            <th>{{ __('identity-ldap.mappings.table.group') }}</th>
                            <th>{{ __('identity-ldap.mappings.table.roles') }}</th>
                            <th>{{ __('identity-ldap.mappings.table.scopes') }}</th>
                            <th>{{ __('identity-ldap.mappings.table.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($mappings as $mapping)
                            <tr>
                                <td><div class="entity-title">{{ $mapping['ldap_group'] }}</div></td>
                                <td>{{ $mapping['role_keys'] !== [] ? implode(', ', $mapping['role_keys']) : __('identity-ldap.mappings.unassigned') }}</td>
                                <td>{{ $mapping['scope_ids'] !== [] ? implode(', ', $mapping['scope_ids']) : __('identity-ldap.mappings.all_scopes') }}</td>
                                <td>{{ $mapping['is_active'] ? __('core.status.active') : __('core.status.disabled') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="muted-note">{{ __('identity-ldap.mappings.empty') }}</td>
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
                <h2 class="screen-title" style="font-size:24px;">{{ __('identity-ldap.cached.title') }}</h2>
                <p class="screen-subtitle">{{ __('identity-ldap.cached.subtitle') }}</p>
            </div>
        </div>
        <table class="entity-table">
            <thead>
                <tr>
                    <th>{{ __('identity-ldap.cached.table.person') }}</th>
                    <th>{{ __('identity-ldap.cached.table.username') }}</th>
                    <th>{{ __('identity-ldap.cached.table.groups') }}</th>
                    <th>{{ __('identity-ldap.cached.table.synced') }}</th>
                    <th>{{ __('identity-ldap.cached.table.status') }}</th>
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
                        <td>{{ $user['directory_groups'] !== [] ? implode(', ', $user['directory_groups']) : __('identity-ldap.cached.unassigned') }}</td>
                        <td>{{ $user['directory_synced_at'] ?? __('n/a') }}</td>
                        <td>{{ $user['is_active'] ? __('identity-ldap.cached.active') : __('identity-ldap.cached.disabled') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted-note">{{ __('identity-ldap.cached.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
