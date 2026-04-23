@php
    $memberships = $query['membership_ids'] ?? [];
    if (! is_array($memberships)) {
        $memberships = [];
    }

    $issuedToken = is_array($issued_token ?? null) ? $issued_token : null;
    $issueEditorHidden = ($tokens ?? []) !== [] && $issuedToken === null;
@endphp

<section class="module-screen compact">
    <div class="overview-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
        <div class="metric-card"><div class="metric-label">{{ __('core.api-tokens.metric.tokens') }}</div><div class="metric-value">{{ $metrics['tokens'] ?? 0 }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('core.api-tokens.metric.active') }}</div><div class="metric-value">{{ $metrics['active'] ?? 0 }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('core.api-tokens.metric.expired') }}</div><div class="metric-value">{{ $metrics['expired'] ?? 0 }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('core.api-tokens.metric.revoked') }}</div><div class="metric-value">{{ $metrics['revoked'] ?? 0 }}</div></div>
    </div>

    <div class="surface-note">{{ __('core.api-tokens.summary') }}</div>

    @if (is_array($issuedToken))
        <div class="surface-card" style="padding:16px;">
            <div class="eyebrow">{{ __('core.api-tokens.secret.eyebrow') }}</div>
            <h2 class="screen-title" style="font-size:24px;">{{ __('core.api-tokens.secret.title') }}</h2>
            <p class="screen-subtitle">{{ __('core.api-tokens.secret.subtitle') }}</p>
            <div class="field" style="margin-top:12px;">
                <label class="field-label">{{ __('core.api-tokens.secret.label') }}</label>
                <input class="field-input" readonly value="{{ $issuedToken['token'] ?? '' }}">
            </div>
            <div class="table-note" style="margin-top:8px;">
                {{ __('core.api-tokens.secret.prefix_owner', ['prefix' => $issuedToken['token_prefix'] ?? __('n/a'), 'owner' => $issuedToken['owner_principal_id'] ?? __('n/a')]) }}
            </div>
        </div>
    @endif

    <div class="surface-card" id="api-token-issue-editor" @if ($issueEditorHidden) hidden @endif style="padding:16px;">
        <div class="screen-header" style="margin-bottom:10px;">
            <div>
                <h2 class="screen-title" style="font-size:24px;">{{ __('core.api-tokens.issue.title') }}</h2>
                <p class="screen-subtitle">{{ __('core.api-tokens.issue.subtitle') }}</p>
            </div>
        </div>

        <form class="upload-form" method="POST" action="{{ $issue_token_route }}">
            @csrf
            <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
            <input type="hidden" name="locale" value="{{ $query['locale'] ?? 'en' }}">
            <input type="hidden" name="theme" value="{{ $query['theme'] ?? '' }}">
            <input type="hidden" name="menu" value="core.api-tokens">
            @foreach ($memberships as $membershipId)
                <input type="hidden" name="membership_ids[]" value="{{ $membershipId }}">
            @endforeach

            <div class="overview-grid" style="grid-template-columns:repeat(3, minmax(0, 1fr));">
                <div class="field">
                    <label class="field-label">{{ __('core.api-tokens.form.label') }}</label>
                    <input class="field-input" name="label" required value="{{ old('label', __('core.api-tokens.form.default_label')) }}" @disabled(! $can_manage_api_tokens)>
                </div>
                <div class="field">
                    <label class="field-label">{{ __('core.api-tokens.form.owner_user') }}</label>
                    <select class="field-select" name="owner_principal_id" required @disabled(! $can_manage_api_tokens)>
                        <option value="">{{ __('core.api-tokens.form.select_user') }}</option>
                        @foreach ($principal_options as $principal)
                            <option value="{{ $principal['id'] }}" @selected(old('owner_principal_id', $selected_owner_principal_id ?? null) === $principal['id'])>{{ $principal['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">{{ __('core.api-tokens.form.expires_in_days') }}</label>
                    <input class="field-input" type="number" name="expires_in_days" min="1" max="730" value="{{ old('expires_in_days', '90') }}" @disabled(! $can_manage_api_tokens)>
                </div>
                <div class="field">
                    <label class="field-label">{{ __('core.api-tokens.form.organization_boundary') }}</label>
                    <select class="field-select" name="organization_id" @disabled(! $can_manage_api_tokens)>
                        <option value="">{{ __('core.api-tokens.form.platform_wide') }}</option>
                        @foreach ($organization_options as $organization)
                            <option value="{{ $organization['id'] }}" @selected(old('organization_id', $organization_id ?? null) === $organization['id'])>{{ $organization['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">{{ __('core.api-tokens.form.scope_boundary') }}</label>
                    <select class="field-select" name="scope_id" @disabled(! $can_manage_api_tokens)>
                        <option value="">{{ __('core.api-tokens.form.all_scopes') }}</option>
                        @foreach ($scope_options as $scope)
                            <option value="{{ $scope['id'] }}" @selected(old('scope_id', $scope_id ?? null) === $scope['id'])>{{ $scope['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">{{ __('core.api-tokens.form.abilities') }}</label>
                    <input class="field-input" name="abilities" value="{{ old('abilities') }}" placeholder="{{ __('core.api-tokens.form.abilities_placeholder') }}" @disabled(! $can_manage_api_tokens)>
                </div>
            </div>

            <div class="table-note" style="margin-top:8px;">{{ __('core.api-tokens.form.abilities_copy') }}</div>

            @if ($can_manage_api_tokens)
                <div class="action-cluster" style="margin-top:12px;">
                    <button class="button button-primary" type="submit">{{ __('core.api-tokens.form.issue_button') }}</button>
                </div>
            @endif
        </form>
    </div>

    @if (is_array($selected_token))
        <div class="surface-card" style="padding:16px;">
            <div class="row-between" style="align-items:flex-start;">
                <div>
                    <div class="eyebrow">{{ __('core.api-tokens.detail.eyebrow') }}</div>
                    <h2 class="screen-title" style="font-size:26px;">{{ $selected_token['label'] }}</h2>
                    <div class="table-note">{{ $selected_token['id'] }}</div>
                    <div class="table-note">{{ __('core.api-tokens.detail.owner', ['owner' => $selected_token['principal_label']]) }}</div>
                </div>
                <span class="pill">{{ $selected_token['status'] }}</span>
            </div>
            <div class="overview-grid" style="grid-template-columns:repeat(3, minmax(0, 1fr)); margin-top:12px;">
                <div class="metric-card"><div class="metric-label">{{ __('core.api-tokens.detail.prefix') }}</div><div class="meta-copy">{{ $selected_token['token_prefix'] }}</div></div>
                <div class="metric-card"><div class="metric-label">{{ __('core.api-tokens.detail.boundary') }}</div><div class="meta-copy">{{ $selected_token['organization_label'] }} · {{ $selected_token['scope_label'] }}</div></div>
                <div class="metric-card"><div class="metric-label">{{ __('core.api-tokens.detail.last_used') }}</div><div class="meta-copy">{{ $selected_token['last_used_at'] ?? __('n/a') }}</div></div>
            </div>
        </div>
    @endif

    <div class="table-card">
        <div class="screen-header">
            <div>
                <h2 class="screen-title" style="font-size:24px;">{{ __('core.api-tokens.list.title') }}</h2>
                <p class="screen-subtitle">{{ __('core.api-tokens.list.subtitle') }}</p>
            </div>
        </div>

        <table class="entity-table">
            <thead>
                <tr>
                    <th>{{ __('core.api-tokens.table.token') }}</th>
                    <th>{{ __('core.api-tokens.table.owner') }}</th>
                    <th>{{ __('core.api-tokens.table.boundary') }}</th>
                    <th>{{ __('core.api-tokens.table.status') }}</th>
                    <th>{{ __('core.api-tokens.table.last_used') }}</th>
                    <th>{{ __('core.api-tokens.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tokens as $token)
                    <tr>
                        <td>
                            <div class="entity-title">{{ $token['label'] }}</div>
                            <div class="entity-id">{{ $token['token_prefix'] }}</div>
                            <div class="table-note">{{ $token['id'] }}</div>
                        </td>
                        <td>
                            <div>{{ $token['principal_label'] }}</div>
                            <div class="table-note">{{ $token['principal_id'] }}</div>
                        </td>
                        <td>
                            <div>{{ $token['organization_label'] }}</div>
                            <div class="table-note">{{ $token['scope_label'] }}</div>
                        </td>
                        <td>
                            <span class="pill">{{ $token['status'] }}</span>
                            <div class="table-note">{{ __('core.api-tokens.table.expires', ['value' => $token['expires_at'] ?? __('n/a')]) }}</div>
                        </td>
                        <td>
                            <div class="table-note">{{ $token['last_used_at'] ?? __('n/a') }}</div>
                        </td>
                        <td>
                            <div class="action-cluster">
                                <a class="button button-secondary" href="{{ $token['open_url'] }}">{{ __('core.actions.open') }}</a>
                                @if ($can_manage_api_tokens && ! ($token['is_revoked'] ?? false) && ! ($token['is_expired'] ?? false))
                                    <form method="POST" action="{{ $token['rotate_route'] }}">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                        <input type="hidden" name="owner_principal_id" value="{{ $selected_owner_principal_id ?? '' }}">
                                        <input type="hidden" name="organization_id" value="{{ $organization_id ?? '' }}">
                                        <input type="hidden" name="scope_id" value="{{ $scope_id ?? '' }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] ?? 'en' }}">
                                        <input type="hidden" name="theme" value="{{ $query['theme'] ?? '' }}">
                                        <input type="hidden" name="menu" value="core.api-tokens">
                                        @foreach ($memberships as $membershipId)
                                            <input type="hidden" name="membership_ids[]" value="{{ $membershipId }}">
                                        @endforeach
                                        <button class="button button-ghost" type="submit">{{ __('core.api-tokens.table.rotate') }}</button>
                                    </form>
                                @endif
                                @if ($can_manage_api_tokens && ! ($token['is_revoked'] ?? false))
                                    <form method="POST" action="{{ $token['revoke_route'] }}">
                                        @csrf
                                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                                        <input type="hidden" name="owner_principal_id" value="{{ $selected_owner_principal_id ?? '' }}">
                                        <input type="hidden" name="organization_id" value="{{ $organization_id ?? '' }}">
                                        <input type="hidden" name="scope_id" value="{{ $scope_id ?? '' }}">
                                        <input type="hidden" name="locale" value="{{ $query['locale'] ?? 'en' }}">
                                        <input type="hidden" name="theme" value="{{ $query['theme'] ?? '' }}">
                                        <input type="hidden" name="menu" value="core.api-tokens">
                                        @foreach ($memberships as $membershipId)
                                            <input type="hidden" name="membership_ids[]" value="{{ $membershipId }}">
                                        @endforeach
                                        <button class="button button-ghost" type="submit">{{ __('core.api-tokens.table.revoke') }}</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="table-note">{{ __('core.api-tokens.table.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
