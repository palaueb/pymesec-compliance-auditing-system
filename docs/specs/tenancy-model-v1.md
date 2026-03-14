# Title

Tenancy and Organizational Scope Model v1

# Status

Draft

# Context

The PRD requires the core to provide:

- base multi-organization and multi-tenant support
- organizations and scopes as part of the core foundation
- permission evaluation by organization and scope
- plugin-capable domain modules that must remain separable from core infrastructure

ADR-001 places cross-cutting platform infrastructure in the core and domain capabilities in plugins. ADR-003 defines access identity and functional actors as distinct concepts. ADR-004 requires organization-aware and scope-aware authorization. The platform therefore needs a shared tenancy model that:

- gives the core a stable organizational boundary
- supports isolation of data and access evaluation
- remains neutral with respect to domain-specific business structures
- allows plugins to attach their own domain data to organizations and scopes without redefining tenancy rules

# Specification

## 1. Objectives

This specification defines:

- the core tenancy primitives
- how organizations and scopes relate
- how principals and memberships bind to tenancy
- how plugins must attach their data to tenancy boundaries
- what belongs in the core versus in domain plugins

## 2. Core Tenancy Primitives

The v1 tenancy model is based on two core primitives:

- `organization`
- `scope`

These are platform infrastructure concepts, not domain models.

### Organization

An `organization` is the primary tenant boundary of the platform.

An organization represents:

- the top-level ownership and segregation boundary for operational data
- the unit against which memberships, permissions, settings, and policy context are evaluated
- the default boundary for plugin-owned domain records unless a narrower scope is declared

An organization does not imply:

- legal entity modeling completeness
- business-unit hierarchy completeness
- asset structure completeness
- risk taxonomy completeness

Those richer business concepts belong to plugins.

### Scope

A `scope` is an optional bounded operational partition within one organization.

A scope may represent:

- a business unit
- a subsidiary perimeter
- an assessment perimeter
- a geography
- a technical environment
- another bounded working subset approved by platform policy

Rules:

- every scope belongs to exactly one organization
- a scope cannot exist without an organization
- scope identifiers are unique within the platform, but their meaning remains organization-local
- plugins may attach domain semantics to scopes, but the core treats them as bounded operational containers

## 3. Separation from Domain Structures

The core tenancy model is intentionally minimal.

The following belong in plugins, not in the core tenancy layer:

- legal responsibility registries
- business-unit catalogs beyond basic scope usage
- asset inventories
- data-flow maps
- compliance frameworks
- control catalogs
- policy documents and exceptions
- continuity plans
- risk registers
- compliance-management workflows

Those plugin models must attach to core organizations and optionally to scopes, but they must not replace the core tenancy primitives.

## 4. Membership and Access Binding

Memberships bind access identity to tenancy.

Rules:

- every membership belongs to one principal and one organization
- a membership may include zero or more scopes
- permissions may be granted at platform, organization, or scope level
- a principal may belong to multiple organizations through multiple memberships
- a principal may have different effective permissions in different organizations or scopes

The core must not assume that organization membership implies any functional ownership or business accountability.

## 5. Tenancy Attachment Rules for Core and Plugin Data

The platform recognizes the following attachment patterns:

- `platform-global`
- `organization-bound`
- `scope-bound`

### Platform-Global Data

Platform-global data is not tenant business data. It is infrastructure or catalog data needed by the platform itself.

Examples:

- core plugin registry metadata
- public compatibility metadata
- platform configuration defaults

Platform-global data must still respect security and administration controls, but it is not owned by one organization.

### Organization-Bound Data

Organization-bound data belongs to exactly one organization.

Examples:

- memberships
- organization locale settings
- tenant-level plugin settings
- most plugin business records by default

### Scope-Bound Data

Scope-bound data belongs to exactly one scope and, by implication, one organization.

Examples:

- an assessment limited to one scope
- a subset of assets attached to one perimeter
- scope-limited grants

Plugins must not create scope-bound data detached from the owning organization.

## 6. Isolation Rules

The core must enforce tenant isolation as a platform rule.

Minimum v1 rules:

- organization-bound data must never resolve across organizations unless an explicit platform-global or cross-organization contract exists
- scope-bound access must remain inside the owning organization
- plugin data queries and APIs must carry organization context when operating on organization-bound or scope-bound records
- authorization checks must evaluate tenancy context before or together with permission presence

Recommended development rule:

- default-deny when organization context required by the resource is missing

## 7. Organization and Scope Lifecycle

The core governs the lifecycle of organizations and scopes.

Minimum lifecycle operations:

- create organization
- update organization metadata
- enable or archive organization
- create scope within an organization
- update scope metadata
- enable or archive scope

The v1 implementation must expose these operations through the core shell for authorized platform administrators. CLI support may remain available for automation and recovery, but web administration is the primary operational path.

Deletion policy in v1:

- hard deletion of organizations or scopes is not the default lifecycle path
- archival or disabled state is preferred until data-retention and purge policy is formally defined

## 8. Locale and Configuration Context

Organizations are configuration boundaries.

The core must support organization-level settings for at least:

- default locale
- default timezone
- approved plugins and plugin settings where applicable

User- or principal-level preferences may override organization defaults where platform policy allows it, but the organization remains the baseline shared context.

## 9. Plugin Responsibilities

Plugins must integrate with the tenancy model as consumers, not re-definers.

Plugins are responsible for:

- declaring whether their records are platform-global, organization-bound, or scope-bound
- storing organization and scope references consistently
- respecting authorization context supplied by the core
- avoiding private tenancy models that bypass core organization and scope boundaries

Plugins may add richer organizational models, such as:

- business units
- legal entities
- departments
- operational perimeters
- control applicability groups

But those models must map onto the core tenancy boundary instead of replacing it.

## 10. Examples

Examples of valid modeling:

- one consultant principal belongs to `ORG-A` and `ORG-B` through separate memberships
- an assessment plugin creates assessment `ASM-1` bound to scope `scope-eu` under `ORG-A`
- an asset plugin stores asset `AST-9` as organization-bound under `ORG-A` even when no narrower scope is selected
- a policy plugin defines an exception record bound to `ORG-A` and optionally linked to one scope

Examples of invalid modeling:

- a plugin stores organization business data with no organization reference even though the data is tenant-specific
- a scope from `ORG-A` is reused directly inside a record owned by `ORG-B`
- a plugin treats a business-unit record as a replacement for the core organization boundary

## 11. Initial Core Entity Expectations

The core tenancy layer should expose stable references for:

- `OrganizationReference`
- `ScopeReference`
- tenancy-aware membership linkage

The core may keep the structural metadata minimal in v1, but it must be sufficient to support:

- authorization context
- plugin data attachment
- locale defaults
- audit-log tenancy context

## 12. Auditability Requirements

At minimum, the platform must record auditable events for:

- organization creation, archival, and key settings changes
- scope creation, archival, and key settings changes
- membership changes that alter organization or scope access

Detailed audit structure is defined by the audit-trail specification.

# Consequences

- The core gets a stable tenant boundary that domain plugins can build on consistently.
- Business-unit modeling remains possible without forcing every organizational concept into the core.
- Authorization, localization, and audit trail can share one common organization and scope context model.
- Plugin authors must be explicit about whether their data is platform-global, organization-bound, or scope-bound.
- Future domain plugins such as assets, risks, controls, and privacy can be designed on one stable tenancy substrate.
