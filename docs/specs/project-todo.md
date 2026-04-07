# Project TODO (Canonical Single File)

Snapshot date: 2026-04-05

This is the only active TODO checklist for product work.

Archived (non-canonical) backlog history:
- `status-index-v1.md`
- `longer-term-execution-todo-v1.md`
- `application-missing-backlog-v1.md`
- `ui-review-and-refactor-todo-2026-03.md`
- `project-todo-after-all-current-work.md`

## Delivery Rules (Always On)

- [x] Keep the public demo alive as a first-class maintained surface.
- [x] For every feature change, explicitly check whether the `demo` branch and `demo_builder/patches/` need refresh.
- [x] If product changes affect demo behavior or patch-pack files, regenerate the affected demo patches before closing.
- [x] Require tests for every new or changed mutable parameter (ownership, permission, role, scope, quota).
- [x] Require documentation updates for every completed slice.
- [x] Require in-application `HELP` updates whenever a feature/workflow/screen/object changes.
- [x] Treat `tests + demo check + documentation update` as minimum closeout.
- [ ] Require API parity for product actions: every relevant UI action must also be available through authenticated and authorized REST API endpoints.

## Active Pending: Current Wave

- [ ] Automation package artifact install flow (`download -> verify -> register -> enable`).
- [ ] `pack.json` manifest schema and validation gate in platform install flow.
- [ ] Static inspection gate for forbidden functions/patterns before install.
- [ ] Capability/permission approval view before enabling a pack.
- [ ] Brokered runtime contract (no direct DB access from pack code).
- [ ] Generated config forms from pack metadata and per-pack encrypted secret storage.
- [ ] Kill switch and repository-level revocation controls.
- [ ] Runtime overlap lock to prevent concurrent executions of the same pack/scope slot.
- [ ] Per-run timeout controls and explicit timeout outcomes in run/check diagnostics.
- [ ] Structured runtime counters/events per mapping and target (`resolved`, `ok`, `failed`, `skipped`, `guardrail_denied`, `duration_ms`).
- [ ] Operator diagnostics panel for runtime failures, guardrail denials, and retry-heavy mappings.
- [ ] Optional scheduled reminder notifications for links near expiry.
- [ ] Optional scheduled operator digest for external collaboration lifecycle posture.
- [ ] Optional scheduled retention cleanup/archive policy for old revoked/expired links.

## Active Pending: UI and Data Governance Residual

- [ ] Enforce focused detail mode in every module: when a detail is open, hide parent index panels by default.
- [ ] Normalize governed fields so reporting/filtering does not depend on free text.
- [ ] Define return-to-origin flows for `create related record` actions.
- [ ] Introduce managed vocabularies for business fields still using free text.
- [ ] Distinguish system workflow enums from business catalogues.
- [ ] Add selectors or lookup widgets for governed references where still missing.
- [ ] Support multiple accountable parties for assets where still unresolved.
- [ ] Govern exercise types and execution types if they remain business-level concepts.
- [ ] Replace single owner semantics with assignment-based ownership where still unresolved.
- [ ] Govern assessment results as system values where still unresolved.
- [ ] Govern finding severity through controlled values where still unresolved.
- [ ] Keep requirements and controls linked by object, not by copied text.
- [ ] Review linked object fields for multi-select governance where needed.
- [ ] Standardize placement of page-level primary actions.

## Active Pending: Post-Current-Work Competitive Gaps

- [ ] Connector depth: cloud (AWS/Azure/GCP), identity (Okta/Azure AD/Google Workspace), and code platforms (GitHub/GitLab).
- [ ] Automated evidence collection (machine captures and log/artifact retrieval).
- [ ] Asset discovery and Shadow IT detection.
- [ ] Continuous monitoring and drift detection alerts.
- [ ] Dynamic risk posture recalculation from threat signals/CVEs.
- [ ] Full vendor portal maturity for supplier-provided certifications/evidence.
- [ ] Remediation workflow integration with delivery tools (Jira/Linear).
- [ ] Push updates for legal/framework changes (for example ENS revisions).

## Completed Baseline (Reference)

- [x] Third-party risk / vendor review workspace.
- [x] Secure external collaboration generalized beyond third-party-risk and link-only flows.
- [x] Questionnaire engine with internal, brokered, and direct secure external collection modes.
- [x] Automation catalog with first installable pack and connector-backed autonomous execution baseline.
- [x] Collaboration layer for comments, requests, handoffs, tasks, and shared draft continuity.
- [x] Deeper management reporting across assessments, evidence, risks, findings, and readiness slices.
