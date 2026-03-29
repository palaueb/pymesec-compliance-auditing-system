# Governed Reference Data: Policy Areas v1

## Status

Implemented.

## Goal

Replace legacy free-text `policy area` values with a governed business catalogue so policy reporting, filtering, and administration do not depend on arbitrary wording.

## Scope

This slice governs:

- `policies.area`

It adds:

- shipped defaults in `core/config/reference_data.php`
- managed catalog exposure through `Reference catalogs`
- runtime validation on policy create and update
- rendered labels in the policy workspace
- migration of legacy policy area values to stable catalog keys

## Default Catalog

The default `policies.areas` catalog ships with:

- `identity`
- `resilience`
- `operations`
- `third-parties`
- `governance`
- `privacy`

Stored values are the stable keys above. UI surfaces render the managed label.

## Runtime Rules

- policy create and update routes must reject values outside `policies.areas`
- policy screens must render a selector, not a free-text input
- detail and list screens must show the effective label
- organization-managed overrides may add new area options without code changes

## Migration Rules

Legacy installations may already contain free-text policy areas.

The normalization migration therefore:

- converts shipped legacy labels such as `Identity` to their stable keys
- preserves custom organization-specific policy areas by creating managed catalog entries
- rewrites affected policy records to the normalized option key

## UI Distinction

The admin `Reference catalogs` screen now states explicitly that these catalogs are business-managed vocabularies.

Workflow states such as `draft`, `review`, or `approved` remain system enums and are not edited through the catalog UI.

## Product Outcome

This removes one of the last remaining business free-text classifiers from the governance workspaces and improves:

- reporting consistency
- policy filtering quality
- audit readability
- tenant-specific vocabulary fit without code edits
