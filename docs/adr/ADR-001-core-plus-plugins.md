# Title

ADR-001: CORE + Plugins Architecture

# Status

Accepted

# Context

The product PRD defines the platform as an open source compliance auditing system built around a `CORE + plugins` architecture. The platform must support progressive delivery of functionality without compromising the stability of the base system. It must also remain extensible for third parties that add frameworks, connectors, reports, automations, and other capabilities without modifying core code.

The PRD establishes several non-negotiable constraints:

- the core must remain minimal, stable, and domain-agnostic
- functional capabilities should be implemented as plugins whenever possible
- identity and access concerns are distinct from functional domain ownership
- the core must be identity-provider-agnostic
- multilingual UI support is a core concern
- the system is designed as a modular monolith first

This decision is needed to define the boundary between the core platform and plugin-delivered capabilities before detailed technical specifications and implementation begin.

# Decision

The platform will adopt a plugin-first architecture within a modular monolith. The system will be composed of:

1. a minimal `CORE`
2. pluggable functional modules
3. shared extension contracts and public APIs

The `CORE` is responsible only for platform capabilities required to host, execute, and govern plugins consistently across the system. The `CORE` must not contain compliance-domain logic, framework-specific logic, or provider-specific identity logic.

The `CORE` includes:

- plugin manager, plugin registry, dependency handling, compatibility checks, and lifecycle orchestration
- extension contracts, SDK foundations, public APIs, events, and hooks
- application container and service registry
- shared persistence abstractions and base shared entities
- audit trail and sensitive-operation logging
- scheduler, job queue foundations, and base async execution support
- configuration management
- internationalization and localization foundations
- UI shell, extensible routing, and extensible menu system
- permission and policy engine decoupled from identity providers
- access principal and membership abstractions
- functional actor and functional assignment abstractions
- base workflow engine, notifications, health checks, and observability
- multi-organization / multi-tenant foundations
- base storage for attachments and artifacts

The following concerns are plugin-capable by default and should be implemented as plugins unless a later ADR explicitly states otherwise:

- identity and authentication providers
- user directory and team management
- compliance domain modules such as controls, requirements, evidence, assessments, findings, risks, privacy, assets, vendors, and questionnaires
- functional ownership models built on top of the core actor abstractions
- framework packs such as ISO 27001, ENS, NIS2, and RGPD/LOPDGDD
- reporting and dashboard modules
- connectors and automated evidence collection
- advanced workflows, rules engines, imports, exports, and marketplace capabilities

Boundary rule:

- if a capability is required to operate the platform itself, host plugins, or provide cross-cutting generic infrastructure, it belongs in the `CORE`
- if a capability expresses business domain behavior, regulatory content, provider-specific integration, or replaceable product functionality, it belongs in a plugin

The system will be implemented as a modular monolith first. Core modules and plugin modules will run in a single deployable application process space while preserving strict architectural boundaries through contracts, namespaces, manifests, migrations, events, and public integration surfaces. This keeps development and operations simple in the initial phases without weakening the separation between core and plugin responsibilities.

Plugins must extend the platform only through approved contracts, events, and public APIs. Plugins must not modify core internals directly. Each plugin must declare its own manifest, dependencies, compatible versions, permissions, routes, migrations, seeds, translation resources, and scheduled tasks where applicable.

# Consequences

- The `CORE` remains smaller, more stable, and easier to version.
- Most product evolution happens through plugins, reducing pressure to change core contracts frequently.
- Identity remains swappable because the core depends on abstractions rather than a concrete provider.
- Compliance domains can evolve independently without redefining platform foundations.
- Framework packs can be versioned independently from the core and from each other.
- Reporting and connectors can be added or replaced without redesigning the base platform.
- The modular monolith approach lowers initial operational complexity while keeping clean extension boundaries.
- Strong contract design, plugin lifecycle management, and compatibility testing become mandatory platform responsibilities.
- Some features may require additional abstraction effort up front to prevent domain leakage into the core.

# Rejected Alternatives

1. Monolithic business application with domain logic in the core

This was rejected because it would make the core harder to evolve, reduce third-party extensibility, and conflict with the PRD principle that functional features should be pluggable.

2. Core with hardcoded identity and user management

This was rejected because the PRD requires the core to be identity-provider-agnostic and to separate access identity from functional business ownership.

3. Microservices-first architecture

This was rejected because the PRD explicitly targets a modular monolith first for faster delivery, simpler operations, and lower coordination overhead during early product phases.

4. Plugins allowed to patch or override core internals directly

This was rejected because it weakens stability, breaks upgradeability, and undermines compatibility guarantees between the core and plugins.

5. Framework-specific compliance model embedded in the core

This was rejected because framework content is domain-specific and must remain independently versioned and replaceable as plugin-delivered functionality.

# Open Questions

- Which plugin manifest fields are mandatory in v1, and which remain optional until later milestones?
- What compatibility policy will be enforced between core versions and plugin versions beyond semantic versioning?
- Which minimal core workflow features are required before advanced workflow behavior is delegated entirely to plugins?
- What test harness and contract-validation strategy will be required before third-party plugins are considered supported?
