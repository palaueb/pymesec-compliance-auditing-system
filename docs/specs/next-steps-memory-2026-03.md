# Next Steps Memory — 2026-03

This note captures the current recommendation for the next implementation blocks after the first usable platform baseline.

## Current Recommendation

Priority order:

1. `governed-reference-data`
2. `assessment-sign-off-and-exports`
3. `object-level-access phase 2`

## Why This Order

### Completed

The following roadmap items are now in place:

- `assessments-audits v1`
- `evidence-management v1`
- `object-level-access v1` for assets, risks, findings, remediation actions, and dashboard metrics
- `governed-reference-data v1` started in `Asset Catalog`
- `governed-reference-data v1` extended to `Continuity` and `Privacy`

### 1. Governed Reference Data

The next highest leverage block is normalizing business values that are still free text and therefore difficult to govern, filter, and report on.

Next increment:

- managed vocabularies for asset type, criticality, and classification
- assignment-based ownership instead of free-text owner labels
- controlled business values in continuity and privacy where they are still open text

Expected outcome:

- cleaner reporting
- less entropy in user-entered data
- stronger object-level access policies later

### 2. Assessment Sign-Off and Exports

Assessments now exist as a usable audit workspace, but they still need closure workflow and richer reporting to complete the audit loop.

Next increment:

- assessment sign-off
- closure workflow
- richer export bundles
- management-facing summary outputs

### 3. Object-Level Access Phase 2

The first slice is now live, but coverage is still partial.

Next increment:

- extend object-level filters to controls, continuity, privacy, policy, and assessments
- refine dashboards and boards so all summaries respect scoped visibility
- tighten legacy fallback once domains are fully governed by assignments

Expected outcome:

- consistent team-based visibility across the full product
- fewer workspaces exposing broad organization data by default

## Immediate Execution Decision

Start with `governed-reference-data`.

Target for the current implementation slice:

- governed vocabularies for assets first
- extend governed business values to continuity and privacy
- ownership expressed through assignments instead of free-text labels
- foundation for extending object-level access to the remaining domains
