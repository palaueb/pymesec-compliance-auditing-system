# Framework Adoption Workflows v1

## Status

Implemented.

## Goal

Turn framework packs from passive library content into explicit organization and scope-level adoption choices.

## Scope

This slice activates the existing `org_framework_adoptions` table and wires it into product workflows.

It adds:

- adoption status per `organization + optional scope + framework`
- target level support for frameworks such as `ENS`
- adoption management inside `Controls Catalog`
- assessment framework selection based on adopted frameworks in the current workspace

## Data Model

`org_framework_adoptions` stores:

- `organization_id`
- `framework_id`
- `scope_id`
- `target_level`
- `adopted_at`
- `status`

The runtime uses the effective adoption for the current scope:

- exact scope adoption first
- otherwise organization-wide adoption

## UI Behavior

### Controls Catalog

The framework library now shows:

- current adoption status
- scope of adoption
- adopted date
- target level when set

Operators can update adoption directly from the framework card.

### Assessments

The assessment form now prioritizes adopted frameworks for the active workspace.

When adoption records exist for the organization or selected scope:

- only frameworks with `active` or `in-progress` adoption appear in the selector

When no adoption records exist yet:

- the old visible framework list remains as fallback

## Product Outcome

This makes framework use intentional:

- organizations can declare what they are actually working against
- scope-specific work can target different frameworks or maturity levels
- assessments stop behaving like the full library is always in force

## Demo Data

The demo seed now includes:

- `org-a / scope-eu` adopting `ISO 27001`
- `org-a / scope-eu` onboarding `GDPR`
- `org-a / scope-it` adopting `ENS` with target level `medium`
- `org-b / scope-ops` adopting `SOC 2`

## Remaining Work

This does not yet include:

- guided onboarding checklists per framework
- adoption approval workflow
- framework-specific reporting presets beyond the current summaries
- management dashboards dedicated to adoption progress
