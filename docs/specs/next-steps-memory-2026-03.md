# Next Steps Memory — 2026-03

This note captures the current recommendation for the next implementation blocks after the first usable platform baseline.

## Current Recommendation

Priority order:

1. `assessments-audits`
2. `evidence-management`
3. `object-level-access`

## Why This Order

### 1. Assessments and Audits

This is the largest functional gap against the PRD promise of running assessments and audits with traceability across controls, evidence, findings, and conclusions.

First practical increment:

- create assessment campaigns
- define organization, scope, dates, and framework perimeter
- select relevant controls
- allow a basic lifecycle for draft, active, and closed assessments

Expected outcome:

- the product stops being only a set of domain registers and starts behaving like an actual audit workspace

### 2. Evidence Management

Artifacts already exist, but evidence is not yet managed as a first-class compliance object.

Next increment after assessment campaigns:

- evidence metadata
- validity and expiration
- review / validation status
- reuse of evidence across controls and assessments

Expected outcome:

- better audit execution
- better traceability
- clearer dashboards and reminders

### 3. Object-Level Access

Current access is already filtered by organization, scope, memberships, and grants, but not yet by real responsibility over specific records.

Next increment after evidence:

- object-level filters for assets, risks, controls, findings, and continuity records
- connection between functional ownership and visibility
- personal and team-focused dashboards

Expected outcome:

- department users only see the records they should manage
- the platform becomes usable in larger organizations without overexposing data

## Immediate Execution Decision

Start with `assessments-audits v1`.

Target for the first implementation slice:

- a new plugin for assessment campaigns
- campaign creation and editing
- organization and optional scope perimeter
- date range and framework selection
- control selection
- basic campaign status and list view

This slice is intentionally smaller than the full PRD block, but it creates the right backbone for later workpapers, conclusions, sign-off, exports, and evidence reuse.
