# Governance Page Pattern v1

## Purpose

Define the reusable interaction pattern for admin-heavy screens.

Governance pages are intentionally different from operational pages:

- they hold dense setup work
- they configure reusable rules
- they keep platform or workspace administration out of operational modules

## Structure

A governance page should generally have:

1. a top note stating that the page is a governance surface
2. summary metrics for the governed area
3. creation or setup forms hidden behind explicit actions when the workflow is dense
4. summary lists with `Open`
5. detail workspaces for one selected governed object when the page supports contextual detail

## Copy Rules

Governance copy should:

- use business-facing terms where possible
- say what belongs here
- say what belongs in operational workspaces instead
- avoid exposing architecture language unless the screen is inherently technical

## Action Rules

- use `Open` for governed objects listed in a table
- use `Back to ...` from governed detail workspaces
- keep destructive or lifecycle actions grouped with the selected governed object
- keep global setup separate from per-object editing

## Applied Screens

This pattern is now applied to:

- `core.tenancy`
- `core.roles`
- `core.plugins`
- `core.permissions`
- `core.functional-actors`
- `plugin.identity-local.users`
- `plugin.identity-local.memberships`
- `plugin.identity-ldap.directory`
