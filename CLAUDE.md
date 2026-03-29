# PymeSec — Claude Code Context

## Project Overview

PymeSec is an open-source compliance auditing platform for SMEs. Built with PHP 8.x + Laravel 10, it follows a monorepo + plugin architecture. Target frameworks: ISO/IEC 27001, ENS, NIS2, GDPR/LOPDGDD. Vision: "WordPress of compliance" — stable core, everything else pluggable.

## Monorepo Layout

```
core/       → Laravel application — platform infrastructure only, never domain logic
plugins/    → Official domain plugins, each independently loadable
packages/   → Shared PHP packages (SDK, contracts, base classes)
docs/       → PRDs, ADRs, specs (source of truth for decisions)
docker/     → Docker service definitions
Makefile    → All common dev commands
```

## Demo Governance

The public demo is a maintained product surface, not a disposable side branch.

- Always preserve the demo workflow under `demo_builder/`.
- Before shipping product changes, assess whether the demo branch or exported patch pack must also be updated.
- If a change affects demo behavior, demo content, demo auth/setup, or any file already covered by `demo_builder/patches/`, refresh the corresponding demo implementation and regenerate the patch pack.
- Do not introduce demo-only runtime behavior into `main` unless the user explicitly changes that policy.
- Treat `demo_builder/state/` as local metadata only; the reviewable source of truth for the demo delta is `demo_builder/patches/`.
- If `demo_builder/demo-builder.sh drift` reports drift on touched files, reconcile the demo branch before considering the work complete.

## Delivery Workflow

Every feature, fix, refactor, or new slice must close with these three checks inside the normal delivery flow:

1. tests: create, update, or repair the tests needed to prove the worked area behaves correctly and still enforces ownership, authorization, and mutation boundaries
2. demo: check whether the demo branch or exported patch pack must be refreshed, and refresh it when the touched work affects the demo surface
3. documentation: update existing docs or add new docs/spec notes so the implemented behavior, workflow, or product surface does not drift from the documented state

## Key Architecture Rules

These come from accepted ADRs in `docs/adr/` — never work against them:

- **Core owns:** plugin manager, permission engine, event bus, audit trail, tenancy, UI shell, i18n, workflow engine, artifact storage, authorization roles/grants
- **Plugins own:** all domain logic, framework packs, connectors, reporting
- **Boundary rule:** cross-cutting infrastructure → core; domain-specific or replaceable → plugin
- **Identity vs Actors (ADR-003):** platform access identity (auth/sessions/memberships) is STRICTLY SEPARATE from functional actors (ownership/responsibility). An actor can exist without login; a user can have no functional role.
- **Tenancy (ADR-009):** Organization = primary tenant boundary. Scope = optional narrower partition within an org.
- **Menus (ADR-011):** plugins contribute menu items via manifest only — never direct binding or core modification.
- **Translations (ADR-007):** namespaced JSON files — `core.*` for core, `plugin.<id>.*` for plugins. No global pool.
- **Permissions (ADR-004):** core permission engine is organization-aware and scope-aware. Plugins register permissions through contracts, not by modifying core.
- **Workflows (ADR-012):** core defines mechanics (state transitions, guards, hooks); plugins define semantics (states, business rules, side effects).
- **Audit trail (ADR-010):** append-only, sensitive operations only. Plugins use core audit contract. No secrets in payloads.

## Plugin Structure

Every plugin lives under `plugins/<plugin-id>/`:

```
plugin.json              → manifest (id, name, version, requires, permissions, routes, migrations)
src/
  Plugin.php             → plugin bootstrap class
  Routes/                → route definitions
  Http/Controllers/      → controllers
  Models/                → Eloquent models
  Repositories/          → data access layer
resources/
  views/                 → Blade templates
  lang/                  → translation JSON files per language
database/
  migrations/            → plugin-scoped migrations
```

## Common Commands

```bash
# Environment
make up              # Start Docker stack
make down            # Stop stack
make shell           # Enter app container
make logs            # Follow container logs

# Data
make migrate         # Run all migrations
make seed-system     # Bootstrap-only (no demo data)
make seed-demo       # Full demo dataset
make reset-demo      # Drop and re-seed demo

# Quality
make test            # Run PHPUnit suite
make lint            # Run PHP linter

# Plugin management (inside container or via docker compose exec app)
php artisan plugins:list
php artisan plugins:enable <plugin-id>
php artisan plugins:disable <plugin-id>
php artisan menus:list
php artisan workflows:list
```

## Testing Conventions

- Base tests use in-memory SQLite with `TestDatabaseSeeder` — fast and isolated
- Integration tests must hit a real database, never mock it
- Every product or core change that introduces or touches request parameters, linked IDs, ownership fields, authorization context, or mutable state must ship with tests that prove unauthorized users cannot spoof or modify those parameters outside their real permissions, memberships, roles, scopes, ownership, or quota boundaries.
- Treat parameter ownership and authorization guarantees as part of the feature definition, not optional hardening after the fact.
- Do not treat documentation as optional cleanup. If the implemented behavior changed, the relevant spec, README, TODO state, or workflow note must change in the same slice.
- Run `make test` from repo root

### Test Quality Policy

Coverage target is **behavior coverage, not line coverage**. Do not add tests solely to raise a percentage. Every test must assert an observable outcome that would catch a real regression.

**Priority tiers — test all three when touching a feature:**

1. **Authorization boundary tests** — the most critical tier. For every action, prove that:
   - An unauthenticated request is rejected (401/403)
   - A user from a different organization cannot access or mutate the resource
   - A user without the required permission is rejected even within the same org
   - Ownership/scope fields cannot be spoofed via request parameters

2. **Behavior contract tests** — prove the feature does what the spec says:
   - Happy path: valid input produces the expected state change or output
   - Rejection path: invalid input is rejected with a meaningful error, not a 500
   - State transitions: workflow guards reject invalid transitions; valid ones succeed

3. **Plugin contract tests** — for every plugin, prove the integration with core:
   - Permissions are registered correctly and checked via the core engine
   - Routes are scoped to the plugin and require the expected permission
   - Events/hooks fire when expected; the plugin does not bypass the core contract

**What NOT to test:**
- Trivial getters, config arrays, or Eloquent attribute casts — no regression value
- Framework internals (Laravel validation, Eloquent save) — trust the framework
- Implementation details that can change without breaking behavior

**When writing or reviewing tests with AI assistance:**
- Write the test description first (what behavior is being proved), then the implementation
- After generating a test, verify the assertion would *fail* if the production code were broken — if it passes vacuously, it is not a test
- Prefer one clear assertion per test over multiple loosely related assertions
- If a test is hard to write, it is usually a signal that the production code has too many responsibilities

## Current State (March 2026)

**Complete:** Core infrastructure, plugin lifecycle, permission engine, UI shell, audit trail, i18n, tenancy, artifact storage, workflow engine.

**Implemented plugins:** identity-local, controls-catalog, risk-management, findings-remediation, policy-exceptions, data-flows-privacy, continuity-bcm, asset-catalog, actor-directory, assessments-audits (basic state only).

**In progress:**
- assessments-audits: full workflow, workpapers, evidence integration — see `docs/specs/assessments-audits-plugin-v1.md`
- UI master-detail refactor — see `docs/specs/ui-review-and-refactor-todo-2026-03.md`
- Evidence management as first-class object

**Pending:** framework packs (ISO 27001, ENS, NIS2, GDPR), automated connectors (Wazuh, osquery, OpenSCAP), advanced reporting, notifications delivery, object-level access control.

## Docs Entry Points

- `docs/prd/prd-compliance-core-plugins.md` — full product requirements (1100+ lines, source of truth)
- `docs/adr/` — 19 architectural decision records, all Accepted
- `docs/specs/` — 25+ implementation specs (one per feature area)
- `docs/specs/next-steps-memory-2026-03.md` — prioritised next steps
