# Title

Translation Files Convention v1

# Status

Draft

# Context

The PRD requires multilingual UI support from the core, with English as the primary product language and four official initial languages: English, Spanish, French, and German. The PRD also requires:

- the core to maintain its own translation files
- each plugin to maintain its own translation files
- translation files to use simple editable formats such as JSON or similar
- a common convention for translation structure and keys
- stable, semantic keys
- avoidance of hardcoded literal UI text in business code or difficult-to-maintain templates

The platform therefore needs a shared translation file convention that is simple for contributors, compatible with the `CORE + plugins` architecture, and stable enough for long-term evolution.

# Specification

## 1. Objectives

This convention defines:

- supported locale identifiers
- file naming and directory conventions
- translation key namespaces
- loading behavior for core and plugins
- fallback behavior
- contributor guidance for editing and extending translations

This specification governs UI and user-facing platform text. It does not define localization rules for date, time, number, or currency formatting beyond their relationship to locale selection.

## 2. Official Languages

The official initial languages in v1 are:

- English: `en`
- Spanish: `es`
- French: `fr`
- German: `de`

English is the primary language and the source language for v1.

Rules:

- every official core release must provide English translations
- official plugins should provide translations for `en`, `es`, `fr`, and `de`
- community plugins must provide at least `en` and may add other locales over time

## 3. Translation Ownership

Translation ownership follows component boundaries:

- the core owns its own translation files
- each plugin owns its own translation files

Rules:

- the core must not store plugin translations inside core translation files
- a plugin must not write translations into the core namespace
- plugin translations must remain removable with the plugin lifecycle

## 4. Supported File Format

Translation files must use simple human-editable formats.

Approved v1 format:

- JSON

Other formats may be added later by explicit platform decision, but v1 standardization is based on JSON to keep contribution and validation simple.

Rules:

- one locale per file
- UTF-8 encoding
- flat key-value JSON object structure in v1
- no executable logic inside translation files

## 5. Directory Conventions

Recommended component-local directory layout:

- core translations: `core/resources/lang/`
- plugin translations: `plugins/<plugin-id>/resources/lang/`

Each component stores one file per locale:

- `en.json`
- `es.json`
- `fr.json`
- `de.json`

Rules:

- translation files must be stored inside the component that owns them
- file names must use lowercase ISO-style locale codes for v1
- locale variants such as `en-GB` or `es-MX` may be supported later, but the baseline official files use `en`, `es`, `fr`, and `de`

## 6. Namespace Conventions

Translation keys must be stable, semantic, and namespaced by owning component.

Recommended key patterns:

- core keys: `core.<area>.<item>`
- plugin keys: `plugin.<plugin-id>.<area>.<item>`

Examples:

- `core.nav.dashboard`
- `core.actions.save`
- `core.permissions.manage`
- `plugin.controls.nav.controls`
- `plugin.controls.fields.control_code.label`
- `plugin.reporting.actions.export_pdf`

Rules:

- keys must describe meaning, not English sentence text
- keys must not depend on UI position such as `left_menu_item_1`
- keys must not encode temporary implementation details
- keys must remain stable across copy changes whenever the meaning stays the same

## 7. Key Structure Conventions

Keys should group text by functional area rather than by page fragments only.

Recommended segments:

- component namespace
- feature area
- object or field
- text purpose

Recommended suffixes:

- `.label`
- `.help`
- `.hint`
- `.description`
- `.title`
- `.subtitle`
- `.empty`
- `.success`
- `.error`
- `.confirm`

Examples:

- `core.auth.login.title`
- `core.organizations.fields.name.label`
- `plugin.controls.fields.status.help`
- `plugin.evidence.messages.upload.success`

## 8. Loading Rules

Translation loading must follow component ownership and runtime enablement.

Core loading rules:

- core translations are always loaded and available

Plugin loading rules:

- plugin translations are loaded only for installed and enabled plugins
- disabled plugins must not contribute active translation resources to the runtime UI
- translation discovery must be driven by plugin registration or manifest metadata, not by blind filesystem crawling alone

Resolution rules:

- when resolving a key, the system first uses the declared component namespace of that key
- a `core.*` key resolves only against core translation resources
- a `plugin.<plugin-id>.*` key resolves only against that plugin’s translation resources

This prevents accidental key collisions and keeps translation ownership explicit.

## 9. Fallback Rules

Fallback behavior in v1 is:

1. requested locale for the owning component
2. English `en` for the owning component
3. visible missing-key fallback according to platform policy

Rules:

- English is the universal fallback locale
- fallback must stay inside the same owning component
- the platform must not silently substitute one plugin’s text from another plugin or from core
- missing translations should be observable during development and test environments

Recommended missing-key display behavior:

- render the key itself or a platform-defined missing-translation marker

## 10. Core and Plugin Responsibilities

### Core Responsibilities

The core is responsible for:

- locale selection infrastructure
- translation loading orchestration
- fallback handling
- common validation rules
- translation key resolution conventions
- developer guidance and tooling policy

### Plugin Responsibilities

Each plugin is responsible for:

- maintaining its own translation files
- using only its own translation namespace
- shipping at least English translations
- keeping keys stable across plugin releases when meaning does not change
- updating translations when UI text or features change

## 11. Contribution Guidelines

Community contribution must be low-friction.

Contributor rules:

- edit translation JSON files directly
- preserve existing keys unless the meaning has truly changed
- add new keys rather than repurposing old keys for different semantics
- keep English text clear and concise because it is the source language
- keep translations semantically aligned across locales
- do not embed HTML, markdown-heavy formatting, or business logic unless explicitly supported by UI rendering policy
- do not sort keys differently per locale if project tooling expects consistent ordering

When changing translations:

- if only wording changes and meaning stays the same, keep the same key
- if meaning changes, create a new key and deprecate the old one through normal cleanup policy
- if a plugin is split or renamed, translation namespace changes must be handled through an explicit migration strategy

## 12. Validation Guidelines

Translation validation in v1 should verify:

- valid JSON format
- one file per declared locale
- no duplicate keys within a file
- namespace compliance for component ownership
- presence of English keys for every component
- optional completeness checks for official locales

Recommended quality checks:

- detect missing official locale entries for official components
- detect orphan keys that are no longer referenced
- detect placeholder mismatches across locales

## 13. Placeholder Conventions

If a translation string includes runtime placeholders, placeholder names must be semantic and stable.

Examples:

- `{organization_name}`
- `{plugin_name}`
- `{control_code}`

Rules:

- placeholder names must be identical across locales for the same key
- placeholders must not depend on positional ordering alone
- translators should be able to move placeholders within a sentence as needed for grammar

## 14. Examples

Example core entries:

- `core.nav.dashboard`
- `core.actions.save`
- `core.actions.cancel`
- `core.plugins.manage.title`

Example plugin entries:

- `plugin.controls.nav.controls`
- `plugin.controls.fields.owner.label`
- `plugin.reporting.actions.export_pdf`
- `plugin.privacy.incidents.empty`

# Consequences

- The core and plugins can evolve independently without mixing translation ownership.
- Contributors can update text using simple JSON files without editing application logic.
- Stable namespaces and key conventions reduce collisions and make tooling easier to build.
- English remains the reliable source and fallback language across the platform.
- Official multilingual coverage becomes measurable for core and official plugins.

# Open Questions

- Should locale variants such as `en-GB`, `es-ES`, or `fr-CA` be allowed in v1 or deferred to a later version?
- Should official plugins be required to ship complete `es`, `fr`, and `de` files before release, or can partial translation coverage be accepted temporarily?
- What exact project tooling should validate key completeness and placeholder consistency?
- Should translation deprecation rules be documented in a separate contributor guide or folded into this spec later?

# Acceptance Constraints

- English must be the primary language.
- The official initial languages must be English, Spanish, French, and German.
- The core must have its own translation files.
- Each plugin must have its own translation files.
- Translation files must use simple editable formats such as JSON.
- The convention must define naming, namespaces, loading rules, fallback rules, and contributor guidance.
- The convention must remain compatible with the `CORE + plugins` architecture.
