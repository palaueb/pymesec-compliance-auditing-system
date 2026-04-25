# Project TODO (Canonical Single File)

Snapshot date: 2026-04-23

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
- [x] Require API route-level OpenAPI metadata as source of truth (`/api/v1` routes must declare `_openapi`; generated contract must come from router extraction).
- [x] Require API parity for product actions: every relevant UI action must also be available through authenticated and authorized REST API endpoints.
- [x] Require complete application auditability: every WEB and API operation (read/write, success/failure) must be recorded in append-only database logs.
- [x] Require governed-write discipline in API: clients must obtain allowed values for constrained fields before write operations, and API must reject out-of-catalog values.

## Active Pending: P0 API/OpenAPI Program

- [x] Publish a canonical modular `openapi.json` for the full product surface (core + all functional domains).
- [x] Define and enforce OpenAPI operation ownership per module/plugin so no UI-only workflow remains undocumented in API.
- [x] Expose lookup/reference endpoints for governed fields and use the same contracts across UI, integrations, and MCP.
- [x] Implement a unified append-only operation log store for WEB + API with mandatory `channel` and `author` tagging on every record.
- [x] Enforce object-level authorization parity in every API endpoint (organization, scope, role/permission, assignment, object access rules).
- [x] Add API contract/security regression tests in CI (schema validity, authz boundaries, sensitive field redaction, audit/log side effects).
- [x] Add MCP-ready API conventions (stable `operationId`, machine-readable errors, discoverable capability endpoints).
- [x] Enforce OpenAPI coverage guardrails in tests (`api -> openapi` operation presence and baseline `web write -> api operation` parity checks for implemented product domains).
- [x] Expose relation lookup endpoints for dynamic write fields (actors, frameworks, controls, risks) and declare lookup sources per write contract.
- [x] Expand API domain coverage from current core/asset/risk/controls/assessments/findings/automation-catalog (+ remediation actions and assessment reviews) baseline to all enabled product modules and workflows.
- [x] Expand route-owned OpenAPI contracts (and shared components where needed) to cover all enabled plugin domains with complete write/action contracts.
- [x] Complete API token lifecycle governance (issue/revoke/rotate/expiry/scope policy) with platform UI and audit controls.
- [x] Publish a stable versioned OpenAPI artifact endpoint for external tooling consumption with compatibility policy.

## Active Pending: Agent-First Surfaces and Distribution

- [x] Add and maintain root `SKILL.md` as the canonical agent usage guide for API/MCP capabilities.
- [x] Publish an official MCP server implementation with tenancy, permission, and object-access enforcement parity.
- [x] Provide a reproducible MCP smoke workflow (`make mcp-smoke`) to validate authenticated tool parity against API/OpenAPI.
- [ ] Register official agent integration surfaces (MCP server + skill metadata) in discovery registries/context hubs.
- [ ] Provide a versioned machine-readable sample dataset bundle (organization, scopes, assets, controls, risks, assessments, findings) for agent/integration onboarding.
- [ ] Add dual-mode CLI outputs (`human` + `--json`) for key operational commands to enable non-HTTP automation.
- [ ] Standardize machine-consumable API and CLI error contracts (stable code, reason, retryability, remediation hint).

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

## Active Pending: Framework Packs and Regulatory Operations

- [x] Add framework-specific onboarding kits so adopting GDPR, ENS, NIS2, or ISO 27001 creates a usable starter workspace instead of only enabling library content.
- [x] Add approval/governance workflow for framework adoption changes, including who approved, when, and why the framework became active or retired for an org/scope.
- [x] Add framework-specific reporting presets, management views, and export bundles per adopted framework and scope.
- [x] Add readiness summaries and progress dashboards per organization, scope, and adopted framework.
- [ ] Add legal/framework update ingestion so framework packs can publish change notices, version deltas, and upgrade guidance without manual diff work.
- [x] Add starter control/policy/evidence mapping packs per framework so adoption produces operational objects, not only framework elements.
- [ ] Complete GDPR operational coverage beyond the starter pack: full RoPA legal model and richer record-of-processing fields.
- [ ] Add GDPR DPIA workflows and artifacts so high-risk processing can be assessed as a first-class privacy object.
- [ ] Add GDPR data breach / privacy incident register and workflow, including supervisory notification handling.
- [ ] Add GDPR data subject rights case handling (access, rectification, erasure, objection, portability, restriction).
- [ ] Add GDPR consent and retention governance where processing depends on consent or time-bound retention rules.
- [ ] Add processor/subprocessor governance links between third-party risk, privacy records, and GDPR obligations.

## Active Pending: Post-Current-Work Competitive Gaps

- [ ] Connector depth: cloud (AWS/Azure/GCP), identity (Okta/Azure AD/Google Workspace), and code platforms (GitHub/GitLab).
- [ ] Automated evidence collection (machine captures and log/artifact retrieval).
- [ ] Asset discovery and Shadow IT detection.
- [ ] Continuous monitoring and drift detection alerts.
- [ ] Dynamic risk posture recalculation from threat signals/CVEs.
- [ ] Full vendor portal maturity for supplier-provided certifications/evidence.
- [ ] Remediation workflow integration with delivery tools (Jira/Linear).
- [ ] Expand framework pack coverage beyond current baseline (including SOC 2 and NIST families).
- [ ] Build certification-grade assurance track (evidence integrity, process controls, attestable operation logs).
- [ ] Introduce anonymized cross-tenant benchmark signals (remediation times, control failure patterns, recurring audit gaps) with strict privacy model.
- [ ] Add agent desire-path telemetry for failed/abandoned API flows and use it to prioritize aliases and contract ergonomics.
- [ ] Define long-term open-source distribution strategy separating open interoperability layers from advanced proprietary assurance signals.

## Future Architecture Refactors. Not to be done yet.

- [ ] Separate framework-pack availability from plugin enabled state so framework metadata, onboarding kits, and reporting presets can be discovered without treating every `framework-*` plugin as an enabled runtime module by default.

## Completed Baseline (Reference)

- [x] Third-party risk / vendor review workspace.
- [x] Secure external collaboration generalized beyond third-party-risk and link-only flows.
- [x] Questionnaire engine with internal, brokered, and direct secure external collection modes.
- [x] API route ownership physically split by plugin (`plugins/<plugin-id>/routes/api.php`) while preserving one unified OpenAPI artifact.
- [x] Automation catalog with first installable pack and connector-backed autonomous execution baseline.
- [x] Collaboration layer for comments, requests, handoffs, tasks, and shared draft continuity.
- [x] Deeper management reporting across assessments, evidence, risks, findings, and readiness slices.
