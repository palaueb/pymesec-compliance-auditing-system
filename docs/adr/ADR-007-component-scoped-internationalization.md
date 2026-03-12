# Title

ADR-007: Component-Scoped Internationalization and Translation Ownership

# Status

Accepted

# Context

The PRD requires multilingual support as a core responsibility and explicitly states that:

- the core must provide base internationalization and localization
- the system must support translation files owned by the core and by each plugin
- official initial languages are English, Spanish, French, and German
- language and locale must be definable per organization or user

ADR-001 establishes that the core owns cross-cutting infrastructure while plugins own their own functional capabilities. The translation-file specification further defines the conventions for locale files, namespaces, fallback rules, and ownership boundaries.

The platform therefore needs an architectural decision that fixes translation ownership and resolution boundaries before UI shell, menus, plugin navigation, and administrative surfaces grow further.

# Decision

The platform will use a `component-scoped internationalization model` in which the core owns translation infrastructure and each component owns its own translation resources.

Ownership rules:

- the core owns its own translation files and translation namespace
- each plugin owns its own translation files and translation namespace
- the core must not store plugin UI text inside core translation files
- plugins must not write or register translations into the core namespace

Initial official language policy:

- English `en` is the primary and fallback source language
- the official initial languages for core and official plugins are `en`, `es`, `fr`, and `de`

Resource format and layout policy:

- v1 standardizes on JSON translation files
- the core stores translations under its own component-local language directory
- each plugin stores translations under its own component-local language directory
- translation discovery is driven by component registration and plugin manifest metadata, not by blind global filesystem crawling

Resolution policy:

- `core.*` keys resolve only against core translation resources
- `plugin.<plugin-id>.*` keys resolve only against translation resources owned by that plugin
- disabled plugins must not contribute active translation resources to runtime resolution
- fallback remains inside the owning component: requested locale, then English, then visible missing-key behavior

Key policy:

- translation keys must be stable, semantic, and component-namespaced
- manifests, menus, and UI extension points should reference translation keys rather than hardcoded literal labels where the text is user-facing

Responsibility split:

- the core owns locale selection infrastructure, fallback behavior, loading orchestration, and validation rules
- plugins own their locale files, key stability, and completeness according to project policy

This decision governs user-facing product text. It does not define the full formatting model for dates, numbers, currency, or timezone presentation beyond requiring the core to provide the localization infrastructure those concerns will later use.

# Consequences

- Translation ownership remains aligned with component boundaries and plugin lifecycle.
- Disabled or removed plugins can be unloaded cleanly without leaving orphan runtime translation data in the core.
- UI shell, menus, and plugin-contributed screens can share one predictable naming and fallback model.
- Contributors get a low-friction editing format and a stable key convention.
- The project will need validation tooling for locale completeness, namespace compliance, and missing-key detection.
- Later locale and formatting decisions can build on a stable ownership model rather than retrofitting it after the UI expands.

# Rejected Alternatives

1. One global translation pool shared by core and plugins

This was rejected because it weakens ownership boundaries, makes plugin removal harder, and increases the chance of key collisions and accidental coupling.

2. Hardcoded user-facing text inside plugin code or templates

This was rejected because it conflicts with the PRD multilingual requirement and makes maintenance and community contribution harder.

3. Plugin translations stored under the core language tree

This was rejected because plugins must remain independently ownable, removable, and releasable.

4. Locale fallback across component boundaries

This was rejected because the system must not silently substitute plugin text from the core or from unrelated plugins.

5. Free-form translation key naming with no namespace discipline

This was rejected because long-term maintainability, plugin isolation, and validation depend on stable ownership-aware keys.

# Open Questions

- What exact locale selection precedence should apply when user, organization, and platform defaults all exist?
- How should plugin translation completeness be enforced differently for official plugins versus community plugins?
- Which validation rules should block release readiness versus only warn during development?
- What future strategy should govern locale variants such as `en-GB`, `es-MX`, or organization-specific terminology overlays?
