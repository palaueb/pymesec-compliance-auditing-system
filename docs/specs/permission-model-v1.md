# Title

Permission Model v1

# Status

Draft

# Context

The PRD requires a core permission engine that is decoupled from identity providers, supports multi-organization and scope-aware access control, and remains separate from functional business ownership. ADR-003 establishes that platform access identity and functional domain actors are distinct concerns. ADR-004 establishes that permissions are access-control concepts, not ownership concepts, and that plugins must be able to register permissions through core contracts.

The platform therefore needs a shared permission model that:

- defines common authorization concepts
- works across multiple identity plugins
- supports core and plugin capabilities uniformly
- remains separate from functional domain roles such as control owner, risk owner, approver, or DPO in the business sense

# Specification

## 1. Core Concepts

### Permission

A `permission` is a stable access-control capability that answers whether a principal may perform an operation in the platform.

Examples:

- view plugin administration
- manage plugin lifecycle
- view audit logs
- create controls
- export assessment results

Rules:

- permissions are not business responsibilities
- permissions must be stable, named, and registrable
- permissions may be defined by the core or by plugins

### Role

A `role` is a named grouping of permissions used to simplify grant management.

Examples:

- platform administrator
- compliance editor
- read-only auditor

Rules:

- roles are authorization containers, not domain ownership labels
- a role may aggregate permissions from the core and from plugins
- role names may vary by organization, but permission keys remain stable

### Policy

A `policy` is a runtime rule that evaluates authorization for a specific action, resource, or context beyond simple permission presence.

Examples:

- allow `controls.update` only within the current organization
- allow `reports.export` only if the principal also has access to the selected scope
- deny plugin administration in a tenant context unless the operation is explicitly tenant-scoped

Rules:

- policies refine authorization checks
- policies do not replace permission registration
- policies must remain access-control logic, not business ownership logic

### Scope

A `scope` is a bounded authorization context within an organization used to restrict access to a subset of data or operations.

Examples:

- a business unit
- a compliance program
- a country-specific assessment scope
- a technical asset scope

Rules:

- scopes are part of access evaluation context
- a permission grant may apply globally, per organization, or per scope depending on platform policy

### Membership

A `membership` links an access principal to an organization and optionally to one or more scopes, roles, or grant sets.

Examples:

- principal `P-123` is a member of organization `ORG-A`
- principal `P-123` has role `compliance-editor` in `ORG-A`
- principal `P-123` has read access only in scope `scope-eu-west`

Rules:

- memberships are access constructs
- memberships do not imply business ownership of domain objects
- memberships may be supplied by identity plugins, but must map to core membership abstractions

### Grant

A `grant` is the assignment of a permission or role to a principal within a defined context.

Possible grant targets:

- principal
- membership
- role

Possible grant contexts:

- platform-wide
- organization-wide
- scope-specific

Rules:

- grants must be explicit
- grants must be auditable
- grants may be direct or inherited through roles

### Authorization Check

An `authorization check` is the runtime evaluation that determines whether a principal may perform an action in a given context.

An authorization check evaluates:

- principal identity
- membership context
- requested permission
- organization
- scope
- applicable grants
- applicable policies

Result states in v1:

- `allow`
- `deny`
- `unresolved`

`unresolved` means no decisive authorization result was produced and must be treated as deny unless a higher-level explicit rule defines otherwise.

## 2. Separation from Functional Domain Roles

Functional domain roles such as `control owner`, `risk owner`, `approver`, `auditor`, or `DPO` are not permissions and are not roles in the authorization sense unless separately mapped through explicit access configuration.

Examples of what the permission model must not assume:

- a control owner automatically gains `controls.update`
- a risk owner automatically gains `risks.approve`
- a DPO automatically gains organization-wide privacy administration rights

Examples of valid explicit mappings:

- an organization administrator grants `privacy.records.view` to the principal linked to a DPO actor
- a reviewer role includes `assessments.conclude` for a limited scope

The permission model controls platform access. Functional actor models control business accountability. These are separate systems that may be linked, but neither implies the other by default.

## 3. Permission Naming

Permissions must use stable, namespaced keys.

Recommended naming pattern:

- `core.plugins.manage`
- `core.audit_logs.view`
- `plugin.controls.controls.view`
- `plugin.controls.controls.update`
- `plugin.reporting.reports.export`

Naming guidance:

- include origin namespace
- include resource or feature area
- include operation
- avoid embedding organization identifiers or actor identities in permission keys

## 4. Core Data Relationships

The permission model relies on the following conceptual relationships:

- a `principal` may have zero or more `memberships`
- a `membership` belongs to one organization
- a `membership` may reference zero or more scopes
- a `membership` may receive direct grants
- a `role` groups permissions
- a grant may assign a role or a permission
- a policy may evaluate permissions against runtime context

The model must remain compatible with multiple identity plugins by depending on `principal` and `membership` abstractions rather than a hardcoded user entity.

## 5. Plugin Permission Registration

Plugins register permissions through the core extension contracts and plugin manifest metadata.

Each registered permission should include:

- stable key
- label
- description
- plugin identifier
- resource or feature area
- supported context metadata if relevant

Registration behavior:

- the core validates permission keys and metadata during plugin install or enable
- plugin permissions become available to roles, grants, UI administration, API exposure, and tests
- disabling a plugin removes its runtime permission availability without corrupting stored authorization data

Plugins may also register policy hooks for their resources, but they must use the core permission engine for final access evaluation rather than implementing isolated private authorization systems.

## 6. Runtime Evaluation Flow

Authorization checks in v1 follow this sequence:

1. Resolve the current principal through the active identity plugin.
2. Resolve the relevant organization and optional scope context.
3. Load memberships for the principal in that context.
4. Expand direct grants and role-derived permissions.
5. Determine whether the requested permission is present in the relevant context.
6. Run any applicable core or plugin policy checks.
7. Return `allow`, `deny`, or `unresolved`.
8. Treat `unresolved` as deny unless an explicit higher-level rule says otherwise.

Evaluation rules:

- scope-specific grants are narrower than organization-wide grants
- organization context is mandatory for organization-bound resources
- plugin policies may refine decisions but must not override core contract semantics arbitrarily
- absence of a grant is not the same as business disapproval; it is simply lack of access

## 7. Core Permission Examples

Examples of core-defined permissions:

- `core.plugins.view`
  Meaning: view plugin catalog and plugin status information

- `core.plugins.manage`
  Meaning: install, enable, disable, or upgrade plugins subject to platform policy

- `core.audit_logs.view`
  Meaning: view audit log records

- `core.organizations.manage`
  Meaning: administer organization-level settings and memberships

- `core.permissions.manage`
  Meaning: manage roles, grants, and permission assignments

These permissions belong to the core because they govern platform infrastructure rather than domain-specific capabilities.

## 8. Plugin Permission Examples

Examples of plugin-defined permissions:

- `plugin.controls.controls.view`
  Meaning: view control records provided by the Controls plugin

- `plugin.controls.controls.update`
  Meaning: edit control records provided by the Controls plugin

- `plugin.evidence.evidence.upload`
  Meaning: upload evidence artifacts through the Evidence plugin

- `plugin.reporting.reports.export`
  Meaning: export reports through the Reporting plugin

- `plugin.identity_local.memberships.manage`
  Meaning: manage memberships provided by a local identity plugin, if that plugin owns such administration surfaces

These permissions belong to plugins because they govern plugin-delivered capabilities rather than core platform infrastructure.

## 9. Role Examples

Illustrative authorization roles:

- `platform-admin`
  Contains: `core.plugins.manage`, `core.permissions.manage`, `core.organizations.manage`

- `compliance-editor`
  Contains: `plugin.controls.controls.view`, `plugin.controls.controls.update`, `plugin.evidence.evidence.upload`

- `read-only-auditor`
  Contains: `core.audit_logs.view`, `plugin.controls.controls.view`, `plugin.reporting.reports.export`

These are authorization roles only. They do not mean the holder is automatically a control owner, risk owner, or approver in the domain model.

## 10. Policy Examples

Examples of policy refinement:

- `plugin.controls.controls.update` is granted, but the policy denies access outside the principal’s organization
- `plugin.reporting.reports.export` is granted organization-wide, but export is denied for a scope the membership does not cover
- `core.plugins.manage` is granted, but policy restricts certain lifecycle actions to global platform administration context

## 11. Auditability

The platform must audit, at minimum:

- permission registration from plugins
- role creation and modification
- grant creation, change, and revocation
- policy-relevant administrative changes
- sensitive authorization failures where platform policy requires logging

## 12. Interoperability with Identity Plugins

Identity plugins are responsible for authentication and for supplying principal context, but not for redefining the permission model.

Identity plugin obligations:

- provide principal identity in core-compatible form
- provide or map memberships into core abstractions where applicable
- interoperate with role and grant resolution

Identity plugins must not:

- hardcode a private permission model that bypasses the core engine
- redefine permissions as ownership semantics
- require the core to depend on a concrete user schema

# Consequences

- The platform gains a shared authorization vocabulary across core and plugins.
- Multiple identity plugins remain compatible because authorization depends on principal and membership abstractions.
- Plugin capabilities can participate in roles, grants, policies, UI administration, and APIs in a uniform way.
- Access control stays clearly separated from functional ownership and business accountability.
- The platform will need clear governance for permission naming, role administration, and policy behavior across plugins.

# Open Questions

- What exact metadata is mandatory when a plugin registers a permission in v1?
- Should role definitions be fully tenant-managed, or may official plugins ship recommended default roles?
- Which policy hook points are mandatory in v1 for UI routes, API routes, and background operations?
- How should permission deprecation be handled when a plugin changes capability boundaries across versions?

# Acceptance Constraints

- The model must define permission, role, policy, scope, membership, grant, and authorization check.
- The model must clearly separate permissions from functional domain roles.
- The model must explain how plugins register permissions through core contracts.
- The model must explain runtime permission evaluation.
- The model must include examples for both core permissions and plugin permissions.
- The model must remain compatible with multiple identity plugins and the core permission engine.
