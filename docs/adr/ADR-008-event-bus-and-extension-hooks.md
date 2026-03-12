# Title

ADR-008: Core Event Bus and Public Extension Hooks

# Status

Accepted

# Context

The PRD requires the core to provide:

- an event and hooks system
- public API and internal events
- plugin event subscription
- plugin event publication
- scheduler and asynchronous jobs
- auditability for sensitive operations

ADR-001 establishes that plugins must extend the platform through approved contracts, public APIs, events, and hooks rather than by modifying core internals directly. ADR-002 selects Laravel as the implementation framework, but explicitly states that framework mechanisms must sit behind project-defined platform contracts rather than becoming the architecture themselves.

The platform therefore needs an architectural decision that defines events and hooks as a first-class extension surface of the core.

# Decision

The platform will provide a `core-governed event bus and public hook model` as the standard mechanism for cross-component extension and decoupling inside the modular monolith.

Scope of the mechanism:

- core components may publish public events
- plugins may publish public events
- plugins may subscribe to approved public events
- the core may expose explicit hooks for lifecycle or extension points where subscription alone is not sufficient

Boundary rules:

- public events and hooks are part of the platform contract surface
- internal implementation events may exist, but they are not valid integration contracts unless explicitly published as public
- plugins must not depend on private core listener order, hidden container bindings, or undocumented framework events as if they were stable platform APIs

Event model policy:

- public events must use stable names or stable event classes governed by the platform
- event payloads must be treated as contract data, not incidental internal state dumps
- event payload evolution must follow compatibility policy in the same way other plugin-facing contracts do
- event handlers must be isolated so that one failing plugin subscriber does not corrupt core consistency

Execution policy for the modular monolith phase:

- synchronous in-process dispatch is the default baseline for v1
- queued or asynchronous handling may be used where the platform marks an event or hook as async-capable
- event publication must remain compatible with future out-of-process delivery without redefining the business meaning of the event

Governance policy:

- lifecycle-sensitive operations such as plugin enablement, disablement, upgrades, authorization administration, and other auditable actions should publish explicit public events where extension or observability is expected
- subscription must happen through declared plugin registration and approved contracts
- the platform must be able to identify which events are public, versioned, and safe for plugin consumption

Design intent:

- events are for decoupled reaction and coordination
- hooks are for approved extension points with more explicit semantics
- neither mechanism permits arbitrary mutation of core internals outside the published contract

# Consequences

- Plugins gain a standard way to react to core and peer-component activity without direct hard coupling.
- The core can grow extension points without turning every integration into a bespoke API.
- Observability, notifications, automation, and future connector behaviors can build on one consistent cross-component mechanism.
- Event and payload versioning become explicit platform concerns.
- The project will need a catalog of public events and hooks, plus testing rules for subscriber isolation and compatibility.
- Some current Laravel-native event usage will need wrapping or documentation before it can be treated as a stable platform contract.

# Rejected Alternatives

1. Plugins calling one another or the core directly for all integration needs

This was rejected because it creates tight coupling, weakens replaceability, and makes plugin isolation harder.

2. Unrestricted hook points that let plugins mutate arbitrary core internals

This was rejected because it undermines upgradeability, safety, and the architectural boundary defined in ADR-001.

3. Framework-native events treated automatically as public product contracts

This was rejected because Laravel implementation details must not become accidental long-term platform APIs.

4. No event bus, only explicit REST-style APIs

This was rejected because many extension scenarios in the PRD require decoupled reaction, automation, notifications, and observability rather than only request-response integration.

5. Fully asynchronous-only event delivery from the start

This was rejected because the modular monolith needs a simpler baseline first, and not all extension points justify queueing overhead in v1.

# Open Questions

- Which initial event catalog should be considered public in v1, and which remain internal-only?
- What ordering, retry, and error-isolation guarantees should the platform provide for synchronous versus queued subscribers?
- Which auditable actions must always emit public events in addition to audit-log records?
- How should plugin-declared scheduled tasks and automation hooks relate to the event bus contract over time?
