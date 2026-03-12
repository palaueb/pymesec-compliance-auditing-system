# Title

Findings and Remediation Plugin Specification v1

# Status

Draft

# Context

The PRD requires the platform to support operational compliance management, not only catalogs. Once controls, risks, artifacts, workflow, tenancy, and functional actors exist, the next useful domain capability is a way to register detected issues and drive corrective action to closure.

# Specification

## 1. Purpose

The `findings-remediation` plugin manages:

- findings detected from controls, risks, audits, reviews, or operations
- corrective actions linked to those findings
- accountable owners, due dates, workflow state, and evidence

## 2. Core Domain Model

### Finding

A finding is a recorded deviation, gap, weakness, or nonconformity.

Minimum fields in v1:

- stable finding identifier
- organization and optional scope
- title
- severity
- description
- optional linked control identifier
- optional linked risk identifier
- optional due date

### Remediation Action

A remediation action is a concrete corrective step linked to one finding.

Minimum fields in v1:

- stable action identifier
- parent finding identifier
- organization and optional scope
- title
- status
- notes
- optional due date

## 3. Ownership Model

Ownership must use the core functional actor system.

In v1:

- findings may have one active owner assignment
- remediation actions may have one active owner assignment

## 4. Workflow Model

The plugin defines a minimal finding workflow:

- `open`
- `triaged`
- `remediating`
- `resolved`

Transitions:

- `triage`
- `start-remediation`
- `resolve`
- `reopen`

Remediation actions use a simpler operational status in v1:

- `planned`
- `in-progress`
- `blocked`
- `done`

## 5. Evidence Model

Findings may receive evidence attachments through the core artifacts service.

In v1, remediation actions do not yet carry their own artifact stream.

## 6. UI Model

The plugin contributes:

- a findings register
- a remediation board

The findings register must support:

- create finding
- edit finding
- transition finding workflow
- attach evidence
- create remediation action from the finding row

The remediation board must support:

- cross-finding action visibility
- edit action status, due date, notes, and owner

## 7. Security Model

Permissions:

- `plugin.findings-remediation.findings.view`
- `plugin.findings-remediation.findings.manage`

All write operations must be routed through the core authorization middleware.

## 8. Out of Scope for v1

- audit import pipelines
- automated finding generation from frameworks
- exception handling
- remediation dependencies and sequencing
- SLA escalation automation
- action-level workflow engine
