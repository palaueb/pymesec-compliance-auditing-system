# Governed Reference Data: Continuity And Privacy V1

## Scope

This slice removes high-value free-text reference fields from the `Continuity` and `Privacy` workspaces and replaces them with governed option lists.

Covered in this version:

- `continuity service impact tier`
- `continuity service dependency kind`
- `privacy data flow transfer type`
- `privacy processing activity lawful basis`

## Why

These fields are reused in reporting, filtering, and audit conversations. Leaving them as free text creates inconsistent values and weakens the governance model introduced for assets.

## Rules

- UI must render these fields as selectors, not free-text inputs.
- Backend must reject values outside the governed catalog.
- Detail and list views must render the governed display label.
- Stored values remain stable keys, not translated labels.

## Current Catalogs

### Continuity

- `impact_tier`: `critical`, `high`, `medium`, `low`
- `dependency_kind`: `critical`, `supporting`, `external`

### Privacy

- `transfer_type`: `internal`, `vendor`, `customer`, `cross-border`, `regulator`
- `lawful_basis`: `consent`, `contract`, `legal-obligation`, `vital-interests`, `public-task`, `legitimate-interests`

## Notes

- This is a governed-reference-data slice, not yet a full reference-data administration UI.
- The source of truth currently lives in `core/config/reference_data.php`.
- A later phase can move these catalogs to admin-managed data if needed.
