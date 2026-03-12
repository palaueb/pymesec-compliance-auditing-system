# Title

ADR-009: Core Tenancy Model Based on Organizations and Scopes

# Status

Accepted

# Context

The PRD requires the core to provide multi-organization and multi-tenant foundations, with organizations and scopes explicitly included in the milestone-one core deliverables. The same PRD also requires:

- permission evaluation by organization and scope
- plugin-delivered domain models such as assets, controls, risks, privacy, policies, and exceptions
- support for language and locale at organization or user level
- auditability and traceability with tenant context

ADR-001 places cross-cutting infrastructure in the core and domain capabilities in plugins. ADR-003 and ADR-004 already depend on organization-aware memberships and scope-aware authorization, but there has not yet been a formal ADR defining the tenancy boundary itself.

The platform therefore needs an architectural decision that fixes the tenant boundary before official domain plugins are designed.

# Decision

The platform will adopt a `core tenancy model` based on:

- `organization` as the primary tenant boundary
- `scope` as the optional bounded partition inside one organization

The core owns these tenancy primitives and the rules that govern them.

`Organization` is the authoritative top-level boundary for:

- memberships
- tenant settings
- tenant-aware authorization context
- tenant-aware plugin data attachment
- tenant-aware audit context

`Scope` is a narrower operational boundary within one organization and may be used to partition access, assessments, data sets, or other bounded working areas without replacing the organization as the main tenant boundary.

Boundary rule:

- the core defines tenancy primitives
- plugins consume and extend them
- plugins must not redefine tenant identity with private incompatible models

This means domain-specific structures such as:

- business units
- legal entities
- asset perimeters
- compliance applicability groups
- responsibility registries

may be modeled by plugins, but they must map onto core organizations and optional scopes rather than replacing them.

Data attachment rule:

- platform infrastructure data may be platform-global where justified
- tenant business data is organization-bound by default
- narrower records may be scope-bound within one organization

Lifecycle rule:

- organization and scope lifecycle is governed by the core
- archival or disabled state is preferred over default hard deletion until retention and purge policy is explicitly defined

# Consequences

- The platform gets one stable tenant boundary that all plugins can build on.
- Authorization, locale defaults, and audit context share the same organizational model.
- Domain plugins can model richer structures without forcing business semantics into the core.
- Plugin authors must be explicit about whether data is platform-global, organization-bound, or scope-bound.
- Future plugins for assets, risks, controls, policies, privacy, or compliance management can remain decoupled while still interoperating on one tenancy substrate.

# Rejected Alternatives

1. No formal tenancy model in the core, leaving each plugin to define its own organization semantics

This was rejected because permissions, auditability, localization, and shared platform administration require a common tenant boundary.

2. Fully generic tenant abstraction with no explicit organization concept

This was rejected because the PRD and product language are organization-centric, and the first platform users need understandable administrative concepts.

3. Scope as the primary tenant boundary with organizations optional

This was rejected because memberships, settings, and most plugin business records need a stable top-level tenant boundary.

4. Rich business hierarchy embedded directly in the core

This was rejected because legal structures, business units, and domain perimeters vary by use case and belong in plugins.

5. Hard deletion as the default lifecycle for organizations and scopes

This was rejected because traceability, auditability, and retention concerns require a safer default lifecycle.

# Open Questions

- What minimum metadata should `OrganizationReference` and `ScopeReference` expose in v1?
- Which cross-organization operations, if any, should be supported by official platform policy?
- How should archival interact with plugin data that still references an organization or scope?
- Which tenant configuration settings belong in the core from v1 versus in plugins?
