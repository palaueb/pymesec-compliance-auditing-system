# Title

Policies and Exceptions Plugin Specification v1

# Status

Implemented in iterative slices.

# Context

The PRD requires governance capabilities that go beyond catalogs and findings. Organizations need a way to manage policy statements, publish approved versions, and track explicit exceptions with compensating controls and expiry dates.

# Specification

## 1. Purpose

The `policy-exceptions` plugin manages:

- governance policies
- lifecycle state of those policies
- policy exceptions
- compensating controls, expiry dates, and linked findings

## 2. Core Domain Model

### Policy

A policy is a governed statement with accountability and review cadence.

Minimum fields in v1:

- stable policy identifier
- organization and optional scope
- title
- area from the governed `policies.areas` business catalog
- version label
- statement text
- optional linked control identifier
- optional review due date

### Policy Exception

A policy exception is a time-bound deviation from a policy.

Minimum fields in v1:

- stable exception identifier
- parent policy identifier
- organization and optional scope
- title
- rationale
- optional compensating control
- optional linked finding identifier
- optional expiry date

## 3. Ownership Model

Ownership uses the core functional actor assignment system.

Current runtime:

- policies may have multiple active owner assignments
- policy exceptions may have multiple active owner assignments
- owner removal is explicit and auditable

## 4. Workflow Model

Policies use a publication workflow:

- `draft`
- `review`
- `active`
- `retired`

Exceptions use an approval workflow:

- `requested`
- `approved`
- `expired`
- `revoked`

## 5. Evidence Model

Policies and exceptions may receive attachments through the core artifacts service.

Examples:

- policy documents
- exception approval memos
- compensating control evidence

## 6. UI Model

The plugin contributes:

- a policies register
- an exceptions board

The policies register must support:

- create policy
- edit policy
- transition policy workflow
- attach policy documents
- create exceptions from the policy row

The exceptions board must support:

- cross-policy visibility of exceptions
- transition exception workflow
- attach evidence
- edit scope, owner, rationale, and expiry

## 7. Security Model

Permissions:

- `plugin.policy-exceptions.policies.view`
- `plugin.policy-exceptions.policies.manage`

All write operations must be enforced by the core authorization middleware.

Mutable governed fields such as `area`, linked references, scope, and owner assignments must carry regression tests proving unauthorized actors cannot spoof or mutate them outside their allowed context.

## 8. Out of Scope for v1

- full document version diffing
- approval quorum logic
- exception renewal automation
- policy attestations
- exception-to-asset scoping rules beyond organization and scope
