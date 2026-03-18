# Governed Reference Data — Asset Catalog v1

## Purpose

Replace free-text business fields in the asset domain with controlled values so the catalog can support consistent reporting, filtering, and access policies.

## v1 Scope

This first governed-data slice applies to:

- `asset type`
- `asset criticality`
- `asset classification`
- `asset owner` editing

## Rules

### 1. Asset Type

`asset type` must come from a controlled catalogue.

Allowed values in v1:

- `application`
- `storage`
- `endpoint`
- `network`
- `service`

The UI must use a selector, not a text input.

### 2. Criticality

`criticality` must come from a controlled scale.

Allowed values in v1:

- `high`
- `medium`
- `low`

The UI must use a selector, not a text input.

### 3. Classification

`classification` must come from a controlled classification scheme.

Allowed values in v1:

- `public`
- `internal`
- `restricted`
- `confidential`

The UI must use a selector, not a text input.

### 4. Owner Editing

`owner_label` is legacy-only in v1.

Rules:

- users must no longer edit `owner_label`
- ownership is assigned through `owner actor`
- list and detail views prefer resolved actor ownership
- legacy `owner_label` may still be displayed as fallback during transition

This keeps the runtime compatible with seeded or migrated records without preserving the anti-pattern in the edit flow.

## Implementation

v1 stores the governed values as constrained keys and resolves labels from a managed reference-data configuration.

This is not yet a full governance UI.

The current implementation uses:

- central reference-data configuration
- backend validation against allowed values
- selectors in create and edit forms

## Why This Shape

This is the smallest change that produces real value now:

- no more arbitrary strings for key asset dimensions
- cleaner filters and reporting
- stronger basis for object-level access
- minimal migration pressure on current data

## Not Yet in v1

- web administration for managing the vocabularies themselves
- multi-party asset ownership
- assignment types such as `accountable`, `reviewer`, or `backup owner`
- automatic migration of all legacy owner labels into actor assignments

## Next Step

Extend the same pattern to:

- continuity reference data
- privacy reference data
- policy and exception severity-style business values
