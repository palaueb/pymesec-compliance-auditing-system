# Next Steps Memory — 2026-03

This note captures the current recommendation for the next implementation blocks after the first usable platform baseline.

## Current Recommendation

Priority order:

1. `governed-reference-data phase 2`
2. `framework-packs and reporting depth`
3. `evidence automation and repository depth`

## Why This Order

### Completed

The following roadmap items are now in place:

- `assessments-audits v1`
- `evidence-management v1`
- `object-level-access v1` for assets, risks, findings, remediation actions, and dashboard metrics
- `governed-reference-data v1` started in `Asset Catalog`
- `governed-reference-data v1` extended to `Continuity` and `Privacy`
- `assessment sign-off and export formats`
- `object-level-access phase 2` across controls, continuity, privacy, policy, and assessments

### 1. Governed Reference Data Phase 2

The first slice is already in place, but there are still business fields and ownership patterns that need stronger governance.

Next increment:

- controlled values for remaining business enums
- multiple accountable parties where the domain requires it
- stronger governed links in controls, findings, policy, and assessment results

### 3. Framework Packs and Reporting Depth

The product vision depends on reusable framework content and stronger management outputs.

Next increment:

- framework pack plugins with seeded requirements and mappings
- richer management-facing reports
- reusable export layouts

### 3. Evidence Automation and Repository Depth

The repository is now usable, but evidence still depends too much on manual creation and linking.

Next increment:

- stronger evidence states and review cycles
- automatic evidence creation from operational workflows where it makes sense
- richer link management between evidence, controls, findings, and assessments

Expected outcome:

- less manual repetition during audits
- a stronger chain from operational work to auditable evidence

## Immediate Execution Decision

Start with `governed-reference-data phase 2`.

Target for the current implementation slice:

- remove remaining free-text business enums and legacy ownership labels
- strengthen controlled links for controls, findings, policies, and assessment records
- prepare the data model for richer reporting and tighter object-scoped governance
