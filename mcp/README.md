# PymeSec MCP (Go Binary)

Official MCP sidecar implemented in Go, designed for local execution next to LLM clients without PHP runtime dependencies.

## Build

From repository root:

```bash
make compile
```

Build artifacts are generated in `dist/` for:

- Linux: `amd64`, `arm64`
- macOS: `amd64`, `arm64`
- Windows: `amd64`, `arm64`

To package release artifacts for GitHub uploads, run:

```bash
make release
```

This creates compressed archives under `dist/releases/v<version>/` for each
binary in `dist/`. Each binary is packaged as a `.tar.gz`; if `zip` is
available on your system, a matching `.zip` is created too. The same release
directory also includes `SHA256SUMS` covering the binaries and the compressed
archives.

## Run

```bash
./dist/pymesec-mcp-linux-amd64 \
  --api-base-url=http://127.0.0.1:8000 \
  --api-token=YOUR_TOKEN
```

The server uses MCP JSON-RPC over stdio.

## MCP Client Config Template

Use this template in any MCP client that supports `stdio` servers:

```json
{
  "mcpServers": {
    "pymesec": {
      "command": "/ABSOLUTE/PATH/TO/pymesec/dist/pymesec-mcp-linux-amd64",
      "args": [],
      "env": {
        "PYMESEC_API_BASE_URL": "http://127.0.0.1:18080",
        "PYMESEC_API_TOKEN": "pmsk_...",
        "PYMESEC_API_PREFIX": "/api/v1",
        "PYMESEC_SYNC_OPENAPI_ON_START": "true"
      }
    }
  }
}
```

Practical notes:

- Replace `command` with your real absolute binary path.
- On macOS/Windows use the corresponding binary from `dist/`.
- Keep token scope minimal (least privilege) for the intended agent workflow.

## Smoke Test

Run end-to-end MCP checks (stdio protocol + authenticated tool calls + operation parity):

```bash
MCP_SMOKE_TOKEN="pmsk_..." make mcp-smoke
```

Optional overrides:

- `MCP_SMOKE_BIN` (default `./dist/pymesec-mcp-linux-amd64`)
- `MCP_SMOKE_API_BASE_URL` (default `http://127.0.0.1:18080`)
- `MCP_SMOKE_OPERATION_ID` (default `coreGetMcpServerProfile`)
- `MCP_SMOKE_REQUEST_TIMEOUT` (default `30s`)

`make mcp-smoke` validates:

1. `pymesec_get_capabilities`
2. `pymesec_call_operation` (OpenAPI operationId routing)
3. `pymesec_api_request`
4. parity between `call_operation` and direct `api_request` response body/status

## MCP Resources

The server also exposes MCP resources for clients that surface `resources/list`,
`resources/templates/list`, and `resources/read`:

- `pymesec://mcp/profile`
- `pymesec://meta/capabilities`
- `pymesec://openapi/v1`

Resource templates:

- `pymesec://operations/{operation_id}`

## Configuration

CLI flags and environment variables:

- `--api-base-url` / `PYMESEC_API_BASE_URL`
- `--api-prefix` / `PYMESEC_API_PREFIX` (default `/api/v1`)
- `--api-token` / `PYMESEC_API_TOKEN`
- `--openapi-url` / `PYMESEC_OPENAPI_URL` (default `<api-base-url>/openapi/v1.json`)
- `--request-timeout` / `PYMESEC_REQUEST_TIMEOUT` (default `30s`)
- `--sync-openapi-on-start` / `PYMESEC_SYNC_OPENAPI_ON_START` (default `true`)

## OpenAPI Autoconfiguration

The binary loads OpenAPI and builds an operation index (`operationId -> method + path`).

This enables:

- `pymesec_call_operation` by `operation_id`
- `pymesec_list_operations` to discover available operations

So model clients can use operation IDs directly instead of hardcoding paths.

## Security Model

The binary is API-first:

- all calls are forwarded to `/api/v1`
- tenancy, permissions, object-access, and audit are enforced by existing API middleware
- no direct DB/domain access from MCP
