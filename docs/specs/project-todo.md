# Project TODO

Simple list of the main pending items.

## Delivery Rules

- [x] Keep the public demo alive as a first-class maintained surface.
- [x] For every feature change, explicitly check whether the `demo` branch and `demo_builder/patches/` need to be refreshed.
- [x] If product changes affect demo-specific behavior or files already represented in the patch pack, regenerate the affected demo patches before closing the work.
- [x] Require tests for every new or changed mutable parameter so ownership, permission, role, scope, and quota boundaries are enforced and cannot be spoofed by unauthorized users.
- [x] Require documentation updates for every completed slice so specs, workflow notes, and product-facing docs stay aligned with the implemented behavior.
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

- [ ] LDAP login mode completion on top of the current sync connector.
- [x] Clear web UI for governing object access matrices.
- [ ] Better separation between platform administration and delegated access governance where still rough.
- [ ] Extend parameter-level authorization coverage until every GET/POST mutation path that changes governed data has an explicit ownership and authorization regression test.

## Data Governance

- [x] Support multiple accountable parties for assets.
- [ ] Replace remaining single-owner semantics with assignment-based ownership where needed.
- [ ] Finish governed business catalogues where free text still exists.
- [ ] Distinguish clearly between system enums and business-managed catalogues in all affected screens.

## UI and Usability

- [ ] Finish the remaining UI cleanup batches from `ui-review-and-refactor-todo-2026-03.md`.
- [ ] Define and apply a reusable governance-page interaction pattern for admin-heavy screens.
- [ ] Finish shell support for cleaner contextual `index -> detail` navigation.
- [ ] Keep updating help/support content as screens and workflows change.

## Reporting and Compliance Content

- [ ] More framework packs beyond the current baseline.
- [ ] Deeper management reporting across assessments, evidence, risks, and findings.
- [x] Readiness summaries by organization, scope, and adopted framework.

## Longer-Term

- [ ] Vendor / third-party risk workflows.
- [ ] External evidence collectors and automated fetch connectors.
- [ ] Shared draft persistence and cross-user editing continuity.
