# Assessment Detail UI v1

## Purpose

Define the `Assessment Detail` interaction pattern for the `assessments-audits` plugin.

This slice makes the screen contract explicit:

- campaign list for perimeter and result summary
- detail workspace for assessment execution

## Contract

The `Assessment Campaigns` screen now has two explicit modes:

- list mode for campaign perimeter, framework, period, result summary, and navigation
- detail mode for one selected assessment campaign

The list must stay focused on:

- title and campaign identity
- scope and framework perimeter
- review outcome summary
- linked findings and workpaper counts
- state
- `Open`

The detail workspace owns:

- checklist review work
- workpaper uploads
- linked finding creation
- export actions
- sign-off and closure
- summary and perimeter editing
- owner management

## Implementation

Implemented in:

- `plugins/assessments-audits/resources/views/index.blade.php`

Behavior:

- the list now states explicitly that it is a perimeter-and-result-summary surface
- the selected assessment now states explicitly that execution and governance happen in detail
- product copy now aligns with the master-detail contract instead of leaving it implicit

## Validation

Regression coverage lives in:

- `core/tests/Feature/AssessmentsAuditsTest.php`

Covered cases:

- list mode renders the campaign-list guidance
- detail mode renders the assessment-detail guidance
- checklist and linked-work execution still render from detail

## Relationship to UI Review TODO

This slice closes the `Assessments and Audits` cleanup target from `ui-review-and-refactor-todo-2026-03.md`:

- `Assessment Detail` is the execution workspace
- checklist reviews, workpapers, linked findings, and summary actions stay in detail
- the list stays focused on perimeter and result summary
