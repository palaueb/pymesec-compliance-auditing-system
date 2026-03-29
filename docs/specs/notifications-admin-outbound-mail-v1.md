# Title

Notifications Admin Outbound Mail v1

# Status

Draft

# Context

The base notifications specification already defines in-app notifications and scheduler-safe due dispatch. The next product slice needs real administrative control over reminder delivery so organizations can configure SMTP, verify the connector, and let due notifications reach a principal by email without exposing broader customer data.

# Specification

## 1. Objectives

This slice defines:

- how an organization configures outbound SMTP from the admin UI
- how the core sends a test email without exposing domain records
- how due notifications attempt best-effort outbound delivery
- what delivery metadata is stored for audit and troubleshooting

## 2. Scope

This slice adds:

- a core admin screen for notifications and outbound delivery
- one organization-scoped SMTP configuration record
- a test-email action addressed only to a principal in the same organization
- best-effort outbound email during `dispatchDue()`

This slice does not yet add:

- conditional or rich HTML templates
- arbitrary external recipients
- retries or queue-backed mail pipelines
- per-principal notification preferences

## 3. Data Model

The core stores SMTP settings in `notification_mail_settings`.

Each record is unique per `organization_id` and includes:

- enable/disable flag
- SMTP host, port, encryption, and username
- encrypted password
- from address and reply-to address
- `last_tested_at`
- `updated_by_principal_id`

Passwords must never be stored in plain text.

## 4. Admin UI Rules

The admin screen lives under `core.notifications`.

Rules:

- the screen is visible with `core.notifications.view`
- SMTP changes require `core.notifications.manage`
- settings are organization-scoped and must not bleed across organizations
- the test-email action only accepts a principal that belongs to the active organization
- the test-email action must not expose workspace data or customer records in the email body

## 5. Dispatch Rules

When `dispatchDue()` promotes a due notification from `pending` to `dispatched`:

- the in-app notification state is updated first-class regardless of email outcome
- if SMTP is enabled for the notification organization and the target principal has an active local email, the core attempts outbound email
- outbound email failure must not roll back the notification dispatch
- delivery status is written into notification metadata under `channels.email`

The metadata shape may include:

- `status`
- `recipient_principal_id`
- `reason`
- `attempted_at`

## 6. Security Rules

This slice must preserve ownership and authorization boundaries.

Rules:

- only `core.notifications.manage` may mutate SMTP settings
- only principals from the same organization may be selected for test delivery
- SMTP settings never grant broader access to notification payloads or customer records
- audit summaries must avoid leaking SMTP secrets

## 7. Audit and Events

The core records and emits:

- `core.notifications.mail-settings.updated`
- `core.notifications.test-email.sent`
- existing `core.notifications.dispatched`

These operations remain auditable as core-sensitive administrative actions.

# Consequences

- organizations can enable outbound reminder delivery without plugin-specific mail code
- the notification subsystem remains core-governed and tenancy-aware
- templates and richer communications can be layered later without discarding the stored delivery state
