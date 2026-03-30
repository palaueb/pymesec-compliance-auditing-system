<section class="module-screen">
    <div class="surface-note">
        Governance page. `People` defines identity records and sign-in modes. Organization access and scope grants stay in `Organization Access` so the two concerns remain visually separate.
    </div>

    @if ($errors->any())
        <div class="surface-card">
            <div class="surface-note error">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        </div>
    @endif

    @if (is_array($selected_row))
        @php $selectedUser = $selected_row['user']; @endphp
        <div class="surface-card" style="padding:16px; display:grid; gap:16px;">
            <div class="surface-note">
                Person Detail keeps profile maintenance in one governance workspace. Use the people list to browse identity records and open the person you want to edit.
            </div>

            <div class="row-between" style="align-items:flex-start;">
                <div>
                    <div class="eyebrow">Person Detail</div>
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
                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
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
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
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

            <div class="surface-card" id="identity-user-import" hidden>
                <div class="row-between" style="margin-bottom:14px;">
                    <div>
                        <div class="eyebrow">People</div>
                        <div class="entity-title" style="font-size:24px;">Import people from CSV / TSV</div>
                    </div>
                </div>

                <div class="surface-note" style="margin-bottom:14px;">
                    Required columns: full name and work email. Username is optional and will be generated safely when missing. Team or department is optional and maps to the local role field. The import stays blocked until every row is valid.
                </div>

                <form class="upload-form" method="POST" action="{{ $import_upload_route }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                    <input type="hidden" name="organization_id" value="{{ $organization_id }}">
                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                    <input type="hidden" name="menu" value="plugin.identity-local.users">
                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">

                    <div class="field">
                        <label class="field-label" for="identity-import-file">CSV or TSV file</label>
                        <input class="field-input" id="identity-import-file" name="import_file" type="file" accept=".csv,.tsv,.txt,text/csv,text/tab-separated-values" required>
                    </div>

                    <div class="action-cluster" style="margin-top:14px;">
                        <button class="button button-primary" type="submit">Upload file</button>
                    </div>
                </form>
            </div>
        @endif

        <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
            <div class="metric-card"><div class="metric-label">People</div><div class="metric-value">{{ count($rows) }}</div></div>
            <div class="metric-card"><div class="metric-label">Ready for workspace</div><div class="metric-value">{{ collect($rows)->filter(fn ($row) => $row['workspace_ready'] ?? false)->count() }}</div></div>
            <div class="metric-card"><div class="metric-label">Responsible profiles</div><div class="metric-value">{{ collect($rows)->sum(fn ($row) => count($row['linked_actors'])) }}</div></div>
            <div class="metric-card"><div class="metric-label">Inactive</div><div class="metric-value">{{ collect($rows)->where('user.is_active', false)->count() }}</div></div>
        </div>

        <div class="surface-card">
            <div class="entity-title">People list</div>
            <div class="table-note" style="margin-top:6px;">This list stays focused on identity readiness, directory source, and Open. Use Person Detail to manage sign-in modes, profile data, and local-account maintenance.</div>
        </div>

        @if ($can_manage_users && is_array($import_upload))
            @php $currentMapping = is_array($import_review['mapping'] ?? null) ? $import_review['mapping'] : ($import_upload['default_mapping'] ?? []); @endphp
            <div class="surface-card">
                <div class="row-between" style="margin-bottom:12px; gap:12px;">
                    <div>
                        <div class="eyebrow">Import</div>
                        <div class="entity-title" style="font-size:20px;">Map uploaded columns</div>
                    </div>
                    <div class="action-cluster">
                        <div class="table-note">{{ $import_upload['file_name'] }} · {{ $import_upload['delimiter_label'] }} · {{ $import_upload['row_count'] }} rows</div>
                        <form method="POST" action="{{ $import_reset_route }}">
                            @csrf
                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                            <input type="hidden" name="organization_id" value="{{ $organization_id }}">
                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                            <input type="hidden" name="menu" value="plugin.identity-local.users">
                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                            <button class="button button-ghost" type="submit">Reset import</button>
                        </form>
                    </div>
                </div>

                <form class="upload-form" method="POST" action="{{ $import_review_route }}">
                    @csrf
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                    <input type="hidden" name="organization_id" value="{{ $organization_id }}">
                    <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                    <input type="hidden" name="menu" value="plugin.identity-local.users">
                    <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">

                    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                        <div class="field">
                            <label class="field-label">Full name</label>
                            <select class="field-select" name="mapping[display_name]" required>
                                <option value="">Select a column</option>
                                @foreach ($import_upload['headers'] as $header)
                                    <option value="{{ $header }}" @selected(($currentMapping['display_name'] ?? '') === $header)>{{ $header }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">Work email</label>
                            <select class="field-select" name="mapping[email]" required>
                                <option value="">Select a column</option>
                                @foreach ($import_upload['headers'] as $header)
                                    <option value="{{ $header }}" @selected(($currentMapping['email'] ?? '') === $header)>{{ $header }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">Username</label>
                            <select class="field-select" name="mapping[username]">
                                <option value="">Generate automatically</option>
                                @foreach ($import_upload['headers'] as $header)
                                    <option value="{{ $header }}" @selected(($currentMapping['username'] ?? '') === $header)>{{ $header }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">Team / department</label>
                            <select class="field-select" name="mapping[job_title]">
                                <option value="">Do not import</option>
                                @foreach ($import_upload['headers'] as $header)
                                    <option value="{{ $header }}" @selected(($currentMapping['job_title'] ?? '') === $header)>{{ $header }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <details style="margin-top:12px;">
                        <summary class="button button-ghost" style="display:inline-flex;">Preview raw rows</summary>
                        <div class="table-card" style="margin-top:12px;">
                            <table class="entity-table">
                                <thead>
                                    <tr>
                                        @foreach ($import_upload['headers'] as $header)
                                            <th>{{ $header }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($import_upload['sample_rows'] as $sampleRow)
                                        <tr>
                                            @foreach ($import_upload['headers'] as $header)
                                                <td>{{ $sampleRow[$header] ?? '' }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>

                    <div class="action-cluster" style="margin-top:14px;">
                        <button class="button button-secondary" type="submit">Validate import</button>
                    </div>
                </form>
            </div>
        @endif

        @if ($can_manage_users && is_array($import_review))
            <div class="surface-card">
                <div class="row-between" style="margin-bottom:12px; gap:12px;">
                    <div>
                        <div class="eyebrow">Import review</div>
                        <div class="entity-title" style="font-size:20px;">Validation results</div>
                    </div>
                    <div class="action-cluster">
                        <div class="table-note">
                            {{ $import_review['summary']['valid_count'] ?? 0 }} valid · {{ $import_review['summary']['invalid_count'] ?? 0 }} invalid
                        </div>
                        <form method="POST" action="{{ $import_reset_route }}">
                            @csrf
                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                            <input type="hidden" name="organization_id" value="{{ $organization_id }}">
                            <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                            <input type="hidden" name="menu" value="plugin.identity-local.users">
                            <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                            <button class="button button-ghost" type="submit">Reset import</button>
                        </form>
                    </div>
                </div>

                <div class="surface-note" style="margin-bottom:12px;">
                    Usernames are normalized to lowercase. Generated usernames are shown below before you confirm the import.
                </div>

                <div class="table-card">
                    <table class="entity-table">
                        <thead>
                            <tr>
                                <th>Row</th>
                                <th>Full name</th>
                                <th>Email</th>
                                <th>Username</th>
                                <th>Team / department</th>
                                <th>Validation</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($import_review['rows'] as $row)
                                <tr>
                                    <td>{{ $row['row_number'] }}</td>
                                    <td>{{ $row['normalized']['display_name'] }}</td>
                                    <td>{{ $row['normalized']['email'] }}</td>
                                    <td>{{ $row['normalized']['username'] }}</td>
                                    <td>{{ $row['normalized']['job_title'] !== '' ? $row['normalized']['job_title'] : 'Not imported' }}</td>
                                    <td>
                                        @if ($row['errors'] === [])
                                            <span class="pill">ready</span>
                                        @else
                                            <div class="data-stack">
                                                @foreach ($row['errors'] as $error)
                                                    <div class="data-item">{{ $error }}</div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if (($import_review['summary']['invalid_count'] ?? 0) === 0)
                    <form method="POST" action="{{ $import_commit_route }}" style="margin-top:14px;">
                        @csrf
                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                        <input type="hidden" name="organization_id" value="{{ $organization_id }}">
                        <input type="hidden" name="locale" value="{{ $query['locale'] }}">
                        <input type="hidden" name="menu" value="plugin.identity-local.users">
                        <input type="hidden" name="membership_id" value="{{ $query['membership_ids'][0] ?? 'membership-org-a-hello' }}">
                        <button class="button button-primary" type="submit">Create {{ $import_review['summary']['valid_count'] }} people</button>
                    </form>
                @endif
            </div>
        @endif

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
                            <td><a class="button button-secondary" href="{{ $row['open_url'] }}">Open</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
