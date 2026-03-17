# ADR-020 Framework Packs as Plugins with Separated Skeleton and Implementation Data

## Status

Accepted

## Context

PymeSec needs to support multiple compliance frameworks (ISO 27001:2022, NIS2, ENS, GDPR) and
allow organizations to demonstrate compliance against them. A naive implementation would embed
the framework catalog data (clauses, controls, articles) inside the demo seeder or tie it to a
specific organization, as was the case in the initial prototype.

The original `control_frameworks` and `control_requirements` tables had a non-nullable
`organization_id` column, which meant that each organization owned its own copy of the framework
catalog. This created three problems:

1. A fresh production installation (`SystemBootstrapSeeder`) contained zero framework data, making
   the platform unusable for compliance purposes immediately after install.
2. Multiple organizations using the same standard (ISO 27001) would need duplicate copies of all
   93 Annex A controls, making maintenance and updates brittle.
3. Framework catalog data was mixed with demo/test fixture data in `CoreTenancySeeder`, blurring
   the boundary between production content and development scaffolding.

Additionally, different frameworks have structurally different catalogs. ISO 27001 has themes and
controls (2-3 levels). NIS2 has articles and obligations (2 levels). ENS has categories and
measures with applicability levels (basic/medium/high). A simple custom framework might be a flat
checklist. Any data model must accommodate all of these without requiring schema changes per
framework.

## Decision

### Framework packs are plugins

Each compliance framework ships as an independent plugin under `plugins/framework-<id>/`:

- `plugins/framework-iso27001/`
- `plugins/framework-nis2/`
- `plugins/framework-ens/`
- etc.

The plugin contains a seeder that populates global framework catalog tables. It does not own any
organization-level data. The plugin's seeder is called by `SystemBootstrapSeeder`, making the
framework catalog available on any fresh installation.

### Two-layer data model

**Layer 1 — Framework skeleton (global, plugin-seeded, no organization_id)**

```
frameworks
  id, code, name, version, description, kind, organization_id (nullable)
  organization_id = null means the framework is a global system pack.
  organization_id = 'org-x' means the framework is a custom org-specific framework.

framework_elements
  id, framework_id, parent_id (nullable, self-referential)
  code (nullable), title, description (nullable)
  element_type: domain | control | clause | article | obligation | criterion | item
  applicability_level (nullable) — used by ENS for basic | medium | high thresholds
  sort_order, metadata (JSON nullable)
  No organization_id — element ownership is inferred from the parent framework.
```

**Layer 2 — Organization implementation (per-org, with organization_id)**

```
controls
  id, organization_id, scope_id, framework_element_id (nullable FK → framework_elements)
  name, framework (label), domain, evidence
  The organization creates controls that may or may not map to a specific framework element.

control_requirement_mappings
  id, organization_id, control_id, framework_element_id (FK → framework_elements)
  coverage, notes
  Records how an org's control addresses a specific framework element/requirement.

org_framework_adoptions (optional, for explicit adoption tracking)
  id, organization_id, framework_id, scope_id, target_level (nullable), adopted_at, status
```

### Custom org-level frameworks

Organizations can still create their own custom frameworks (not backed by a plugin). These are
created with `organization_id` set to the organization's ID. Their elements are implicitly scoped
to that organization through the framework FK. The controls catalog UI allows creating these
frameworks and their elements via the same interface used to manage org controls.

### ControlsCatalogRepository visibility rules

When listing frameworks visible to an organization, the repository returns:
- All global framework packs (`organization_id IS NULL`)
- All custom frameworks owned by that organization (`organization_id = orgId`)

When listing framework elements (requirements/clauses), the repository joins through the
framework to apply the same visibility rule.

### SystemBootstrapSeeder

`SystemBootstrapSeeder` now calls each registered framework pack seeder. On a fresh install, the
framework catalog is immediately populated before any organization is created. This means that
when the first organization is set up via the setup wizard, the framework catalog is already
available for use.

Framework pack seeders use `insertOrIgnore` and are idempotent — running them multiple times
or across migrations is safe.

### Replacement of legacy tables

The legacy tables `control_frameworks` and `control_requirements` (both with non-nullable
`organization_id`) are dropped. They are replaced by `frameworks` and `framework_elements`
respectively. The `control_requirement_mappings.requirement_id` column is renamed to
`framework_element_id` to accurately reflect the new relationship.

## Consequences

Positive:

- Framework catalogs are available on every fresh installation without running demo seeders.
- A single copy of ISO 27001 Annex A serves all organizations, with no duplication.
- The architecture naturally supports different framework structures (flat, hierarchical,
  level-gated) through the `element_type`, `parent_id`, and `applicability_level` columns.
- Adding a new framework (ENS, GDPR) is a self-contained plugin operation — no core changes.
- Custom org-level frameworks use the same tables and UI as system packs; they are just scoped
  by organization_id on the framework record.

Tradeoffs:

- Existing `control_frameworks` and `control_requirements` data must be migrated in a single
  destructive migration. Since no production installs exist at this point, this is acceptable.
- The `ControlsCatalogRepository` must be updated to query the new tables. The method signatures
  and return shapes remain backward-compatible.
- Framework pack seeders must be registered in `SystemBootstrapSeeder`. New framework plugins
  must be added there to be included in the system bootstrap.

Follow-up:

- `org_framework_adoptions` is created but initially unused — to be activated when the UI
  supports explicit framework adoption workflows.
- ENS applicability level filtering (show only elements up to target level) is deferred to
  the `framework-ens` plugin implementation.
- The setup wizard should eventually offer framework selection as part of first-org creation.
