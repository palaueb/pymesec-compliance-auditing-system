# API OpenAPI Foundation v1

## Goal

Define a canonical REST API contract for the entire application and publish one generated `openapi.json` that is complete, secure, and stable enough for MCP/OpenAPI proxy usage.

This includes all product domains. No domain is excluded from API-first coverage.

## Scope

v1 covers:

- core platform APIs
- all currently active domain/plugin APIs
- a generated unified `openapi.json`
- OpenAPI compatibility for MCP proxy integration

v1 does not attempt:

- GraphQL support
- non-HTTP transport protocols
- automatic SDK generation as a release gate

## Mandatory Principles

### 1) Full domain coverage

API coverage must include every functional area used in product workflows, including:

- assets
- risks
- controls
- assessments
- evidence/artifacts
- findings/remediation/actions
- third-party risk/vendors
- questionnaires
- collaboration objects
- automation catalog/runtime controls
- reference catalogs and governance metadata
- tenancy/scope/membership context where applicable

Rule: no critical workflow is considered complete if it is available only from UI.

### 2) Modular ownership, unified output

Each module/plugin owns route-level OpenAPI metadata, and core assembles the final artifact.

Recommended structure:

- metadata per API route (`_openapi`) in module-owned route files
- optional fragment per module for shared components/schemas/examples
- central builder/assembler command that extracts routes first
- generated unified output at a stable path (for example `public/openapi.json`)

Route metadata is mandatory for every `/api/v1` operation. Missing metadata must fail generation/tests.
`paths` must be generated from router extraction, not manually curated JSON operation lists.
For mutable endpoints, `requestBody` schemas should be generated from shared validation contracts (`request_form_request` preferred, `request_rules` accepted) plus governed field annotations, not manually duplicated schemas.
For mutable endpoints with relational selectors (`*_id`, `*_ids`), route metadata must publish `lookup_fields` so clients can discover which lookup endpoint provides valid options.
For mutable endpoints with governed catalog fields, generated schemas must expose both `x-governed-catalog` and `x-governed-source` so clients can resolve allowed options before write calls.

Implementation baseline (current):

- core-only operations remain in `core/routes/api.php`
- plugin domain operations are split into `plugins/<plugin-id>/routes/api.php`
- OpenAPI stays unified from active router extraction (`/openapi.json`, `/openapi/v1.json`)

### 3) Stable machine-facing contracts

Required OpenAPI qualities:

- stable and unique `operationId`
- domain tags by module
- schema-first request/response bodies
- consistent pagination/filter/sort conventions
- consistent error envelope and error codes

### 4) MCP compatibility by design

For MCP/OpenAPI proxy usage:

- operations must be discoverable and unambiguous
- schemas must be explicit (avoid hidden dynamic fields)
- errors must be machine-readable and actionable
- capability discovery endpoints should expose effective permissions/context

### 5) Security and audit parity with WEB

API-first does not reduce audit obligations.

All operations must remain auditable across both channels:

- WEB
- API

The API program must preserve one shared append-only audit model for the whole application.
That shared model must use one unified WEB+API log set tagged with `channel` and `author`.

## Governed Field Write Discipline

Constrained/governed fields must follow this API pattern:

1. client fetches allowed values through lookup/reference endpoints
2. client submits write payload using a declared value key
3. API validates against effective catalog/enum in caller context
4. out-of-catalog writes are rejected with validation errors

For relational references:

1. write contracts must expose lookup sources (`x-lookup-fields` in OpenAPI)
2. lookup source endpoints must be authenticated, authorized, and context-scoped
3. write requests with unknown or cross-context relation IDs must be rejected

This rule applies equally to:

- UI clients
- external integrations
- MCP-driven automation agents

## API Surface Contract

Common baseline requirements:

- versioned base path (for example `/api/v1`)
- authenticated requests only (except explicit public external-collab routes)
- clear deprecation/version evolution policy
- idempotency strategy for non-idempotent operations where required
- upload/download operations defined explicitly in OpenAPI

## OpenAPI Artifact Endpoint and Compatibility Policy

Published endpoints:

- `/openapi/v1.json` (version-pinned canonical artifact for v1 clients)
- `/openapi.json` (alias to latest supported stable version)

Compatibility policy for `v1`:

- additive changes are allowed in minor delivery (new paths, optional fields, optional enum values)
- breaking changes require a new artifact version path (for example `/openapi/v2.json`)
- existing `operationId` values are immutable within `v1`
- existing required request fields are immutable within `v1`
- response shape changes that remove or rename existing fields are not allowed within `v1`

## OpenAPI Build and Governance

Required workflow:

1. module adds or updates endpoint + route `_openapi` metadata
2. module tests include authz and response shape assertions
3. OpenAPI assembler regenerates canonical `openapi.json`
4. publish command writes both canonical artifacts (`public/openapi.json` and `public/openapi/v1.json`) from the same generated document
5. CI runs `php artisan openapi:publish --check` and blocks any artifact drift
6. CI enforces `api -> openapi` operation coverage and baseline `web write -> api operation` parity checks

## Acceptance Criteria

v1 is considered complete when:

- the generated `openapi.json` covers the active product domains end-to-end
- UI-reachable critical workflows are API-reachable with equivalent authorization controls
- governed-field writes are contractually lookup-first and validated server-side
- MCP proxy can discover and execute operations reliably from OpenAPI only
