# Governed Reference Data Phase 2 v1

## Status

Implemented.

## Goal

Extend governed reference data beyond the first slice (`assets`, `continuity impact/dependency`, `privacy`) so that the remaining high-value business enums are no longer free text.

## Scope

This phase governs:

- `findings.severity`
- `remediation_actions.status`
- `assessment_campaigns.status`
- `assessment_control_reviews.result`
- `continuity_plan_exercises.exercise_type`
- `continuity_plan_exercises.outcome`
- `continuity_plan_test_executions.execution_type`
- `continuity_plan_test_executions.status`

## Source of Truth

The canonical catalogs live in `core/config/reference_data.php`.

New groups introduced in this phase:

- `reference_data.findings.severity`
- `reference_data.findings.remediation_status`
- `reference_data.assessments.review_result`
- `reference_data.assessments.status`
- `reference_data.continuity.exercise_type`
- `reference_data.continuity.exercise_outcome`
- `reference_data.continuity.execution_type`
- `reference_data.continuity.execution_status`

## Runtime Contract

Each affected domain exposes a small reference-data helper:

- `FindingsReferenceData`
- `AssessmentReferenceData`
- `ContinuityReferenceData` extended with exercise and execution catalogs

The helpers provide:

- labels for rendering
- valid keys for request validation
- normalized option lists for selects

## UI Rules

User-facing forms must use selectors, not arbitrary text fields, for governed values.

The following screens now render controlled options:

- `Findings Register`
- `Remediation board`
- `Assessment Campaigns`
- `Recovery Plans`

Where a label is shown back to the user, the UI renders the governed label instead of the raw key.

## Validation Rules

Routes reject non-governed values with standard Laravel validation errors.

The following operations are covered:

- create and update finding
- create and update remediation action
- create and update assessment
- update assessment review
- record continuity exercise
- record continuity test execution

## Product Outcome

This phase reduces inconsistent wording in day-to-day operations and improves:

- reporting quality
- filter consistency
- audit readability
- predictability of object-level access and analytics

## Remaining Work

This does not yet provide UI-managed catalogs. The catalogs are still config-backed.

Likely next steps:

- governed vocabularies for remaining governance modules
- UI-managed reference catalogs for administrators
- multi-owner or richer accountability models where the domain requires them
