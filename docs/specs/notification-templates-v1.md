# Title

Notification Templates v1

# Status

Draft

# Context

Outbound delivery is now configurable from the admin UI and due notifications can already leave the platform by email. The next gap is message governance: organizations need to adapt reminder and operational follow-up copy without editing plugin code or diverging between in-app and email delivery.

# Specification

## 1. Objectives

This slice defines:

- organization-scoped notification templates keyed by `notification_type`
- safe placeholder interpolation for title and body
- template editing from the admin notifications screen
- application of templates when notifications are created

## 2. Scope

This slice adds:

- one template record per `organization_id + notification_type`
- override fields for title and body
- enable/disable control per template
- curated template catalogue entries for current reminder and operational types

This slice does not yet add:

- conditional logic
- per-channel template branching
- rich HTML email layouts
- version history or approval workflows

## 3. Data Model

Templates are stored in `notification_templates`.

Each record includes:

- `organization_id`
- `notification_type`
- `is_active`
- `title_template`
- `body_template`
- `updated_by_principal_id`

The table is unique by `organization_id + notification_type`.

## 4. Rendering Rules

Templates are evaluated when `NotificationServiceInterface::notify()` is called.

Rules:

- if no active template exists, the original title and body are stored unchanged
- if an active template exists, the core renders the override before persisting the notification
- the stored notification body becomes the source for both in-app and outbound email delivery
- template application metadata is written back into notification metadata for traceability

## 5. Placeholder Rules

The renderer supports:

- common placeholders such as `notification_title`, `notification_body`, `notification_type`, `organization_id`, `scope_id`, `principal_id`, and `deliver_at`
- scalar metadata keys already supplied by the plugin or core notification caller

Unknown placeholders resolve to an empty string.

## 6. Admin Rules

The template editor lives inside `core.notifications`.

Rules:

- viewing templates requires `core.notifications.view`
- saving templates requires `core.notifications.manage`
- templates are always organization-scoped
- the UI must make the supported placeholders visible per notification type

# Consequences

- organizations can adapt reminder and operational follow-up copy without forking plugin behavior
- in-app and outbound messages stay aligned because templating happens before persistence
- richer content controls can be layered later on top of the same template records
