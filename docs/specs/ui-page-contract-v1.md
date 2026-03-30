# UI Page Contract v1

## Purpose

Define the reusable page contract for the application UI after the 2026-03 cleanup pass.

The product now uses three first-class page types:

- `index`
- `detail`
- `governance`

## Index Pages

An `index` page is a browse-and-open surface.

It should focus on:

- summary metrics
- record identity and business context
- owner or accountability summary where useful
- current state or health summary
- one clear primary row action: `Open`

It must not be the place where the user performs dense maintenance on child collections or workflow-heavy records.

## Detail Pages

A `detail` page is the maintenance workspace for one selected record.

It owns:

- workflow actions
- child collection maintenance
- linked record creation where the child belongs to the parent workspace
- evidence or artifact work
- ownership management
- edit forms for the selected record

It should say explicitly that it is the working workspace for the selected object.

## Governance Pages

A `governance` page is an administration surface, not an operational workspace.

It owns:

- platform-wide setup
- reusable policy, role, plugin, and tenancy configuration
- dense administration forms that should stay outside day-to-day work modules
- catalogs and reference views that explain or control application behavior

It should say explicitly that operational work happens elsewhere.

## Navigation Rules

- `Open` is the default action from summary lists.
- `Back to ...` returns the user to the originating list or governance view.
- Contextual detail pages should preserve the caller when the shell provides `context_label` and `context_back_url`.

## Applied In This Cleanup Pass

This contract is now applied across:

- operational registers such as risks, findings, policies, privacy records, continuity services, assessments, controls, assets, and recovery plans
- workflow/support views such as boards, lifecycle history, and assignment registers
- governance/admin screens such as tenancy, roles, plugins, permissions, functional actors, local identity, and LDAP directory setup
