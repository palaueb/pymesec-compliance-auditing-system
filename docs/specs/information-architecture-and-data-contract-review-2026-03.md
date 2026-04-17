# Information Architecture and Data Contract Review 2026-03

## Goal

Capture the current product and architecture issues surfaced by the new management reporting screen before adding more reporting, collaboration, or third-party workflows.

This is not a rewrite plan for the whole platform. It is a focused review of the current contract between:

- operational modules
- executive reporting
- tenancy and visibility rules
- view composition

## Current State

The product is already usable and coherent at a module level, but the new cross-domain reporting surface exposed three structural tensions:

1. Reporting contracts are assembled as ad hoc arrays inside one service instead of being expressed as stable section-level view models.
2. Visibility and context rules are real product rules, but they are embedded directly inside the reporting implementation.
3. The UI composition mixes executive summary blocks with operational queue tables, so the screen is neither a clean dashboard nor a clean operational workspace.

## Findings

### 1. Section contracts are too ad hoc

[`ManagementReportingService.php`](/media/marc/PROJECTES/web/pymesec.com/pymesec/core/src/Reporting/ManagementReportingService.php#L23) returns a wide nested array with keys such as:

- `metrics`
- `status_breakdown`
- `result_breakdown`
- `state_breakdown`
- `severity_breakdown`
- `action_breakdown`
- `rows`
- `section_url`

This works for one screen, but it does not scale well because:

- every section shape is only partially shared
- the view needs to know too much about each section's internal shape
- empty-state behavior is generic, while metric semantics are domain-specific

The current contract is implementation-friendly, but not product-friendly.

### 2. Context semantics are correct, but encoded in the wrong place

[`ManagementReportingService.php`](/media/marc/PROJECTES/web/pymesec.com/pymesec/core/src/Reporting/ManagementReportingService.php#L35) and [`ManagementReportingService.php`](/media/marc/PROJECTES/web/pymesec.com/pymesec/core/src/Reporting/ManagementReportingService.php#L477) hardcode important product rules:

- assessments and evidence include organization-wide records when scoped
- risks and findings stay scope-exact
- assessments, risks, and findings use object access filtering
- evidence does not yet use object access filtering

These are product semantics, not just reporting semantics. If more summary screens appear, the same logic will be copied again unless we move it into reusable query/context policies.

### 3. Workflow state resolution is coupled to per-row assembly

[`ManagementReportingService.php`](/media/marc/PROJECTES/web/pymesec.com/pymesec/core/src/Reporting/ManagementReportingService.php#L347) and [`ManagementReportingService.php`](/media/marc/PROJECTES/web/pymesec.com/pymesec/core/src/Reporting/ManagementReportingService.php#L470) resolve workflow state while building report rows.

That is acceptable for a first slice, but structurally it means:

- reporting logic knows workflow storage details
- row ordering and state semantics are joined together
- future reporting depth will tend toward N+1 style expansion in service code

The platform needs a small reporting-facing projection for workflow state instead of repeatedly reconstructing it in each service.

### 4. Executive reporting and operational queues are blended

[`management-reporting.blade.php`](/media/marc/PROJECTES/web/pymesec.com/pymesec/core/resources/views/management-reporting.blade.php#L79) renders each domain as:

- summary KPIs
- breakdown cards
- operational top rows table

This creates a hybrid screen:

- too dense for a pure executive surface
- too summary-only for actual operations

Evidence is the clearest example. The reporting page exposes an attention queue that partially overlaps with the native evidence screen in [`index.blade.php`](/media/marc/PROJECTES/web/pymesec.com/pymesec/plugins/evidence-management/resources/views/index.blade.php#L354).

The product needs a cleaner distinction between:

- executive rollup
- attention queue
- operational registry

### 5. Metric definitions are still local to each screen

The screen currently defines headline metrics directly inside the service in [`ManagementReportingService.php`](/media/marc/PROJECTES/web/pymesec.com/pymesec/core/src/Reporting/ManagementReportingService.php#L45).

That is fine for bootstrapping, but it means:

- the KPI catalog is not explicit
- labels and thresholds live beside SQL/query code
- different screens may end up using slightly different definitions for similar concepts

This is especially risky for:

- overdue
- active
- in workflow
- needs validation

## Product Interpretation

The main issue is not that the current implementation is wrong.

The issue is that the product is now crossing from single-module workspaces into cross-domain product surfaces. That requires a more explicit contract between:

- data source
- visibility policy
- metric definition
- summary presentation
- drill-down destination

Without that layer, every new dashboard, portal, vendor review, questionnaire workspace, or automation health page will reinvent the same structure.

## Recommended Next Refactor Order

### 1. Define a shared reporting section contract

Introduce a small reporting DTO or normalized array contract for all cross-domain sections.

Minimum shape:

- `headline_metrics`
- `breakdowns`
- `attention_rows`
- `open_url`
- `empty_state`

This is better than the current domain-specific top-level key sprawl.

### 2. Extract workspace visibility policies from reporting services

Create reusable query helpers or policy objects for:

- organization-only visibility
- scoped plus organization-wide visibility
- scope-exact visibility
- object-access-aware visibility

These rules should be reusable by future reporting and collaboration surfaces.

### 3. Separate executive summary from operational attention lists

The management page should become clearly executive.

If needed later, create a second cross-domain page for:

- items needing action
- due reviews
- overdue findings
- active campaigns needing attention

That split will make the information architecture much clearer.

### 4. Define a KPI dictionary

Document and centralize how common cross-domain metrics are computed.

Start with:

- overdue
- open
- active
- in workflow
- stale / review due
- validation gap

### 5. Reduce view-local styling drift

The bug fixed today showed that reporting-specific UI composition is still too local.

[`management-reporting.blade.php`](/media/marc/PROJECTES/web/pymesec.com/pymesec/core/resources/views/management-reporting.blade.php#L1) now contains screen-local layout styles to fix the immediate rendering issue. That is acceptable as a tactical fix, but the shell still lacks a small shared primitive for:

- label/value summary rows
- compact breakdown lists
- management KPI strips

## What Not To Do Yet

Do not start with:

- a platform-wide schema rewrite
- a generic analytics engine
- charting
- exports
- a full design system overhaul

The next good step is smaller: stabilize the contract for cross-domain product surfaces.

## Recommended Next Slice

Before implementing more reporting-heavy or collaboration-heavy product areas, do one focused refactor slice:

1. normalize reporting section contracts
2. extract workspace visibility/context helpers
3. split `management reporting` into clearer executive vs attention patterns

That refactor is the right foundation for:

- vendor risk workspaces
- questionnaires
- automation health
- external collaboration portals
