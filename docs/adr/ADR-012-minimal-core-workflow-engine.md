# Title

ADR-012: Minimal Core Workflow Engine with Plugin-Defined Semantics

# Status

Accepted

# Context

The PRD requires the core to provide a minimal workflow engine and base notifications, while also stating that advanced workflows belong in plugins. Multiple functional areas in the PRD depend on workflow behavior, including:

- assessments and closure
- findings and remediation
- exceptions and approvals
- privacy incidents
- future automation and reminders

ADR-001 already places a minimal workflow engine in the core, but the boundary between workflow mechanics and domain-specific workflow meaning has not yet been formalized. ADR-003, ADR-004, ADR-008, ADR-009, and ADR-010 all affect workflow behavior through actors, permissions, events, tenancy, and audit requirements.

The platform therefore needs an architectural decision that defines what the core workflow engine is responsible for and what remains plugin territory.

# Decision

The platform will implement a `minimal core workflow engine` that provides generic process mechanics, while plugins define workflow semantics for their domain resources.

The core workflow engine owns:

- workflow definition and instance contracts
- state-transition mechanics
- guard and policy integration hooks
- tenancy-aware workflow context
- integration hooks to audit trail, events, notifications, and scheduler capabilities

Plugins own:

- workflow definitions for their resources
- business-specific states and transitions
- domain validation and side effects
- business notions such as owner, reviewer, approver, remediator, or acceptor

Boundary rule:

- the core defines how workflows progress
- plugins define what those workflows mean

Authorization rule:

- transition execution integrates with the core permission engine
- functional ownership or assignment may be consulted explicitly, but it does not automatically grant transition rights

Tenancy rule:

- tenant business workflows are organization-bound by default and may also be scope-bound

Audit and event rule:

- workflow transitions may emit public events through approved contracts
- sensitive workflow operations must remain auditable through the core audit trail

# Consequences

- The platform gets one reusable workflow substrate for multiple plugins.
- Domain plugins can implement reviews, approvals, closures, reopen flows, reminders, and escalations without each inventing a private incompatible engine.
- Workflow behavior remains compatible with permissions, actors, tenancy, events, and audit from the start.
- The core stays domain-agnostic because it owns process mechanics rather than compliance-domain semantics.
- The project will need clear contracts for workflow definitions, history, guards, and side-effect handling before implementation.

# Rejected Alternatives

1. No core workflow engine, with every plugin implementing its own process runtime

This was rejected because repeated private workflow implementations would fragment platform behavior and integration.

2. Rich business workflow semantics embedded in the core

This was rejected because approvals, exception flows, risk treatments, and assessment states belong to plugins, not to the domain-agnostic core.

3. Workflow transition rights inferred automatically from business ownership

This was rejected because ADR-003 and ADR-004 require separation between business accountability and access control.

4. Fully generic orchestration platform in v1

This was rejected because the core needs a minimal reusable engine first, not a full BPM suite.

5. Notifications and reminders implemented separately from workflow context

This was rejected because many future platform behaviors depend on state progression and deadlines tied to workflow instances.

# Open Questions

- What minimum workflow definition format should the core support in v1?
- Which transition history must be stored as workflow history versus as audit records only?
- Which reminder, escalation, and deadline behaviors belong in the core baseline versus in automation plugins?
- How should cross-plugin workflow composition be constrained if it is introduced later?
