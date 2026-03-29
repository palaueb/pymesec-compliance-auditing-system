# Object-Level Access v2

## Purpose

Extend object-scoped visibility beyond the first slice so the main operational workspaces follow functional ownership consistently.

This second slice keeps the same model introduced in v1:

- roles open a workspace and authorize actions
- functional ownership decides which records inside that workspace stay visible

## Coverage

v2 extends visibility filtering and operation blocking to:

- `controls`
- `continuity services`
- `recovery plans`
- `privacy data flows`
- `processing activities`
- `policies`
- `policy exceptions`
- `assessments`

Combined with v1, the covered domains are now:

- `assets`
- `risks`
- `findings`
- `remediation actions`
- `controls`
- `continuity services`
- `recovery plans`
- `privacy data flows`
- `processing activities`
- `policies`
- `policy exceptions`
- `assessments`

## Resolution Model

The runtime still resolves access in this order:

1. resolve `principal`
2. resolve `organization`
3. resolve linked functional actors for that principal in that organization
4. resolve active assignments for the requested domain
5. if the principal has assignments in that domain, filter to the assigned records only
6. if the principal has no assignments in that domain, keep broad legacy visibility for compatibility

This means a person can be scoped in one workspace and still remain broad in another until ownership is governed there.

## Operation Rules

For the covered domains, write operations are now blocked when the target record is outside the visible set.

Applied in v2:

- control updates, requirement attachment, evidence upload, lifecycle transitions
- continuity service updates, dependencies, evidence upload, lifecycle transitions
- recovery plan updates, exercises, test executions, evidence upload, lifecycle transitions
- privacy data flow updates, evidence upload, lifecycle transitions
- processing activity updates, evidence upload, lifecycle transitions
- policy updates, exception creation, evidence upload, lifecycle transitions
- policy exception updates, evidence upload, lifecycle transitions
- assessment updates, review updates, review workpapers, linked findings, transitions, report export

## UI Impact

The following screens now reflect object-scoped visibility when the current principal is functionally assigned in that domain:

- `Controls Catalog`
- `Control Reviews`
- `Continuity Services`
- `Recovery Plans`
- `Data Flows Register`
- `Processing Activities`
- `Policies Register`
- `Exceptions Board`
- `Assessment Campaigns`

The workspace governance area now also exposes a dedicated governance screen:

- `Object Access Matrix`

When a selected record is outside the visible set, the screen keeps the workspace but does not resolve that detail record.

## Current Limitations

- visibility is still based on direct assignments for the target domain
- v2 does not yet infer cross-domain visibility from related objects
- coverage does not yet include the evidence repository itself
- the fallback to broad visibility remains in place for domains without assignments for the current principal

## Next Step

Use governed reference data and stronger ownership patterns to reduce the remaining fallback cases and make scoped visibility more predictable across the product.
