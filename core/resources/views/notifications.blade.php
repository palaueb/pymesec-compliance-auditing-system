@php
    $settings = is_array($settings ?? null) ? $settings : [];
    $memberships = $query['membership_ids'] ?? [];
    if (! is_array($memberships)) {
        $memberships = [];
    }

    $encryption = old('smtp_encryption', $settings['smtp_encryption'] ?? 'tls');
    $emailEnabled = old('email_enabled', ($settings['email_enabled'] ?? false) ? '1' : '0') === '1';
    $selectedTemplate = is_array($selected_template ?? null) ? $selected_template : null;
@endphp

<section class="module-screen compact">
    <div class="overview-grid" style="grid-template-columns:repeat(5, minmax(0, 1fr));">
        <div class="metric-card"><div class="metric-label">{{ __('core.notifications.metric.visible') }}</div><div class="metric-value">{{ $metrics['notifications'] }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('core.notifications.metric.pending') }}</div><div class="metric-value">{{ $metrics['pending'] }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('core.notifications.metric.dispatched') }}</div><div class="metric-value">{{ $metrics['dispatched'] }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('core.notifications.metric.email_sent') }}</div><div class="metric-value">{{ $metrics['email_sent'] }}</div></div>
        <div class="metric-card"><div class="metric-label">{{ __('core.notifications.metric.email_failed') }}</div><div class="metric-value">{{ $metrics['email_failed'] }}</div></div>
    </div>

    @if (! $has_organization_context)
        <div class="surface-note">{{ __('core.notifications.no_organization') }}</div>
    @else
        <div class="surface-note">{{ __('core.notifications.summary') }}</div>

        <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
            <div class="surface-card" style="padding:16px;">
                <div class="screen-header" style="margin-bottom:10px;">
                    <div>
                        <h2 class="screen-title" style="font-size:22px;">{{ __('core.notifications.smtp.title') }}</h2>
                        <p class="screen-subtitle">{{ __('core.notifications.smtp.subtitle') }}</p>
                    </div>
                    <div class="action-cluster">
                        <span class="pill">{{ $settings['email_enabled'] ?? false ? 'enabled' : 'disabled' }}</span>
                    </div>
                </div>

                <form class="upload-form" method="POST" action="{{ $save_settings_route }}">
                    @csrf
                    <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                    <input type="hidden" name="organization_id" value="{{ $organization_id ?? '' }}">
                    <input type="hidden" name="scope_id" value="{{ $scope_id ?? '' }}">
                    <input type="hidden" name="locale" value="{{ $query['locale'] ?? 'en' }}">
                    <input type="hidden" name="theme" value="{{ $query['theme'] ?? '' }}">
                    <input type="hidden" name="menu" value="core.notifications">
                    @foreach ($memberships as $membershipId)
                        <input type="hidden" name="membership_ids[]" value="{{ $membershipId }}">
                    @endforeach
                    <input type="hidden" name="email_enabled" value="0">

                    <label class="field" style="display:flex; gap:10px; align-items:flex-start;">
                        <input type="checkbox" name="email_enabled" value="1" @checked($emailEnabled) @disabled(! $can_manage_notifications)>
                        <span>
                            <span class="field-label" style="display:block;">{{ __('core.notifications.smtp.enable') }}</span>
                            <span class="table-note">{{ __('core.notifications.smtp.enable_copy') }}</span>
                        </span>
                    </label>

                    <div class="field">
                        <label class="field-label">{{ __('core.notifications.smtp.host') }}</label>
                        <input class="field-input" name="smtp_host" value="{{ old('smtp_host', $settings['smtp_host'] ?? '') }}" @disabled(! $can_manage_notifications)>
                    </div>
                    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                        <div class="field">
                            <label class="field-label">{{ __('core.notifications.smtp.port') }}</label>
                            <input class="field-input" name="smtp_port" type="number" min="1" max="65535" value="{{ old('smtp_port', $settings['smtp_port'] ?? 587) }}" @disabled(! $can_manage_notifications)>
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('core.notifications.smtp.encryption') }}</label>
                            <select class="field-select" name="smtp_encryption" @disabled(! $can_manage_notifications)>
                                <option value="tls" @selected($encryption === 'tls')>{{ __('core.notifications.smtp.tls') }}</option>
                                <option value="ssl" @selected($encryption === 'ssl')>{{ __('core.notifications.smtp.ssl') }}</option>
                                <option value="none" @selected($encryption === 'none' || $encryption === null || $encryption === '')>{{ __('core.notifications.smtp.none') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label">{{ __('core.notifications.smtp.username') }}</label>
                        <input class="field-input" name="smtp_username" value="{{ old('smtp_username', $settings['smtp_username'] ?? '') }}" @disabled(! $can_manage_notifications)>
                    </div>
                    <div class="field">
                        <label class="field-label">{{ __('core.notifications.smtp.password') }}</label>
                        <input class="field-input" name="smtp_password" type="password" placeholder="{{ ($settings['has_password'] ?? false) ? __('core.notifications.smtp.password_stored') : __('core.notifications.smtp.password_optional') }}" @disabled(! $can_manage_notifications)>
                    </div>
                    <div class="field">
                        <label class="field-label">{{ __('core.notifications.smtp.from_address') }}</label>
                        <input class="field-input" name="from_address" type="email" value="{{ old('from_address', $settings['from_address'] ?? '') }}" @disabled(! $can_manage_notifications)>
                    </div>
                    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                        <div class="field">
                            <label class="field-label">{{ __('core.notifications.smtp.from_name') }}</label>
                            <input class="field-input" name="from_name" value="{{ old('from_name', $settings['from_name'] ?? '') }}" @disabled(! $can_manage_notifications)>
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('core.notifications.smtp.reply_to_address') }}</label>
                            <input class="field-input" name="reply_to_address" type="email" value="{{ old('reply_to_address', $settings['reply_to_address'] ?? '') }}" @disabled(! $can_manage_notifications)>
                        </div>
                    </div>

                    <div class="table-note">
                        {{ __('core.notifications.smtp.last_test', ['value' => ($settings['last_tested_at'] ?? '') !== '' ? $settings['last_tested_at'] : __('n/a')]) }}
                        · {{ __('core.notifications.smtp.updated_by', ['value' => ($settings['updated_by_principal_id'] ?? '') !== '' ? $settings['updated_by_principal_id'] : __('n/a')]) }}
                    </div>

                    @if ($can_manage_notifications)
                        <div class="action-cluster" style="margin-top:12px;">
                            <button class="button button-primary" type="submit">{{ __('core.notifications.smtp.save_button') }}</button>
                        </div>
                    @endif
                </form>
            </div>

            <div class="surface-card" style="padding:16px;">
                <div class="screen-header" style="margin-bottom:10px;">
                    <div>
                        <h2 class="screen-title" style="font-size:22px;">{{ __('core.notifications.test.title') }}</h2>
                        <p class="screen-subtitle">{{ __('core.notifications.test.subtitle') }}</p>
                    </div>
                </div>

                @if ($principal_options === [])
                    <div class="surface-note">{{ __('core.notifications.test.empty_people') }}</div>
                @else
                    <form class="upload-form" method="POST" action="{{ $send_test_route }}">
                        @csrf
                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                        <input type="hidden" name="organization_id" value="{{ $organization_id ?? '' }}">
                        <input type="hidden" name="scope_id" value="{{ $scope_id ?? '' }}">
                        <input type="hidden" name="locale" value="{{ $query['locale'] ?? 'en' }}">
                        <input type="hidden" name="theme" value="{{ $query['theme'] ?? '' }}">
                        <input type="hidden" name="menu" value="core.notifications">
                        @foreach ($memberships as $membershipId)
                            <input type="hidden" name="membership_ids[]" value="{{ $membershipId }}">
                        @endforeach

                        <div class="field">
                            <label class="field-label">{{ __('core.notifications.test.recipient') }}</label>
                            <select class="field-select" name="recipient_principal_id" required @disabled(! $can_manage_notifications)>
                                <option value="">{{ __('core.notifications.test.select_person') }}</option>
                                @foreach ($principal_options as $option)
                                    <option value="{{ $option['id'] }}" @selected(old('recipient_principal_id') === $option['id'])>{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="surface-note">{{ __('core.notifications.test.copy') }}</div>

                        @if ($can_manage_notifications)
                            <div class="action-cluster" style="margin-top:12px;">
                                <button class="button button-secondary" type="submit">{{ __('core.notifications.test.button') }}</button>
                            </div>
                        @endif
                    </form>
                @endif
            </div>
        </div>

        <div class="overview-grid" style="grid-template-columns:1.1fr 1fr; margin-top:18px;">
            <div class="table-card">
                <div class="screen-header">
                    <div>
                        <h2 class="screen-title" style="font-size:22px;">{{ __('core.notifications.templates.title') }}</h2>
                        <p class="screen-subtitle">{{ __('core.notifications.templates.subtitle') }}</p>
                    </div>
                </div>

                <table class="entity-table">
                    <thead>
                        <tr>
                            <th>{{ __('core.notifications.templates.type') }}</th>
                            <th>{{ __('core.notifications.templates.status') }}</th>
                            <th>{{ __('core.notifications.templates.variables') }}</th>
                            <th>{{ __('core.notifications.templates.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($templates as $template)
                            @php
                                $templatePlaceholders = implode(', ', array_map(
                                    static fn (string $variable): string => '{'.'{'.$variable.'}'.'}',
                                    $template['variables']
                                ));
                            @endphp
                            <tr>
                                <td>
                                    <div class="entity-title">{{ $template['label'] }}</div>
                                    <div class="entity-id">{{ $template['notification_type'] }}</div>
                                    <div class="table-note">{{ $template['description'] }}</div>
                                </td>
                                <td>
                                    <div class="pill">{{ $template['is_active'] ? __('core.status.active') : __('core.status.default') }}</div>
                                    <div class="table-note">{{ $template['has_override'] ? __('core.notifications.templates.override_stored') : __('core.notifications.templates.using_builtin') }}</div>
                                </td>
                                <td>
                                    <div class="table-note">{{ $templatePlaceholders }}</div>
                                </td>
                                <td>
                                    <a class="button button-ghost" href="{{ $template['open_url'] }}">{{ __('core.notifications.templates.edit_button') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="table-note">{{ __('core.notifications.templates.empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="surface-card" style="padding:16px;">
                <div class="screen-header" style="margin-bottom:10px;">
                    <div>
                        <h2 class="screen-title" style="font-size:22px;">{{ __('core.notifications.editor.title') }}</h2>
                        <p class="screen-subtitle">{{ __('core.notifications.editor.subtitle') }}</p>
                    </div>
                </div>

                @if ($selectedTemplate === null)
                    <div class="surface-note">{{ __('core.notifications.editor.empty') }}</div>
                @else
                    @php
                        $templateEnabled = old('is_active', $selectedTemplate['is_active'] ? '1' : '0') === '1';
                        $selectedTemplatePlaceholders = implode(', ', array_map(
                            static fn (string $variable): string => '{'.'{'.$variable.'}'.'}',
                            $selectedTemplate['variables']
                        ));
                    @endphp
                    <form class="upload-form" method="POST" action="{{ $save_template_route }}">
                        @csrf
                        <input type="hidden" name="principal_id" value="{{ $query['principal_id'] ?? '' }}">
                        <input type="hidden" name="organization_id" value="{{ $organization_id ?? '' }}">
                        <input type="hidden" name="scope_id" value="{{ $scope_id ?? '' }}">
                        <input type="hidden" name="locale" value="{{ $query['locale'] ?? 'en' }}">
                        <input type="hidden" name="theme" value="{{ $query['theme'] ?? '' }}">
                        <input type="hidden" name="menu" value="core.notifications">
                        <input type="hidden" name="notification_type" value="{{ $selectedTemplate['notification_type'] }}">
                        @foreach ($memberships as $membershipId)
                            <input type="hidden" name="membership_ids[]" value="{{ $membershipId }}">
                        @endforeach
                        <input type="hidden" name="is_active" value="0">

                        <div class="entity-title">{{ $selectedTemplate['label'] }}</div>
                        <div class="entity-id" style="margin-top:4px;">{{ $selectedTemplate['notification_type'] }}</div>
                        <div class="table-note" style="margin-top:8px;">{{ $selectedTemplate['description'] }}</div>

                        <label class="field" style="display:flex; gap:10px; align-items:flex-start; margin-top:14px;">
                            <input type="checkbox" name="is_active" value="1" @checked($templateEnabled) @disabled(! $can_manage_notifications)>
                            <span>
                                <span class="field-label" style="display:block;">{{ __('core.notifications.editor.enable_override') }}</span>
                                <span class="table-note">{{ __('core.notifications.editor.enable_override_copy') }}</span>
                            </span>
                        </label>

                        <div class="field">
                            <label class="field-label">{{ __('core.notifications.editor.title_template') }}</label>
                            <textarea class="field-input" name="title_template" rows="4" @disabled(! $can_manage_notifications)>{{ old('title_template', $selectedTemplate['title_template']) }}</textarea>
                        </div>

                        <div class="field">
                            <label class="field-label">{{ __('core.notifications.editor.body_template') }}</label>
                            <textarea class="field-input" name="body_template" rows="8" @disabled(! $can_manage_notifications)>{{ old('body_template', $selectedTemplate['body_template']) }}</textarea>
                        </div>

                        <div class="surface-note">{{ __('core.notifications.editor.placeholders', ['values' => $selectedTemplatePlaceholders]) }}</div>

                        <div class="table-note" style="margin-top:10px;">
                            {{ __('core.notifications.editor.last_updated', ['value' => $selectedTemplate['updated_at'] !== '' ? $selectedTemplate['updated_at'] : __('n/a')]) }}
                            · {{ __('core.notifications.editor.updated_by', ['value' => $selectedTemplate['updated_by_principal_id'] !== '' ? $selectedTemplate['updated_by_principal_id'] : __('n/a')]) }}
                        </div>

                        @if ($can_manage_notifications)
                            <div class="action-cluster" style="margin-top:12px;">
                                <button class="button button-primary" type="submit">{{ __('core.notifications.editor.save_button') }}</button>
                            </div>
                        @endif
                    </form>
                @endif
            </div>
        </div>
    @endif

    <div class="table-card">
        <div class="screen-header">
            <div>
                <h2 class="screen-title" style="font-size:24px;">{{ __('core.notifications.recent.title') }}</h2>
                <p class="screen-subtitle">{{ __('core.notifications.recent.subtitle') }}</p>
            </div>
        </div>

        <table class="entity-table">
            <thead>
                <tr>
                    <th>{{ __('core.notifications.recent.notification') }}</th>
                    <th>{{ __('core.notifications.recent.recipient') }}</th>
                    <th>{{ __('core.notifications.recent.status') }}</th>
                    <th>{{ __('core.notifications.recent.email') }}</th>
                    <th>{{ __('core.notifications.recent.when') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($notifications as $notification)
                    <tr>
                        <td>
                            <div class="entity-title">{{ $notification['title'] }}</div>
                            <div class="entity-id">{{ $notification['type'] }}</div>
                                <div class="table-note">{{ $notification['body'] }}</div>
                            </td>
                            <td>
                                <div>{{ $notification['principal_id'] ?? 'n/a' }}</div>
                                @if (($notification['functional_actor_id'] ?? null) !== null)
                                    <div class="table-note">{{ __('core.notifications.recent.actor', ['value' => $notification['functional_actor_id']]) }}</div>
                                @endif
                            </td>
                            <td>
                                <div class="pill">{{ $notification['status'] }}</div>
                                @if (($notification['deliver_at'] ?? null) !== null)
                                    <div class="table-note">{{ __('core.notifications.recent.due', ['value' => $notification['deliver_at']]) }}</div>
                                @endif
                            </td>
                        <td>
                            <div class="entity-title">{{ $notification['email_delivery_status'] }}</div>
                            @if (($notification['email_delivery_reason'] ?? null) !== null)
                                <div class="table-note">{{ $notification['email_delivery_reason'] }}</div>
                            @endif
                        </td>
                        <td>
                            <div>{{ $notification['dispatched_at'] ?? $notification['created_at'] ?? 'n/a' }}</div>
                            <div class="table-note">{{ $notification['scope_id'] ?? __('core.shell.organization_wide') }}</div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="table-note">{{ __('core.notifications.recent.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
