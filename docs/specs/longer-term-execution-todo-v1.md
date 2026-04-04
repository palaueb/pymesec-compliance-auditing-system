# Longer-Term Execution TODO v1

This file turns the current longer-term product backlog into an executable checklist.

It is intentionally narrower than `project-todo-after-all-current-work.md`.
This document tracks only the active post-baseline backlog that still belongs to the current product evolution wave.

## Status Legend

- `[x]` completed
- `[ ]` pending

## 1. Third-Party Risk / Vendor Review Workspace

### Core register and workspace

- [x] Create a dedicated vendor register.
- [x] Support vendor tiering and vendor status.
- [x] Provide a current review workspace per vendor.
- [x] Keep review state, decision notes, owners, and linked records in one workspace.
- [x] Support review workflow transitions.
- [x] Support review evidence uploads.
- [x] Support review-bound questionnaire items.
- [x] Support review profiles and questionnaire templates.
- [x] Support template application into review-bound questionnaire items.

### Still missing

- [x] Add findings/remediation continuity inside the vendor review workspace instead of only indirect linking.
- [x] Add reassessment-focused list filters such as due soon / overdue / decision pending.
- [x] Add timeline and review activity rendering as a first-class workspace section.
- [x] Add stronger management reporting slices for vendor review load and decision posture.

## 2. Secure External Collaboration Model

### External access baseline already implemented

- [x] Issue external review links bound to one concrete vendor review.
- [x] Make external links expirable.
- [x] Make external links revocable.
- [x] Keep external access object-scoped.
- [x] Separate public portal routes from the internal workspace.
- [x] Allow controlled questionnaire answering through the external portal.
- [x] Allow controlled evidence upload through the external portal.
- [x] Track external-link audit events.
- [x] Track invitation delivery state.
- [x] Support email invitation delivery through the organization SMTP connector.

### Still missing

- [x] Generalize the external collaboration model beyond third-party-risk.
- [x] Add a dedicated external collaborator model instead of relying only on links.
- [x] Add stronger operator-facing lifecycle views for issued, active, expired, and revoked external access.
- [x] Add explicit deployment hardening guidance inside product-facing docs and HELP where relevant.

### Operational scheduling (recommended, not required for access enforcement)

- [ ] Add optional scheduled reminder notifications for links near expiry.
- [ ] Add optional scheduled operator digest for external collaboration lifecycle posture.
- [ ] Add optional scheduled retention cleanup/archive policy for old revoked/expired links.

## 3. Questionnaire Engine

### Questionnaire foundation already implemented

- [x] Support review-bound questionnaire items.
- [x] Support typed response modes for questionnaire items.
- [x] Support response status tracking.
- [x] Support sectioned questionnaire structure inside vendor reviews.
- [x] Support questionnaire templates.
- [x] Support questionnaire templates linked to review profiles.
- [x] Support template application without duplicating already-applied items.
- [x] Support external answering through the vendor review portal.
- [x] Extract the first shared questionnaire engine layer for types, statuses, validation, and section grouping.
- [x] Move the questionnaire engine implementation into a transversal `questionnaires` plugin and keep only contracts in core.

### Still missing

- [x] Extract questionnaire persistence and templates into a transversal plugin instead of keeping storage inside third-party-risk.
- [x] Extract sectioned questionnaire structure into a transversal plugin instead of keeping it only inside vendor reviews.
- [x] Add reusable answer library support.
- [x] Add brokered collection mode.
- [x] Add internal review / accept workflow as a first-class engine concept.
- [x] Add attachment semantics and evidence promotion rules at questionnaire-engine level.

## 4. Automation Catalog

### Current state

- [x] Define the automation-pack object model.
- [x] Add a catalog UI for install / enable / disable.
- [x] Add automation ownership and provenance metadata.
- [x] Add health, last-run, and failure-state tracking.
- [x] Add mapping from automation output to evidence refresh or review workflow updates.
- [x] Add the first installable automation pack (`utility.hello-world` external sample pack).

### Packaging and supply-chain model (next required cut)

- [x] Add external package repository ingestion (`repository.json`) with multi-URL support.
- [ ] Add package artifact install flow (`download -> verify -> register -> enable`).
- [x] Add source-to-deploy publish pipeline in the packs repository (`src -> deploy` with `latest` + versioned zip history).
- [ ] Add first `pack.json` manifest schema and validation gate in platform install flow.

### Security policy implementation (next required cut)

- [ ] Add static inspection gate for forbidden functions/patterns before install.
- [ ] Add capability/permission approval view so operator can review requested scope before enabling a pack.
- [ ] Add brokered runtime contract (no direct DB access from pack code).
- [ ] Add generated config forms from pack metadata and per-pack encrypted secret storage.
- [ ] Add kill switch and repository-level revocation controls as first-class operator actions.

This block now has the foundational catalog and mapping layer, but still needs the first installable pack and run-level telemetry depth.

## 5. Collaboration Layer

### Current state

- [x] Add record comments and discussion timeline.
- [x] Add follow-up requests and task objects.
- [x] Add mention / assignment cues.
- [x] Add handoff states between review, remediation, and approval work.
- [x] Add shared drafts or continuity between users.

This block now has a reusable baseline and no open checklist items in this wave.

## Suggested Delivery Order

If we continue this wave pragmatically, the clean order is:

1. Ship the first installable automation pack.
2. Extend pack-level health into run-level execution history and telemetry.

## Positioning Note

The current product is already beyond a simple checklist tool.

However, the strongest remaining competitive gap is still this:

- the platform organizes and governs work very well
- but it still needs more automation, external participation, and system-to-system connectivity before it feels closer to continuous compliance operations

That is the real purpose of this file: convert that next wave into a trackable execution list instead of one vague longer-term block.
