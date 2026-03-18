# ADR-021: Managed Reference Catalogs

## Status

Accepted.

## Context

Earlier reference-data phases moved high-value business vocabularies from free text to governed keys backed by `core/config/reference_data.php`.

That solved consistency for:

- assets
- continuity
- privacy
- findings
- assessments

But the source of truth still lived only in code. That created three product problems:

1. administrators could not adapt business vocabularies per organization without code changes
2. the UI could validate keys, but governance still depended on developer intervention
3. different organizations could not safely retire or extend options while keeping defaults intact

## Decision

The platform will support organization-managed reference catalogs layered on top of core defaults.

The model is:

- core config provides default catalogs and default options
- administrators manage organization-specific overrides in the application
- managed overrides can add, rename, reorder, deactivate, and reactivate options
- inactive managed entries can retire a default option for that organization
- runtime consumers resolve the effective catalog as:
  - defaults
  - then organization overrides

## Consequences

### Positive

- business vocabulary becomes governable from the web UI
- defaults remain available for new installations and test environments
- domains can keep using small helper classes while moving to a richer source of truth
- per-organization variation becomes possible without forking code

### Tradeoffs

- reference data now requires persistence and admin permissions
- catalogs must be documented as part of product governance, not just developer config
- labels and key lifecycle need care because existing records may reference retired keys

## Implementation Notes

Phase 3 introduces:

- `reference_catalog_entries` for managed entries
- `ReferenceCatalogService` as the runtime resolver
- admin screen `Reference catalogs`
- first managed catalog expansion including `risks.categories`

Config defaults remain the bootstrap and fallback layer.

## Follow-up

Likely next steps:

- extend managed catalogs to remaining domain vocabularies
- add richer UI guidance around where each catalog is used
- consider catalog-level policy such as locked defaults or shared global packs
