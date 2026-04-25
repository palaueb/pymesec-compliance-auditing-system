# AI MCP Client Demo Runbook v1

## Purpose

Prepare a repeatable client demonstration where an AI agent operates PymeSec through the official MCP server and the PymeSec REST API, without direct database access.

This runbook is operational. The canonical agent contract remains [SKILL.md](../../SKILL.md), and the MCP implementation notes remain [mcp/README.md](../../mcp/README.md) and [mcp-server-v1.md](./mcp-server-v1.md).

## What the Demo Proves

- The AI client can discover PymeSec capabilities from OpenAPI.
- The AI client can resolve governed values and relation options before writing.
- The AI client can create an asset and related risk records through API permissions.
- PymeSec still enforces tenancy, permissions, object access, validation, and audit trail.
- The token stays in the MCP client environment, not in the chat prompt.

## Components

- PymeSec application reachable over HTTPS.
- Published OpenAPI artifact at `/openapi/v1.json` or live API contract at `/api/v1/openapi`.
- Official MCP stdio binary: `pymesec-mcp`.
- API token issued for the demonstration principal.
- An MCP-capable AI client configured to launch the `pymesec-mcp` binary.

The MCP sidecar normally runs where the AI client runs. It does not need to run on the PymeSec server as long as it can reach the PymeSec HTTPS URL.

## Demo Token

Use a short-lived API token dedicated to the demonstration. Do not reuse a platform admin token.

Minimum useful abilities for the asset and risk demo:

- `plugin.asset-catalog.assets.view`
- `plugin.asset-catalog.assets.manage`
- `plugin.risk-management.risks.view`
- `plugin.risk-management.risks.manage`
- `plugin.actor-directory.actors.view`

Optional abilities when the demo links risks to controls or inspects audit logs:

- `plugin.controls-catalog.controls.view`
- `core.audit-logs.view`

For seeded demo data, a useful context is:

- organization: `org-a` (`Northwind Manufacturing`)
- scope: `scope-eu`
- principal: `principal-org-a`
- candidate owner actors: `actor-compliance-office`, `actor-it-services`, `actor-ava-mason`

Always prefer resolving these values through lookup tools during the demo instead of hardcoding them in the prompt.

Issue a token from the UI:

1. Sign in as an administrator or a principal allowed to manage API tokens.
2. Open `/admin?menu=core.api-tokens`.
3. Create a token for the demo principal.
4. Restrict it to the demo organization and scope when possible.
5. Select only the abilities needed for the demo.
6. Copy the token immediately. It is shown once.

Or issue a token from the server shell:

```bash
docker compose exec app php artisan api-tokens:issue \
  principal-org-a \
  "Client AI demo" \
  --organization_id=org-a \
  --scope_id=scope-eu \
  --expires_in_days=1
```

The console command prints the token once. This fallback creates a context-bound token, but it does not select a reduced ability list. For strict least privilege, issue the token from the UI or API and set the explicit abilities listed above.
The principal, organization, and scope passed to the console command must already exist and must describe the same tenant context; otherwise the command fails without creating a token.

## Build or Locate the MCP Binary

From the repository root:

```bash
make compile
```

The expected artifacts are under `dist/`, for example:

- `dist/pymesec-mcp-linux-amd64`
- `dist/pymesec-mcp-linux-arm64`
- `dist/pymesec-mcp-darwin-amd64`
- `dist/pymesec-mcp-darwin-arm64`
- `dist/pymesec-mcp-windows-amd64.exe`

Use the binary that matches the machine running the AI client.

## MCP Client Configuration

Add a stdio MCP server named `pymesec` to the AI client configuration.

This configuration does not go in the prompt. It goes in the configuration of the MCP-capable client that will run the AI session. The prompt only asks the AI what to do after the MCP tools are already available.

For interactive end-user setup on Linux, macOS, WSL, or Git Bash, there is also an installer script:

```bash
curl -fsSL https://raw.githubusercontent.com/palaueb/pymesec-compliance-auditing-system/main/scripts/install-pymesec-mcp.sh | bash
```

The installer downloads the correct release asset, verifies it against `SHA256SUMS`, installs the binary locally, validates the server endpoints, and can register the MCP entry in Codex CLI or Claude CLI automatically.

Examples:

- Codex CLI: use `codex mcp add ...`, which writes to `~/.codex/config.toml`.
- Codex CLI with project-local config: keep the real configuration in `.codex/config.toml` inside the working copy, but do not commit real tokens.
- Codex CLI from the project template: copy [.codex/config.example.toml](../../.codex/config.example.toml) to `.codex/config.toml`, then replace the placeholder URL, binary path, and token locally.
- JSON-based MCP clients: put the JSON block below in that client's MCP configuration file or settings screen.
- Custom OpenAI/API application: launch `pymesec-mcp` as a stdio MCP server from your application process and pass these environment variables when spawning the process.

The committed project template is safe to share because it contains no real token. The real `.codex/config.toml` must remain uncommitted.

For Codex CLI, configure it like this:

```bash
codex mcp add pymesec \
  --env PYMESEC_API_BASE_URL=https://demo.pymesec.example \
  --env PYMESEC_API_TOKEN=pmsk_REPLACE_WITH_DEMO_TOKEN \
  --env PYMESEC_API_PREFIX=/api/v1 \
  --env PYMESEC_SYNC_OPENAPI_ON_START=true \
  -- /ABSOLUTE/PATH/TO/pymesec/dist/pymesec-mcp-linux-amd64
```

Then verify:

```bash
codex mcp list
codex mcp get pymesec
```

Restart the Codex session after adding or changing the MCP server.

For clients that use JSON configuration, use this shape:

```json
{
  "mcpServers": {
    "pymesec": {
      "command": "/ABSOLUTE/PATH/TO/pymesec/dist/pymesec-mcp-linux-amd64",
      "args": [],
      "env": {
        "PYMESEC_API_BASE_URL": "https://demo.pymesec.example",
        "PYMESEC_API_TOKEN": "pmsk_REPLACE_WITH_DEMO_TOKEN",
        "PYMESEC_API_PREFIX": "/api/v1",
        "PYMESEC_SYNC_OPENAPI_ON_START": "true"
      }
    }
  }
}
```

Rules:

- Use an absolute `command` path.
- Keep the token in the client environment, not in the prompt.
- Point `PYMESEC_API_BASE_URL` at the application origin, without `/api/v1`.
- Restart the AI client after changing MCP configuration.

## Preflight Checks

Check the application and token:

```bash
export PYMESEC_API_BASE_URL="https://demo.pymesec.example"
export PYMESEC_API_TOKEN="pmsk_REPLACE_WITH_DEMO_TOKEN"

curl -fsS "$PYMESEC_API_BASE_URL/openapi/v1.json" >/tmp/pymesec-openapi.json

curl -fsS \
  -H "Authorization: Bearer $PYMESEC_API_TOKEN" \
  "$PYMESEC_API_BASE_URL/api/v1/meta/mcp-server"

curl -fsS \
  -H "Authorization: Bearer $PYMESEC_API_TOKEN" \
  "$PYMESEC_API_BASE_URL/api/v1/meta/capabilities"
```

Run the MCP smoke test from the repository root when the binary is local:

```bash
MCP_SMOKE_API_BASE_URL="$PYMESEC_API_BASE_URL" \
MCP_SMOKE_TOKEN="$PYMESEC_API_TOKEN" \
make mcp-smoke
```

Expected result:

- `pymesec_get_capabilities` succeeds.
- `pymesec_call_operation` can call `coreGetMcpServerProfile`.
- `pymesec_api_request` returns the same API behavior as direct HTTP.

## AI Client Sanity Prompt

Start with a read-only prompt:

```text
Use the PymeSec MCP tools.

Do not create or update records yet.

1. Get the MCP profile.
2. Get my effective capabilities.
3. List available operations containing "assetCatalog" and "riskManagement".
4. Tell me whether this token can create assets and risks.
```

The agent should use:

- `pymesec_get_mcp_profile`
- `pymesec_get_capabilities`
- `pymesec_list_operations`

If the agent cannot see these tools, the MCP client is not configured or has not been restarted.

## Demo Scenario

Use this scenario because it is concrete and easy to verify in the UI:

```text
Prepare, but do not save yet, an asset and initial risk analysis for:

Organization: Northwind Manufacturing
Scope: EU Operations
Asset: Customer Portal
Type: application
Criticality: high
Classification: confidential
Owner: Compliance Office
Context: public-facing customer portal used for customer support requests.
Data: customer contact details, support history, and account metadata.

Create a practical initial risk analysis aligned with ISO 27001 and GDPR.
Show me the planned API writes first. Wait for my confirmation before saving.
```

Expected agent behavior:

1. Fetch OpenAPI.
2. Fetch capabilities.
3. Resolve `assets.types`, `assets.criticality`, and `assets.classification`.
4. Resolve actor options and choose the matching owner.
5. Prepare one `assetCatalogCreateAsset` call.
6. Prepare several `riskManagementCreateRisk` calls.
7. Ask for confirmation before writing.

Confirm with:

```text
Approved. Create the asset and the proposed risks now. Return the created ids and request ids.
```

Expected write operations:

- `assetCatalogCreateAsset`
- `riskManagementCreateRisk`

The risk records should link to the created asset through `linked_asset_id`.

## Operation IDs Useful During the Demo

Core discovery:

- `coreGetOpenApi`
- `coreGetCapabilities`
- `coreGetMcpServerProfile`

Reference data:

- `referenceDataListCatalogs`
- `referenceDataListCatalogOptions`
- `referenceDataListActorOptions`
- `referenceDataListControlOptions`
- `referenceDataListRiskOptions`

Assets:

- `assetCatalogListAssets`
- `assetCatalogGetAsset`
- `assetCatalogCreateAsset`
- `assetCatalogUpdateAsset`
- `assetCatalogTransitionAsset`

Risks:

- `riskManagementListRisks`
- `riskManagementGetRisk`
- `riskManagementCreateRisk`
- `riskManagementUpdateRisk`
- `riskManagementTransitionRisk`
- `riskManagementAttachRiskArtifact`

## Post-Demo Verification

In the UI:

1. Open the asset catalog and find the new asset.
2. Open the risk register and filter or search for the new linked risks.
3. Open `/admin?menu=core.audit-logs` if the viewer has audit permissions.
4. Confirm that API/MCP-created changes have request ids and API-channel provenance.

Through MCP or curl:

```bash
curl -fsS \
  -H "Authorization: Bearer $PYMESEC_API_TOKEN" \
  "$PYMESEC_API_BASE_URL/api/v1/assets?organization_id=org-a&scope_id=scope-eu"

curl -fsS \
  -H "Authorization: Bearer $PYMESEC_API_TOKEN" \
  "$PYMESEC_API_BASE_URL/api/v1/risks?organization_id=org-a&scope_id=scope-eu"
```

## Cleanup

For a disposable local demo:

```bash
make reset-demo
```

For a shared client demo environment, prefer one of:

- use a dedicated demo organization or scope and reset that environment after the session
- revoke the API token immediately after the demo
- leave created records visible if they are part of the client walkthrough

Revoke a token from the server shell:

```bash
docker compose exec app php artisan api-tokens:revoke TOKEN_ID
```

## Troubleshooting

If the AI client has no PymeSec tools:

- confirm the MCP client configuration has the `pymesec` server
- confirm `command` points to an existing binary
- restart the AI client

If OpenAPI discovery fails:

- check `PYMESEC_API_BASE_URL`
- check `/openapi/v1.json`
- run `php artisan openapi:publish` on the server build if the artifact is missing

If capabilities are empty:

- check the token is not expired or revoked
- check token organization and scope
- check the owner principal has the required role grants
- check token abilities do not exclude required permissions

If writes fail validation:

- ask the agent to resolve governed catalog options first
- inspect `error.details`
- verify `type`, `criticality`, `classification`, and `category` use effective catalog keys, not display labels

If writes fail authorization:

- verify the token has the `*.manage` permission for the target module
- verify object-level access for linked asset, control, actor, and scope references
- verify the token context matches the requested `organization_id` and `scope_id`

## Demo Close Message

Use this explanation with clients:

```text
The AI agent is not bypassing PymeSec. It is using the same API surface as any integration. The MCP server only brokers tool calls into authenticated API requests. PymeSec still decides what the token can see or change, validates governed values, enforces tenant and object boundaries, and records the operation for audit.
```
