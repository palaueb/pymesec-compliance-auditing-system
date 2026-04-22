@php
    $selectedPlugin = is_array($selected_plugin ?? null) ? $selected_plugin : null;
@endphp

<section class="module-screen compact">
    <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
        <div class="metric-card"><div class="metric-label">{{ __('Enabled') }}</div><div class="metric-value">{{ $metrics['enabled'] }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('Booted') }}</div><div class="metric-value">{{ $metrics['booted'] }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('Needs attention') }}</div><div class="metric-value">{{ $metrics['attention'] }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('Overrides') }}</div><div class="metric-value">{{ $metrics['overrides'] }}</div></div>
    </div>

    <div class="surface-note">
        {{ __('Module activation changes are stored in :path. Module-specific setup lives in each module\'s own settings area.', ['path' => $state_path]) }}
    </div>

    <div class="surface-note">
        {{ __('Governance page. Enable or disable modules here, but keep module-specific business operations inside each module workspace.') }}
    </div>

    @if ($selectedPlugin !== null)
        @php
            $stateLabel = match ($selectedPlugin['lifecycle_source']) {
                'config_enabled' => __('enabled by config'),
                'config_disabled' => __('disabled by config'),
                'override_enabled' => __('enabled by override'),
                'override_disabled' => __('disabled by override'),
                default => __('runtime state'),
            };
            $statusLabel = $selectedPlugin['booted']
                ? __('booted')
                : (($selectedPlugin['reason'] ?? null) === 'plugin_not_enabled' ? __('not booted') : __('attention'));
            $actionMode = $selectedPlugin['lifecycle']['operation'] ?? null;
        @endphp

        <div class="table-card">
            <div class="screen-header">
                <div>
                    <h2 class="screen-title" style="font-size:24px;">{{ $selectedPlugin['name'] }}</h2>
                    <p class="screen-subtitle">{{ $selectedPlugin['description'] ?? __('No description provided yet.') }}</p>
                </div>
                <div class="action-cluster">
                    @if (is_string($selectedPlugin['workspace_url'] ?? null))
                        <a class="button button-ghost" href="{{ $selectedPlugin['workspace_url'] }}">{{ __('Open module') }}</a>
                    @endif
                    @if (is_string($selectedPlugin['settings_url'] ?? null))
                        <a class="button button-ghost" href="{{ $selectedPlugin['settings_url'] }}">{{ __('Open settings') }}</a>
                    @elseif (($selectedPlugin['settings_requires_context'] ?? false) === true)
                        <span class="table-note">{{ __('Select an organization to configure this module.') }}</span>
                    @endif
                    @if ($can_manage_plugins)
                        @if ($actionMode === 'disable')
                            <form method="POST" action="{{ $disable_plugin_route($selectedPlugin['id']) }}">
                                @csrf
                                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                <input type="hidden" name="locale" value="{{ $query['locale'] ?? '' }}">
                                <input type="hidden" name="theme" value="{{ $query['theme'] ?? '' }}">
                                <input type="hidden" name="menu" value="core.plugins">
                                <input type="hidden" name="plugin_id" value="{{ $selectedPlugin['id'] }}">
                                <input type="hidden" name="organization_id" value="{{ $query['organization_id'] ?? '' }}">
                                <input type="hidden" name="scope_id" value="{{ $query['scope_id'] ?? '' }}">
                                @foreach ($query['membership_ids'] ?? [] as $membershipId)
                                    <input type="hidden" name="membership_ids[]" value="{{ $membershipId }}">
                                @endforeach
                                <button class="button button-secondary" type="submit" @disabled(($selectedPlugin['lifecycle']['blocked'] ?? false) === true)>{{ __('Disable') }}</button>
                            </form>
                        @else
                            <form method="POST" action="{{ $enable_plugin_route($selectedPlugin['id']) }}">
                                @csrf
                                <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                <input type="hidden" name="locale" value="{{ $query['locale'] ?? '' }}">
                                <input type="hidden" name="theme" value="{{ $query['theme'] ?? '' }}">
                                <input type="hidden" name="menu" value="core.plugins">
                                <input type="hidden" name="plugin_id" value="{{ $selectedPlugin['id'] }}">
                                <input type="hidden" name="organization_id" value="{{ $query['organization_id'] ?? '' }}">
                                <input type="hidden" name="scope_id" value="{{ $query['scope_id'] ?? '' }}">
                                @foreach ($query['membership_ids'] ?? [] as $membershipId)
                                    <input type="hidden" name="membership_ids[]" value="{{ $membershipId }}">
                                @endforeach
                                <button class="button button-primary" type="submit" @disabled(($selectedPlugin['lifecycle']['blocked'] ?? false) === true)>{{ __('Enable') }}</button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
                <div class="metric-card">
                    <div class="metric-label">{{ __('State') }}</div>
                    <div class="metric-value">{{ $stateLabel }}</div>
                    <div class="meta-copy">{{ $statusLabel }}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">{{ __('Permissions') }}</div>
                    <div class="metric-value">{{ $selectedPlugin['permission_count'] }}</div>
                    <div class="meta-copy">{{ __('Registered access rules.') }}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">{{ __('Routes') }}</div>
                    <div class="metric-value">{{ $selectedPlugin['route_count'] }}</div>
                    <div class="meta-copy">{{ __('Available entry points.') }}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">{{ __('Menus') }}</div>
                    <div class="metric-value">{{ $selectedPlugin['menu_count'] }}</div>
                    <div class="meta-copy">{{ __('Visible navigation items.') }}</div>
                </div>
            </div>

            <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                <div class="surface-card" style="padding:16px;">
                    <div class="field-label">{{ __('Dependencies') }}</div>
                    <div class="body-copy" style="margin-top:8px;">
                        @if (($selectedPlugin['required_dependencies'] ?? []) !== [])
                            {{ implode(', ', $selectedPlugin['required_dependencies']) }}
                        @else
                            {{ __('No required dependencies.') }}
                        @endif
                    </div>
                    @if (($selectedPlugin['missing_dependencies'] ?? []) !== [])
                        <div class="table-note" style="margin-top:10px;">{{ __('Missing: :values', ['values' => implode(', ', $selectedPlugin['missing_dependencies'])]) }}</div>
                    @endif
                    @if (($selectedPlugin['dependent_plugins'] ?? []) !== [])
                        <div class="table-note" style="margin-top:10px;">{{ __('Used by: :values', ['values' => implode(', ', $selectedPlugin['dependent_plugins'])]) }}</div>
                    @endif
                </div>
                <div class="surface-card" style="padding:16px;">
                    <div class="field-label">{{ __('Availability') }}</div>
                    <div class="body-copy" style="margin-top:8px;">
                        @if (($selectedPlugin['lifecycle']['blocked'] ?? false) === true)
                            {{ $actionMode === 'disable'
                                ? __('Disable is blocked until dependent modules are disabled first.')
                                : __('Enable is blocked until required dependencies are enabled.') }}
                        @else
                            {{ $actionMode === 'disable'
                                ? __('This module can be disabled now.')
                                : __('This module can be enabled now.') }}
                        @endif
                    </div>
                    @if (($selectedPlugin['lifecycle']['dependencies'] ?? []) !== [])
                        <div class="table-note" style="margin-top:10px;">{{ __('Checks: :values', ['values' => implode(', ', $selectedPlugin['lifecycle']['dependencies'])]) }}</div>
                    @endif
                    <div class="table-note" style="margin-top:10px;">{{ __('State source: :value', ['value' => $stateLabel]) }}</div>
                </div>
            </div>
        </div>
    @endif

    <div class="table-card">
        <table class="entity-table">
            <thead>
                <tr>
                    <th>{{ __('Module') }}</th>
                    <th>{{ __('State') }}</th>
                    <th>{{ __('Dependencies') }}</th>
                    <th>{{ __('Access') }}</th>
                    <th>{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($plugins as $plugin)
                    @php
                        $stateLabel = match ($plugin['lifecycle_source']) {
                            'config_enabled' => __('enabled by config'),
                            'config_disabled' => __('disabled by config'),
                            'override_enabled' => __('enabled by override'),
                            'override_disabled' => __('disabled by override'),
                            default => __('runtime state'),
                        };
                        $statusLabel = $plugin['booted']
                            ? __('booted')
                            : (($plugin['reason'] ?? null) === 'plugin_not_enabled' ? __('not booted') : __('attention'));
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
                            <div class="table-note">{{ $plugin['reason'] ?? __('ok') }}</div>
                        </td>
                        <td>
                            @if (($plugin['required_dependencies'] ?? []) !== [])
                                <div>{{ implode(', ', $plugin['required_dependencies']) }}</div>
                            @else
                                <div class="table-note">{{ __('none') }}</div>
                            @endif
                            @if (($plugin['dependent_plugins'] ?? []) !== [])
                                <div class="table-note">{{ __('Used by :values', ['values' => implode(', ', $plugin['dependent_plugins'])]) }}</div>
                            @endif
                        </td>
                        <td>
                            <div class="action-cluster">
                                @if (is_string($plugin['workspace_url'] ?? null))
                                    <a class="button button-ghost" href="{{ $plugin['workspace_url'] }}">{{ __('Open') }}</a>
                                @endif
                                @if (is_string($plugin['settings_url'] ?? null))
                                    <a class="button button-ghost" href="{{ $plugin['settings_url'] }}">{{ __('Settings') }}</a>
                                @endif
                            </div>
                            @if (($plugin['settings_requires_context'] ?? false) === true)
                                <div class="table-note">{{ __('Select an organization to configure this module.') }}</div>
                            @endif
                        </td>
                        <td>
                            <div class="action-cluster">
                                <a class="button button-ghost" href="{{ $plugin['open_url'] }}">{{ __('Open') }}</a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
