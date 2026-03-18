# Evidence Management Plugin v1

## Purpose

Promote raw uploaded artifacts into governed evidence records that can be validated, reused, and linked across multiple compliance objects.

This first version adds a central evidence repository on top of the existing artifact storage layer.

## Goals

- create evidence records from a new uploaded file or from an existing artifact
- keep business metadata separate from raw file storage
- track evidence status, validity dates, and validation notes
- reuse one evidence record across multiple records
- provide an evidence library screen inside the application workspace

## Out of Scope for v1

- file download and preview workflows
- automated evidence collection connectors
- evidence approval workflow with multiple reviewers
- expiration reminders and scheduled notifications
- object-level evidence policies

## Main Concepts

### Evidence Record

An evidence record is the governed wrapper around a file or artifact.

It captures:

- title
- summary
- evidence kind
- status
- validity window
- review due date
- validation date
- validator
- validation notes
- links to one or more workspace records

### Artifact

The evidence plugin reuses the existing `artifacts` capability for file storage, hashing, and physical persistence.

An evidence record always points to one artifact in v1.

### Evidence Link

An evidence record may be linked to multiple workspace records such as:

- controls
- assessments
- findings
- risks
- continuity plans
- policies
- privacy records
- assets

## Data Model

### `evidence_records`

- `id`
- `organization_id`
- `scope_id` nullable
- `artifact_id`
- `title`
- `summary` nullable
- `evidence_kind`
- `status`
- `valid_from` nullable
- `valid_until` nullable
- `review_due_on` nullable
- `validated_at` nullable
- `validated_by_principal_id` nullable
- `validation_notes` nullable
- `created_by_principal_id` nullable
- `updated_by_principal_id` nullable
- timestamps

Statuses for v1:

- `draft`
- `active`
- `approved`
- `expired`
- `superseded`

### `evidence_record_links`

- `id`
- `evidence_id`
- `domain_type`
- `domain_id`
- `domain_label`
- `organization_id`
- `scope_id` nullable
- `created_at`

## UI

Main screen:

- menu entry in the application workspace
- central evidence library list
- creation form hidden behind a primary action button
- each row shows:
  - title
  - status
  - validity
  - backing artifact
  - number of linked records

Detail screen:

- overview cards
- artifact summary and checksum
- linked records with navigation back to their details
- validation summary
- update form for metadata, artifact replacement, and linked records

## Permissions

New permissions:

- `plugin.evidence-management.evidence.view`
- `plugin.evidence-management.evidence.manage`

Contexts:

- `organization`

Suggested roles:

- `evidence-viewer`
- `evidence-operator`

## Demo Data

Demo data should include:

- at least one approved evidence record
- at least one evidence record pending review
- reused links to an assessment, a control, a finding, and a continuity plan
- at least one existing artifact promoted into an evidence record

## Test Coverage

Required feature tests:

- route requires view permission
- screen renders inside the shell
- evidence can be created from uploaded files
- evidence can be created from existing artifacts
- evidence can be updated and keep linked records
- manage routes reject view-only memberships

## Follow-Up

After v1:

1. add download and preview actions
2. add reminders for expiring or unvalidated evidence
3. allow richer approval workflow and formal sign-off
4. integrate direct “promote to evidence” actions from assessment, risk, finding, and continuity detail screens
