# Project TODO

Simple list of the main pending items.

## Delivery Rules

- [ ] Keep the public demo alive as a first-class maintained surface.
- [ ] For every feature change, explicitly check whether the `demo` branch and `demo_builder/patches/` need to be refreshed.
- [ ] If product changes affect demo-specific behavior or files already represented in the patch pack, regenerate the affected demo patches before closing the work.
- [ ] Require tests for every new or changed mutable parameter so ownership, permission, role, scope, and quota boundaries are enforced and cannot be spoofed by unauthorized users.

## Product

- [ ] Multi-owner and assignment depth across assets, risks, findings, continuity, privacy, policy, controls, and assessments.
- [ ] Framework-specific reporting presets and readiness views.
- [ ] Communications and reminder channels from the admin UI.
- [ ] SMTP and outbound mail configuration from the web admin.
- [ ] Notification templates for reminders and operational follow-up.
- [ ] Reminder delivery beyond in-app dispatch.
- [ ] Framework-specific exports for adopted frameworks.
- [ ] Leadership snapshots per framework and scope.

## Identity and Access

- [ ] LDAP login mode completion on top of the current sync connector.
- [ ] Clear web UI for governing object access matrices.
- [ ] Better separation between platform administration and delegated access governance where still rough.
- [ ] Extend parameter-level authorization coverage until every GET/POST mutation path that changes governed data has an explicit ownership and authorization regression test.

## Data Governance

- [ ] Support multiple accountable parties for assets.
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
- [ ] Readiness summaries by organization, scope, and adopted framework.

## Longer-Term

- [ ] Vendor / third-party risk workflows.
- [ ] External evidence collectors and automated fetch connectors.
- [ ] Shared draft persistence and cross-user editing continuity.
