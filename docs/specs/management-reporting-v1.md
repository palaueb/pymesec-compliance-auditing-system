# Management Reporting v1

## Goal

Add a dedicated workspace reporting page that gives management a cross-domain view across:

- assessments
- evidence
- risks
- findings

This is intentionally a management summary, not a replacement for the operational modules themselves.

## Scope

The first slice ships as a new core workspace screen:

- menu id: `core.management-reporting`
- area: `app`
- audience: any visible workspace principal

The page must summarize only data visible in the active workspace context.

## Context Rules

The reporting page follows the same visibility and tenancy semantics already used by the underlying modules:

- organization context is required
- assessment campaigns include organization-wide records when a scope is selected
- evidence records include organization-wide records when a scope is selected
- risks remain scope-exact when a scope is selected
- findings remain scope-exact when a scope is selected
- object access filtering continues to apply to assessments, risks, and findings where governed assignments exist
- evidence remains unfiltered by object access until evidence-level access governance exists

## Page Contract

The page contains:

1. headline metrics spanning all four domains
2. one section per domain
3. direct `Open module` / `Open` links back into operational records

Each domain section should stay summary-oriented:

- assessments: campaign status, review result mix, linked findings, latest campaigns
- evidence: status mix, review/expiry attention, validation gaps
- risks: workflow state mix, residual score view, top risks
- findings: workflow state, severity mix, overdue items, remediation action pressure

## Non-Goals

This slice does not add:

- exports from the management screen itself
- custom saved filters
- trend charts over time
- framework-specific executive scorecards beyond the readiness/reporting work already implemented elsewhere

## Test Expectations

Coverage must prove:

- the screen renders in `/app`
- the screen is not available in `/admin`
- menu ordering includes the new core page
- scope filtering remains correct per domain
- object-access filtering still hides restricted risks and findings from delegated principals
