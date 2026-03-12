# Title

Identity and Functional Actors Model v1

# Status

Draft

# Context

The PRD requires a strict separation between platform access identity and functional domain roles. ADR-003 defines that authenticated access, memberships, and permissions are distinct from ownership, accountability, and business responsibility. The permission model v1 further defines `principal`, `membership`, and authorization concepts as access-control constructs rather than domain semantics.

The platform therefore needs a shared conceptual and structural model that:

- keeps the core identity-provider-agnostic
- allows multiple identity plugins
- allows functional actors to exist without login access
- allows authenticated users to exist without any ownership role
- provides stable abstractions for plugins that manage identity, user directories, functional actors, and ownership assignments

# Specification

## 1. Conceptual Layers

The model is split into two architectural layers:

- `access identity layer`
- `functional domain actor layer`

The access identity layer answers:

- who can authenticate into the platform
- which identity provider authenticated them
- which organizations and scopes they belong to
- which permissions they have through grants, roles, and policies

The functional domain actor layer answers:

- who is accountable for a domain object or business outcome
- who owns an asset, control, risk, action, or finding
- who acts as approver, auditor, DPO, reviewer, or stakeholder
- how responsibility is assigned regardless of login access

These two layers may be linked, but they are not the same model.

## 2. Definitions

### Principal

A `principal` is the core access-identity abstraction used by the platform for authentication result handling and authorization checks.

A principal represents:

- a subject that can be authenticated
- a subject against which permissions, memberships, and grants are evaluated

A principal does not imply:

- business ownership
- domain accountability
- existence of a user profile in a specific identity plugin

### Identity Provider

An `identity provider` is a plugin-capable component that authenticates subjects and resolves or supplies principal context to the core.

Examples:

- local authentication
- LDAP or Active Directory
- OIDC
- SAML
- Google Workspace
- GitHub

Identity providers belong to plugins, not the core.

### User

A `user` is a plugin-defined identity record representing a human or service account managed by a concrete identity plugin.

Examples:

- a local user account
- a synchronized LDAP account
- an OIDC-backed directory profile

Rules:

- a user is not a core primitive
- a user may map to one principal
- different identity plugins may define different user schemas
- a user may exist with no functional ownership role

### Membership

A `membership` is a core access abstraction linking a principal to an organization and optionally to scopes, roles, or grants.

A membership captures:

- organizational inclusion
- access context
- permission-bearing relationship to the platform

A membership does not capture:

- control ownership
- risk accountability
- business approval responsibility

### Functional Actor

A `functional actor` is the core domain-reference abstraction for a person, team, unit, external stakeholder, or other accountable party used in business workflows.

A functional actor may represent:

- an internal named person
- an external consultant
- a team or committee
- a department or organizational stakeholder

Rules:

- a functional actor may exist without platform login access
- a functional actor is not a permission-bearing access construct by default
- concrete actor profile data belongs to plugins

### Functional Assignment

A `functional assignment` is a core abstraction linking a functional actor to a domain object, role-in-context, or responsibility slot.

Examples:

- functional actor `A-10` is control owner of control `C-22`
- functional actor `A-10` is approver for action plan `AP-4`
- functional actor `TEAM-7` is risk owner for risk `R-11`

Functional assignments express accountability, not access.

### Ownership

`Ownership` is a type of functional assignment in which a functional actor is designated as the accountable party for a domain object or business responsibility.

Examples:

- asset owner
- control owner
- risk owner
- action owner

Ownership does not automatically grant access permissions.

### Linkage

A `linkage` is an explicit association between an access-side identity object and a functional-side actor object.

Examples:

- principal `P-100` linked to functional actor `FA-9`
- local user `U-55` linked to functional actor `FA-9`

Linkages are optional and must never be assumed implicitly.

## 3. Core Abstractions vs Plugin Responsibilities

### Core Abstractions

The core owns the stable abstractions and shared references for:

- `PrincipalReference`
- `MembershipReference`
- `FunctionalActorReference`
- `FunctionalAssignment`
- linkage reference contracts between principals and functional actors

The core is responsible for:

- abstract identity compatibility across plugins
- organization and scope-aware membership handling
- permission and policy evaluation using principals and memberships
- stable referencing of functional actors from domain plugins
- stable assignment semantics for ownership and accountability

### Plugin Responsibilities

Identity plugins own concrete implementations for:

- authentication mechanisms
- user records and user directory schemas
- session or token handling details where relevant
- mapping authenticated subjects into core principal abstractions
- optional membership provisioning or synchronization

Functional actor or domain actor plugins own concrete implementations for:

- actor profile schemas
- actor categories such as person, team, external stakeholder, or department
- actor directory and management workflows
- ownership and responsibility UI flows
- mapping of domain-specific responsibility types onto core functional assignment abstractions

Domain plugins own:

- the specific domain objects being assigned
- domain-specific assignment semantics beyond the shared assignment model
- workflow rules that use functional assignments

## 4. Structural Model

The v1 structural relationships are:

- one identity provider plugin may manage many users
- one authenticated subject resolves to one principal in the current access flow
- one principal may have zero or more memberships
- one principal may have zero or more optional linkages to functional actors
- one functional actor may have zero, one, or many linkages to principals depending on policy
- one functional actor may have zero or more functional assignments
- one domain object may have zero or more functional assignments

The model must support these cases:

- a principal with memberships and permissions, but no functional actor linkage
- a functional actor with assignments, but no principal and no login access
- a linked human represented both as principal and as functional actor
- a team actor linked to no principal at all

## 5. Linkage Rules

Linkage between access-side and functional-side records is explicit and optional.

Rules:

- no principal is automatically created from a functional actor
- no functional actor is automatically created from a principal unless a plugin workflow explicitly does so
- no permissions are granted automatically from a linkage alone
- no functional assignments are created automatically from membership alone
- a linkage may be one-to-one, one-to-many, or many-to-one only if platform policy explicitly permits it

Recommended v1 baseline:

- support one principal linked to zero or more functional actors
- support one functional actor linked to zero or more principals only if justified by organization policy or integration needs

Examples:

- a sysadmin principal has memberships and permissions but no linkage to any functional actor
- an external DPO exists as a functional actor with privacy assignments but no principal
- a compliance manager has one principal and one linked functional actor, but still receives access permissions only through memberships and grants

## 6. Ownership Rules

Ownership is represented through functional assignments and remains separate from authorization.

Rules:

- ownership belongs to the functional domain actor layer
- ownership may target plugin-defined domain objects
- ownership does not imply authentication capability
- ownership does not imply any permission grant
- ownership may be held by a person, team, external actor, or organizational unit if the actor plugin supports those forms

Examples:

- a control owner may be an external consultant with no system account
- a risk owner may be a department actor rather than a specific user
- an authenticated user may edit controls because of permissions while not owning any control

## 7. Access Rules

Access remains driven by principals, memberships, roles, grants, and policies as defined in the permission model.

Rules:

- authenticated users are represented through principals
- access checks are evaluated against principals and memberships
- users and identity provider records are inputs to principal resolution, not substitutes for the principal abstraction
- access may exist without any linkage to a functional actor

Examples:

- a read-only auditor principal may view records across a scope with no ownership assignments
- a platform administrator may manage plugins without being owner of any asset, control, or risk

## 8. Plugin Interoperability Rules

Identity plugins must:

- authenticate subjects
- resolve subjects into principal references
- interoperate with membership and permission evaluation
- avoid redefining ownership semantics as part of identity

Functional actor plugins must:

- expose actor records through `FunctionalActorReference`
- expose responsibility links through `FunctionalAssignment`
- avoid treating ownership as permission grant

Domain plugins must:

- reference functional actors and assignments through core abstractions
- avoid hardcoding ownership to a concrete identity-plugin user model
- allow unlinked functional actors where business requirements need them

## 9. Illustrative Scenarios

### Scenario A: Functional actor without login

- An external consultant is assigned as DPO for privacy workflows.
- The consultant is represented as a functional actor.
- The consultant has no principal, no membership, and no login access.
- Domain ownership and approval references still work.

### Scenario B: Authenticated user without ownership

- A platform administrator signs in through an OIDC identity plugin.
- The authenticated subject resolves to a principal with organization memberships and core admin permissions.
- No functional actor linkage exists.
- The administrator can manage platform settings but is not owner of any control, risk, or action.

### Scenario C: Linked identity and ownership

- A compliance manager authenticates through the local identity plugin.
- The user record maps to a principal.
- The principal is explicitly linked to a functional actor profile.
- The functional actor is assigned as control owner for several controls.
- Access to edit those controls still depends on permission grants, not on the ownership link alone.

# Consequences

- The core remains identity-provider-agnostic while still supporting rich access and domain-assignment models.
- Domain responsibility can be represented even when the accountable party never logs into the platform.
- Access administration stays clearer because ownership is not overloaded into the permission model.
- Identity plugins, actor plugins, and domain plugins can evolve independently as long as they honor the shared abstractions.
- Integrations will require explicit mapping and lifecycle rules when principals, users, and actors represent the same real-world person.

# Open Questions

- What minimum fields are mandatory in v1 for `PrincipalReference`, `MembershipReference`, and `FunctionalActorReference`?
- Should v1 officially support non-person actors such as teams and departments from the first actor plugin release?
- What audit events are required for creating, changing, and deleting identity-to-actor linkages?
- What lifecycle rules apply when a principal is deactivated but the linked functional actor must remain as historical ownership evidence?

# Acceptance Constraints

- The model must define principal, identity provider, user, membership, functional actor, functional assignment, ownership, and linkage rules.
- The model must specify what belongs in core abstractions versus plugins.
- The model must allow functional actors without login access.
- The model must allow authenticated users without ownership roles.
- The model must preserve the separation between access identity and functional domain responsibility.
