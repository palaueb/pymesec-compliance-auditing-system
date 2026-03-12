# Title

ADR-011: Core-Owned UI Shell and Extensible Menu System

# Status

Accepted

# Context

The PRD requires the core to provide a UI shell / admin shell and an extensible menu system. The same PRD requires plugins to extend the platform without modifying the core directly, and it identifies UI plugins as valid plugin types. ADR-001 already places the UI shell and menu system in the core, but there has not yet been a dedicated ADR defining the boundary between shared shell infrastructure and plugin-contributed navigation.

ADR-006 also establishes that routes and menu contributions should be governed through manifest-backed extension points, and ADR-007 requires translation ownership to remain component-scoped.

The platform therefore needs an architectural decision that fixes the shell and menu ownership model before plugin UI surfaces expand.

# Decision

The platform will use a `core-owned UI shell` with an `extensible menu registry`.

The core owns:

- the shared application frame
- navigation slots and rendering semantics
- permission-aware shell integration
- tenancy-aware context framing
- lifecycle-aware inclusion or removal of plugin navigation

Plugins may contribute menu items and routes through approved contracts, but they do not own the shell frame itself.

Menu contribution rules:

- menu items are component-owned
- plugin menu items are contributed through core-governed registration
- menu labels must use component-scoped translation keys
- visibility must be permission-aware
- disabling a plugin removes its active menu contributions

Boundary rule:

- the core defines shell structure and slot semantics
- plugins define their own navigation entries inside those approved extension points
- plugins must not bypass the shell with private incompatible navigation structures presented as platform-standard UI

The menu system is a navigation registry, not an authorization system. Hidden items do not replace route-level access control.

# Consequences

- The platform gets a stable shared UI frame while preserving plugin extensibility.
- Navigation remains consistent, locale-aware, and permission-aware across core and plugin surfaces.
- Plugin navigation becomes governable through manifests and lifecycle state rather than runtime ad hoc code.
- The core avoids absorbing domain-specific navigation and keeps the shell generic.
- The project will need explicit slot definitions, menu validation rules, and shell contracts for future UI implementation.

# Rejected Alternatives

1. Each plugin renders its own independent shell and top-level navigation with no shared platform frame

This was rejected because the product needs one coherent administrative experience and one stable integration model.

2. Hardcoded core menus for all official capabilities

This was rejected because most functional capabilities belong in plugins and must remain independently extensible.

3. Menu visibility used as the primary security mechanism

This was rejected because navigation visibility is not a substitute for authorization checks on routes and actions.

4. Plugins freely inventing arbitrary shell regions with no slot governance

This was rejected because it would erode consistency and make the shared UI harder to evolve safely.

5. Storing plugin menu labels directly in core translation files

This was rejected because translation ownership must remain aligned with component ownership.

# Open Questions

- Which slot set should be considered the stable baseline for v1 UI implementation?
- How much shell customization should official plugins be allowed before it becomes a new extension contract?
- Which menu validation failures should block plugin enablement versus only disable the invalid contribution?
- What user-level navigation personalization, if any, should be supported later without weakening platform consistency?
