# Control Detail UI v1

## Purpose

Define the `Control Detail` interaction pattern for the `controls-catalog` plugin.

This slice makes the screen contract explicit:

- control list for catalog browsing and summary
- detail workspace for control maintenance and execution

## Contract

The `Controls Catalog` screen now has two explicit modes:

- list mode for control identity, framework context, owner summary, evidence summary, state, and navigation
- detail mode for one selected control

The list must stay focused on:

- control name and identity
- framework and domain context
- owner summary
- evidence summary
- state
- `Open`

The detail workspace owns:

- requirement mappings
- ownership management
- evidence uploads and promotion
- workflow transitions
- control editing

Framework governance and requirement governance stay in the dedicated `Framework Adoption` workspace.

## Implementation

Implemented in:

- `plugins/controls-catalog/resources/views/catalog.blade.php`

Behavior:

- the list now states explicitly that it is a catalog browsing surface
- the selected control now states explicitly that maintenance and workflow actions happen in detail
- the create form remains available in list mode and is no longer rendered inside detail mode

## Validation

Regression coverage lives in:

- `core/tests/Feature/ControlsCatalogTest.php`

Covered cases:

- list mode renders the control-list guidance
- detail mode renders the control-detail guidance
- detail mode keeps requirement mapping, evidence, and control editing actions together

## Relationship to UI Review TODO

This slice closes the `Controls Catalog` cleanup target from `ui-review-and-refactor-todo-2026-03.md`:

- `Control Detail` is the maintenance workspace
- artifacts and requirement mappings stay in detail
- framework and requirement governance remain in dedicated governance screens
- the list stays focused on summary and `Open`
