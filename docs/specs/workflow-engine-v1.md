# Title

Workflow Engine v1

# Status

Draft

# Context

The PRD requires the core to provide:

- a minimal workflow engine
- base notifications
- scheduler and asynchronous jobs
- support for plugins that implement domain workflows such as assessments, findings, exceptions, risks, privacy incidents, and approvals

ADR-001 places the minimal workflow engine in the core while explicitly leaving advanced workflows to plugins. ADR-003 requires workflow responsibility assignments to remain distinct from access identity. ADR-004 requires workflow actions to integrate with the core permission engine. ADR-008 establishes a public event and hook model that workflow transitions may use. ADR-010 requires sensitive workflow operations to be auditable.

The concrete v1 notification and reminder contract used by workflows is further detailed in `notifications-and-reminders-v1.md`.

The platform therefore needs a shared workflow foundation that:

- supports stateful process progression across plugins
- keeps business semantics out of the core
- integrates with permissions, actors, tenancy, events, notifications, and audit trail

# Specification

## 1. Objectives

This specification defines:

- what the minimal core workflow engine provides
- how workflows are modeled at a generic level
- what workflow semantics belong to plugins
- how transitions interact with authorization, actors, events, and audit

## 2. Core Workflow Scope

The core workflow engine is a generic state-transition capability.

In v1, the core workflow engine should provide:

- workflow definition registration
- workflow instance state tracking
- transition execution
- guard evaluation hooks
- assignment and responsibility references
- due-date and reminder hooks
- event and notification integration hooks
- audit integration for sensitive transitions

The core workflow engine does not define domain meaning for:

- risk acceptance
- finding closure
- exception approval
- control review
- privacy breach handling
- continuity-plan activation

Those workflow meanings belong to plugins.

## 3. Generic Workflow Concepts

### Workflow Definition

A `workflow definition` is the declarative description of allowed states and transitions for a process type.

It should define:

- stable workflow identifier
- owning component
- target domain resource type or process type
- states
- transitions
- optional guard or policy references
- optional reminder or deadline metadata

### Workflow Instance

A `workflow instance` is the runtime progression of one resource or process through a workflow definition.

It should reference:

- workflow definition
- target resource reference
- current state
- tenancy context
- assignment context
- transition history

### State

A `state` is a stable named step in the process lifecycle.

Examples:

- `draft`
- `in_review`
- `approved`
- `closed`
- `reopened`

State names are generic technical labels. Their business meaning belongs to the owning plugin.

### Transition

A `transition` is an allowed movement from one state to another.

A transition may declare:

- stable transition identifier
- source state or states
- target state
- required permission or policy hook
- assignment constraints
- audit sensitivity flag
- notification or reminder hooks

## 4. Core Versus Plugin Responsibilities

The core owns:

- workflow engine contracts
- generic transition execution model
- workflow state persistence abstractions
- integration hooks to permissions, actors, notifications, events, and audit trail

Plugins own:

- workflow definitions for their domain resources
- business-specific state names and transition meanings
- business validation and side effects
- domain-specific reviewer, approver, or owner semantics

Boundary rule:

- the core governs workflow mechanics
- plugins govern workflow semantics

## 5. Authorization and Responsibility Rules

Workflow transitions must integrate with access control without collapsing into business ownership.

Rules:

- transition execution may require permissions evaluated by the core authorization engine
- functional actor assignments may be consulted through explicit plugin rules or guards
- being the business owner or approver of a record does not automatically grant workflow transition permission
- having permission does not automatically make the principal the business owner or approver

## 6. Tenancy Rules

Workflow instances are tenancy-aware.

Rules:

- every tenant business workflow instance must be organization-bound
- a workflow instance may also be scope-bound
- transition execution must preserve organization and scope context for authorization, audit, and notifications

## 7. Events, Notifications, and Scheduler Hooks

The workflow engine must integrate with other core subsystems.

Minimum v1 integration points:

- emit workflow transition events through the public event or hook model where appropriate
- create auditable records for sensitive transitions
- allow reminder or deadline scheduling hooks
- allow notification hooks on state entry, transition, or overdue conditions

The engine provides hooks; it does not require every workflow to use all of them.

## 8. History and Audit

Workflow history and audit trail are related but not identical.

Rules:

- the workflow engine should keep state-transition history for process progression
- the audit trail records sensitive administrative or business-significant actions according to audit policy
- workflow history may be user-facing
- audit records remain governance-grade traceability records

## 9. Failure and Idempotency Rules

Transition execution should be controlled and predictable.

Rules:

- invalid transitions must fail closed
- guard failures must not partially apply workflow side effects
- sensitive transitions should be auditable whether they succeed or fail where policy requires
- retries of asynchronous workflow side effects must not silently duplicate state transitions

## 10. Minimal v1 Use Cases

The workflow engine should be sufficient for plugins to model, at minimum:

- review and close cycles
- approval and rejection cycles
- reopen flows
- due-date reminders
- escalation hooks

This gives plugins a reusable substrate without forcing the core to own compliance-domain process logic.

## 11. Out of Scope in v1

This v1 specification does not yet define:

- graphical workflow designers
- BPMN-style orchestration
- arbitrary cross-plugin workflow composition
- full SLA engine semantics
- complex parallel approvals
- marketplace-distributed workflow templates

# Consequences

- The platform gets one reusable process substrate for plugin-defined domain workflows.
- Plugins can implement assessments, findings, exceptions, risks, or privacy flows without each inventing their own private workflow mechanics.
- Authorization, functional assignments, reminders, notifications, events, and audit can integrate through one common runtime model.
- The core remains domain-agnostic because it owns process mechanics rather than process meaning.
- The project will need workflow contracts, transition-history storage, and clear guard and side-effect boundaries before implementation.
