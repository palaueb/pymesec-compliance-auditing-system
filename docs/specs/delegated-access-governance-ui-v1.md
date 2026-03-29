# Title

Delegated Access Governance UI v1

# Status

Draft

# Context

The permission model already distinguishes platform administration from workspace access governance. Roles, grants, tenancy, modules, and SMTP settings belong to platform administration, while principal-to-actor links, ownership, and object-scoped visibility are delegated governance concerns inside one organization context.

Until this slice, the UI still exposed functional actors and object access under `/admin`, which blurred that boundary.

# Specification

## 1. Objectives

This slice defines:

- a dedicated workspace entrypoint for delegated access governance
- separation between `/admin` platform operations and `/app` governance operations
- consistent navigation for functional actors and object access from the workspace shell

## 2. Scope

The delegated governance entrypoint lives under `core.governance` in the `/app` shell.

It groups:

- `core.functional-actors`
- `core.object-access`

It does not change:

- the underlying permission keys
- the functional assignment model
- platform administration screens such as roles, tenancy, notifications, audit, or modules

## 3. Boundary Rules

Platform administration remains responsible for:

- roles and grants
- tenancy and memberships
- SMTP and notification administration
- plugin lifecycle
- audit and reference-data administration

Delegated governance remains responsible for:

- linking principals to functional actors
- assigning ownership and accountability
- governing object-scoped visibility
- correcting responsibility matrices in the active organization

## 4. Navigation Rules

- `core.governance` is a workspace menu in `/app`
- `core.functional-actors` is a child of `core.governance`
- `core.object-access` is a child of `core.governance`
- these screens must not be available from `/admin`

## 5. Permission Rules

The move is presentational, not a new authorization model.

- viewing delegated governance screens still requires `core.functional-actors.view`
- mutating actor links or object access still requires `core.functional-actors.manage`

## 6. UX Rules

The governance entrypoint should make the split explicit:

1. platform setup happens in `/admin`
2. delegated workspace accountability happens in `/app`
3. ownership and object visibility are governed in organization context, not as platform-global admin work

# Consequences

- the product now follows its own permission-model guidance more closely
- platform operators and delegated workspace owners no longer share the same navigation surface for these tasks
- the UI becomes a better foundation for future delegated governance work without touching LDAP yet
