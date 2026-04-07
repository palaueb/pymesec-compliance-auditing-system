# PymeSec Agent Skill

## Purpose

Provide one canonical integration guide for AI agents and automation clients that operate PymeSec through the REST API surface.

This document defines the operational contract, security constraints, and recommended call order.

## Canonical Machine Interfaces

- OpenAPI contract: `/openapi.json`
- API base path: `/api/v1`
- Capability discovery: `GET /api/v1/meta/capabilities`
- Reference/governed lookups:
  - `GET /api/v1/lookups/reference-catalogs`
  - `GET /api/v1/lookups/reference-catalogs/{catalogKey}/options`
  - `GET /api/v1/lookups/actors/options`
  - `GET /api/v1/lookups/frameworks/options`
  - `GET /api/v1/lookups/controls/options`
  - `GET /api/v1/lookups/risks/options`

## Authentication and Context

API callers must authenticate with one of:

- Bearer API token (recommended for integrations)
- Authenticated web session (same principal context)

Every operation resolves a principal and tenancy context:

- `principal_id` is resolved from authenticated context
- `organization_id` and `scope_id` define tenant boundaries
- `membership_id` or `membership_ids` further constrain effective access

Agents must never assume cross-organization visibility.

## Mandatory Agent Call Order

1. Fetch `/openapi.json` and resolve the exact operation and request schema.
2. Fetch `GET /api/v1/meta/capabilities` for effective permissions in current context.
3. Resolve governed and relational options before write calls:
   - governed fields via reference catalogs (`x-governed-catalog`)
   - relational selectors via lookup metadata (`x-lookup-fields`)
4. Execute write operation only with allowed values.
5. Handle typed API errors and retry only when semantically safe.

## OpenAPI Extensions Used by PymeSec

Agents must consume these operation extensions:

- `x-permissions`: required permission keys for the operation
- `x-governed-catalog`: constrained catalog key for a field
- `x-lookup-fields`: relation field to lookup endpoint mapping

Write operations are contract-first:

- request schemas are generated from route metadata + validation contracts
- writes with invalid governed or cross-context relation values are rejected

## Response and Error Shape

Success envelope:

```json
{
  "data": {},
  "meta": {
    "request_id": "..."
  }
}
```

Error envelope:

```json
{
  "error": {
    "code": "validation_failed|authentication_failed|authorization_failed|request_failed|internal_error",
    "message": "..."
  }
}
```

Validation errors include field details in `error.details`.

## Safety and Policy Rules

- Respect object-level authorization: permissions alone are not sufficient.
- Treat `request_id` as traceability metadata for support and audit review.
- Do not attempt hidden or inferred fields not declared by OpenAPI.
- Do not bypass lookup-first discipline for constrained fields.
- Use least-privilege tokens and constrained tenant context by default.

## Example Integration Flow

1. Read `/openapi.json`
2. Resolve capabilities with `GET /api/v1/meta/capabilities`
3. Read lookups (`actors`, `frameworks`, `controls`, `risks`, catalogs)
4. Create/update records (`assets`, `risks`, `controls`, `assessments`, `findings`, `remediation actions`)
5. Correlate failures by `request_id`

## Versioning

- Skill version: `v1`
- Last updated: `2026-04-07`
