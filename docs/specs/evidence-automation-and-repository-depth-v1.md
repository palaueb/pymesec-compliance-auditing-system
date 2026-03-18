# Evidence Automation and Repository Depth v1

## Purpose

Deepen the evidence repository so that teams can promote operational artifacts into governed evidence directly from their working screens, not only from the central library.

## Goals

- promote existing artifacts into evidence records with one action
- infer initial evidence links from the source object automatically
- expose recent promotion candidates inside the evidence library
- show source provenance on the evidence detail screen
- reduce manual re-entry during assessments, findings, continuity work, and other operational flows

## Main Additions

### Direct Artifact Promotion

Users with evidence management permission can promote an existing artifact directly into an evidence record.

Initial defaults:

- `status = active`
- `valid_from = today`
- `review_due_on = +90 days`
- `title` suggested from artifact label
- `evidence_kind` normalized from artifact type

If the artifact was already promoted before, the system reuses the existing evidence record instead of creating a duplicate.

### Source Provenance

Evidence detail now shows the source of the promoted artifact:

- source component
- source subject type
- source workspace object label when it can be resolved
- navigation back to the source record

### Automatic Link Inference

Promotion infers initial links when possible.

Examples:

- `assessment-review` artifact:
  - assessment
  - control
  - linked finding if present
- `continuity-plan` artifact:
  - recovery plan
  - parent continuity service
  - linked finding or policy if present
- direct object artifacts:
  - asset
  - control
  - risk
  - finding
  - policy
  - policy exception
  - privacy data flow
  - privacy processing activity
  - continuity service
  - recovery plan
  - assessment

### Promotion Candidates

The evidence library now includes a `Recent uploads ready for evidence` section.

It lists recent artifacts in the current organization and scope that:

- do not yet back an evidence record
- can be promoted directly
- show their likely source and suggested links

## UI Changes

### Evidence Library

- stronger dashboard metrics:
  - records
  - approved
  - expiring soon
  - review due
  - needs validation
  - linked
- promotion candidates table
- richer detail screen with source provenance

### Workspace Detail Screens

Artifact lists in detail screens now offer `Promote to evidence`, including:

- assessments
- controls
- risks
- findings
- continuity services
- recovery plans
- policies
- policy exceptions
- privacy data flows
- privacy processing activities

## Out of Scope

- artifact download and file preview
- scheduled reminders for review due or expiring evidence
- multi-step approval workflow for evidence validation
- external evidence collectors and automated fetch connectors

## Test Coverage

Required checks:

- evidence detail shows source provenance
- recent promotion candidates render in the library
- promoting a seeded artifact creates one evidence record only once
- inferred links include assessment, control, finding, or other relevant domain objects

