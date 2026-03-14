<section class="module-screen compact">
    <div class="overview-grid">
        <div class="metric-card"><div class="metric-label">Enabled</div><div class="metric-value">{{ $metrics['enabled'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Booted</div><div class="metric-value">{{ $metrics['booted'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Needs attention</div><div class="metric-value">{{ $metrics['attention'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Overrides</div><div class="metric-value">{{ $metrics['overrides'] }}</div></div>
    </div>

    <div class="surface-note">
        Lifecycle changes are persisted as local overrides in <code>{{ $state_path }}</code>. Plugin-owned settings stay inside each plugin workspace screen.
    </div>

    <div class="table-card">
        <table class="entity-table">
            <thead>
                <tr>
                    <th>Plugin</th>
                    <th>State</th>
                    <th>Dependencies</th>
                    <th>Access</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($plugins as $plugin)
                    @php
                        $detailId = 'plugin-'.$plugin['id'];
                        $stateLabel = match ($plugin['lifecycle_source']) {
                            'config_enabled' => 'enabled by config',
                            'config_disabled' => 'disabled by config',
                            'override_enabled' => 'enabled by override',
                            'override_disabled' => 'disabled by override',
                            default => 'runtime state',
                        };
                        $statusLabel = $plugin['booted']
                            ? 'booted'
                            : ($plugin['reason'] === 'plugin_not_enabled' ? 'not booted' : 'attention');
                    @endphp
                    <tr>
                        <td>
                            <div class="entity-title">{{ $plugin['name'] }}</div>
                            <div class="entity-id">{{ $plugin['id'] }} · {{ $plugin['version'] }} · {{ $plugin['type'] }}</div>
                        </td>
                        <td>
                            <div class="action-cluster">
                                <span class="pill">{{ $stateLabel }}</span>
                                <span class="tag">{{ $statusLabel }}</span>
                            </div>
                            <div class="table-note">
                                {{ $plugin['reason'] ?? 'ok' }}
                            </div>
                        </td>
                        <td>
                            @if ($plugin['required_dependencies'] !== [])
                                <div>{{ implode(', ', $plugin['required_dependencies']) }}</div>
                            @else
                                <div class="table-note">none</div>
                            @endif
                            @if ($plugin['dependent_plugins'] !== [])
                                <div class="table-note">Used by {{ implode(', ', $plugin['dependent_plugins']) }}</div>
                            @endif
                        </td>
                        <td>
                            <div class="action-cluster">
                                @if (is_string($plugin['workspace_url'] ?? null))
                                    <a class="button button-ghost" href="{{ $plugin['workspace_url'] }}">Open</a>
                                @endif
                                @if (is_string($plugin['settings_url'] ?? null))
                                    <a class="button button-ghost" href="{{ $plugin['settings_url'] }}">Settings</a>
                                @elseif (($plugin['settings_requires_context'] ?? false) === true)
                                    <span class="table-note">Select an organization to configure</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="action-cluster">
                                <button class="button button-ghost" type="button" data-editor-toggle="{{ $detailId }}">Details</button>
                                @if ($can_manage_plugins)
                                    @if (($plugin['lifecycle']['operation'] ?? null) === 'disable')
                                        <form method="POST" action="{{ $disable_plugin_route($plugin['id']) }}">
                                            @csrf
                                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                            <input type="hidden" name="locale" value="{{ $query['locale'] ?? '' }}">
                                            <input type="hidden" name="theme" value="{{ $query['theme'] ?? '' }}">
                                            <input type="hidden" name="menu" value="core.plugins">
                                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] ?? '' }}">
                                            <input type="hidden" name="scope_id" value="{{ $query['scope_id'] ?? '' }}">
                                            @foreach ($query['membership_ids'] ?? [] as $membershipId)
                                                <input type="hidden" name="membership_ids[]" value="{{ $membershipId }}">
                                            @endforeach
                                            <button class="button button-secondary" type="submit" @disabled(($plugin['lifecycle']['blocked'] ?? false) === true)>Disable</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ $enable_plugin_route($plugin['id']) }}">
                                            @csrf
                                            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                            <input type="hidden" name="locale" value="{{ $query['locale'] ?? '' }}">
                                            <input type="hidden" name="theme" value="{{ $query['theme'] ?? '' }}">
                                            <input type="hidden" name="menu" value="core.plugins">
                                            <input type="hidden" name="organization_id" value="{{ $query['organization_id'] ?? '' }}">
                                            <input type="hidden" name="scope_id" value="{{ $query['scope_id'] ?? '' }}">
                                            @foreach ($query['membership_ids'] ?? [] as $membershipId)
                                                <input type="hidden" name="membership_ids[]" value="{{ $membershipId }}">
                                            @endforeach
                                            <button class="button button-primary" type="submit" @disabled(($plugin['lifecycle']['blocked'] ?? false) === true)>Enable</button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                    <tr class="table-editor-row" data-editor-target="{{ $detailId }}" hidden>
                        <td colspan="5">
                            <div class="editor-panel">
                                <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                                    <div class="surface-card" style="padding:16px;">
                                        <div class="field-label">Runtime</div>
                                        <div class="body-copy" style="margin-top:8px;">
                                            {{ $plugin['description'] ?? 'No description provided.' }}
                                        </div>
                                        <div class="body-copy" style="margin-top:12px;">
                                            {{ $plugin['permission_count'] }} permissions · {{ $plugin['route_count'] }} routes · {{ $plugin['menu_count'] }} menus
                                        </div>
                                        @if (($plugin['missing_dependencies'] ?? []) !== [])
                                            <div class="table-note" style="margin-top:8px;">Missing dependencies: {{ implode(', ', $plugin['missing_dependencies']) }}</div>
                                        @endif
                                    </div>
                                    <div class="surface-card" style="padding:16px;">
                                        <div class="field-label">Lifecycle</div>
                                        <div class="body-copy" style="margin-top:8px;">
                                            @if (($plugin['lifecycle']['blocked'] ?? false) === true)
                                                {{ ($plugin['lifecycle']['operation'] ?? 'change') === 'disable'
                                                    ? 'Disable is blocked until dependent plugins are disabled first.'
                                                    : 'Enable is blocked until required dependencies are enabled.' }}
                                            @else
                                                {{ ($plugin['lifecycle']['operation'] ?? 'change') === 'disable'
                                                    ? 'This plugin can be disabled now.'
                                                    : 'This plugin can be enabled now.' }}
                                            @endif
                                        </div>
                                        @if (($plugin['lifecycle']['dependencies'] ?? []) !== [])
                                            <div class="table-note" style="margin-top:8px;">
                                                {{ implode(', ', $plugin['lifecycle']['dependencies']) }}
                                            </div>
                                        @endif
                                        <div class="table-note" style="margin-top:12px;">State source: {{ $stateLabel }}</div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
