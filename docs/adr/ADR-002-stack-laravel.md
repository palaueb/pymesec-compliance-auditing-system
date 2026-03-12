# Title

ADR-002: Laravel as the Initial Application Framework

# Status

Accepted

# Context

The PRD recommends PHP 8.x and identifies Laravel or Symfony as viable application framework options. The same PRD also requires:

- a modular monolith as the initial deployment model
- a minimal, stable, domain-agnostic core
- plugin-first delivery of functional capabilities
- strong support for queues, events, migrations, policies, APIs, and extensibility
- clean separation between platform infrastructure and domain/plugin logic

ADR-001 establishes that the system architecture is `CORE + plugins`, with strict boundaries enforced through contracts, public APIs, events, manifests, and isolated plugin responsibilities. The framework choice must accelerate delivery of the modular monolith without coupling domain design to framework internals.

# Decision

The platform will use `Laravel` as the initial application framework for the modular monolith implementation.

Laravel is selected because it provides a strong balance of delivery speed and platform capabilities needed by the PRD:

- mature queue and job infrastructure for asynchronous processing
- native event and listener patterns that fit plugin hooks and internal integration points
- established migration and seeding tooling for core and plugin data evolution
- authorization and policy support that can back the core permission engine
- broad package ecosystem that reduces time-to-foundation for storage, API, auth-adjacent integration, observability, and admin support
- strong developer ergonomics for bootstrapping the initial platform quickly

Symfony was considered and remains a credible alternative, especially for teams optimizing for maximal explicitness and lower-level decoupling. It is not selected for the initial phase because Laravel offers faster foundation velocity with less setup overhead while still allowing disciplined architectural boundaries.

This decision does not change the architectural rules defined in the PRD and ADR-001:

- Laravel is an implementation framework, not the product architecture
- the core remains minimal, stable, and domain-agnostic
- plugins remain the default location for domain capabilities
- identity, compliance modules, framework packs, reporting, and connectors remain plugin-capable concerns
- framework-specific conveniences must not become implicit domain contracts

To preserve this boundary, the system will define explicit internal contracts for:

- plugin lifecycle and manifests
- event publication and subscription
- permission and policy integration
- access principal abstractions
- functional actor abstractions
- shared service registration and extension points

Laravel components may be used to implement these contracts, but the contracts themselves must remain platform-defined and stable from the perspective of core and plugin developers.

# Consequences

- Initial implementation speed should improve because Laravel provides batteries-included support for queues, events, migrations, policies, configuration, and background jobs.
- The team can focus early effort on core/plugin contracts and product architecture instead of recreating framework-level plumbing.
- Plugin development can build on familiar Laravel package and module patterns, provided those patterns do not weaken the defined extension boundaries.
- The project gains access to a large ecosystem of well-known packages, which should reduce foundation risk for common platform concerns.
- Additional discipline is required to avoid leaking Laravel-specific conventions directly into domain contracts or plugin APIs.
- Future migration away from Laravel would be harder if plugin contracts are allowed to depend on framework internals instead of project-defined abstractions.

# Rejected Alternatives

1. Symfony as the initial framework

Symfony was rejected for the initial phase because, although architecturally strong and highly flexible, it generally requires more assembly and convention choices up front. The project currently prioritizes delivery speed and rapid establishment of the modular monolith foundation.

2. Custom framework or minimal PHP foundation

This was rejected because it would slow down delivery, duplicate mature ecosystem capabilities, and divert effort away from the plugin platform and compliance product concerns.

3. Framework-driven architecture where Laravel packages define the product structure

This was rejected because it would invert the intended architecture. The product must be defined by the PRD, ADRs, and project contracts, not by whichever framework conventions are most convenient.

4. Domain logic embedded directly into framework-specific layers

This was rejected because it would blur core/plugin boundaries, make plugins harder to evolve independently, and undermine the domain-agnostic core model.

# Open Questions

- Which Laravel subsystems will be treated as mandatory platform dependencies in v1, and which will remain replaceable behind project abstractions?
- How strict should plugin authoring guidelines be about direct dependence on Laravel container, events, facades, and Eloquent features?
- What repository and package structure best balances Laravel ergonomics with clear separation between `core`, `plugins`, and reusable `packages`?
- Which testing conventions are required to validate plugin compatibility without coupling tests too tightly to framework internals?
