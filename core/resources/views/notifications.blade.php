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
        <div class="metric-card"><div class="metric-label">Visible notifications</div><div class="metric-value">{{ $metrics['notifications'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Pending</div><div class="metric-value">{{ $metrics['pending'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Dispatched</div><div class="metric-value">{{ $metrics['dispatched'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Email sent</div><div class="metric-value">{{ $metrics['email_sent'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Email failed</div><div class="metric-value">{{ $metrics['email_failed'] }}</div></div>
    </div>

    @if (! $has_organization_context)
        <div class="surface-note">
            Select an organization first. SMTP delivery is stored per organization so outbound settings are never shared across tenants.
        </div>
    @else
        <div class="surface-note">
            Outbound delivery stays organization-scoped. The platform only stores the SMTP connector for the active organization and notifications keep their in-app record even if email delivery fails.
        </div>

        <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
            <div class="surface-card" style="padding:16px;">
                <div class="screen-header" style="margin-bottom:10px;">
                    <div>
                        <h2 class="screen-title" style="font-size:22px;">SMTP delivery</h2>
                        <p class="screen-subtitle">Configure the outbound connector used when due notifications are dispatched for this organization.</p>
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
                            <span class="field-label" style="display:block;">Enable outbound email</span>
                            <span class="table-note">When enabled, due notifications with a matching principal email are also sent through SMTP.</span>
                        </span>
                    </label>

                    <div class="field">
                        <label class="field-label">SMTP host</label>
                        <input class="field-input" name="smtp_host" value="{{ old('smtp_host', $settings['smtp_host'] ?? '') }}" @disabled(! $can_manage_notifications)>
                    </div>
                    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                        <div class="field">
                            <label class="field-label">Port</label>
                            <input class="field-input" name="smtp_port" type="number" min="1" max="65535" value="{{ old('smtp_port', $settings['smtp_port'] ?? 587) }}" @disabled(! $can_manage_notifications)>
                        </div>
                        <div class="field">
                            <label class="field-label">Encryption</label>
                            <select class="field-select" name="smtp_encryption" @disabled(! $can_manage_notifications)>
                                <option value="tls" @selected($encryption === 'tls')>TLS</option>
                                <option value="ssl" @selected($encryption === 'ssl')>SSL</option>
                                <option value="none" @selected($encryption === 'none' || $encryption === null || $encryption === '')>None</option>
                            </select>
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label">SMTP username</label>
                        <input class="field-input" name="smtp_username" value="{{ old('smtp_username', $settings['smtp_username'] ?? '') }}" @disabled(! $can_manage_notifications)>
                    </div>
                    <div class="field">
                        <label class="field-label">SMTP password</label>
                        <input class="field-input" name="smtp_password" type="password" placeholder="{{ ($settings['has_password'] ?? false) ? 'Stored securely. Leave blank to keep current password.' : 'Optional unless your provider requires it.' }}" @disabled(! $can_manage_notifications)>
                    </div>
                    <div class="field">
                        <label class="field-label">From address</label>
                        <input class="field-input" name="from_address" type="email" value="{{ old('from_address', $settings['from_address'] ?? '') }}" @disabled(! $can_manage_notifications)>
                    </div>
                    <div class="overview-grid" style="grid-template-columns:repeat(2, minmax(0, 1fr));">
                        <div class="field">
                            <label class="field-label">From name</label>
                            <input class="field-input" name="from_name" value="{{ old('from_name', $settings['from_name'] ?? '') }}" @disabled(! $can_manage_notifications)>
                        </div>
                        <div class="field">
                            <label class="field-label">Reply-to address</label>
                            <input class="field-input" name="reply_to_address" type="email" value="{{ old('reply_to_address', $settings['reply_to_address'] ?? '') }}" @disabled(! $can_manage_notifications)>
                        </div>
                    </div>

                    <div class="table-note">
                        Last test: {{ ($settings['last_tested_at'] ?? '') !== '' ? $settings['last_tested_at'] : 'never' }}
                        · Updated by: {{ ($settings['updated_by_principal_id'] ?? '') !== '' ? $settings['updated_by_principal_id'] : 'n/a' }}
                    </div>

                    @if ($can_manage_notifications)
                        <div class="action-cluster" style="margin-top:12px;">
                            <button class="button button-primary" type="submit">Save SMTP settings</button>
                        </div>
                    @endif
                </form>
            </div>

            <div class="surface-card" style="padding:16px;">
                <div class="screen-header" style="margin-bottom:10px;">
                    <div>
                        <h2 class="screen-title" style="font-size:22px;">Send test email</h2>
                        <p class="screen-subtitle">Verify the connector with a real principal from the active organization.</p>
                    </div>
                </div>

                @if ($principal_options === [])
                    <div class="surface-note">No active principals with email addresses are available in this organization yet.</div>
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
                            <label class="field-label">Recipient</label>
                            <select class="field-select" name="recipient_principal_id" required @disabled(! $can_manage_notifications)>
                                <option value="">Select a person</option>
                                @foreach ($principal_options as $option)
                                    <option value="{{ $option['id'] }}" @selected(old('recipient_principal_id') === $option['id'])>{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="surface-note">
                            The test message only proves connector health. It does not expose workspace records or customer data.
                        </div>

                        @if ($can_manage_notifications)
                            <div class="action-cluster" style="margin-top:12px;">
                                <button class="button button-secondary" type="submit">Send test email</button>
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
                        <h2 class="screen-title" style="font-size:22px;">Notification templates</h2>
                        <p class="screen-subtitle">Override title and body per notification type without changing plugin code.</p>
                    </div>
                </div>

                <table class="entity-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Variables</th>
                            <th>Actions</th>
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
                                    <div class="pill">{{ $template['is_active'] ? 'active' : 'default' }}</div>
                                    <div class="table-note">{{ $template['has_override'] ? 'override stored' : 'using built-in copy' }}</div>
                                </td>
                                <td>
                                    <div class="table-note">{{ $templatePlaceholders }}</div>
                                </td>
                                <td>
                                    <a class="button button-ghost" href="{{ $template['open_url'] }}">Edit template</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="table-note">No notification types have been discovered for this organization yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="surface-card" style="padding:16px;">
                <div class="screen-header" style="margin-bottom:10px;">
                    <div>
                        <h2 class="screen-title" style="font-size:22px;">Template editor</h2>
                        <p class="screen-subtitle">Use placeholders to adapt reminders and operational follow-up to your organization tone.</p>
                    </div>
                </div>

                @if ($selectedTemplate === null)
                    <div class="surface-note">Select a notification type first to edit its template.</div>
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
                                <span class="field-label" style="display:block;">Enable override</span>
                                <span class="table-note">If disabled, the notification falls back to the title and body supplied by the core or plugin.</span>
                            </span>
                        </label>

                        <div class="field">
                            <label class="field-label">Title template</label>
                            <textarea class="field-input" name="title_template" rows="4" @disabled(! $can_manage_notifications)>{{ old('title_template', $selectedTemplate['title_template']) }}</textarea>
                        </div>

                        <div class="field">
                            <label class="field-label">Body template</label>
                            <textarea class="field-input" name="body_template" rows="8" @disabled(! $can_manage_notifications)>{{ old('body_template', $selectedTemplate['body_template']) }}</textarea>
                        </div>

                        <div class="surface-note">
                            Available placeholders: {{ $selectedTemplatePlaceholders }}
                        </div>

                        <div class="table-note" style="margin-top:10px;">
                            Last updated: {{ $selectedTemplate['updated_at'] !== '' ? $selectedTemplate['updated_at'] : 'never' }}
                            · Updated by: {{ $selectedTemplate['updated_by_principal_id'] !== '' ? $selectedTemplate['updated_by_principal_id'] : 'n/a' }}
                        </div>

                        @if ($can_manage_notifications)
                            <div class="action-cluster" style="margin-top:12px;">
                                <button class="button button-primary" type="submit">Save template</button>
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
                <h2 class="screen-title" style="font-size:24px;">Recent notifications</h2>
                <p class="screen-subtitle">Latest reminder and notification records for the active organization context.</p>
            </div>
        </div>

        <table class="entity-table">
            <thead>
                <tr>
                    <th>Notification</th>
                    <th>Recipient</th>
                    <th>Status</th>
                    <th>Email</th>
                    <th>When</th>
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
                                <div class="table-note">Actor {{ $notification['functional_actor_id'] }}</div>
                            @endif
                        </td>
                        <td>
                            <div class="pill">{{ $notification['status'] }}</div>
                            @if (($notification['deliver_at'] ?? null) !== null)
                                <div class="table-note">Due {{ $notification['deliver_at'] }}</div>
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
                            <div class="table-note">{{ $notification['scope_id'] ?? 'organization-wide' }}</div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="table-note">No notifications recorded for the active organization context yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
