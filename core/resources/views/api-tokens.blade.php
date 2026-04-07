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
        <div class="metric-card"><div class="metric-label">Tokens</div><div class="metric-value">{{ $metrics['tokens'] ?? 0 }}</div></div>
        <div class="metric-card"><div class="metric-label">Active</div><div class="metric-value">{{ $metrics['active'] ?? 0 }}</div></div>
        <div class="metric-card"><div class="metric-label">Expired</div><div class="metric-value">{{ $metrics['expired'] ?? 0 }}</div></div>
        <div class="metric-card"><div class="metric-label">Revoked</div><div class="metric-value">{{ $metrics['revoked'] ?? 0 }}</div></div>
    </div>

    <div class="surface-note">
        API tokens are bound to one person and can optionally be restricted to one organization or scope. Token secrets are only shown once after issuance.
    </div>

    @if (is_array($issuedToken))
        <div class="surface-card" style="padding:16px;">
            <div class="eyebrow">Token Secret</div>
            <h2 class="screen-title" style="font-size:24px;">Copy this token now</h2>
            <p class="screen-subtitle">This value is not stored in clear text and cannot be retrieved again after leaving this page.</p>
            <div class="field" style="margin-top:12px;">
                <label class="field-label">Bearer token</label>
                <input class="field-input" readonly value="{{ $issuedToken['token'] ?? '' }}">
            </div>
            <div class="table-note" style="margin-top:8px;">
                Prefix: {{ $issuedToken['token_prefix'] ?? 'n/a' }} · Owner: {{ $issuedToken['owner_principal_id'] ?? 'n/a' }}
            </div>
        </div>
    @endif

    <div class="surface-card" id="api-token-issue-editor" @if ($issueEditorHidden) hidden @endif style="padding:16px;">
        <div class="screen-header" style="margin-bottom:10px;">
            <div>
                <h2 class="screen-title" style="font-size:24px;">Issue API token</h2>
                <p class="screen-subtitle">Create a new token and bind it to a user identity.</p>
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
                    <label class="field-label">Label</label>
                    <input class="field-input" name="label" required value="{{ old('label', 'Integration token') }}" @disabled(! $can_manage_api_tokens)>
                </div>
                <div class="field">
                    <label class="field-label">Owner user</label>
                    <select class="field-select" name="owner_principal_id" required @disabled(! $can_manage_api_tokens)>
                        <option value="">Select a user</option>
                        @foreach ($principal_options as $principal)
                            <option value="{{ $principal['id'] }}" @selected(old('owner_principal_id', $selected_owner_principal_id ?? null) === $principal['id'])>{{ $principal['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">Expires in days</label>
                    <input class="field-input" type="number" name="expires_in_days" min="1" max="730" value="{{ old('expires_in_days', '90') }}" @disabled(! $can_manage_api_tokens)>
                </div>
                <div class="field">
                    <label class="field-label">Organization boundary</label>
                    <select class="field-select" name="organization_id" @disabled(! $can_manage_api_tokens)>
                        <option value="">Platform-wide</option>
                        @foreach ($organization_options as $organization)
                            <option value="{{ $organization['id'] }}" @selected(old('organization_id', $organization_id ?? null) === $organization['id'])>{{ $organization['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">Scope boundary</label>
                    <select class="field-select" name="scope_id" @disabled(! $can_manage_api_tokens)>
                        <option value="">All scopes</option>
                        @foreach ($scope_options as $scope)
                            <option value="{{ $scope['id'] }}" @selected(old('scope_id', $scope_id ?? null) === $scope['id'])>{{ $scope['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">Abilities (optional)</label>
                    <input class="field-input" name="abilities" value="{{ old('abilities') }}" placeholder="assets.read risks.write findings.manage" @disabled(! $can_manage_api_tokens)>
                </div>
            </div>

            <div class="table-note" style="margin-top:8px;">
                Abilities are permission keys separated by spaces or commas. If left blank, the token receives effective owner permissions for the selected context.
            </div>

            @if ($can_manage_api_tokens)
                <div class="action-cluster" style="margin-top:12px;">
                    <button class="button button-primary" type="submit">Issue token</button>
                </div>
            @endif
        </form>
    </div>

    @if (is_array($selected_token))
        <div class="surface-card" style="padding:16px;">
            <div class="row-between" style="align-items:flex-start;">
                <div>
                    <div class="eyebrow">Token Detail</div>
                    <h2 class="screen-title" style="font-size:26px;">{{ $selected_token['label'] }}</h2>
                    <div class="table-note">{{ $selected_token['id'] }}</div>
                    <div class="table-note">Owner: {{ $selected_token['principal_label'] }}</div>
                </div>
                <span class="pill">{{ $selected_token['status'] }}</span>
            </div>
            <div class="overview-grid" style="grid-template-columns:repeat(3, minmax(0, 1fr)); margin-top:12px;">
                <div class="metric-card"><div class="metric-label">Prefix</div><div class="meta-copy">{{ $selected_token['token_prefix'] }}</div></div>
                <div class="metric-card"><div class="metric-label">Boundary</div><div class="meta-copy">{{ $selected_token['organization_label'] }} · {{ $selected_token['scope_label'] }}</div></div>
                <div class="metric-card"><div class="metric-label">Last used</div><div class="meta-copy">{{ $selected_token['last_used_at'] ?? 'never' }}</div></div>
            </div>
        </div>
    @endif

    <div class="table-card">
        <div class="screen-header">
            <div>
                <h2 class="screen-title" style="font-size:24px;">Issued tokens</h2>
                <p class="screen-subtitle">All tokens currently issued for the selected platform context.</p>
            </div>
        </div>

        <table class="entity-table">
            <thead>
                <tr>
                    <th>Token</th>
                    <th>Owner</th>
                    <th>Boundary</th>
                    <th>Status</th>
                    <th>Last used</th>
                    <th>Actions</th>
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
                            <div class="table-note">Expires: {{ $token['expires_at'] ?? 'never' }}</div>
                        </td>
                        <td>
                            <div class="table-note">{{ $token['last_used_at'] ?? 'never' }}</div>
                        </td>
                        <td>
                            <div class="action-cluster">
                                <a class="button button-secondary" href="{{ $token['open_url'] }}">Open</a>
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
                                        <button class="button button-ghost" type="submit">Rotate</button>
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
                                        <button class="button button-ghost" type="submit">Revoke</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="table-note">No API tokens issued yet in this context.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
