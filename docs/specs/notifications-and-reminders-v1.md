# Title

Notifications and Reminders v1

# Status

Draft

# Context

The PRD requires the platform to provide base notifications, reminder hooks, and scheduler-oriented automation. ADR-001 places these base capabilities in the core. ADR-008 requires notification behavior to integrate with the public event bus. ADR-010 requires auditable handling for sensitive delivery operations. ADR-012 requires workflow transitions to trigger reminder and notification hooks without moving workflow semantics into the core.

The platform therefore needs a minimal notification substrate that:

- is reusable by the core and plugins
- is tenancy-aware
- can represent immediate and scheduled delivery
- integrates with events, workflow, and audit
- remains simple enough to evolve later toward richer channels

# Specification

## 1. Objectives

This specification defines:

- what the v1 notification subsystem provides
- how reminders are represented in the runtime
- how plugins may request notifications
- what the scheduler is responsible for in v1

## 2. Core Scope

In v1, the core notification subsystem should provide:

- in-app notification storage
- immediate notification creation
- scheduled notification creation through a `deliver_at` timestamp
- due-dispatch processing through a scheduler-safe command
- event publication for created and dispatched notifications
- audit records for sensitive dispatch actions

The core does not yet provide:

- email delivery
- SMS or chat delivery
- per-user notification preferences
- digesting, batching, or escalation policies
- delivery retries across external providers

## 3. Notification Model

A notification is a stored message addressed to one or more runtime references.

In v1, a notification may reference:

- `principal_id`
- `functional_actor_id`
- `organization_id`
- `scope_id`
- `source_event_name`
- arbitrary metadata

The stored record must contain:

- stable notification identifier
- notification type
- title
- body
- status
- created timestamp
- optional due or delivery timestamp
- optional dispatched timestamp

## 4. Reminder Model

In v1, reminders are not a separate engine. A reminder is represented as a scheduled notification whose `deliver_at` is in the future.

This keeps the first implementation minimal:

- the scheduler only needs to dispatch due notifications
- workflows and plugins can request reminders without a separate reminder DSL
- future reminder policies can still grow on top of the same primitive

## 5. Status Model

The minimal status model is:

- `pending`
- `dispatched`

Rules:

- notifications without `deliver_at` may be created as immediately dispatched
- notifications with future `deliver_at` must start as `pending`
- scheduler processing promotes due notifications from `pending` to `dispatched`

## 6. Scheduler Rules

The scheduler contract in v1 is intentionally small.

Rules:

- the core exposes a command to dispatch due notifications
- the command must be safe to run repeatedly
- dispatch processing must only affect `pending` notifications whose `deliver_at` is due
- dispatch processing should preserve tenancy context where present

The platform may later invoke this command from cron, a queue worker, or a dedicated scheduler service.

## 7. Event and Audit Integration

Notification operations must integrate with the rest of the core.

Rules:

- notification creation publishes `core.notifications.created`
- due dispatch publishes `core.notifications.dispatched`
- scheduler-driven dispatch should be auditable as a core-sensitive operation
- plugins may subscribe to notification events through the public event bus

## 8. Plugin Responsibilities

Plugins may:

- request immediate notifications
- request scheduled notifications and reminders
- include plugin-specific metadata needed for review boards, dashboards, or domain follow-up
- react to public notification events if they need derived behavior

Plugins must not:

- bypass core notification persistence
- assume a specific future delivery channel
- implement private notification state machines for shared platform behavior

## 9. Minimal v1 Use Cases

The subsystem should be sufficient for:

- workflow review requests
- due-date reminders
- internal in-app alerts for platform operators
- plugin-specific review boards that need notification history

## 10. Out of Scope in v1

This v1 specification does not yet define:

- user inbox UX beyond simple shell-backed screens
- notification dismissal or read-state semantics
- channel adapters
- throttling and anti-spam rules
- SLA-driven escalations

# Consequences

- The platform gets one shared primitive for reminders and in-app notifications.
- Workflow and plugin automation can build on one scheduler-safe dispatch model.
- Events and audit stay consistent with the rest of the core runtime.
- Future channel delivery can extend the subsystem without changing plugin-facing intent.
