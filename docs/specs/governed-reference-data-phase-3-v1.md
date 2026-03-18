# Governed Reference Data Phase 3 v1

## Status

Implemented.

## Goal

Move governed reference data from config-only catalogs to organization-managed catalogs exposed in the administration UI.

## Scope

This phase adds:

- persistent managed catalog entries
- admin management UI
- effective catalog resolution as `defaults + organization overrides`
- first organization-managed domain extension for `risks.categories`

## Runtime Model

### Default layer

`core/config/reference_data.php` remains the bootstrap source for shipped defaults.

### Managed layer

`reference_catalog_entries` stores organization-specific entries with:

- `catalog_key`
- `option_key`
- `label`
- `description`
- `sort_order`
- `is_active`

### Resolution

`ReferenceCatalogService` resolves the effective catalog by:

1. loading defaults
2. applying managed entries for the selected organization
3. removing default options when an inactive managed entry retires them
4. sorting by `sort_order` and label

## Administration UI

The admin area now exposes `Reference catalogs`.

Administrators can:

- browse available catalogs
- see effective options and managed overrides
- add a managed option
- edit a managed option
- archive or reactivate an organization override

## Domain Impact

Phase 3 applies the managed-catalog runtime to existing helpers and adds risk categories as a governed catalog:

- `assets.types`
- `assets.criticality`
- `assets.classification`
- `continuity.impact_tier`
- `continuity.dependency_kind`
- `privacy.transfer_type`
- `privacy.lawful_basis`
- `findings.severity`
- `risks.categories`

Routes and forms now validate `risk.category` against the effective catalog instead of free text.

## Product Outcome

This phase changes governed vocabularies from a developer-only mechanism into an administrative capability.

That improves:

- consistency of operational data
- organization-specific fit
- reporting quality
- audit readability
- readiness for later governance features

## Remaining Work

This phase does not yet include:

- catalog usage analytics
- cross-organization shared catalog packs beyond defaults
- UI-managed catalogs for every governed enum in the system
- migration of old free-text records beyond the current slices
