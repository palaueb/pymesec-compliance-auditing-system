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
                linear-gradient(180deg, rgba(255,255,255,0.28), transparent 18%),
                linear-gradient(135deg, var(--bg), var(--bg-alt));
            color: var(--ink);
            font-family: var(--font-body);
        }

        .shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 280px 1fr;
        }

        .sidebar {
            padding: 20px 16px;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.22), rgba(255,255,255,0.03)),
                var(--panel-alt);
            border-right: 1px solid var(--line);
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .brand {
            display: grid;
            gap: 6px;
            padding: 16px 16px 16px 18px;
            border: 1px solid var(--line);
            border-left: 5px solid var(--accent);
            border-radius: 6px;
            background: rgba(255,255,255,0.34);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.3);
        }

        .brand-kicker {
            font-size: 11px;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .brand-title {
            font-family: var(--font-heading);
            font-size: 30px;
            line-height: 1;
        }

        .brand-copy {
            color: var(--muted);
            font-size: 14px;
        }

        .menu-stack {
            display: grid;
            gap: 8px;
        }

        .menu-card {
            background: rgba(255,255,255,0.2);
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 4px;
            box-shadow: none;
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

        .menu-link {
            padding: 11px 12px;
        }

        .menu-child {
            padding: 9px 12px 9px 44px;
            color: var(--muted);
        }

        .menu-link:hover,
        .menu-child:hover {
            background: rgba(255,255,255,0.32);
        }

        .menu-link.active,
        .menu-child.active {
            background: linear-gradient(90deg, var(--accent-soft), rgba(255,255,255,0.72));
            color: var(--ink);
            box-shadow: inset 3px 0 0 var(--accent);
        }

        .icon-pill {
            width: 30px;
            height: 30px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            background: rgba(31, 42, 34, 0.05);
            border: 1px solid rgba(31, 42, 34, 0.08);
            color: var(--accent-alt);
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

        .main {
            padding: 28px;
            display: grid;
            gap: 20px;
            background-image:
                linear-gradient(rgba(255,255,255,0.11) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.11) 1px, transparent 1px);
            background-size: 40px 40px;
        }

        .topbar {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 14px;
            align-items: flex-start;
            padding-bottom: 14px;
            border-bottom: 1px solid rgba(31, 42, 34, 0.1);
        }

        .headline {
            display: grid;
            gap: 6px;
        }

        .headline h1 {
            margin: 0;
            font-family: var(--font-heading);
            font-size: clamp(32px, 5vw, 52px);
            line-height: 0.95;
        }

        .headline p {
            margin: 0;
            color: var(--muted);
            max-width: 64ch;
        }

        .theme-switcher {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .topbar-stack {
            display: grid;
            gap: 12px;
            align-items: start;
        }

        .context-forms {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: flex-end;
        }

        .context-form {
            display: flex;
            align-items: end;
            gap: 8px;
            padding: 10px 12px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.58);
            border-radius: 4px;
        }

        .context-field {
            display: grid;
            gap: 4px;
            min-width: 180px;
        }

        .context-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
        }

        .context-select {
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.72);
            color: var(--ink);
            padding: 9px 10px;
            border-radius: 4px;
            min-height: 40px;
            font: inherit;
        }

        .context-note {
            color: var(--muted);
            font-size: 12px;
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: flex-end;
        }

        .theme-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.56);
            padding: 9px 12px;
            border-radius: 4px;
            color: inherit;
            text-decoration: none;
            font-size: 13px;
        }

        .theme-chip.active {
            background: var(--ink);
            color: white;
            border-color: var(--ink);
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .card,
        .detail-card {
            background: rgba(255,255,255,0.78);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.045);
        }

        .card-label {
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .card-value {
            margin-top: 6px;
            font-family: var(--font-heading);
            font-size: 28px;
        }

        .layout {
            display: grid;
            grid-template-columns: minmax(0, 1.7fr) minmax(300px, 0.72fr);
            gap: 18px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 18px;
        }

        .detail-cell {
            border: 1px solid var(--line);
            border-radius: 4px;
            padding: 12px 14px;
            background: rgba(255,255,255,0.36);
        }

        .detail-key {
            color: var(--muted);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .detail-value {
            margin-top: 5px;
            font-size: 14px;
            word-break: break-word;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            border-radius: 4px;
            padding: 11px 16px;
            font-weight: 700;
        }

        .button-primary {
            background: var(--accent);
            color: #fff;
        }

        .button-secondary {
            border: 1px solid var(--line);
            color: var(--ink);
            background: rgba(255,255,255,0.44);
        }

        .stack {
            display: grid;
            gap: 16px;
        }

        .screen-body > *:first-child { margin-top: 0; }
        .screen-body > *:last-child { margin-bottom: 0; }

        .list {
            margin: 0;
            padding-left: 18px;
            color: var(--muted);
        }

        pre {
            margin: 0;
            padding: 16px;
            background: #18212b;
            color: #ebf0f2;
            border-radius: 6px;
            overflow: auto;
            font-family: var(--font-mono);
            font-size: 12px;
            line-height: 1.5;
        }

        .empty-note {
            color: var(--muted);
            padding: 12px 0;
        }

        @media (max-width: 1080px) {
            .shell {
                grid-template-columns: 1fr;
            }

            .sidebar {
                border-right: 0;
                border-bottom: 1px solid var(--line);
            }

            .cards,
            .layout,
            .detail-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-kicker">PymeSec Core</div>
            <div class="brand-title">{{ __('core.app.name') }}</div>
            <div class="brand-copy">{{ __('core.shell.sidebar_copy') }}</div>
        </div>

        <nav class="menu-stack" aria-label="{{ __('core.shell.menu_registry') }}">
            @foreach ($menus as $menu)
                <section class="menu-card">
                    <a class="menu-link {{ $selectedMenuId === $menu['id'] ? 'active' : '' }}" href="{{ $menu['shell_url'] }}">
                        <span class="icon-pill">{{ strtoupper(substr((string) ($menu['icon'] ?? $menu['owner']), 0, 2)) }}</span>
                        <span class="menu-meta">
                            <span class="menu-title">{{ $menu['label'] }}</span>
                            <span class="menu-caption">{{ $menu['owner'] }}</span>
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
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="headline">
                <h1>{{ __('core.shell.title') }}</h1>
                <p>{{ __('core.shell.subtitle') }}</p>
            </div>

            <div class="topbar-stack">
                <div class="theme-switcher">
                    @foreach ($themeOptions as $option)
                        <a href="{{ $option['url'] }}" class="theme-chip {{ $option['active'] ? 'active' : '' }}">
                            {{ $option['label'] }}
                        </a>
                    @endforeach
                </div>

                <div class="context-forms">
                    <form class="context-form" method="GET" action="{{ route('core.shell.index') }}">
                        <input type="hidden" name="principal_id" value="{{ $principalId }}">
                        <input type="hidden" name="locale" value="{{ $locale }}">
                        <input type="hidden" name="theme" value="{{ $themeKey }}">
                        @if ($selectedMenuId !== null)
                            <input type="hidden" name="menu" value="{{ $selectedMenuId }}">
                        @endif
                        <div class="context-field">
                            <label class="context-label" for="organization_id">{{ __('core.shell.organization_selector') }}</label>
                            <select class="context-select" id="organization_id" name="organization_id">
                                @foreach ($organizations as $organization)
                                    <option value="{{ $organization['id'] }}" @selected($organizationId === $organization['id'])>
                                        {{ $organization['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <button class="button button-secondary" type="submit">{{ __('core.shell.apply_context') }}</button>
                    </form>

                    <form class="context-form" method="GET" action="{{ route('core.shell.index') }}">
                        <input type="hidden" name="principal_id" value="{{ $principalId }}">
                        <input type="hidden" name="locale" value="{{ $locale }}">
                        <input type="hidden" name="theme" value="{{ $themeKey }}">
                        @if ($selectedMenuId !== null)
                            <input type="hidden" name="menu" value="{{ $selectedMenuId }}">
                        @endif
                        @if ($organizationId !== null)
                            <input type="hidden" name="organization_id" value="{{ $organizationId }}">
                        @endif
                        <div class="context-field">
                            <label class="context-label" for="scope_id">{{ __('core.shell.scope_selector') }}</label>
                            <select class="context-select" id="scope_id" name="scope_id">
                                <option value="">{{ __('core.shell.all_scopes') }}</option>
                                @foreach ($scopes as $scope)
                                    <option value="{{ $scope['id'] }}" @selected($scopeId === $scope['id'])>
                                        {{ $scope['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <button class="button button-secondary" type="submit">{{ __('core.shell.apply_context') }}</button>
                    </form>
                </div>
            </div>
        </header>

        <section class="cards">
            <article class="card">
                <div class="card-label">{{ __('core.shell.principal') }}</div>
                <div class="card-value">{{ $principalId }}</div>
            </article>
            <article class="card">
                <div class="card-label">{{ __('core.shell.organization') }}</div>
                <div class="card-value">{{ $selectedOrganization['name'] ?? ($organizationId ?? 'n/a') }}</div>
                <div class="context-note">{{ $organizationId ?? 'n/a' }}</div>
            </article>
            <article class="card">
                <div class="card-label">{{ __('core.shell.scope') }}</div>
                <div class="card-value">{{ $selectedScope['name'] ?? __('core.shell.all_scopes') }}</div>
                <div class="context-note">{{ $scopeId ?? __('core.shell.organization_wide') }}</div>
            </article>
        </section>

        <section class="layout">
            <article class="detail-card">
                <div class="card-label">{{ __('core.shell.active_menu') }}</div>
                @if ($selectedMenu !== null)
                    <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;">
                        <div>
                            <h2 style="margin: 10px 0 0; font-family: var(--font-heading); font-size: 34px;">
                                {{ $screen?->title ?? $selectedMenu['label'] }}
                            </h2>
                            <p style="margin: 8px 0 0; color: var(--muted);">
                                {{ $screen?->subtitle ?? __('core.shell.preview') }}
                            </p>
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
                        <div class="screen-body" style="margin-top:18px;">
                            {!! $screen->content !!}
                        </div>
                    @else
                        <div class="detail-grid">
                            <div class="detail-cell">
                                <div class="detail-key">ID</div>
                                <div class="detail-value">{{ $selectedMenu['id'] }}</div>
                            </div>
                            <div class="detail-cell">
                                <div class="detail-key">Owner</div>
                                <div class="detail-value">{{ $selectedMenu['owner'] }}</div>
                            </div>
                            <div class="detail-cell">
                                <div class="detail-key">Route</div>
                                <div class="detail-value">{{ $selectedMenu['route'] ?? 'n/a' }}</div>
                            </div>
                            <div class="detail-cell">
                                <div class="detail-key">Permission</div>
                                <div class="detail-value">{{ $selectedMenu['permission'] ?? 'n/a' }}</div>
                            </div>
                        </div>
                    @endif

                    <div class="actions">
                        @if ($selectedMenu['url'] !== null)
                            <a class="button button-primary" href="{{ $selectedMenu['url'] }}" target="_blank" rel="noreferrer">
                                {{ __('core.shell.open_route') }}
                            </a>
                        @endif
                        <a class="button button-secondary" href="{{ $menuApiUrl }}" target="_blank" rel="noreferrer">
                            {{ __('core.shell.menu_registry') }}
                        </a>
                    </div>
                @else
                    <div class="empty-note">{{ __('core.shell.no_selection') }}</div>
                @endif
            </article>

            <section class="stack">
                <article class="detail-card">
                    <div class="card-label">{{ __('core.shell.theme') }}</div>
                    <h2 style="margin: 10px 0 0; font-family: var(--font-heading); font-size: 28px;">{{ $theme['label'] }}</h2>
                    <p style="margin: 10px 0 0; color: var(--muted);">{{ __('core.shell.theme_copy') }}</p>
                    <ul class="list">
                        <li>{{ __('core.shell.theme_rule_core') }}</li>
                        <li>{{ __('core.shell.theme_rule_plugins') }}</li>
                        <li>{{ __('core.shell.theme_rule_tokens') }}</li>
                    </ul>
                </article>

                <article class="detail-card">
                    <div class="card-label">{{ __('core.shell.debug_payload') }}</div>
                    <pre>{{ $visibleMenusJson }}</pre>
                </article>
            </section>
        </section>
    </main>
</div>
</body>
</html>
