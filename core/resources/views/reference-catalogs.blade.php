@php
    $selectedCatalog = is_array($selected_catalog ?? null) ? $selected_catalog : null;
@endphp

<section class="module-screen compact">
    <div class="overview-grid">
        <div class="metric-card"><div class="metric-label">{{ __('core.reference-data.metric.catalogs') }}</div><div class="metric-value">{{ $metrics['catalogs'] }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('core.reference-data.metric.effective_options') }}</div><div class="metric-value">{{ $metrics['effective_options'] }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('core.reference-data.metric.managed_overrides') }}</div><div class="metric-value">{{ $metrics['managed_entries'] }}</div></div>
    </div>

    <div class="surface-note">
        {{ __('core.reference-data.summary') }}
    </div>
    <div class="surface-note">
        {{ __('core.reference-data.scope_note') }}
    </div>

    <div class="table-card">
        <table class="entity-table">
            <thead>
                <tr>
                    <th>{{ __('core.reference-data.table.catalog') }}</th>
                    <th>{{ __('core.reference-data.table.scope') }}</th>
                    <th>{{ __('core.reference-data.table.current_options') }}</th>
                    <th>{{ __('core.reference-data.table.overrides') }}</th>
                    <th>{{ __('core.reference-data.table.action') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($catalogs as $catalog)
                    <tr>
                        <td>
                            <div class="entity-title">{{ $catalog['label'] }}</div>
                            <div class="table-note">{{ $catalog['description'] }}</div>
                        </td>
                        <td>{{ $selected_organization_id ?? __('core.reference-data.no_organization') }}</td>
                        <td>{{ $catalog['effective_count'] }}</td>
                        <td>
                            <span class="pill">{{ $catalog['uses_default'] ? __('core.reference-data.defaults') : __('core.reference-data.managed') }}</span>
                            <div class="table-note">{{ __('core.reference-data.organization_entries', ['value' => $catalog['managed_count']]) }}</div>
                        </td>
                        <td>
                            <a class="button button-secondary" href="{{ $catalog['open_url'] }}">{{ __('core.reference-data.edit_details') }}</a>
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
                        <div class="eyebrow">{{ __('core.reference-data.editor.eyebrow') }}</div>
                        <div class="entity-title" style="font-size:24px;">{{ __('core.reference-data.editor.title') }}</div>
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
                            <label class="field-label">{{ __('core.reference-data.editor.option_key') }}</label>
                            <input class="field-input" name="option_key" placeholder="for-example" required>
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('core.reference-data.editor.label') }}</label>
                            <input class="field-input" name="label" required>
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('core.reference-data.editor.sort_order') }}</label>
                            <input class="field-input" name="sort_order" type="number" min="1" value="100" required>
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('core.reference-data.editor.description') }}</label>
                            <input class="field-input" name="description">
                        </div>
                    </div>
                    <div class="action-cluster">
                        <button class="button button-primary" type="submit">{{ __('core.reference-data.editor.save_button') }}</button>
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
                <div class="metric-card"><div class="metric-label">{{ __('core.reference-data.selected.organization') }}</div><div class="metric-value" style="font-size:18px;">{{ $selected_organization_id ?? __('n/a') }}</div></div>
                <div class="metric-card"><div class="metric-label">{{ __('core.reference-data.selected.effective_options') }}</div><div class="metric-value">{{ count($effective_entries) }}</div></div>
                <div class="metric-card"><div class="metric-label">{{ __('core.reference-data.selected.managed_entries') }}</div><div class="metric-value">{{ count($managed_entries) }}</div></div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">{{ __('core.reference-data.selected.effective_options') }}</div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($effective_entries as $entry)
                            <div class="data-item">
                                <div class="entity-title">{{ $entry['label'] }}</div>
                                <div class="table-note">{{ $entry['option_key'] }} · {{ $entry['source'] === 'managed' ? __('core.reference-data.managed_override') : __('core.reference-data.default_option') }}</div>
                            </div>
                        @empty
                            <span class="muted-note">{{ __('core.reference-data.selected.no_effective_options') }}</span>
                        @endforelse
                    </div>
                </div>

                <div class="surface-card" style="padding:14px;">
                    <div class="metric-label">{{ __('core.reference-data.selected.managed_overrides') }}</div>
                    <div class="data-stack" style="margin-top:10px;">
                        @forelse ($managed_entries as $entry)
                            @php $editorId = 'reference-entry-'.$entry['id']; @endphp
                            <div class="data-item">
                                <div class="row-between" style="align-items:flex-start; gap:12px;">
                                    <div>
                                        <div class="entity-title">{{ $entry['label'] }}</div>
                                        <div class="table-note">{{ $entry['option_key'] }} · {{ $entry['is_active'] ? __('core.status.active') : __('core.status.inactive') }}</div>
                                        @if ($entry['description'] !== '')
                                            <div class="table-note">{{ $entry['description'] }}</div>
                                        @endif
                                    </div>
                                    @if ($can_manage_reference_data)
                                        <div class="action-cluster">
                                            <button class="button button-ghost" type="button" data-editor-toggle="{{ $editorId }}">{{ __('core.actions.edit') }}</button>
                                            <form method="POST" action="{{ $entry['is_active'] ? $archive_entry_route($entry['id']) : $activate_entry_route($entry['id']) }}">
                                                @csrf
                                                <input type="hidden" name="menu" value="core.reference-data">
                                                <input type="hidden" name="catalog_key" value="{{ $selectedCatalog['key'] }}">
                                                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                                <input type="hidden" name="organization_id" value="{{ $selected_organization_id ?? '' }}">
                                                <input type="hidden" name="locale" value="{{ $query['locale'] ?? 'en' }}">
                                                <input type="hidden" name="theme" value="{{ $query['theme'] ?? '' }}">
                                                <button class="button {{ $entry['is_active'] ? 'button-ghost' : 'button-primary' }}" type="submit">{{ $entry['is_active'] ? __('core.reference-data.archive') : __('core.reference-data.reactivate') }}</button>
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
                                                    <label class="field-label">{{ __('core.reference-data.editor.option_key') }}</label>
                                                    <input class="field-input" name="option_key" value="{{ $entry['option_key'] }}" required>
                                                </div>
                                                <div class="field" style="flex:1;">
                                                    <label class="field-label">{{ __('core.reference-data.editor.label') }}</label>
                                                    <input class="field-input" name="label" value="{{ $entry['label'] }}" required>
                                                </div>
                                            </div>
                                            <div class="row-between">
                                                <div class="field" style="flex:1;">
                                                    <label class="field-label">{{ __('core.reference-data.editor.sort_order') }}</label>
                                                    <input class="field-input" name="sort_order" type="number" min="1" value="{{ $entry['sort_order'] }}" required>
                                                </div>
                                                <div class="field" style="flex:1;">
                                                    <label class="field-label">{{ __('core.reference-data.editor.description') }}</label>
                                                    <input class="field-input" name="description" value="{{ $entry['description'] }}">
                                                </div>
                                            </div>
                                            <div class="action-cluster">
                                                <button class="button button-secondary" type="submit">{{ __('core.reference-data.editor.save_button') }}</button>
                                            </div>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <span class="muted-note">{{ __('core.reference-data.selected.no_managed_entries') }}</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif
</section>
