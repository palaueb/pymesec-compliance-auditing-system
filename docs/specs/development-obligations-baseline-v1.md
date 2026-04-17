# Title

Development Obligations Baseline v1

# Status

Active

# Purpose

Define one clear, enforceable baseline for how we implement changes so quality is consistent across modules, APIs, tests, documentation, and security controls.

# Non-Negotiable Obligations

A change is not complete unless all obligations below are satisfied.

1. Security-first design
- Apply least privilege and object-level authorization for all mutable operations.
- Preserve data isolation by organization/scope and avoid cross-context leakage.

2. API-first implementation parity
- Every new or changed product capability must include REST API coverage.
- For plugin domain features, implement routes in `plugins/<plugin-id>/routes/api.php`.
- Every `/api/v1` operation must define `_openapi` metadata.
- If a capability is intentionally not exposed by API, document the exception and rationale explicitly in spec.

3. Documentation obligation (module + API)
- Update the affected module documentation in `docs/specs/`.
- Ensure each module spec includes current API surface (or a direct pointer to API contract section).
- Update in-application `HELP` for any added/changed/removed feature, workflow, screen, or object.

4. OpenAPI artifact obligation
- Run `php artisan openapi:publish` when API contracts change.
- Commit both canonical artifacts:
- `core/public/openapi.json`
- `core/public/openapi/v1.json`
- Verify drift gate with `php artisan openapi:publish --check`.

5. Global test obligation
- Run full global validation, not only targeted tests:
- `composer lint`
- `composer test`
- No feature is done if global suite is red.

6. Auditability obligation
- Preserve unified append-only audit trail for both WEB and API operations.
- New operations must keep required audit context (`channel`, `author`, `principal`, outcome).

7. Demo and delivery obligation
- If behavior affects demo flows or demo patch pack, refresh `demo_builder/patches/` before closeout.
- Validate demo deployment path for changes that impact startup/config/routes.

8. Status and backlog hygiene obligation
- Update `docs/specs/project-todo.md` to reflect real completion status.
- Do not mark items complete without code, docs, and validation evidence.

# Definition of Done (Checklist)

Use this checklist for each delivered slice:

- [ ] Feature implemented with required security and authorization checks.
- [ ] API endpoints implemented for the feature (or documented exception approved).
- [ ] `_openapi` metadata present for all new/changed `/api/v1` routes.
- [ ] Module spec updated (including API behavior).
- [ ] In-app `HELP` updated.
- [ ] OpenAPI artifacts regenerated and committed.
- [ ] Global lint and test suite executed successfully.
- [ ] Audit behavior verified for WEB/API changes.
- [ ] Demo impact evaluated and updated if required.
- [ ] `project-todo.md` updated to real state.

# Notes

This document is operational and mandatory.
If any other document is less strict, this baseline prevails until explicitly revised.
