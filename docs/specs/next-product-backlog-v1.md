# Next Product Backlog v1

## Goal

Define the next product wave after the current governance, reporting, and domain baseline.

This wave should prioritize product competitiveness, not platform-internal technical depth.

## Product Direction

The next differentiating product loop should be:

`request -> answer -> evidence -> review -> finding -> remediation -> follow-up`

This loop should work first for third-party risk and later expand into broader collaboration and automation.

## Guardrails

### 1. Internet-facing operation is an official supported mode

The product must support both:

- internet-facing deployments
- private or internally restricted deployments

The platform should not treat public exposure as an edge case, but it must treat it as a
security-first operating mode.

Therefore:

- questionnaires may run through direct secure external access
- vendor collaboration may run in internet-facing deployments
- the product must also remain valid for internal-only or brokered operating models
- external-access features must have explicit security boundaries and deployment guidance

### 2. Sensitive data handling must stay conservative

External users must never receive implicit access to broad internal records just because they are linked to a vendor.

Any external collaboration model must be:

- explicit
- object-scoped
- revocable
- auditable

Internet-facing support does not change this rule.

### 2b. File uploads must stay security-first

Every upload flow should validate that the uploaded file matches the document family expected by that flow.

The platform should not accept unrestricted uploads where the product actually expects:

- pdf
- spreadsheet
- office document
- image
- plain text

This matters especially for:

- external collaboration
- vendor evidence requests
- questionnaire attachments
- review workpapers
- evidence promotion

### 3. Automation must stay modular

The platform should not assume that every deployment wants every automation or connector.

Automations should be:

- plugin-capable
- installable or enableable selectively
- visible through a catalog or marketplace-style administration surface

The open-source model fits a catalog better than a closed commercial marketplace.

## Priority Order

## 1. Third-Party Risk / Vendor Review Workspace

This is the first product wedge because it naturally combines:

- vendors
- questionnaires
- evidence
- findings
- remediation
- follow-up governance

### v1 Scope

- vendor register
- vendor tiering and review status
- vendor review workspace
- internal and controlled external questionnaire collection
- linked evidence and review artifacts
- findings and remediation tied to one vendor review
- reminders and follow-up workflow

### v1 Non-Goals

- full procurement lifecycle
- financial contract management
- broad supplier master-data replacement

## 2. Secure External Collaboration Model

This is not a fallback mode. It is a first-class product capability for vendors, auditors, and
other controlled external participants.

The product needs an explicit model for external participants such as vendors, auditors, or consultants.

### v1 Scope

- external collaborator records
- explicit external access grants per review / questionnaire / request
- narrow object-scoped visibility
- time-bounded access
- auditable invite / revoke lifecycle
- security baseline for externally reachable collaboration flows
- upload restrictions tied to explicit allowed file profiles

### Access Rule

An external vendor should only see:

- the vendor review or request they are participating in
- the questionnaire, evidence requests, findings, and actions explicitly shared with them

They should not automatically see the full internal asset inventory or unrelated records.

## 3. Questionnaire Engine

The questionnaire engine should support multiple collection modes without forcing one deployment
model.

### v1 Modes

- internal mode:
  internal staff completes or reviews the questionnaire inside the normal workspace
- brokered mode:
  the company sends the questionnaire externally and then captures the received answers back into the platform
- direct external mode:
  the external participant receives a controlled invitation or secure link and answers inside the platform

### v1 Scope

- questionnaire templates
- sections and typed questions
- answer records
- review / accept workflow
- answer-to-evidence linkage
- reusable answer library
- controlled external response flow when enabled

## 4. Automation Catalog

Automation should be delivered as selective product capability, not a monolithic always-on feature set.

### v1 Scope

- automation catalog or market-style screen
- install / enable / disable selected automation packs
- automation ownership metadata
- health and last-run status
- mapping from automation output to evidence or review workflows

### Examples

- evidence refresh packs
- connector-backed control checks
- scheduled follow-up jobs
- vendor review reminder flows

## 5. Collaboration Layer

Once vendor review, questionnaires, and automations exist, the product needs collaboration primitives to keep work moving.

### v1 Scope

- record comments and discussion timeline
- requests and follow-up tasks
- mentions / assignment cues
- handoff states
- shared drafts and continuity across users

## Commercial / Responsibility Note

This is not a product feature backlog item, but it is a product-adjacent requirement:

- self-hosted deployment guidance must clearly state that secure exposure, network posture, patching, secrets handling, and operational hardening remain deployment responsibilities
- any internet-facing or external-access mode should be documented with explicit warnings, recommended deployment patterns, and operator responsibilities

That boundary should be reflected in documentation, licensing, and commercial terms, not only in code.
