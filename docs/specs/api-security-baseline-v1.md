# API Security Baseline v1

## Goal

Define mandatory security controls for the application API layer and its parity with WEB behavior, with explicit focus on preventing unauthorized access, data exfiltration, and integrity issues.

## Non-Negotiable Security Rule

Audit coverage is mandatory for all operations:

- all WEB operations
- all API operations
- read and write operations
- success and failure outcomes

No exception by feature/module.

## Audit and Request Logging Model

The platform must persist append-only records for every request/operation in one unified log set shared by WEB and API, with enough context to reconstruct who did what, where, and with which result.

Unified model rule:

- one log dataset/table family for both channels
- `channel` is mandatory (`web` or `api`)
- `author` is mandatory (principal id, service identity, or external token identity)

Minimum audit/request fields:

- timestamp
- request id / correlation id
- channel (`web` or `api`)
- author identity (`principal_id` or external token/service identity)
- organization and scope context
- target object type and target object id (when available)
- operation id / route signature
- authorization decision result
- validation result
- final outcome/status code
- source ip and user agent

Security constraints:

- append-only behavior
- no silent mutation/deletion of audit rows
- secret redaction in payload snapshots

## Authentication Baseline

- strong authenticated access for internal APIs
- short-lived tokens and revocation capability
- no long-lived shared credentials as a default integration pattern
- explicit handling for external-collaboration routes with narrow scope

## Authorization Baseline

All API endpoints must enforce:

- organization membership context
- scope boundary
- role/permission checks
- object-level access rules
- assignment-based constraints where domain rules require it

Default deny policy:

- if context is missing or ambiguous, access is denied

## Data Leakage and Integrity Controls

- sensitive fields must be redacted by default unless explicitly authorized
- server-side validation for every input (including nested payloads)
- strict schema validation for uploads and metadata bindings
- bounded pagination/filter behavior to prevent bulk exfiltration abuse

## Governed Field Write Policy

For constrained fields, write operations must use validated governed values:

1. client obtains allowed options via lookup/reference endpoints
2. client writes selected governed key/value
3. server validates against effective catalog/enum in caller context
4. invalid values are rejected

This policy is mandatory for WEB and API paths equally.

## CI Security Gates

Release gates should include:

- authorization boundary regression tests
- sensitive-field redaction tests
- audit-side-effect tests (WEB/API operation must generate logs)
- OpenAPI schema integrity checks

## MCP Compatibility Security Notes

When consumed through MCP/OpenAPI proxy:

- only scoped credentials should be used
- operation set should reflect effective permissions
- responses should remain least-privilege by default
- audit trace must preserve model-initiated operation provenance
