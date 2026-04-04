# Project TODO

Simple list of the main pending items.

Status note:

- High-level status in this file mirrors `status-index-v1.md`.
- Execution-level status and checklists live in `longer-term-execution-todo-v1.md`.

## Delivery Rules

- [x] Keep the public demo alive as a first-class maintained surface.
- [x] For every feature change, explicitly check whether the `demo` branch and `demo_builder/patches/` need to be refreshed.
- [x] If product changes affect demo-specific behavior or files already represented in the patch pack, regenerate the affected demo patches before closing the work.
- [x] Require tests for every new or changed mutable parameter so ownership, permission, role, scope, and quota boundaries are enforced and cannot be spoofed by unauthorized users.
- [x] Require documentation updates for every completed slice so specs, workflow notes, and product-facing docs stay aligned with the implemented behavior.
- [x] Require in-application `HELP` updates whenever a feature, workflow, screen, object, or governed record type is added, changed, improved, or removed.
- [x] Treat `tests + demo check + documentation update` as the minimum closeout checklist for every code change.

## Product

- [x] Finish multi-owner and assignment depth for assessments and any remaining surfaces still outside the shared assignment model.
- [x] Framework-specific reporting presets and readiness views.
- [x] Communications and reminder channels from the admin UI.
- [x] SMTP and outbound mail configuration from the web admin.
- [x] Notification templates for reminders and operational follow-up.
- [x] Reminder delivery beyond in-app dispatch.
- [x] Framework-specific exports for adopted frameworks.
- [x] Leadership snapshots per framework and scope.

## Identity and Access

- [x] LDAP login mode completion on top of the current sync connector.
- [x] Clear web UI for governing object access matrices.
- [x] Better separation between platform administration and delegated access governance where still rough.
- [x] Extend parameter-level authorization coverage until every GET/POST mutation path that changes governed data has an explicit ownership and authorization regression test.

## Data Governance

- [x] Support multiple accountable parties for assets.
- [x] Replace remaining single-owner semantics with assignment-based ownership where needed.
- [x] Finish governed business catalogues where free text still exists.
- [x] Distinguish clearly between system enums and business-managed catalogues in all affected screens.

## UI and Usability

- [x] Finish the remaining UI cleanup batches from `ui-review-and-refactor-todo-2026-03.md`.
- [x] Define and apply a reusable governance-page interaction pattern for admin-heavy screens.
- [x] Finish shell support for cleaner contextual `index -> detail` navigation.
- [x] Keep updating help/support content as screens and workflows change.

## Reporting and Compliance Content

- [x] Deeper management reporting across assessments, evidence, risks, and findings.
- [x] Readiness summaries by organization, scope, and adopted framework.

## Longer-Term

- [x] Third-party risk / vendor review workspace.
- [x] Secure external collaboration model generalized beyond third-party-risk and link-only flows.
- [x] Questionnaire engine with internal, brokered, and direct secure external collection modes.
- [ ] Automation catalog with the first installable pack and connector-backed autonomous execution runs.
- [x] Collaboration layer for comments, requests, handoffs, tasks, and shared draft continuity.
