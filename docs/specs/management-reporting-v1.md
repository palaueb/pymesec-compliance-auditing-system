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
2. one executive summary section per domain
3. one operational attention section per domain
4. direct `Open module` / `Open` links back into operational records

Each domain section should stay summary-oriented:

- assessments: campaign status, review result mix, linked findings, latest campaigns
- evidence: status mix, review/expiry attention, validation gaps
- risks: workflow state mix, residual score view, top risks
- findings: workflow state, severity mix, overdue items, remediation action pressure

## Section Contract

Cross-domain reporting sections should now converge on one shared shape instead of exposing one-off top-level keys per domain.

Minimum section shape:

- `metrics`: raw values still available for service-level rollups
- `summary_metrics`: ordered label/value pairs used by the section summary strip
- `breakdowns`: titled grouped rows for status/state/result distributions
- `rows`: the drill-down list rendered at the bottom of the section
- `section_url`: link back to the operational module
- `empty_copy`: shared empty-state copy for the section

This keeps the reporting view generic enough to render sections consistently while still allowing domain-specific row payloads.

The page should also keep a clear visual split between:

- executive summary blocks used for management scanning
- operational attention queues used for drill-down and follow-up

## KPI Dictionary

Common reporting KPIs should be defined in one shared catalog instead of being handwritten in each reporting surface.

This first slice should at least centralize:

- `active_assessments`
- `failing_reviews`
- `evidence_review_due`
- `risks_in_workflow`
- `overdue_findings`
- `review_due`
- `needs_validation`
- `open_findings`
- `open_actions`

The goal is not to build a generic analytics engine. The goal is to keep labels and management-facing copy stable across reporting screens.

## UI Composition

Management reporting should not carry screen-local styling for basic summary primitives.

The page should use shared shell primitives for:

- summary metric strips
- compact breakdown lists
- repeated executive section composition

This keeps the reporting screen aligned with the broader shell and reduces one-off layout drift as new reporting pages are added.

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
