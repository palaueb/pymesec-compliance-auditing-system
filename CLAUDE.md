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
- Run `make test` from repo root

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
