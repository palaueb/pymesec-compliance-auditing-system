# Title

Official MCP Server v1

# Status

Active

# Goal

Provide an official Model Context Protocol (MCP) server for PymeSec that exposes the platform API surface to agent runtimes while preserving API security guarantees (tenancy, permission checks, object-level access, and auditability).

# Scope

v1 delivers:

- official MCP Go binary over stdio (`pymesec-mcp`)
- JSON-RPC request handling for `initialize`, `tools/list`, and `tools/call`
- MCP tool broker to `/api/v1` endpoints (no direct database/domain bypass)
- API discovery endpoint for MCP profile metadata (`GET /api/v1/meta/mcp-server`)
- OpenAPI-driven autoconfiguration (`operationId -> method/path`) for operation calls

v1 does not deliver:

- external discovery registry publication (tracked separately in canonical TODO)
- non-stdio transports

# Architecture

Go project:

- `mcp/cmd/pymesec-mcp/main.go`
- `mcp/cmd/pymesec-mcp/main_test.go`
- root `Makefile` target `make compile` for cross-platform binaries

Runtime command:

- `pymesec-mcp --api-base-url=<url> --api-token=<token>`
- `make compile` builds Linux/macOS/Windows binaries (`amd64` + `arm64`)
- `make mcp-smoke` runs authenticated end-to-end MCP tool smoke checks against a live API

Security boundary:

- MCP tools call only `/api/v1/*` paths.
- Every call flows through the same API middleware stack (`ResolveApiPrincipal`, `AuthorizePermission`, audit middleware, validation pipeline).
- Result: MCP operations inherit API parity for tenancy, permission grants, token abilities, object access rules, and request auditing.

# Official Tool Surface (v1)

- `pymesec_api_request` (generic authenticated `/api/v1` request broker)
- `pymesec_call_operation` (OpenAPI operationId-based call)
- `pymesec_list_operations` (discover indexed operation IDs)
- `pymesec_get_capabilities` (`GET /api/v1/meta/capabilities`)
- `pymesec_get_openapi` (`GET /api/v1/openapi`)
- `pymesec_get_mcp_profile` (`GET /api/v1/meta/mcp-server`)

# API Contract Additions

Endpoint: `GET /api/v1/meta/mcp-server`

Returns:

- MCP server identity/version/transport/binary metadata
- official tool names
- autoconfiguration hints (`PYMESEC_API_BASE_URL`, `PYMESEC_API_TOKEN`, `PYMESEC_OPENAPI_URL`, `PYMESEC_API_PREFIX`)
- security parity declaration
- authenticated context snapshot (principal, organization, scope, memberships)

OpenAPI:

- operation id: `coreGetMcpServerProfile`
- route-owned `_openapi` metadata in `core/routes/api.php`

# Audit and Compliance Notes

- MCP-requested operations are recorded as API-channel requests in the shared append-only audit trail.
- When bearer tokens are used, audit author type/id follows API token identity parity.

# Acceptance Criteria

v1 is complete when:

- MCP stdio server is available as a PHP-independent Go binary.
- MCP tool calls reach product operations through `/api/v1`, not out-of-band data access.
- Unauthorized/token-restricted/cross-context actions are rejected with the same API behavior.
- MCP profile endpoint is documented in OpenAPI and available to authenticated clients.
