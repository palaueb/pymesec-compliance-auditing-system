# Title

ADR-014: Persistent Authorization Roles and Grants

# Status

Accepted

# Context

ADR-004 establishes the core permission engine and allows role-based grouping and direct grants as authorization constructs. The current platform now has:

- persistent tenancy and memberships from ADR-009
- auditable sensitive operations from ADR-010
- shell-based administrative UI from ADR-011
- multiple domain plugins that need repeatable access administration

The platform therefore needs an explicit architectural decision for how roles and grants are modeled persistently, and especially whether they belong to organizations, to principals, or to both.

# Decision

The platform will implement `persistent authorization roles and grants` in the core.

The model is intentionally split into two layers:

- a `role definition` is a reusable named bundle of permissions stored centrally by the core
- a `grant` assigns either a role or a direct permission to a target in a given context

Role definitions are platform-managed authorization templates. They are not business ownership constructs and they are not tied to a single plugin.

Grant targets are:

- `principal`
- `membership`

Grant contexts are:

- `platform`
- `organization`
- `scope`

The practical rule is:

- platform-wide administrative access may be granted directly to a `principal`
- tenant business access should normally be granted to a `membership`

This means that most day-to-day roles are effectively organization-scoped because they are applied through memberships, while selected core administrative roles may remain principal-wide.

The core remains responsible for:

- storing role definitions and role-to-permission mappings
- storing grants and grant context
- resolving authorization from principal, memberships, organization, and scope context
- exposing administrative UI and CLI for role and grant management
- auditing changes to roles and grants

Plugins may register permissions, but they must not persist private access models outside the core authorization system for platform access control.

Functional actors, business owners, approvers, DPOs, and similar domain responsibilities remain outside this model and continue to follow ADR-003.

# Consequences

- The platform gets one persistent authorization store instead of temporary config-only access rules.
- Tenant access is modeled cleanly through membership grants, matching the tenancy substrate from ADR-009.
- Platform administration remains possible without forcing every global operation through an organization context.
- UI, CLI, tests, and audit can all operate against one stable role and grant model.
- The project must keep clear language so that authorization roles are not confused with business roles or functional ownership.

# Rejected Alternatives

1. Principal-only roles for all access

This was rejected because tenant access needs to vary by organization and sometimes by scope. Principal-only grants are too coarse.

2. Organization-owned roles with no principal grants

This was rejected because the core still needs platform-level administrators whose rights are not attached to one tenant membership.

3. Membership-local ad hoc permissions with no reusable roles

This was rejected because it would make administration repetitive and hard to audit across plugins.

4. Reusing functional actor assignments as access grants

This was rejected because it would collapse ADR-003 and ADR-004 into one ambiguous model and produce incorrect privilege propagation.

# Open Questions

- Should custom roles support lifecycle flags such as archived or deprecated in v1.1?
- When identity plugins become richer, should the core provide bulk grant assignment flows by principal group or identity attribute?
- What export and import format should be used for tenant-level role administration later on?
