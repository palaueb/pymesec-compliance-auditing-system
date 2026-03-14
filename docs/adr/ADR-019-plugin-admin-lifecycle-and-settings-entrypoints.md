# Title

ADR-019: Plugin Lifecycle Management and Settings Entrypoints in the Shell

# Status

Accepted

# Context

ADR-006 establishes the manifest and the core plugin manager as the authoritative lifecycle contract, but the initial implementation still exposed lifecycle operations mainly through environment configuration and CLI commands. As the shell UI has become the primary operator surface for tenancy, identity, and compliance modules, plugin administration also needs a usable web workflow.

The platform now has three related concerns that must be made explicit:

- effective plugin activation state is derived from base configuration plus local overrides
- lifecycle operations must remain core-governed and auditable
- plugin-specific configuration should be reachable from the plugin administration experience without turning core into the owner of every plugin's internal settings model

At the same time, the runtime must stay safe when operators disable plugins that are still required by other enabled plugins.

# Decision

The shell `Plugins` screen becomes the primary operator UI for plugin lifecycle management in local and self-hosted deployments.

Lifecycle model:

- base activation still comes from `PLUGINS_ENABLED`
- local enable or disable changes are persisted in the plugin state store as overrides
- the effective enabled set is the result of `base config + local overrides`
- enable and disable actions remain core-governed operations
- every lifecycle action must be auditable

Dependency policy:

- plugin manifests may declare dependencies either as string ids or structured entries
- string entries are interpreted as `required`
- a plugin cannot be enabled if any required dependency is not effectively enabled
- a plugin cannot be disabled while another effectively enabled plugin still requires it
- if runtime state becomes inconsistent through manual configuration, the plugin manager must refuse to boot the dependent plugin and surface the dependency failure in status data

Settings entrypoint policy:

- plugin-specific configuration remains owned by the plugin itself
- the core `Plugins` screen may link to a plugin-owned configuration screen
- v1 uses an optional manifest field `admin.settings_menu_id` to declare that entrypoint
- if the current shell context cannot access that menu, the core should explain that additional workspace context is required instead of inventing fallback behavior

Scope of v1:

- lifecycle operations supported in the shell are `enable` and `disable`
- installation, upgrade, migration orchestration, and uninstall remain outside the shell scope for now
- plugin settings are linked, not re-modeled inside core

# Consequences

- platform admins can operate plugin lifecycle without leaving the shell
- the core keeps authoritative control of lifecycle state and auditability
- dependency-aware safeguards prevent obvious inconsistent states
- plugins keep ownership of their own settings UX while still being discoverable from administration
- the manifest contract grows slightly, but in a focused way that remains compatible with later packaging work

# Rejected Alternatives

1. Keep plugin lifecycle exclusively in CLI and environment variables

This was rejected because the shell is intended to be the primary operational UI for a usable installation.

2. Store plugin enablement directly in plugin-owned tables

This was rejected because lifecycle authority must remain in the core and must work even when a plugin is disabled.

3. Build one generic core-hosted settings schema for every plugin

This was rejected because plugins have heterogeneous configuration needs and already own their domain-specific UX.

4. Allow disabling dependencies and let the runtime fail later

This was rejected because avoidable invalid states should be blocked before persistence whenever possible.
