<section class="module-screen compact">
    <div class="surface-note">
        Governance page. Use this area to define tenant boundaries, default workspace settings, and operational scopes. Day-to-day record maintenance stays in the application workspaces, not here.
    </div>

    <div class="overview-grid">
        <div class="metric-card"><div class="metric-label">Organizations</div><div class="metric-value">{{ $metrics['organizations'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Active scopes</div><div class="metric-value">{{ $metrics['active_scopes'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Memberships</div><div class="metric-value">{{ $metrics['memberships'] }}</div></div>
    </div>

    @if ($can_manage_tenancy)
        <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
            <div class="table-card">
                <div class="screen-header">
                    <div>
                        <h2 class="screen-title" style="font-size:24px;">Create organization</h2>
                        <p class="screen-subtitle">Start a new tenant boundary with its default locale and timezone.</p>
                    </div>
                </div>
                <form class="stack" method="POST" action="{{ $create_organization_route }}">
                    @csrf
                    <input type="hidden" name="menu" value="core.tenancy">
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                    <input type="hidden" name="locale" value="{{ $query['locale'] ?? 'en' }}">
                    <input type="hidden" name="theme" value="{{ $query['theme'] ?? '' }}">
                    @foreach (($query['membership_ids'] ?? []) as $membershipId)
                        <input type="hidden" name="membership_ids[]" value="{{ $membershipId }}">
                    @endforeach
                    <div class="field">
                        <label class="field-label" for="org-name">Organization name</label>
                        <input class="field-input" id="org-name" name="name" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="org-slug">Slug</label>
                        <input class="field-input" id="org-slug" name="slug" placeholder="optional-auto-slug">
                    </div>
                    <div class="field">
                        <label class="field-label" for="org-locale">Default locale</label>
                        <select class="field-select" id="org-locale" name="default_locale">
                            @foreach ($locale_options as $locale)
                                <option value="{{ $locale }}">{{ strtoupper($locale) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="org-timezone">Default timezone</label>
                        <input class="field-input" id="org-timezone" name="default_timezone" value="UTC" required>
                    </div>
                    <button class="button button-primary" type="submit">Create organization</button>
                </form>
            </div>

            <div class="table-card">
                <div class="screen-header">
                    <div>
                        <h2 class="screen-title" style="font-size:24px;">Create scope</h2>
                        <p class="screen-subtitle">Add a bounded workspace inside an existing organization.</p>
                    </div>
                </div>
                <form class="stack" method="POST" action="{{ $create_scope_route }}">
                    @csrf
                    <input type="hidden" name="menu" value="core.tenancy">
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                    <input type="hidden" name="locale" value="{{ $query['locale'] ?? 'en' }}">
                    <input type="hidden" name="theme" value="{{ $query['theme'] ?? '' }}">
                    @foreach (($query['membership_ids'] ?? []) as $membershipId)
                        <input type="hidden" name="membership_ids[]" value="{{ $membershipId }}">
                    @endforeach
                    <div class="field">
                        <label class="field-label" for="scope-org">Organization</label>
                        <select class="field-select" id="scope-org" name="organization_id" required>
                            @foreach ($organizations as $organization)
                                <option value="{{ $organization['id'] }}" @selected(($query['organization_id'] ?? null) === $organization['id'])>
                                    {{ $organization['name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="scope-name">Scope name</label>
                        <input class="field-input" id="scope-name" name="name" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="scope-slug">Slug</label>
                        <input class="field-input" id="scope-slug" name="slug" placeholder="optional-auto-slug">
                    </div>
                    <div class="field">
                        <label class="field-label" for="scope-description">Description</label>
                        <input class="field-input" id="scope-description" name="description" placeholder="Optional working perimeter description">
                    </div>
                    <button class="button button-primary" type="submit">Create scope</button>
                </form>
            </div>
        </div>
    @endif

    <div class="table-card">
        <div class="screen-header">
            <div>
                <h2 class="screen-title" style="font-size:26px;">Organizations</h2>
                <p class="screen-subtitle">Tenant boundaries, defaults, and current workspace state.</p>
            </div>
        </div>
        <table class="entity-table">
            <thead>
                <tr>
                    <th>Organization</th>
                    <th>Defaults</th>
                    <th>Status</th>
                    <th>{{ $can_manage_tenancy ? 'Actions' : 'Notes' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($organizations as $organization)
                    @php $organizationEditorId = 'org-editor-'.$organization['id']; @endphp
                    @php $organizationEditorOpen = old('editor_target') === $organizationEditorId; @endphp
                    <tr>
                        <td>
                            <div class="entity-title">{{ $organization['name'] }}</div>
                            <div class="entity-id">{{ $organization['id'] }} · {{ $organization['slug'] }}</div>
                            <div class="table-note">{{ $organization['scope_count'] }} scopes · {{ $organization['membership_count'] }} memberships</div>
                        </td>
                        <td>{{ strtoupper($organization['default_locale']) }} · {{ $organization['default_timezone'] }}</td>
                        <td>
                            <span class="pill">{{ $organization['is_active'] ? 'active' : 'archived' }}</span>
                        </td>
                        <td>
                            @if ($can_manage_tenancy)
                                <div class="action-cluster">
                                    <button
                                        class="button button-secondary"
                                        type="button"
                                        data-editor-toggle="{{ $organizationEditorId }}"
                                        aria-expanded="{{ $organizationEditorOpen ? 'true' : 'false' }}"
                                    >
                                        Edit
                                    </button>
                                    <form method="POST" action="{{ $organization['is_active'] ? $archive_organization_route($organization['id']) : $activate_organization_route($organization['id']) }}">
                                        @csrf
                                        <input type="hidden" name="menu" value="core.tenancy">
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] ?? 'en' }}">
                                        <input type="hidden" name="theme" value="{{ $query['theme'] ?? '' }}">
                                        <button class="button {{ $organization['is_active'] ? 'button-ghost' : 'button-primary' }}" type="submit">
                                            {{ $organization['is_active'] ? 'Archive' : 'Reactivate' }}
                                        </button>
                                    </form>
                                </div>
                            @else
                                <div class="table-note">Platform admins can change tenant metadata here.</div>
                            @endif
                        </td>
                    </tr>
                    @if ($can_manage_tenancy)
                        <tr id="{{ $organizationEditorId }}" class="table-editor-row" @if (! $organizationEditorOpen) hidden @endif>
                            <td colspan="4">
                                <div class="editor-panel">
                                    <div class="row-between">
                                        <div>
                                            <div class="entity-title">Edit organization</div>
                                            <div class="table-note">Update tenant identity and defaults without squeezing the form into the table.</div>
                                        </div>
                                        <button
                                            class="button button-ghost"
                                            type="button"
                                            data-editor-toggle="{{ $organizationEditorId }}"
                                            aria-expanded="{{ $organizationEditorOpen ? 'true' : 'false' }}"
                                        >
                                            Close
                                        </button>
                                    </div>
                                    <form class="stack" method="POST" action="{{ $update_organization_route($organization['id']) }}">
                                        @csrf
                                        <input type="hidden" name="menu" value="core.tenancy">
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] ?? 'en' }}">
                                        <input type="hidden" name="theme" value="{{ $query['theme'] ?? '' }}">
                                        <input type="hidden" name="editor_target" value="{{ $organizationEditorId }}">
                                        <div class="row-between">
                                            <div class="field" style="flex:1;">
                                                <label class="field-label">Name</label>
                                                <input class="field-input" name="name" value="{{ old('editor_target') === $organizationEditorId ? old('name', $organization['name']) : $organization['name'] }}" required>
                                            </div>
                                            <div class="field" style="flex:1;">
                                                <label class="field-label">Slug</label>
                                                <input class="field-input" name="slug" value="{{ old('editor_target') === $organizationEditorId ? old('slug', $organization['slug']) : $organization['slug'] }}" required>
                                            </div>
                                        </div>
                                        <div class="row-between">
                                            <div class="field" style="flex:1;">
                                                <label class="field-label">Locale</label>
                                                <select class="field-select" name="default_locale">
                                                    @foreach ($locale_options as $locale)
                                                        @php $localeValue = old('editor_target') === $organizationEditorId ? old('default_locale', $organization['default_locale']) : $organization['default_locale']; @endphp
                                                        <option value="{{ $locale }}" @selected($localeValue === $locale)>{{ strtoupper($locale) }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="field" style="flex:1;">
                                                <label class="field-label">Timezone</label>
                                                <input class="field-input" name="default_timezone" value="{{ old('editor_target') === $organizationEditorId ? old('default_timezone', $organization['default_timezone']) : $organization['default_timezone'] }}" required>
                                            </div>
                                        </div>
                                        <div class="action-cluster">
                                            <button class="button button-primary" type="submit">Save organization</button>
                                        </div>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="table-card">
        <div class="screen-header">
            <div>
                <h2 class="screen-title" style="font-size:26px;">Scopes</h2>
                <p class="screen-subtitle">Operational perimeters bound to one organization.</p>
            </div>
        </div>
        <table class="entity-table">
            <thead>
                <tr>
                    <th>Scope</th>
                    <th>Organization</th>
                    <th>Status</th>
                    <th>{{ $can_manage_tenancy ? 'Actions' : 'Notes' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($scopes as $scope)
                    @php $scopeEditorId = 'scope-editor-'.$scope['id']; @endphp
                    @php $scopeEditorOpen = old('editor_target') === $scopeEditorId; @endphp
                    <tr>
                        <td>
                            <div class="entity-title">{{ $scope['name'] }}</div>
                            <div class="entity-id">{{ $scope['id'] }} · {{ $scope['slug'] }}</div>
                            <div class="table-note">{{ $scope['description'] !== '' ? $scope['description'] : 'No description yet.' }}</div>
                        </td>
                        <td>{{ $scope['organization_id'] }}</td>
                        <td><span class="pill">{{ $scope['is_active'] ? 'active' : 'archived' }}</span></td>
                        <td>
                            @if ($can_manage_tenancy)
                                <div class="action-cluster">
                                    <button
                                        class="button button-secondary"
                                        type="button"
                                        data-editor-toggle="{{ $scopeEditorId }}"
                                        aria-expanded="{{ $scopeEditorOpen ? 'true' : 'false' }}"
                                    >
                                        Edit
                                    </button>
                                    <form method="POST" action="{{ $scope['is_active'] ? $archive_scope_route($scope['id']) : $activate_scope_route($scope['id']) }}">
                                        @csrf
                                        <input type="hidden" name="menu" value="core.tenancy">
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                        <input type="hidden" name="organization_id" value="{{ $scope['organization_id'] }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] ?? 'en' }}">
                                        <input type="hidden" name="theme" value="{{ $query['theme'] ?? '' }}">
                                        <button class="button {{ $scope['is_active'] ? 'button-ghost' : 'button-primary' }}" type="submit">
                                            {{ $scope['is_active'] ? 'Archive' : 'Reactivate' }}
                                        </button>
                                    </form>
                                </div>
                            @else
                                <div class="table-note">Scope lifecycle is managed by platform administrators.</div>
                            @endif
                        </td>
                    </tr>
                    @if ($can_manage_tenancy)
                        <tr id="{{ $scopeEditorId }}" class="table-editor-row" @if (! $scopeEditorOpen) hidden @endif>
                            <td colspan="4">
                                <div class="editor-panel">
                                    <div class="row-between">
                                        <div>
                                            <div class="entity-title">Edit scope</div>
                                            <div class="table-note">Keep the table readable and open the editor only when you need it.</div>
                                        </div>
                                        <button
                                            class="button button-ghost"
                                            type="button"
                                            data-editor-toggle="{{ $scopeEditorId }}"
                                            aria-expanded="{{ $scopeEditorOpen ? 'true' : 'false' }}"
                                        >
                                            Close
                                        </button>
                                    </div>
                                    <form class="stack" method="POST" action="{{ $update_scope_route($scope['id']) }}">
                                        @csrf
                                        <input type="hidden" name="menu" value="core.tenancy">
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] ?? 'en' }}">
                                        <input type="hidden" name="theme" value="{{ $query['theme'] ?? '' }}">
                                        <input type="hidden" name="organization_id" value="{{ $scope['organization_id'] }}">
                                        <input type="hidden" name="editor_target" value="{{ $scopeEditorId }}">
                                        <div class="row-between">
                                            <div class="field" style="flex:1;">
                                                <label class="field-label">Name</label>
                                                <input class="field-input" name="name" value="{{ old('editor_target') === $scopeEditorId ? old('name', $scope['name']) : $scope['name'] }}" required>
                                            </div>
                                            <div class="field" style="flex:1;">
                                                <label class="field-label">Slug</label>
                                                <input class="field-input" name="slug" value="{{ old('editor_target') === $scopeEditorId ? old('slug', $scope['slug']) : $scope['slug'] }}" required>
                                            </div>
                                        </div>
                                        <div class="field">
                                            <label class="field-label">Description</label>
                                            <input class="field-input" name="description" value="{{ old('editor_target') === $scopeEditorId ? old('description', $scope['description']) : $scope['description'] }}">
                                        </div>
                                        <div class="action-cluster">
                                            <button class="button button-primary" type="submit">Save scope</button>
                                        </div>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="table-card">
        <div class="screen-header">
            <div>
                <h2 class="screen-title" style="font-size:24px;">Memberships</h2>
                <p class="screen-subtitle">Access assignments stay visible here, but user access is managed from People and Access.</p>
            </div>
        </div>
        <div class="surface-note">Use the identity screens to create memberships, assign scopes, and grant roles. This panel keeps tenancy structure and access structure clearly separated.</div>
        <div class="data-stack" style="margin-top:14px;">
            @foreach ($memberships as $membership)
                <div class="data-item">
                    <div class="entity-title">{{ $membership['principal_id'] }}</div>
                    <div class="entity-id">{{ $membership['organization_id'] }} · {{ $membership['id'] }}</div>
                    <div class="table-note">{{ $membership['is_active'] ? 'Active workspace access' : 'Inactive membership' }}</div>
                </div>
            @endforeach
        </div>
    </div>
</section>
