# Title

Menu System and UI Shell v1

# Status

Draft

# Context

The PRD requires the core to provide:

- a UI shell / admin shell
- an extensible menu system
- extensible routing
- support for core and plugin translations
- a plugin architecture in which UI capabilities can be contributed without modifying the core directly

ADR-001 places the UI shell and extensible menu system in the core. ADR-006 establishes the plugin manifest as the authoritative declaration point for routes and menu contributions. ADR-007 establishes component-scoped translation ownership, which directly affects menu labels and shell text. The platform therefore needs a shared UI-shell and menu model that:

- lets the core define the common navigation frame
- lets plugins contribute navigation safely and declaratively
- keeps menu visibility permission-aware and lifecycle-aware
- avoids hardcoding product-domain navigation into the core

# Specification

## 1. Objectives

This specification defines:

- the responsibilities of the core UI shell
- the structure of the extensible menu system
- how plugins contribute menu entries
- visibility and permission rules
- what belongs in the core versus in plugins

## 2. Core UI Shell Responsibilities

The core UI shell is the shared application frame for the modular monolith.

In v1, the shell is responsible for:

- top-level application layout
- navigation regions and menu slots
- common header and contextual navigation framing
- organization and scope context display
- locale-aware shell text rendering
- plugin-aware route entry points
- permission-aware visibility handling for shared navigation

The v1 runtime exposes two separate shell entrypoints:

- `/app` for functional product workspaces contributed mainly by plugins
- `/admin` for core administration and platform operations

Core administration must not be mixed into the default product landing experience.

The `/app` shell should open on a lightweight workspace dashboard before sending the user into one specific module. That landing view may be core-owned, but it must summarize operational work and route the user into plugin workspaces rather than platform administration.

The core UI shell is not responsible for:

- domain-specific forms
- plugin-specific dashboards as a mandatory requirement for every plugin
- framework-specific navigation logic
- domain-specific page composition beyond shell integration

Those concerns belong to plugins.

The core may still provide a minimal cross-workspace dashboard for `/app` that highlights the current organization context, recent audit activity, and quick links into visible work areas.

## 3. Menu System Model

The menu system is the structured registry of navigation contributions rendered inside the UI shell.

Each menu contribution should declare, at minimum:

- menu item identifier
- owning component
- target route reference
- label translation key
- optional parent menu identifier
- ordering hint
- visibility metadata

Menu items are registered through core-managed extension points, and plugin-provided items must be removable when the plugin is disabled.

## 4. Menu Hierarchy

The core shell owns the navigation frame, but plugins may create:

- top-level items
- one level of child items under a top-level item

Rules:

- top-level items are allowed for both the core and plugins
- child items may be attached to a parent owned by the same plugin
- child items may also be attached to a core-owned or other-plugin-owned parent only when the parent exists and the contributing plugin declares an explicit dependency on that other plugin
- deeper nesting is not allowed in v1

This keeps the menu flexible enough for modular growth while limiting navigation sprawl and invalid cross-plugin coupling.

## 5. Shell Rendering Responsibility

The core may still render different shell regions internally, but plugin contracts do not target arbitrary free-form slots in v1.

Rules:

- plugins register menu items and parent relationships, not custom shell regions
- the core remains free to render top-level and child items inside the left navigation and contextual shell areas according to platform design
- plugins must not invent private shell regions that bypass the core shell model

## 6. Menu Contribution Ownership

Menu ownership follows component boundaries.

Rules:

- the core owns core menu items
- each plugin owns its own contributed menu items
- plugin menu entries must use plugin-owned translation keys
- disabling a plugin removes its active menu contributions from runtime navigation
- menu contributions must be discoverable from manifest-backed registration, not only from runtime side effects

## 7. Visibility and Authorization Rules

Menu visibility must be permission-aware.

Rules:

- a menu item may declare one required permission or a visibility rule reference
- hidden navigation is not a substitute for backend authorization
- routes must still enforce authorization independently of menu visibility
- items with unmet visibility rules must not appear in the rendered menu for the active principal and tenancy context

Visibility evaluation may use:

- current principal
- current memberships
- organization context
- scope context
- enabled-plugin state

## 8. Routing Relationship

Menu items reference routes; they do not replace route registration.

Rules:

- a menu item target must refer to a core or plugin route already registered through approved contracts
- menus must not point to private or disabled plugin routes
- route and menu metadata should remain consistent through manifest validation

## 9. Translation Rules

Menu and shell text must follow the shared internationalization model.

Rules:

- menu labels must be translation keys, not hardcoded literal text
- core shell labels use the `core.*` namespace
- plugin menu labels use the `plugin.<plugin-id>.*` namespace
- missing translation behavior follows the component-scoped translation policy

## 10. Tenancy and Context Awareness

The UI shell is tenancy-aware.

Minimum v1 expectations:

- the shell can display the active organization context
- the shell can display the active scope context when relevant
- menu visibility may vary by organization or scope according to permission and policy evaluation

The shell provides context framing, but it does not define domain-specific organization hierarchies or workflows.

## 11. Theme Foundation

The visual theme layer belongs to the core shell, not to plugins.

Rules:

- the core may expose approved themes through design tokens such as colors, typography, spacing, and shell presentation variables
- plugins must not inject arbitrary shell CSS or redefine the visual contract of shared navigation
- theme switching must remain safe and core-governed

This keeps design personalization possible without turning visual customization into an uncontrolled plugin surface.

## 12. Plugin Responsibilities

Plugins are responsible for:

- contributing routes and menu metadata through approved contracts
- providing translated labels for their navigation items
- keeping menu entries aligned with the permissions and routes they expose
- declaring explicit plugin dependencies before attaching child items beneath other plugins
- avoiding assumptions about shell rendering beyond the published hierarchy contract

Plugins may contribute:

- domain navigation entries
- plugin administration entries
- contextual entries for their own enabled capabilities

But the core remains the owner of the shared shell frame.

## 13. Failure and Lifecycle Rules

Menu rendering must be resilient to plugin lifecycle changes.

Rules:

- if a plugin is disabled, its menu items must disappear from active navigation
- invalid menu definitions should fail validation and not degrade the whole shell
- invalid cross-plugin parent references must be rejected by default
- the shell must tolerate plugins with no menu contributions

## 14. Menu Definition Guidance

Recommended v1 menu fields are:

- `id`
- `label_key`
- `route`
- `parent_id`
- `icon`
- `order`
- `permission`

The core may ignore unsupported fields and reject unsafe or inconsistent references.

## 15. Out of Scope in v1

This v1 specification does not yet define:

- visual design system details
- third-party theming APIs beyond approved core-governed theme tokens
- plugin-contributed custom layout regions outside approved slots
- drag-and-drop user-customized navigation
- marketplace or plugin-install UI

# Consequences

- The platform gets a stable navigation frame that remains core-owned while still being plugin-extensible.
- Plugin navigation becomes governable through manifests and runtime validation.
- Permission-aware and locale-aware navigation can be enforced consistently across core and plugin UI surfaces.
- The core avoids absorbing domain-specific navigation logic that belongs to plugins.
- The project will need a menu registry, shell slot model, and validation rules to keep route, translation, and permission references coherent.
