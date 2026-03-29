# Title

Data Flows and Privacy Plugin Specification v1

# Status

Draft

# Context

After controls, risks, findings, policies, and exceptions, the next high-value vertical is privacy operations. The platform needs a domain capability to describe personal data processing, map data flows, link systems and risks, and track privacy-oriented governance obligations without pushing this logic into the core.

# Specification

## 1. Purpose

The `data-flows-privacy` plugin manages:

- data flows
- processing activities
- system and vendor touchpoints
- privacy ownership and review cadence
- links to risks, policies, findings, and evidence

## 2. Core Domain Model

### Data Flow

A data flow represents movement of information between actors, systems, or organizational boundaries.

Minimum fields in v1:

- stable data flow identifier
- organization and optional scope
- title
- source
- destination
- data category summary
- transfer type
- review due date

### Processing Activity

A processing activity represents a governed privacy use case.

Minimum fields in v1:

- stable activity identifier
- organization and optional scope
- title
- purpose
- lawful basis
- linked data flow identifiers
- linked risk identifiers

## 3. Ownership Model

Ownership must use functional actors from the core.

In v1:

- data flows may have multiple active owner assignments
- processing activities may have multiple active owner assignments
- owner removal is explicit and auditable

## 4. Workflow Model

The plugin should reuse the core workflow engine for:

- `draft`
- `review`
- `active`
- `retired`

for both data flows and processing activities where appropriate.

## 5. Evidence Model

The plugin must integrate with core artifacts for:

- records of processing
- transfer assessments
- vendor documentation
- data flow diagrams

## 6. Integration Model

The plugin must be able to link to:

- `risk-management`
- `policy-exceptions`
- `findings-remediation`
- `asset-catalog`

## 7. UI Model

The first usable UI should provide:

- a data flows register
- an activities register
- simple create and edit forms
- evidence attachments
- review workflow transitions

## 8. Out of Scope for v1

- full RoPA legal model
- cross-border transfer engine
- DPIA orchestration
- consent management
- data subject rights case handling
