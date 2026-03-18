# Object-Level Access v1

## Purpose

Add a first usable layer of object-level visibility on top of the existing organization, scope, membership, and role model.

This v1 connects access to functional ownership without collapsing identity, permissions, and accountability into the same concept.

## Principles

- Roles still decide whether a person can open a workspace or perform an operation.
- Object-level access decides which records inside that workspace are visible.
- Functional actors remain the source of accountability and ownership.
- A principal only becomes object-scoped when that principal is linked to functional actors that already own records of that domain.
- `platform-admin` keeps unrestricted visibility.

## v1 Scope

Implemented in this first slice:

- `assets`
- `risks`
- `findings`
- `remediation actions` on the findings board
- dashboard metrics for assets, risks, and findings

Not yet covered in this slice:

- `controls`
- `continuity services`
- `recovery plans`
- `privacy data flows`
- `processing activities`
- `policies`
- `policy exceptions`
- `assessments`
- `evidence repository`

## Resolution Model

For a given request context:

1. resolve `principal`
2. resolve `organization`
3. resolve linked functional actors for that principal in that organization
4. resolve active functional assignments for the target domain
5. if matching assignments exist for that domain, visibility is limited to those records
6. if no matching assignments exist for that domain, the runtime keeps legacy broad visibility for compatibility

This fallback is intentional for v1 to avoid breaking domains that are not yet fully governed by assignments.

## Visibility Rules

### Unrestricted Cases

A principal sees the full domain list when:

- the principal is `platform-admin`
- the principal has no functional actor links in that organization
- the principal has linked actors, but those actors have no assignments for that domain in that organization

### Scoped Cases

A principal sees only assigned records when:

- the principal is linked to one or more functional actors
- those actors have active assignments for that domain in the current organization

When a scope is selected, assignment resolution also respects that scope.

## Operation Rules

For covered domains, write operations are blocked when the target record is outside the principal's visible set.

Applied in v1:

- asset update and lifecycle transition
- risk update, evidence upload, and lifecycle transition
- finding update, evidence upload, and lifecycle transition
- remediation action create and update

## Dashboard

Dashboard metrics now reflect object-scoped visibility for:

- assets in view
- risks under assessment
- open findings

This keeps the landing page aligned with the records the current user can actually work on.

## Current Limitations

- v1 does not yet infer visibility from indirect relations such as:
  - seeing an asset because you own a linked risk
  - seeing a control because you own a linked finding
- v1 does not yet force assignment coverage for all domains
- some domains still rely on free-text or incomplete governed reference data

## Next Step

Extend the same model to:

- `controls`
- `continuity`
- `privacy`
- `policy`
- `assessments`

In parallel, reduce free-text business fields so ownership and filtering can rely on governed objects instead of labels.
