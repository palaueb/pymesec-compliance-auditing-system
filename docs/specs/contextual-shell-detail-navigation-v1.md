# Contextual Shell Detail Navigation v1

## Purpose

Define the shell behavior for `index -> detail` navigation when a module opens a record detail without creating a dedicated sidebar entry.

The goal is to keep detail screens contextual to their parent list while preserving shell consistency for:

- theme switching
- locale switching
- organization and scope switching
- return-to-origin navigation

## Problem

Several list pages already expose `Open` actions with `context_label` and `context_back_url`, but the shared shell did not treat those parameters as a first-class contract.

This caused three rough edges:

- detail pages could lose their parent-list return path
- shell utility actions could drop the selected detail query such as `plan_id`
- unvalidated `context_back_url` values could introduce unsafe or misleading links

## Contract

The shell now treats contextual detail navigation as a controlled view concern, not as a sidebar concern.

Rules:

- contextual detail pages stay under the same menu id as their parent list
- the sidebar remains focused on modules, not individual records
- `context_back_url` is accepted only for local `/app` or `/admin` routes
- invalid or external `context_back_url` values are ignored
- `context_label` is shown only when a valid contextual back link is present
- shell utility actions must preserve the selected detail query and contextual return data

## Implementation

Implemented in:

- `core/routes/web.php`
- `core/resources/views/shell.blade.php`

Behavior:

- the shell sanitizes `context_back_url` before rendering it
- same-host absolute URLs are normalized back to relative shell URLs
- locale, theme, organization, and scope controls now preserve:
  - current `menu`
  - current detail identifiers such as `plan_id`
  - current contextual return metadata
  - resolved membership ids
- contextual back navigation renders above the page toolbar so the page still keeps one main action area

## Validation

Regression coverage lives in:

- `core/tests/Feature/ShellNavigationTest.php`

Covered cases:

- valid contextual detail navigation renders a safe back link
- shell utilities preserve detail and context query parameters
- external contextual return URLs are rejected

## Out of Scope

This slice does not yet:

- refactor every domain list/detail page to the new contract
- standardize every page-level primary action across the whole product
- move all inline child maintenance out of domain screens
