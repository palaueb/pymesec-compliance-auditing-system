# Title

Delivery Guardrails v1

# Status

Active

# Purpose

Define non-negotiable implementation guardrails so future changes remain consistent, testable, auditable, and API-first without depending on ad-hoc LLM output.

# Mandatory Delivery Gates

A change is not complete unless all gates below are satisfied.

1. Tests gate:
- Add or update automated tests for behavior changes.
- Include authorization and validation boundaries for mutable operations.

2. Documentation gate:
- Update the affected spec documents.
- Keep `docs/specs/project-todo.md` aligned with real status.

3. HELP gate:
- Update in-application `HELP` whenever a feature/workflow/screen/object is added, changed, improved, or removed.

4. Demo gate:
- If behavior affects demo or patch-pack files, refresh `demo_builder/patches/` before closeout.

5. Audit gate:
- Every WEB and API operation must remain auditable in the shared append-only audit model.

# API/OpenAPI Source-of-Truth Policy

For REST API contract generation, route definitions are the canonical source of truth.

Mandatory rules:

1. Every `/api/v1` route must define `_openapi` metadata in route defaults.
2. Required metadata keys for each operation:
- `operation_id`
- `tags`
- `summary`
- `responses`
3. For write operations, route metadata should include `request_form_request` (preferred) or `request_rules` so request schemas are generated from validation contracts.
4. Governed constrained fields should be declared in route metadata (`governed_fields`) so OpenAPI can expose lookup-driven write constraints.
5. Relation-style write fields (`*_id`, `*_ids`) must declare lookup sources in route metadata (`lookup_fields`) so API clients can resolve selectable values before writes.
6. OpenAPI `paths` are generated from router metadata extraction.
7. JSON fragments are allowed only for shared components/schemas/examples and high-level document metadata, not as a replacement for route operation declarations.
8. Missing `_openapi` metadata on any `/api/v1` route must fail generation/tests.

# Security and Authorization Baseline

All API routes must preserve parity with WEB security controls:

- authenticated access (except explicitly public external-collaboration routes)
- permission checks
- object-level access checks where domain objects are involved
- governed write validation for constrained fields (lookup-first pattern)

# CI Expectations

At minimum, CI must enforce:

- style/lint checks
- API contract guardrails (including mandatory route OpenAPI metadata)
- API parity guardrails (`api -> openapi` coverage and baseline `web write -> api operation` checks)
- functional tests for changed domains
- regression checks for authorization and audit side effects

# Notes

This document is normative for implementation quality gates.  
If another spec conflicts with these guardrails, this guardrail spec takes precedence until explicitly revised.
