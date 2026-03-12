# Title

ADR-004: Core Permission Engine for Access Control

# Status

Accepted

# Context

The PRD requires the core to provide a permission and policy engine decoupled from identity providers, with support for multi-organization and scope-aware access control. The PRD also requires strict separation between:

- platform access identity
- functional domain ownership and business responsibility

ADR-001 defines the platform as a minimal `CORE + plugins` architecture, where cross-cutting infrastructure belongs in the core and business capabilities remain pluggable. ADR-003 defines that permissions are part of platform access control and must not be conflated with functional ownership roles such as control owner, risk owner, approver, or auditor in the business sense.

This ADR defines the permission engine as a core capability that can be consumed by multiple identity plugins and extended by domain and infrastructure plugins.

# Decision

The platform will implement a `core permission engine` responsible for access-control decisions across UI, API, administrative operations, and plugin-exposed capabilities.

The permission engine is an access-control mechanism, not a business ownership model.

Permissions answer questions such as:

- may this principal view a screen, API resource, or plugin feature
- may this principal create, update, approve, export, administer, or delete within a given context
- may this principal perform an operation within a given organization or scope

Permissions do not answer questions such as:

- who owns this control
- who is responsible for this risk
- who is the approver in a business workflow

Those concerns remain part of functional actor and domain ownership models as defined in ADR-003.

The core permission engine will provide:

- a stable permission definition model
- registration of permissions from the core and from plugins
- organization-aware and scope-aware authorization evaluation
- evaluation against access principals and memberships
- policy hooks for resource or operation-level decisions
- a common result model for allow, deny, or unresolved outcomes
- auditability for sensitive authorization changes and permission-related administration

Plugins must be able to register permissions through public extension contracts. Registered permissions must use stable names and metadata so they can be surfaced consistently in administration UI, APIs, documentation, and tests.

Authorization evaluation must support context-aware resolution, including:

- organization or tenant
- scope
- plugin-defined resource type or operation
- principal identity and membership context

The core permission engine must remain compatible with multiple identity plugins. To achieve this, authorization must depend on core abstractions such as principals and memberships rather than a concrete user implementation. Identity plugins may authenticate users and supply principal context, but they must not redefine the core permission model.

The engine may support role-based grouping, direct grants, and policy-based checks, but these remain authorization constructs. They must not become implicit carriers of domain ownership semantics.

Plugins may declare and consume permissions for their own capabilities, but they must not bypass the core engine with private authorization models for platform access decisions. Plugin-specific rules may add detail to evaluation, yet final platform authorization must remain interoperable with the core permission contracts.

# Consequences

- Access control becomes consistent across the core and plugins.
- Multiple identity plugins can coexist because authorization relies on principal abstractions rather than a hardcoded user model.
- Plugins can expose capabilities safely by registering permissions through a common contract.
- Organization and scope-aware authorization can be enforced uniformly across UI and API surfaces.
- Functional ownership remains cleanly separated from permissions, reducing ambiguity in workflows and administration.
- The platform will need explicit permission naming conventions, registration metadata, and testing rules.
- Some plugin features may require layered evaluation where core permissions and plugin-specific policy checks both participate.

# Rejected Alternatives

1. Each plugin implements its own standalone authorization model

This was rejected because it would create inconsistent access behavior, fragment administration, and undermine the role of the core as provider of shared platform infrastructure.

2. Permission model embedded inside identity plugins

This was rejected because the PRD requires the core to remain identity-provider-agnostic and compatible with multiple identity implementations.

3. Functional ownership used directly as authorization

This was rejected because ownership and responsibility are business concepts, not access-control concepts. Conflating them would violate ADR-003 and create incorrect privilege assumptions.

4. Global permissions without organization or scope context

This was rejected because the PRD requires multi-organization support and scope-aware evaluation. A flat permission model would be too coarse for the platform’s tenancy and assessment use cases.

5. Hardcoded core permission list with no plugin registration

This was rejected because plugins must be able to expose and govern their own capabilities without modifying core code.

# Open Questions

- What canonical permission naming convention should be enforced across core and plugins in v1?
- What minimum metadata is required when a plugin registers a permission?
- How should unresolved authorization results be handled when multiple plugins participate in policy evaluation?
- Which administrative actions around permission grants, revocations, and role changes must always generate audit events?
