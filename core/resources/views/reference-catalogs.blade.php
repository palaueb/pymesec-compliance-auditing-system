@php
    $selectedCatalog = is_array($selected_catalog ?? null) ? $selected_catalog : null;
@endphp

<section class="module-screen compact">
    <div class="overview-grid">
        <div class="metric-card"><div class="metric-label">Catalogs</div><div class="metric-value">{{ $metrics['catalogs'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Effective options</div><div class="metric-value">{{ $metrics['effective_options'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Managed overrides</div><div class="metric-value">{{ $metrics['managed_entries'] }}</div></div>
    </div>

    <div class="surface-note">
        Reference catalogs keep business labels consistent across the workspace. Defaults are available immediately; organization-specific overrides let admins add, rename, or retire options without editing code.
    </div>

    <div class="table-card">
        <table class="entity-table">
            <thead>
                <tr>
                    <th>Catalog</th>
                    <th>Scope</th>
                    <th>Current options</th>
                    <th>Overrides</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($catalogs as $catalog)
                    <tr>
                        <td>
                            <div class="entity-title">{{ $catalog['label'] }}</div>
                            <div class="table-note">{{ $catalog['description'] }}</div>
                        </td>
                        <td>{{ $selected_organization_id ?? 'No organization' }}</td>
                        <td>{{ $catalog['effective_count'] }}</td>
                        <td>
                            <span class="pill">{{ $catalog['uses_default'] ? 'defaults' : 'managed' }}</span>
                            <div class="table-note">{{ $catalog['managed_count'] }} organization entries</div>
                        </td>
                        <td>
                            <a class="button button-secondary" href="{{ $catalog['open_url'] }}">Edit details</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($selectedCatalog !== null)
        @if ($can_manage_reference_data)
            <div class="surface-card" id="reference-catalog-entry-editor" hidden>
                <div class="row-between" style="margin-bottom:14px;">
                    <div>
                        <div class="eyebrow">Catalog option</div>
                        <div class="entity-title" style="font-size:24px;">New managed option</div>
                    </div>
                </div>
                <form class="stack" method="POST" action="{{ $create_entry_route }}">
                    @csrf
                    <input type="hidden" name="menu" value="core.reference-data">
                    <input type="hidden" name="catalog_key" value="{{ $selectedCatalog['key'] }}">
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                    <input type="hidden" name="organization_id" value="{{ $selected_organization_id ?? '' }}">
                    <input type="hidden" name="locale" value="{{ $query['locale'] ?? 'en' }}">
                    <input type="hidden" name="theme" value="{{ $query['theme'] ?? '' }}">
                    @foreach (($query['membership_ids'] ?? []) as $membershipId)
                        <input type="hidden" name="membership_ids[]" value="{{ $membershipId }}">
                    @endforeach
                    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                        <div class="field">
                            <label class="field-label">Option key</label>
                            <input class="field-input" name="option_key" placeholder="for-example" required>
                        </div>
                        <div class="field">
                            <label class="field-label">Label</label>
                            <input class="field-input" name="label" required>
                        </div>
                        <div class="field">
                            <label class="field-label">Sort order</label>
                            <input class="field-input" name="sort_order" type="number" min="1" value="100" required>
                        </div>
                        <div class="field">
                            <label class="field-label">Description</label>
                            <input class="field-input" name="description">
                        </div>
                    </div>
                    <div class="action-cluster">
                        <button class="button button-primary" type="submit">Save option</button>
                    </div>
                </form>
            </div>
        @endif

        <div class="table-card">
            <div class="screen-header">
                <div>
                    <h2 class="screen-title" style="font-size:26px;">{{ $selectedCatalog['label'] }}</h2>
                    <p class="screen-subtitle">{{ $selectedCatalog['description'] }}</p>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(3, minmax(0, 1fr)); margin-bottom:16px;">
                <div class="metric-card"><div class="metric-label">Organization</div><div class="metric-value" style="font-size:18px;">{{ $selected_organization_id ?? 'n/a' }}</div></div>
                <div class="metric-card"><div class="metric-label">Effective options</div><div class="metric-value">{{ count($effective_entries) }}</div></div>
                <div class="metric-card"><div class="metric-label">Managed entries</div><div class="metric-value">{{ count($managed_entries) }}</div></div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Effective options</div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($effective_entries as $entry)
                            <div class="data-item">
                                <div class="entity-title">{{ $entry['label'] }}</div>
                                <div class="table-note">{{ $entry['option_key'] }} · {{ $entry['source'] === 'managed' ? 'Managed override' : 'Default option' }}</div>
                            </div>
                        @empty
                            <span class="muted-note">No effective options yet.</span>
                        @endforelse
                    </div>
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">Managed overrides</div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($managed_entries as $entry)
                            @php $editorId = 'reference-entry-'.$entry['id']; @endphp
                            <div class="data-item">
                                <div class="row-between" style="align-items:flex-start; gap:12px;">
                                    <div>
                                        <div class="entity-title">{{ $entry['label'] }}</div>
                                        <div class="table-note">{{ $entry['option_key'] }} · {{ $entry['is_active'] ? 'active' : 'inactive' }}</div>
                                        @if ($entry['description'] !== '')
                                            <div class="table-note">{{ $entry['description'] }}</div>
                                        @endif
                                    </div>
                                    @if ($can_manage_reference_data)
                                        <div class="action-cluster">
                                            <button class="button button-ghost" type="button" data-editor-toggle="{{ $editorId }}">Edit</button>
                                            <form method="POST" action="{{ $entry['is_active'] ? $archive_entry_route($entry['id']) : $activate_entry_route($entry['id']) }}">
                                                @csrf
                                                <input type="hidden" name="menu" value="core.reference-data">
                                                <input type="hidden" name="catalog_key" value="{{ $selectedCatalog['key'] }}">
                                                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                                <input type="hidden" name="organization_id" value="{{ $selected_organization_id ?? '' }}">
                                                <input type="hidden" name="locale" value="{{ $query['locale'] ?? 'en' }}">
                                                <input type="hidden" name="theme" value="{{ $query['theme'] ?? '' }}">
                                                <button class="button {{ $entry['is_active'] ? 'button-ghost' : 'button-primary' }}" type="submit">{{ $entry['is_active'] ? 'Archive' : 'Reactivate' }}</button>
                                            </form>
                                        </div>
                                    @endif
                                </div>
                                @if ($can_manage_reference_data)
                                    <div id="{{ $editorId }}" class="editor-panel" hidden style="margin-top:12px;">
                                        <form class="stack" method="POST" action="{{ $update_entry_route($entry['id']) }}">
                                            @csrf
                                            <input type="hidden" name="menu" value="core.reference-data">
                                            <input type="hidden" name="catalog_key" value="{{ $selectedCatalog['key'] }}">
                                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                            <input type="hidden" name="organization_id" value="{{ $selected_organization_id ?? '' }}">
                                            <input type="hidden" name="locale" value="{{ $query['locale'] ?? 'en' }}">
                                            <input type="hidden" name="theme" value="{{ $query['theme'] ?? '' }}">
                                            <div class="row-between">
                                                <div class="field" style="flex:1;">
                                                    <label class="field-label">Option key</label>
                                                    <input class="field-input" name="option_key" value="{{ $entry['option_key'] }}" required>
                                                </div>
                                                <div class="field" style="flex:1;">
                                                    <label class="field-label">Label</label>
                                                    <input class="field-input" name="label" value="{{ $entry['label'] }}" required>
                                                </div>
                                            </div>
                                            <div class="row-between">
                                                <div class="field" style="flex:1;">
                                                    <label class="field-label">Sort order</label>
                                                    <input class="field-input" name="sort_order" type="number" min="1" value="{{ $entry['sort_order'] }}" required>
                                                </div>
                                                <div class="field" style="flex:1;">
                                                    <label class="field-label">Description</label>
                                                    <input class="field-input" name="description" value="{{ $entry['description'] }}">
                                                </div>
                                            </div>
                                            <div class="action-cluster">
                                                <button class="button button-secondary" type="submit">Save option</button>
                                            </div>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <span class="muted-note">This organization is still using the default options for this catalog.</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif
</section>
