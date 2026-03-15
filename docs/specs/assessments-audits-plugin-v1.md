# Assessments & Audits Plugin v1

## Purpose

Provide the first real audit workspace layer on top of existing controls, evidence, findings, risks, and tenancy.

This first version now covers assessment campaigns, control-level checklist execution, workpaper attachments, linked findings, and a basic exportable summary report.

## Goals

- create assessment campaigns from the web UI
- bind an assessment to one organization and optionally one scope
- record a planning window with start and end dates
- choose one framework when relevant
- attach a set of controls to the assessment
- record conclusions per included control
- attach workpapers to control reviews
- create linked findings from the review flow
- export a lightweight assessment summary
- track a minimal lifecycle

## Out of Scope for v1

- detailed test steps per control
- assessor conclusions per requirement
- final sign-off workflow
- export bundles
- evidence validation rules

## Main Concepts

### Assessment Campaign

An assessment campaign is a bounded review exercise with:

- title
- summary
- organization
- optional scope
- optional framework
- start date
- end date
- status

### Assessment Review

Each included control gets a review record inside the assessment checklist.

A review captures:

- result
- test notes
- conclusion
- reviewed date
- reviewer principal
- optional linked finding
- workpaper attachments

## Data Model

### `assessment_campaigns`

- `id`
- `organization_id`
- `scope_id` nullable
- `framework_id` nullable
- `title`
- `summary`
- `starts_on`
- `ends_on`
- `status`
- timestamps

Statuses for v1:

- `draft`
- `active`
- `closed`

### `assessment_campaign_controls`

- `id`
- `assessment_id`
- `control_id`
- `position`
- timestamps

### `assessment_control_reviews`

- `id`
- `assessment_id`
- `control_id`
- `organization_id`
- `scope_id` nullable
- `result`
- `test_notes` nullable
- `conclusion` nullable
- `reviewed_on` nullable
- `reviewer_principal_id` nullable
- `linked_finding_id` nullable
- timestamps

Results for v1:

- `not-tested`
- `pass`
- `partial`
- `fail`
- `not-applicable`

## UI

Main screen:

- menu entry under the application workspace
- campaign creation hidden behind a button, not always-open forms
- table with campaign list
- each row shows:
  - title
  - perimeter
  - checklist size
  - result summary
  - status
- each row has:
  - an edit panel
  - a checklist review panel
  - a summary export action

Checklist review panel:

- one section per included control
- requirement mappings visible for review context
- review result and conclusion
- workpaper list
- optional linked finding
- hidden forms behind buttons for:
  - saving the review
  - uploading a workpaper
  - creating a finding

## Permissions

New permissions:

- `plugin.assessments-audits.assessments.view`
- `plugin.assessments-audits.assessments.manage`

Contexts:

- `organization`

## Demo Data

Demo data should include at least:

- one assessment in `org-a`
- linked controls from the existing controls catalog
- seeded review results
- at least one linked finding

## Test Coverage

Required feature tests:

- route requires view permission
- screen renders inside the shell
- campaigns can be created and edited from the shell runtime
- control links persist and render
- reviews can be recorded
- findings can be created from review flow
- workpapers can be uploaded
- summary export is available

## Follow-Up

After v1:

1. add conclusion workflow and formal sign-off
2. add per-requirement conclusions
3. add richer export bundles and report layouts
