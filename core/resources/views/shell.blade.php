<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('core.shell.title') }}</title>
    <style>
        :root {
            --bg: {{ $theme['colors']['bg'] }};
            --bg-alt: {{ $theme['colors']['bg_alt'] }};
            --panel: {{ $theme['colors']['panel'] }};
            --panel-alt: {{ $theme['colors']['panel_alt'] }};
            --line: {{ $theme['colors']['line'] }};
            --ink: {{ $theme['colors']['ink'] }};
            --muted: {{ $theme['colors']['muted'] }};
            --accent: {{ $theme['colors']['accent'] }};
            --accent-alt: {{ $theme['colors']['accent_alt'] }};
            --accent-soft: {{ $theme['colors']['accent_soft'] }};
            --success: {{ $theme['colors']['success'] }};
            --warning: {{ $theme['colors']['warning'] }};
            --font-heading: {!! $theme['font_heading'] !!};
            --font-body: {!! $theme['font_body'] !!};
            --font-mono: {!! $theme['font_mono'] !!};
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.2), transparent 16%),
                linear-gradient(135deg, var(--bg), var(--bg-alt));
            color: var(--ink);
            font-family: var(--font-body);
        }

        body.debug-open {
            overflow: hidden;
        }

        .shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 272px minmax(0, 1fr);
        }

        .sidebar {
            background:
                linear-gradient(180deg, rgba(255,255,255,0.18), rgba(255,255,255,0.03)),
                var(--panel-alt);
            border-right: 1px solid var(--line);
            padding: 18px 14px 20px;
            display: grid;
            grid-template-rows: auto 1fr auto;
            gap: 18px;
        }

        .brand {
            padding: 16px 16px 16px 18px;
            border: 1px solid var(--line);
            border-left: 5px solid var(--accent);
            background: rgba(255,255,255,0.34);
            border-radius: 6px;
            display: grid;
            gap: 6px;
        }

        .brand-kicker,
        .eyebrow,
        .field-label,
        .rail-label,
        .metric-label,
        .table-key {
            font-size: 11px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .brand-title {
            font-family: var(--font-heading);
            font-size: 30px;
            line-height: 1;
        }

        .brand-copy,
        .body-copy,
        .field-note,
        .muted-note,
        .table-note,
        .meta-copy {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.45;
        }

        .menu-stack {
            display: grid;
            gap: 8px;
            align-content: start;
        }

        .menu-card {
            border: 1px solid var(--line);
            border-radius: 6px;
            background: rgba(255,255,255,0.18);
            padding: 4px;
        }

        .menu-link,
        .menu-child {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: inherit;
            border-radius: 4px;
        }

        .menu-link { padding: 11px 12px; }
        .menu-child { padding: 9px 12px 9px 44px; }

        .menu-link:hover,
        .menu-child:hover {
            background: rgba(255,255,255,0.28);
        }

        .menu-link.active,
        .menu-child.active {
            background: linear-gradient(90deg, var(--accent-soft), rgba(255,255,255,0.72));
            box-shadow: inset 3px 0 0 var(--accent);
        }

        .icon-pill {
            width: 30px;
            height: 30px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(31, 42, 34, 0.08);
            background: rgba(31, 42, 34, 0.05);
            color: var(--accent-alt);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            flex: 0 0 auto;
        }

        .menu-meta {
            display: grid;
            gap: 2px;
            min-width: 0;
        }

        .menu-title {
            font-size: 15px;
            font-weight: 700;
        }

        .menu-caption {
            color: var(--muted);
            font-size: 12px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .sidebar-footer {
            border-top: 1px solid rgba(31,42,34,0.08);
            padding-top: 12px;
            display: grid;
            gap: 10px;
        }

        .workspace {
            padding: 24px 26px 28px;
            display: grid;
            gap: 18px;
            align-content: start;
            background-image:
                linear-gradient(rgba(255,255,255,0.09) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.09) 1px, transparent 1px);
            background-size: 42px 42px;
        }

        .topbar {
            display: grid;
            gap: 14px;
            padding-bottom: 14px;
            border-bottom: 1px solid rgba(31,42,34,0.1);
        }

        .headline {
            display: grid;
            gap: 6px;
        }

        .headline h1,
        .screen-title,
        .rail-title {
            margin: 0;
            font-family: var(--font-heading);
        }

        .headline h1 {
            font-size: clamp(30px, 4.8vw, 48px);
            line-height: 0.94;
        }

        .headline p {
            margin: 0;
            max-width: 64ch;
            color: var(--muted);
        }

        .utility-stack {
            display: grid;
            gap: 12px;
            justify-items: stretch;
        }

        .utility-actions,
        .toolbar,
        .action-cluster {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .context-forms {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: flex-start;
            align-items: flex-start;
        }

        .context-form,
        .rail-card,
        .metric-card,
        .surface-card,
        .table-card {
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.72);
            border-radius: 6px;
        }

        .context-form {
            padding: 10px 12px;
            display: flex;
            gap: 8px;
            align-items: end;
        }

        .sidebar-utility {
            width: 100%;
            justify-content: center;
        }

        .sidebar-context {
            border: 1px solid var(--line);
            border-radius: 6px;
            background: rgba(255,255,255,0.32);
            padding: 12px;
            display: grid;
            gap: 10px;
        }

        .sidebar-context-head {
            display: grid;
            gap: 4px;
        }

        .sidebar-context-copy {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.45;
        }

        .sidebar-context-list {
            display: grid;
            gap: 8px;
        }

        .sidebar-context-item {
            display: grid;
            gap: 3px;
            padding: 8px 10px;
            border: 1px solid rgba(31,42,34,0.08);
            border-radius: 4px;
            background: rgba(255,255,255,0.44);
        }

        .sidebar-context-value {
            font-size: 13px;
            font-weight: 700;
            line-height: 1.3;
            word-break: break-word;
        }

        .sidebar-context-form {
            display: grid;
            gap: 6px;
        }

        .sidebar-context-form .field {
            min-width: 0;
        }

        .sidebar-context-form .button {
            width: 100%;
        }

        .sidebar-auth {
            display: grid;
            gap: 8px;
        }

        .sidebar-auth form,
        .sidebar-auth a {
            width: 100%;
        }

        .field {
            display: grid;
            gap: 4px;
            min-width: 180px;
        }

        .field-input,
        .field-select,
        .upload-form input[type="text"],
        .upload-form input[type="file"] {
            width: 100%;
            min-height: 40px;
            border: 1px solid var(--line);
            border-radius: 4px;
            background: rgba(255,255,255,0.84);
            color: var(--ink);
            font: inherit;
            padding: 9px 10px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 40px;
            padding: 10px 14px;
            border-radius: 4px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.58);
            color: inherit;
            text-decoration: none;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
        }

        .button-primary {
            border-color: var(--accent);
            background: var(--accent);
            color: #fff;
        }

        .button-secondary {
            background: rgba(255,255,255,0.58);
        }

        .button-ghost {
            background: rgba(255,255,255,0.38);
        }

        .theme-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 36px;
            padding: 8px 12px;
            border: 1px solid var(--line);
            border-radius: 4px;
            background: rgba(255,255,255,0.56);
            text-decoration: none;
            color: inherit;
            font-size: 13px;
        }

        .theme-chip.active {
            background: var(--ink);
            border-color: var(--ink);
            color: #fff;
        }

        .overview-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .metric-card {
            padding: 16px 18px;
            display: grid;
            gap: 6px;
        }

        .metric-value {
            font-family: var(--font-heading);
            font-size: 24px;
            line-height: 1;
        }

        .content-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 18px;
            align-items: start;
        }

        .surface-card,
        .table-card,
        .rail-card {
            padding: 18px;
        }

        .screen-header {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: flex-start;
            padding-bottom: 14px;
            border-bottom: 1px solid rgba(31,42,34,0.08);
            margin-bottom: 18px;
        }

        .screen-title {
            font-size: 34px;
            line-height: 0.98;
        }

        .screen-subtitle {
            margin: 8px 0 0;
            color: var(--muted);
            max-width: 62ch;
        }

        .screen-body > *:first-child {
            margin-top: 0;
        }

        .screen-body > *:last-child {
            margin-bottom: 0;
        }

        .rail-list,
        .data-list {
            display: grid;
            gap: 10px;
            margin-top: 14px;
        }

        .rail-item,
        .data-item {
            border: 1px solid rgba(31,42,34,0.08);
            border-radius: 4px;
            background: rgba(255,255,255,0.42);
            padding: 10px 12px;
        }

        .module-screen {
            display: grid;
            gap: 16px;
        }

        .module-screen.compact {
            gap: 14px;
        }

        .surface-note {
            border: 1px solid rgba(31,42,34,0.08);
            border-left: 4px solid var(--accent);
            background: rgba(255,255,255,0.54);
            border-radius: 4px;
            padding: 12px 14px;
        }

        .surface-note.success {
            border-left-color: var(--success);
            background: rgba(232, 247, 236, 0.86);
        }

        .surface-note.error {
            border-left-color: var(--warning);
            background: rgba(255, 244, 229, 0.82);
        }

        .flash-stack {
            display: grid;
            gap: 10px;
        }

        .entity-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.56);
        }

        .entity-table th,
        .entity-table td {
            padding: 12px 14px;
            border-bottom: 1px solid rgba(31,42,34,0.08);
            text-align: left;
            vertical-align: top;
            font-size: 14px;
        }

        .entity-table th {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
            background: rgba(255,255,255,0.42);
        }

        .table-editor-row td {
            padding: 0;
            background: rgba(255,255,255,0.38);
        }

        .editor-panel {
            padding: 18px;
            display: grid;
            gap: 14px;
            border-top: 1px solid rgba(31,42,34,0.08);
            background: rgba(255,255,255,0.68);
        }

        .entity-title {
            font-weight: 700;
        }

        .entity-id {
            margin-top: 4px;
            color: var(--muted);
            font-size: 12px;
        }

        .pill,
        .tag {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 4px 10px;
            background: rgba(31,42,34,0.08);
            color: var(--ink);
            font-size: 11px;
            font-weight: 700;
        }

        .pill {
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .tag {
            background: var(--accent-soft);
            color: var(--accent-alt);
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .stack,
        .data-stack,
        .upload-form,
        .workflow-stack {
            display: grid;
            gap: 8px;
        }

        .mini-metrics {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .workflow-card {
            border: 1px solid var(--line);
            border-radius: 6px;
            background: rgba(255,255,255,0.62);
            padding: 14px 16px;
        }

        .workflow-header,
        .row-between {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
        }

        .workflow-history {
            border-top: 1px solid rgba(31,42,34,0.08);
            margin-top: 12px;
            padding-top: 12px;
            display: grid;
            gap: 8px;
        }

        .workflow-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            color: var(--muted);
            font-size: 13px;
        }

        .debug-modal[hidden] {
            display: none;
        }

        .debug-modal {
            position: fixed;
            inset: 0;
            z-index: 40;
        }

        .debug-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(10, 14, 19, 0.58);
            backdrop-filter: blur(3px);
        }

        .debug-dialog {
            position: relative;
            z-index: 41;
            width: min(980px, calc(100vw - 32px));
            max-height: calc(100vh - 48px);
            margin: 24px auto;
            background: #18212b;
            color: #ebf0f2;
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 8px;
            display: grid;
            grid-template-rows: auto 1fr auto;
            overflow: hidden;
            box-shadow: 0 24px 70px rgba(0,0,0,0.36);
        }

        .debug-header,
        .debug-footer {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
        }

        .debug-footer {
            border-top: 1px solid rgba(255,255,255,0.08);
            border-bottom: 0;
        }

        .debug-title {
            margin: 0;
            font-size: 18px;
            font-family: var(--font-heading);
        }

        .debug-copy {
            color: rgba(235,240,242,0.72);
            font-size: 13px;
        }

        .debug-tools {
            display: grid;
            gap: 12px;
            padding: 0 18px 18px;
        }

        .debug-tools .table-key {
            color: rgba(235,240,242,0.62);
        }

        .debug-theme-switcher {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        pre {
            margin: 0;
            padding: 18px;
            overflow: auto;
            background: transparent;
            color: inherit;
            font-family: var(--font-mono);
            font-size: 12px;
            line-height: 1.55;
        }

        @media (max-width: 1180px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 1080px) {
            .shell {
                grid-template-columns: 1fr;
            }

            .sidebar {
                border-right: 0;
                border-bottom: 1px solid var(--line);
                grid-template-rows: auto auto auto;
            }

            .sidebar-auth {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            }
        }

        @media (max-width: 960px) {
            .overview-grid,
            .mini-metrics {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .screen-header {
                flex-direction: column;
            }

            .context-forms {
                justify-content: stretch;
            }

            .context-form {
                width: 100%;
            }
        }

        @media (max-width: 720px) {
            .workspace {
                padding: 18px 16px 24px;
            }

            .overview-grid,
            .mini-metrics {
                grid-template-columns: 1fr;
            }

            .field {
                min-width: 0;
            }

            .debug-dialog {
                width: calc(100vw - 16px);
                margin: 8px auto;
                max-height: calc(100vh - 16px);
            }
        }
    </style>
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-title">{{ __('core.shell.brand_title') }}</div>
        </div>

        <nav class="menu-stack" aria-label="{{ __('core.shell.menu_registry') }}">
            @foreach ($menus as $menu)
                <section class="menu-card">
                    <a class="menu-link {{ $selectedMenuId === $menu['id'] ? 'active' : '' }}" href="{{ $menu['shell_url'] }}">
                        <span class="icon-pill">{{ strtoupper(substr((string) ($menu['icon'] ?? $menu['owner']), 0, 2)) }}</span>
                        <span class="menu-meta">
                            <span class="menu-title">{{ $menu['label'] }}</span>
                        </span>
                    </a>

                    @foreach ($menu['children'] as $child)
                        <a class="menu-child {{ $selectedMenuId === $child['id'] ? 'active' : '' }}" href="{{ $child['shell_url'] }}">
                            {{ $child['label'] }}
                        </a>
                    @endforeach
                </section>
            @endforeach
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-auth">
                @if ($shellArea === 'app' && $adminAreaUrl !== null)
                    <a class="button button-secondary" href="{{ $adminAreaUrl }}">Administration</a>
                @elseif ($shellArea === 'admin' && $appAreaUrl !== null)
                    <a class="button button-secondary" href="{{ $appAreaUrl }}">Back to app</a>
                @endif
            </div>

            <button type="button" class="button button-ghost sidebar-utility" data-debug-open>
                {{ __('core.shell.debug_button') }}
            </button>

            <div class="sidebar-context">
                <div class="sidebar-context-head">
                    <div class="rail-label">{{ __('core.shell.context_help') }}</div>
                    <div class="sidebar-context-copy">
                        {{ __('core.shell.context_help') }}
                        @if ($tenancyShellUrl !== null)
                            <a href="{{ $tenancyShellUrl }}">{{ __('core.shell.context_manage_link') }}</a>
                        @endif
                    </div>
                </div>

                <div class="sidebar-context-list">
                    <div class="sidebar-context-item">
                        <div class="rail-label">{{ __('core.shell.principal') }}</div>
                        <div class="sidebar-context-value">{{ $sessionPrincipalId ?? $principalId }}</div>
                    </div>

                    <form class="sidebar-context-form" method="GET" action="{{ route($currentShellRoute) }}">
                        <input type="hidden" name="principal_id" value="{{ $principalId }}">
                        <input type="hidden" name="locale" value="{{ $locale }}">
                        <input type="hidden" name="theme" value="{{ $themeKey }}">
                        @if ($selectedMenuId !== null)
                            <input type="hidden" name="menu" value="{{ $selectedMenuId }}">
                        @endif
                        <div class="sidebar-context-item">
                            <label class="field-label" for="organization_id">{{ __('core.shell.organization_selector') }}</label>
                            <div class="sidebar-context-value">{{ $selectedOrganization['name'] ?? 'n/a' }}</div>
                            <div class="field">
                                <select class="field-select" id="organization_id" name="organization_id" onchange="this.form.submit()">
                                    @foreach ($organizations as $organization)
                                        <option value="{{ $organization['id'] }}" @selected($organizationId === $organization['id'])>
                                            {{ $organization['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </form>

                    <form class="sidebar-context-form" method="GET" action="{{ route($currentShellRoute) }}">
                        <input type="hidden" name="principal_id" value="{{ $principalId }}">
                        <input type="hidden" name="locale" value="{{ $locale }}">
                        <input type="hidden" name="theme" value="{{ $themeKey }}">
                        @if ($selectedMenuId !== null)
                            <input type="hidden" name="menu" value="{{ $selectedMenuId }}">
                        @endif
                        @if ($organizationId !== null)
                            <input type="hidden" name="organization_id" value="{{ $organizationId }}">
                        @endif
                        <div class="sidebar-context-item">
                            <label class="field-label" for="scope_id">{{ __('core.shell.scope_selector') }}</label>
                            <div class="sidebar-context-value">{{ $selectedScope['name'] ?? __('core.shell.all_scopes') }}</div>
                            <div class="field">
                                <select class="field-select" id="scope_id" name="scope_id" onchange="this.form.submit()">
                                    <option value="">{{ __('core.shell.all_scopes') }}</option>
                                    @foreach ($scopes as $scope)
                                        <option value="{{ $scope['id'] }}" @selected($scopeId === $scope['id'])>
                                            {{ $scope['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="sidebar-auth">
                @if ($sessionPrincipalId !== null && \Illuminate\Support\Facades\Route::has('plugin.identity-local.auth.logout'))
                    <form method="POST" action="{{ route('plugin.identity-local.auth.logout') }}">
                        @csrf
                        <button type="submit" class="button button-secondary" style="width: 100%;">Sign out</button>
                    </form>
                @elseif (\Illuminate\Support\Facades\Route::has('plugin.identity-local.auth.login'))
                    <a class="button button-secondary" href="{{ route('plugin.identity-local.auth.login') }}">Sign in</a>
                @endif
            </div>
        </div>
    </aside>

    <main class="workspace">
        <header class="topbar">
            <div class="headline">
                <h1>{{ $shellError['title'] ?? $screen?->title ?? $selectedMenu['label'] ?? __('core.shell.title') }}</h1>
                <p>{{ $shellError['subtitle'] ?? $screen?->subtitle ?? __('core.shell.workspace_copy') }}</p>
            </div>
        </header>

        @if (session('status') || session('error'))
            <div class="flash-stack">
                @if (session('status'))
                    <div class="surface-note success">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="surface-note error">{{ session('error') }}</div>
                @endif
            </div>
        @endif

        <section class="content-grid">
            <article class="surface-card">
                @if ($shellError !== null)
                    <div class="screen-header">
                        <div>
                            <h2 class="screen-title">{{ $shellError['title'] }}</h2>
                            <p class="screen-subtitle">{{ $shellError['subtitle'] }}</p>
                        </div>
                    </div>

                    <div class="surface-note error">
                        {{ $shellError['message'] }}
                    </div>
                @elseif ($selectedMenu !== null)
                    <div class="screen-header">
                        <div>
                            <h2 class="screen-title">{{ $screen?->title ?? $selectedMenu['label'] }}</h2>
                            <p class="screen-subtitle">{{ $screen?->subtitle ?? __('core.shell.workspace_copy') }}</p>
                        </div>
                        @if ($screen !== null && $screen->toolbarActions !== [])
                            <div class="toolbar">
                                @foreach ($screen->toolbarActions as $action)
                                    <a class="button {{ $action->variant === 'primary' ? 'button-primary' : 'button-secondary' }}" href="{{ $action->url }}" target="{{ $action->target }}">
                                        {{ $action->label }}
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if ($screen !== null)
                        <div class="screen-body">
                            {!! $screen->content !!}
                        </div>
                    @else
                        <div class="overview-grid">
                            <div class="metric-card">
                                <div class="metric-label">ID</div>
                                <div class="meta-copy">{{ $selectedMenu['id'] }}</div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-label">Owner</div>
                                <div class="meta-copy">{{ $selectedMenu['owner'] }}</div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-label">Route</div>
                                <div class="meta-copy">{{ $selectedMenu['route'] ?? 'n/a' }}</div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-label">Permission</div>
                                <div class="meta-copy">{{ $selectedMenu['permission'] ?? 'n/a' }}</div>
                            </div>
                        </div>
                    @endif

                @else
                    <div class="muted-note">{{ __('core.shell.no_selection') }}</div>
                @endif
            </article>
        </section>
    </main>
</div>

<div class="debug-modal" data-debug-modal hidden>
    <div class="debug-backdrop" data-debug-close></div>
    <section class="debug-dialog" role="dialog" aria-modal="true" aria-labelledby="debug-title">
        <header class="debug-header">
            <div>
                <h2 class="debug-title" id="debug-title">{{ __('core.shell.debug_title') }}</h2>
                <div class="debug-copy">{{ __('core.shell.debug_copy') }}</div>
            </div>
            <button type="button" class="button button-secondary" data-debug-close>{{ __('core.shell.close_debug') }}</button>
        </header>
        <div class="debug-tools">
            <div>
                <div class="table-key">{{ __('core.shell.theme') }}</div>
                <div class="debug-theme-switcher">
                    @foreach ($themeOptions as $option)
                        <a href="{{ $option['url'] }}" class="theme-chip {{ $option['active'] ? 'active' : '' }}">
                            {{ $option['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
        <pre>{{ $debugPayloadJson }}</pre>
        <footer class="debug-footer">
            <div class="debug-copy">{{ __('core.shell.debug_footer') }}</div>
            <a class="button button-secondary" href="{{ $menuApiUrl }}" target="_blank" rel="noreferrer">
                {{ __('core.shell.menu_registry') }}
            </a>
        </footer>
    </section>
</div>

<script>
    (() => {
        const parseHtml = (html) => new DOMParser().parseFromString(html, 'text/html');

        const swapShell = (htmlDocument) => {
            const nextShell = htmlDocument.querySelector('.shell');
            const currentShell = document.querySelector('.shell');

            if (!nextShell || !currentShell) {
                if (htmlDocument.defaultView?.location?.href) {
                    window.location.assign(htmlDocument.defaultView.location.href);
                }

                return false;
            }

            const nextDebugModal = htmlDocument.querySelector('[data-debug-modal]');
            const currentDebugModal = document.querySelector('[data-debug-modal]');

            currentShell.replaceWith(nextShell);

            if (currentDebugModal && nextDebugModal) {
                currentDebugModal.replaceWith(nextDebugModal);
            } else if (currentDebugModal && !nextDebugModal) {
                currentDebugModal.remove();
            } else if (!currentDebugModal && nextDebugModal) {
                document.body.appendChild(nextDebugModal);
            }

            document.title = htmlDocument.title || document.title;

            return true;
        };

        const toggleEditor = (targetId) => {
            const target = document.getElementById(targetId);

            if (!target) {
                return;
            }

            const nextHidden = !target.hidden;
            target.hidden = nextHidden;

            document.querySelectorAll(`[data-editor-toggle="${targetId}"]`).forEach((button) => {
                button.setAttribute('aria-expanded', nextHidden ? 'false' : 'true');
            });
        };

        const transformDetailsEditors = () => {
            document.querySelectorAll('table.entity-table details').forEach((details, index) => {
                if (details.dataset.editorTransformed === 'true') {
                    return;
                }

                const summary = details.querySelector('summary');
                const row = details.closest('tr');
                const cell = details.closest('td');

                if (!summary || !row || !cell || !row.parentElement) {
                    return;
                }

                const targetId = details.id || `table-editor-${index}-${Math.random().toString(36).slice(2, 8)}`;
                const open = details.hasAttribute('open');
                const button = document.createElement('button');

                button.type = 'button';
                button.className = summary.className || 'button button-ghost';
                button.textContent = summary.textContent.trim();
                button.setAttribute('data-editor-toggle', targetId);
                button.setAttribute('aria-expanded', open ? 'true' : 'false');

                const editorRow = document.createElement('tr');
                editorRow.id = targetId;
                editorRow.className = 'table-editor-row';
                editorRow.hidden = !open;

                const editorCell = document.createElement('td');
                editorCell.colSpan = row.children.length;

                const editorPanel = document.createElement('div');
                editorPanel.className = 'editor-panel';

                const editorHeader = document.createElement('div');
                editorHeader.className = 'row-between';
                editorHeader.innerHTML = `
                    <div>
                        <div class="entity-title">${button.textContent}</div>
                        <div class="table-note">Edit this record without compressing the table layout.</div>
                    </div>
                `;

                const closeButton = document.createElement('button');
                closeButton.type = 'button';
                closeButton.className = 'button button-ghost';
                closeButton.textContent = 'Close';
                closeButton.setAttribute('data-editor-toggle', targetId);
                closeButton.setAttribute('aria-expanded', open ? 'true' : 'false');
                editorHeader.appendChild(closeButton);

                const editorBody = document.createElement('div');
                editorBody.className = 'stack';

                Array.from(details.children).forEach((child) => {
                    if (child.tagName === 'SUMMARY') {
                        return;
                    }

                    editorBody.appendChild(child);
                });

                editorBody.querySelectorAll('form[method="POST"]').forEach((form) => {
                    if (!form.querySelector('input[name="editor_target"]')) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'editor_target';
                        input.value = targetId;
                        form.appendChild(input);
                    }
                });

                editorPanel.appendChild(editorHeader);
                editorPanel.appendChild(editorBody);
                editorCell.appendChild(editorPanel);
                editorRow.appendChild(editorCell);

                details.dataset.editorTransformed = 'true';
                details.replaceWith(button);
                row.parentElement.insertBefore(editorRow, row.nextSibling);
            });
        };

        const setupDebugModal = () => {
            const modal = document.querySelector('[data-debug-modal]');
            const openButtons = document.querySelectorAll('[data-debug-open]');
            const closeButtons = document.querySelectorAll('[data-debug-close]');

            if (!modal || openButtons.length === 0) {
                return;
            }

            const openModal = () => {
                modal.hidden = false;
                document.body.classList.add('debug-open');
            };

            const closeModal = () => {
                modal.hidden = true;
                document.body.classList.remove('debug-open');
            };

            openButtons.forEach((button) => button.addEventListener('click', openModal));
            closeButtons.forEach((button) => button.addEventListener('click', closeModal));

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !modal.hidden) {
                    closeModal();
                }
            }, { once: true });
        };

        const setupEditorToggles = () => {
            document.querySelectorAll('[data-editor-toggle]').forEach((toggle) => {
                toggle.addEventListener('click', () => {
                    const targetId = toggle.getAttribute('data-editor-toggle');

                    if (!targetId) {
                        return;
                    }

                    toggleEditor(targetId);
                });
            });
        };

        const setupAjaxForms = () => {
            document.querySelectorAll('.workspace form[method="POST"]').forEach((form) => {
                if (form.dataset.ajaxBound === 'true' || form.dataset.syncForm === 'true') {
                    return;
                }

                form.dataset.ajaxBound = 'true';

                form.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    const submitButtons = form.querySelectorAll('button[type="submit"]');
                    submitButtons.forEach((button) => {
                        button.disabled = true;
                    });

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            body: new FormData(form),
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'text/html,application/xhtml+xml',
                            },
                            credentials: 'same-origin',
                        });

                        const html = await response.text();
                        const htmlDocument = parseHtml(html);

                        if (!swapShell(htmlDocument)) {
                            window.location.assign(response.url || form.action);
                            return;
                        }

                        initializeShellUi();
                    } catch (error) {
                        window.location.assign(form.action);
                    }
                });
            });
        };

        window.initializeShellUi = () => {
            transformDetailsEditors();
            setupDebugModal();
            setupEditorToggles();
            setupAjaxForms();
        };

        window.initializeShellUi();
    })();
</script>
</body>
</html>
