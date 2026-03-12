# Title

Event Bus and Hooks v1

# Status

Draft

# Context

ADR-008 establishes a core-governed public event bus and hook model. The current runtime now has:

- synchronous in-process publication
- persisted public event records
- plugin subscription through the plugin runtime context
- public inspection through core routes and artisan commands

# Specification

## 1. Public Event Model

Each public event must include:

- stable event name
- origin component
- optional organization context
- optional scope context
- structured payload
- publication timestamp

The payload must stay contract-oriented and must not dump arbitrary internal state.

## 2. Publication Rules

Public events are published through the core event bus contract.

Current v1 baseline:

- publication is synchronous and in-process
- every published event is also persisted in the `public_events` store
- listener failures must not break the main operation

## 3. Subscription Rules

Plugins subscribe through the runtime context rather than directly wiring framework events as public contracts.

Current v1 baseline:

- exact event-name subscription only
- plugin listeners may react and publish derived public events
- plugins must treat event payloads as stable contract data, not as mutable shared state

## 4. Current Public Inspection Surface

Current runtime inspection surface:

- `GET /core/events`
- `php artisan events:list`

These are inspection tools for the public event stream, not replacements for audit logs.

## 5. Relationship to Audit

The public event bus and the audit trail are different mechanisms.

Rules:

- audit records remain the governance-grade append-only trace for sensitive operations
- public events are the extension and coordination surface
- a sensitive operation may create both an audit record and a public event

## 6. Initial v1 Public Events

The current runtime already emits events for:

- workflow transitions
- tenancy lifecycle changes
- functional actor creation
- functional actor assignments
- principal-to-functional-actor linkages

Plugins may also publish their own namespaced events.

## 7. Out of Scope in v1

This v1 does not yet define:

- wildcard subscriptions
- queued delivery guarantees
- retries and dead-letter handling
- external broker delivery
- event schema version negotiation
