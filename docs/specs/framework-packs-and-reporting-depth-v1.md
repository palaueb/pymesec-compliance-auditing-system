# Framework Packs and Reporting Depth v1

## Status

Implemented.

## Goal

Increase product value in two related areas:

1. ship more reusable framework content as global packs
2. make framework coverage visible in day-to-day governance and audit reporting

## Scope

### Framework packs

The product now includes additional framework-pack plugins:

- `framework-ens`
- `framework-gdpr`

Together with the existing packs:

- `framework-iso27001`
- `framework-nis2`

These packs are discovered automatically by `SystemBootstrapSeeder` under the rules defined in `ADR-020`.

### Reporting depth

The first reporting-depth slice is intentionally pragmatic:

- `Controls Catalog` now shows per-framework coverage summaries
- `Assessments` now exposes framework coverage in the detail screen
- assessment report exports now include a framework coverage section

This is not a standalone reporting module yet. It is a stronger reporting layer embedded into the existing operational screens and exports.

## Framework Pack Content

### ENS

The ENS pack is seeded as a starter pack with:

- domains for governance, protection, detection, and recovery
- measures for governance, access protection, backup protection, monitoring, and service recovery
- `applicability_level` values so later phases can activate level-aware adoption

### GDPR

The GDPR pack is seeded as a starter pack with key articles for:

- processing principles
- records of processing
- security of processing
- breach notification
- data protection impact assessments

## Demo Coverage

Demo mappings now include cross-framework coverage so framework summaries are not empty:

- `Quarterly Access Review` contributes to `GDPR`
- `Backup Governance` contributes to `ENS`

This sits alongside the existing `ISO 27001`, `NIS2`, and `SOC 2` demo mappings.

## UI Changes

### Controls Catalog

Framework rows now show:

- framework source (`global pack` or `custom framework`)
- version and kind when available
- mapped requirements count
- linked controls count
- approximate coverage percentage

### Assessments

Assessment detail now includes a `Framework coverage` section showing:

- linked frameworks
- mapped requirement counts
- linked control counts
- result distribution per framework

### Assessment exports

Markdown export now includes a `Framework coverage` section before the detailed checklist.

JSON bundle now includes `framework_breakdown`.

## Product Outcome

This slice makes framework packs visible as reusable product content instead of hidden seed data.

It also gives management and auditors a faster answer to:

- which frameworks are present
- how much mapped coverage exists
- how assessment results distribute across those frameworks

## Remaining Work

This does not yet include:

- explicit framework adoption workflows
- target-level selection for ENS in the UI
- framework-specific reporting packs
- a dedicated management reporting module

Those remain follow-up work.
