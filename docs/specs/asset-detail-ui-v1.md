# Asset Detail UI v1

## Purpose

Define the `Asset Detail` interaction pattern for the `asset-catalog` plugin.

This slice formalizes the catalog as a browse/open surface and the selected asset as the governed workspace for changes.

## Contract

The `Asset Catalog` screen now has two explicit modes:

- list mode for catalog browsing, summary comparison, and navigation
- detail mode for one selected asset

The list must stay focused on:

- asset name
- governed business labels
- owner summary
- lifecycle state
- `Open`

The detail workspace owns:

- asset editing
- owner assignment management
- lifecycle workflow transitions
- lifecycle history

## Implementation

Implemented in:

- `plugins/asset-catalog/resources/views/catalog.blade.php`

Behavior:

- the list now states explicitly that it is a browse/compare/open view
- the selected asset now states explicitly that governance changes live in detail
- accountability and workflow are grouped as detail concerns instead of reading like list-level operations

## Validation

Regression coverage lives in:

- `core/tests/Feature/AssetCatalogTest.php`

Covered cases:

- list mode renders the summary-only guidance
- detail mode renders the asset-detail workspace guidance
- accountability and governance sections render in detail

## Relationship to UI Review TODO

This slice closes the main `Asset Catalog` cleanup target from `ui-review-and-refactor-todo-2026-03.md`:

- asset editing stays in `Asset Detail`
- the list stays focused on browse/filter/open
- the ownership model is expressed in the detail workspace
