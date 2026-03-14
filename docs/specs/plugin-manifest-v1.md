# Title

Plugin Manifest Specification v1

# Status

Draft

# Context

The PRD defines the platform as a `CORE + plugins` system where plugins are first-class delivery units for domain capabilities, identity providers, framework packs, reporting, connectors, and automation. ADR-001 requires plugins to extend the platform only through approved contracts, events, APIs, and lifecycle mechanisms. ADR-004 requires plugins to register permissions through core extension points rather than private authorization models.

The platform therefore needs a stable plugin manifest format that allows the core to discover, validate, install, enable, disable, upgrade, and operate plugins consistently. The manifest must be implementation-ready for an initial modular monolith while remaining language-agnostic enough to evolve if packaging or runtime details change later.

# Specification

## 1. Purpose

The plugin manifest is the authoritative metadata document for a plugin package. It declares:

- what the plugin is
- which platform and plugin versions it is compatible with
- what other plugins or capabilities it depends on
- which permissions, routes, menus, migrations, translations, and lifecycle hooks it contributes

The core must treat the manifest as the first source of truth during plugin discovery and lifecycle operations.

## 2. Manifest Scope

Each plugin must provide exactly one manifest at its package root.

The manifest describes metadata and declared integration surfaces. It does not replace:

- plugin runtime configuration
- translation content files
- migration files
- route handler implementations
- menu rendering logic

Those assets remain inside the plugin package and are referenced by the manifest.

## 3. Manifest Model

The manifest must support the following top-level sections in v1:

- `plugin`
- `compatibility`
- `dependencies`
- `permissions`
- `routes`
- `menus`
- `admin`
- `support`
- `migrations`
- `translations`
- `lifecycle`

Unknown fields must be ignored by v1 parsers unless a later compatibility policy marks them as invalid. This allows forward evolution without immediately breaking older cores.

## 4. `plugin` Section

This section identifies the plugin and its basic metadata.

Required fields:

- `id`: globally unique stable plugin identifier
- `name`: human-readable plugin name
- `version`: plugin version using semantic versioning
- `type`: plugin category

Recommended fields:

- `description`: short human-readable summary
- `vendor`: organization or maintainer identifier
- `homepage`: documentation or project URL
- `license`: declared license identifier

Constraints:

- `id` must be stable across releases
- `id` must be unique across the plugin ecosystem
- `version` must change on every releasable plugin update
- `type` must be one declared plugin category, such as `identity`, `domain-actor`, `domain`, `framework-pack`, `connector`, `reporting`, `automation`, `ui`, or `backend`

## 5. `compatibility` Section

This section defines the versions of the platform contracts required by the plugin.

Required fields:

- `core`: compatible core version range

Optional fields:

- `sdk`: compatible plugin SDK version range
- `api`: compatible public platform API version range

Constraints:

- compatibility must be expressed as version ranges, not a single exact version only
- the core must reject installation or enablement if compatibility rules are not satisfied
- compatibility evaluation must happen before dependency resolution is considered successful

## 6. `dependencies` Section

This section declares other plugins or capabilities required or optionally consumed by the plugin.

Supported dependency categories:

- required plugin dependencies
- optional plugin dependencies
- conflicting plugins

Each dependency entry should declare:

- target plugin identifier
- compatible version range
- dependency type: `required`, `optional`, or `conflicts`

Constraints:

- a plugin cannot be enabled if any required dependency is missing or incompatible
- optional dependencies must not block installation
- conflicting plugins must prevent simultaneous enablement
- cyclic required dependencies should be treated as invalid

## 7. `permissions` Section

This section declares access-control permissions contributed by the plugin.

Each permission entry must declare:

- stable permission key
- human-readable label
- short description
- default applicability metadata

Recommended metadata:

- resource or feature area
- operation type such as `view`, `create`, `update`, `delete`, `approve`, `export`, `admin`
- scope applicability

Constraints:

- permission keys must be stable and namespaced by plugin identifier
- permissions are access-control concepts only
- permissions must not encode domain ownership semantics
- permission definitions must be registerable by the core permission engine during plugin install or enable

## 8. `routes` Section

This section declares platform routes contributed by the plugin.

Supported route categories:

- UI routes
- API routes
- background or webhook endpoints where allowed by platform policy

Each route entry should declare:

- route identifier
- route category
- path or route pattern
- operation or method metadata
- target handler reference
- permission or policy reference if protected

Constraints:

- route declarations must remain within plugin namespace conventions
- routes must not shadow core routes unless the platform explicitly supports an extension contract for that case
- route protection must integrate with the core permission engine

If a route entry declares `permission`, the core must attach the authorization middleware for that permission to the route group loaded from that manifest entry.

## 9. `menus` Section

This section declares menu contributions to the UI shell.

Each menu entry should declare:

- menu item identifier
- target route reference
- label translation key
- shell area: `app` or `admin`
- optional parent menu identifier
- ordering hint
- visibility rule or permission reference

Constraints:

- menu labels must use translation keys, not hardcoded UI text
- menu visibility must be permission-aware
- menu contributions must be removable when the plugin is disabled
- menu area must default to `app` when omitted
- `admin` menu entries must render only inside the `/admin` shell
- top-level items are allowed
- child items may target the core or another plugin only when platform policy and declared dependencies allow it
- child menu entries must use the same area as their declared parent
- v1 menu hierarchy is limited to one child level below top-level navigation

## 9.1 `admin` Section

This optional section declares core-admin entrypoints related to the plugin.

Initial v1 support:

- `settings_menu_id`: shell menu id that opens the plugin-owned configuration screen

Constraints:

- the referenced menu id must belong to the same plugin
- the core may expose this as a convenience link in `Administration > Plugins`
- the core must not treat this field as a generic plugin settings schema
- if the current context cannot access the referenced menu, the shell should show that additional workspace context is required

## 9.2 `support` Section

This optional section declares plugin-owned support content contributed to the aggregated help experience.

Initial v1 support:

- `path`: relative path to support JSON files
- `supported_locales`: locales available in that support path

Constraints:

- support content must be contributed in JSON documents
- support content must be removable when the plugin is disabled
- the core may fall back to English if the requested locale is not available

## 10. `migrations` Section

This section declares the plugin’s data evolution assets.

Each migration declaration should include:

- migration identifier
- ordered execution reference
- asset location

Optional metadata:

- reversible flag
- data namespace

Constraints:

- plugin migrations must be isolated from core migrations
- plugin data must remain namespaced to avoid collisions
- the core must be able to determine which migrations belong to which plugin version
- uninstall behavior must not assume destructive data deletion by default

## 11. `translations` Section

This section declares the translation resources owned by the plugin.

Each plugin must maintain its own translation files.

The manifest must declare:

- supported locales
- base translation path or resource root
- default locale for the plugin package if needed for validation

Constraints:

- translation resources must be plugin-local, not stored in the core
- files must use a simple editable format such as JSON or equivalent platform-approved formats
- translation keys must follow the common project convention and remain stable
- the manifest must allow the core to discover plugin translations for English, Spanish, French, and German from v1 onward for official plugins

## 12. `lifecycle` Section

This section declares the lifecycle hooks exposed by the plugin package for use by the core plugin manager.

Lifecycle operations in v1 are:

- install
- enable
- disable
- upgrade
- uninstall

For each supported lifecycle operation, the manifest may declare:

- handler reference
- execution phase if the platform supports pre and post stages
- idempotency expectations

Constraints:

- lifecycle hooks must execute only through the core plugin manager
- lifecycle hooks must not mutate core internals outside approved contracts
- `disable` must leave the core in a consistent state without requiring data deletion
- `upgrade` must be evaluable against both compatibility and migration metadata
- `uninstall` behavior must be explicit about whether data is preserved, detached, or eligible for removal

## 13. Validation Rules

The core manifest validator must, at minimum, verify:

- presence of all required sections and required fields
- uniqueness and format validity of plugin identifier
- semantic validity of plugin version
- compatibility range syntax
- dependency graph validity
- uniqueness of declared permission keys within the plugin
- consistency between declared routes, menus, translations, and referenced identifiers
- lifecycle hook declarations only reference supported operations

Validation failure must prevent enablement. Installation behavior may store the package but must not treat an invalid manifest as active.

## 14. Operational Semantics

Manifest usage across the plugin lifecycle:

- discovery: identify plugin package and basic metadata
- install: validate manifest, compatibility, dependencies, permissions, migrations, and translation declarations
- enable: register routes, menus, permissions, translations, and event-driven capabilities
- disable: unregister runtime contributions while preserving platform integrity
- upgrade: compare previous and new manifest versions, then apply compatibility and migration checks
- uninstall: remove plugin registration according to lifecycle policy and data retention rules

## 15. Versioning Policy

This document defines manifest `v1`.

Evolution rules:

- additive fields are preferred over breaking structural changes
- breaking manifest changes require a new manifest version
- plugin packages should declare which manifest version they target

# Consequences

- The core gains a predictable basis for plugin discovery, validation, and lifecycle management.
- Plugin authors have a stable checklist of required declarations before a plugin can be installed or enabled.
- Permission, route, menu, migration, and translation registration become governable through one common metadata contract.
- Translation ownership stays local to each plugin while remaining discoverable by the core.
- Future changes to packaging or runtime internals remain possible because the manifest defines contracts, not framework-specific implementation details.

# Open Questions

- What exact file name and location should be mandated for the manifest in v1?
- Should plugin `type` accept only a fixed enum in v1, or allow custom categories with advisory validation?
- Should translation declarations require all official locales for official plugins but only recommend them for community plugins?
- Should lifecycle hooks be fully optional, or should some operations require explicit declarations even when no-op?

# Acceptance Constraints

- The manifest spec must support plugin identity, versioning, compatibility, dependencies, permissions, routes, menus, migrations, translations, and lifecycle hooks.
- The manifest must remain compatible with the `CORE + plugins` architecture and the core permission engine.
- The spec must keep translation ownership inside each plugin.
- The spec must be implementation-ready without being tied to one framework-specific data structure.
- The spec must not define domain behavior as part of the core manifest contract.
