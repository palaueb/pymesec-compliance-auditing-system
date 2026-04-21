# Title

Continuity and BCM Plugin Specification v1

# Status

Draft

# Context

After controls, risks, findings, policies, privacy, workflow, artifacts, and functional actors are in place, the next operational vertical is business continuity management. The platform needs a way to describe critical services, attach recovery plans, assign accountable owners, and keep continuity evidence and reviews inside the same tenancy and audit substrate.

# Specification

## 1. Purpose

The `continuity-bcm` plugin manages:

- critical continuity services
- recovery plans
- service impact and recovery objectives
- continuity ownership and review cadence
- links to risks, findings, policies, and evidence

## 2. Core Domain Model

### Continuity Service

A continuity service represents an operational capability that requires explicit recovery governance.

Minimum fields in v1:

- stable service identifier
- organization and optional scope
- title
- impact tier
- recovery time objective
- recovery point objective
- linked asset identifier
- linked risk identifier

Generated service, dependency, exercise, test execution, and recovery plan identifiers must remain within the 120-character storage contract even when they are derived from long titles or combined parent-child identifiers. If a generated identifier collides after truncation, the plugin appends a short random suffix while preserving the same maximum length.

### Recovery Plan

A recovery plan represents the documented response and recovery strategy for one continuity service.

Minimum fields in v1:

- stable plan identifier
- linked service identifier
- organization and optional scope
- title
- strategy summary
- test due date
- linked policy identifier
- linked finding identifier

## 3. Ownership Model

Ownership must use functional actors from the core.

In v1:

- continuity services may have multiple active owner assignments
- recovery plans may have multiple active owner assignments
- owner removal is explicit and auditable

## 4. Workflow Model

The plugin should reuse the core workflow engine for:

- `draft`
- `review`
- `active`
- `retired`

for both continuity services and recovery plans where appropriate.

## 5. Evidence Model

The plugin must integrate with core artifacts for:

- continuity plan documents
- recovery exercises
- test outputs
- service dependency maps

## 6. Integration Model

The plugin must be able to link to:

- `risk-management`
- `policy-exceptions`
- `findings-remediation`
- `asset-catalog`

## 7. UI Model

The first usable UI should provide:

- a continuity services register
- a recovery plans register
- simple create and edit forms
- evidence attachments
- workflow transitions

Recovery plans now follow a master-detail pattern:

- the register stays focused on summaries and `Open`
- one selected recovery plan becomes the operational detail workspace
- evidence, exercises, test runs, linked records, ownership, and workflow stay in detail

## 8. Out of Scope for v1

- full dependency graph modeling
- live failover orchestration
- exercise scheduling engine
- crisis communications management
- supplier continuity analysis
