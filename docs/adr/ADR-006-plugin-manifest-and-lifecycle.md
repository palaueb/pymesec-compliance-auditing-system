# Title

ADR-006: Plugin Manifest and Lifecycle as the Authoritative Integration Contract

# Status

Accepted

# Context

The PRD defines the plugin system as a first-class platform capability and explicitly requires the following technical contracts to exist:

- plugin manifest
- lifecycle operations: install, enable, disable, upgrade, uninstall
- plugin-owned migrations and seeds
- route registration
- UI menu registration
- permission registration
- event subscription and publication
- scheduled tasks
- exposed APIs
- compatibility policies

ADR-001 establishes that plugins may extend the platform only through approved contracts, public APIs, events, manifests, and lifecycle mechanisms. ADR-005 further requires plugins to be independent release units with compatibility and traceability rules. The existing specifications also define the initial technical shape of:

- the plugin manifest format
- the local development flow for plugins
- the plugin release pipeline

The platform therefore needs one architectural decision that establishes the manifest and lifecycle model as core-governed contracts rather than incidental implementation details.

# Decision

The platform will treat the `plugin manifest` as the authoritative integration contract for every plugin package and the `core plugin manager` as the only valid lifecycle orchestrator.

Each plugin must provide exactly one manifest at the root of its package. In the coordinated repository layout, that package root is initially:

- `plugins/<plugin-id>/`

The manifest is the first source of truth for plugin discovery, validation, enablement, compatibility checks, and release readiness. The core must read manifest metadata before booting or exposing plugin capabilities.

In v1, the manifest is responsible for declaring the plugin's integration surfaces, including:

- identity and basic metadata
- compatibility ranges
- dependencies and conflicts
- permissions
- routes
- menu contributions
- migrations
- translations
- lifecycle handlers

The manifest defines declared capability, not runtime state. Installation status, enabled state, migration state, and execution history remain managed by the core lifecycle and persistence mechanisms.

Lifecycle rule:

- `install`, `enable`, `disable`, `upgrade`, and `uninstall` are core-governed operations
- plugins may contribute handlers for those phases, but handlers execute only through the core plugin manager
- plugins must not self-activate, self-upgrade, or mutate core state outside approved lifecycle contracts

Operational policy:

- discovery is manifest-driven
- compatibility validation happens before successful enablement
- required dependencies and conflicts must be evaluable from manifest data
- plugin routes, permissions, menus, migrations, translations, jobs, and other declared contributions must be registered through manifest-backed extension points
- unknown manifest fields are tolerated in v1 unless a later compatibility policy explicitly forbids them

Versioning policy:

- the manifest version is the plugin package version source of truth
- release pipelines must validate manifest integrity, compatibility, and version consistency before tagging a plugin release

Implementation guidance for the modular monolith phase:

- local development may use lightweight runtime declarations inside the manifest to let the core load a plugin directly from the monorepo
- this local runtime hook is a bootstrap convenience, not a replacement for the manifest's role as the stable package contract

# Consequences

- The plugin manager has one authoritative metadata source for discovery and validation.
- Plugin lifecycle becomes auditable, governable, and compatible with later release automation.
- Routes, permissions, menus, translations, and migrations can be registered consistently without plugin-specific boot hacks becoming the long-term contract.
- The platform can disable a plugin while preserving core consistency because the core remains in charge of activation state and declared surfaces.
- Independent plugin releases become easier to validate because the release unit has a clear metadata root.
- Manifest schema discipline becomes mandatory, and the project will need validation tooling to keep plugin metadata trustworthy.

# Rejected Alternatives

1. Plugin runtime classes as the primary source of integration metadata

This was rejected because discovery, compatibility, release validation, and safe lifecycle operations need a metadata contract that can be read before arbitrary plugin code is executed.

2. Ad hoc plugin registration through manual service-provider code only

This was rejected because it weakens consistency, makes release validation harder, and shifts platform contracts into scattered implementation details.

3. Multiple metadata files with no single authoritative manifest

This was rejected because lifecycle, packaging, and compatibility decisions require one canonical package contract.

4. Plugins managing their own enable or disable state internally

This was rejected because the core must remain the authority for safe lifecycle orchestration and plugin isolation.

5. Exact-version-only compatibility without declared ranges

This was rejected because ADR-005 and the release specifications require plugins to evolve independently when compatibility is preserved.

# Open Questions

- Which manifest fields become mandatory for third-party plugin support beyond the v1 official-plugin baseline?
- What exact validation policy should the core enforce for dependency cycles, conflicts, and unsupported top-level fields?
- How should lifecycle failures be recorded and recovered when a plugin partially completes an install, upgrade, or disable sequence?
- When packaging evolves beyond the modular monolith, which manifest runtime details remain local-development-only and which become distribution metadata?
