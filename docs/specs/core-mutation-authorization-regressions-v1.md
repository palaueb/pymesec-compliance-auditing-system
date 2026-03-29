# Title

Core Mutation Authorization Regressions v1

# Status

Draft

# Context

Most governed plugin mutation routes already had explicit authorization regressions. The remaining gap was concentrated in core-owned mutation surfaces such as plugin lifecycle, tenancy, reference data, and delegated governance administration.

This slice expands the regression suite so those core mutation paths are covered with explicit unauthorized and authorized expectations.

# Specification

## 1. Covered Mutation Surfaces

This slice adds or strengthens regression coverage for:

- plugin lifecycle enable and disable actions
- reference catalog managed entry mutations
- tenancy organization and scope mutations
- functional actor creation, linking, and assignment mutations
- identity-local user, membership, and import-entry mutations
- identity-ldap connector, mapping, and sync-entry mutations

## 2. Authorization Expectations

The regression suite must prove that:

- organization-scoped operators cannot borrow platform administration capabilities
- delegated governance mutations still require their explicit manage permission
- successful functional-actor mutations redirect back to the workspace governance shell rather than the platform admin shell

## 3. Boundary Expectations

The tests now treat these surfaces explicitly:

- `/admin` keeps platform setup such as plugins, tenancy, reference data, roles, notifications, and audit
- `/app` keeps delegated governance such as functional actors and object access

## 4. Consequences

- unauthorized writes on core mutation routes become harder to regress silently
- workspace governance flows now preserve the correct shell boundary after POST redirects
- the remaining authorization-coverage TODO can continue from a smaller and clearer gap list
