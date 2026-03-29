# Continuity Recovery Plan Detail UI v1

## Purpose

Define the `Recovery Plan Detail` interaction pattern for the `continuity-bcm` plugin.

This slice closes the highest-pain UI issue where recovery plans were acting as full workspaces from the register view.

## Contract

The `Recovery Plans` screen now has two explicit modes:

- list mode for plan summaries and navigation
- detail mode for one selected recovery plan

The register must stay focused on:

- plan title
- parent service
- owner summary
- operational counts
- linked-record summary
- state
- `Open`

The detail view owns:

- workflow transitions
- edit form
- linked records
- accountability and owner management
- evidence uploads
- exercises
- test runs

## Implementation

Implemented in:

- `plugins/continuity-bcm/src/ContinuityBcmPlugin.php`
- `plugins/continuity-bcm/resources/views/plans.blade.php`

Key changes:

- plan detail now exposes linked service, policy, and finding context with direct navigation
- plan list now exposes operational counts instead of acting like an inline workspace
- the page copy explicitly states that the list is summary-only and operational work happens in detail
- creation remains delegated to `Continuity Service Detail` via `Choose service`

## Validation

Regression coverage lives in:

- `core/tests/Feature/ContinuityBcmTest.php`

Covered cases:

- list mode stays summary-only
- detail mode renders the recovery-plan workspace
- evidence, exercises, and test runs remain accessible from detail

## Relationship to UI Review TODO

This slice completes the `Recovery Plans` item from `ui-review-and-refactor-todo-2026-03.md`:

- `Recovery Plan Detail` exists
- evidence, exercises, test runs, workflow transitions, linked records, and edit stay in detail
- list mode stays focused on summaries plus `Open`
