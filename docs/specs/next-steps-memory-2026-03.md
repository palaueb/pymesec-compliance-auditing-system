# Next Steps Memory — 2026-03

This note captures the current recommendation for the next implementation blocks after the first usable platform baseline.

## Current Recommendation

Priority order:

1. `multi-owner and assignment depth`
2. `framework-specific reporting presets`
3. `communications and reminder channels`

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
- `governed-reference-data phase 2` across findings, assessments, and continuity exercises/test runs
- `governed-reference-data phase 3` with admin-managed catalogs and organization overrides
- `framework adoption workflows` with scope-aware adoption and assessment filtering
- `framework-packs and reporting depth` with ENS, GDPR, and framework coverage summaries
- `evidence automation and repository depth` with direct artifact promotion, inferred links, and repository candidates
- `evidence download, preview, and reminder workflows`

### 1. Multi-owner and Assignment Depth

Object-level access and governed vocabularies are stronger now, but accountability is still too single-owner in several domains.

Next increment:

- multi-owner assignments where the domain requires them
- richer assignment types in operational screens
- clearer administration of responsibility matrices

### 2. Framework-Specific Reporting Presets

Frameworks are now adopted explicitly, but reporting is still generic.

Next increment:

- framework-specific management views
- preset exports per adopted framework
- readiness summaries per scope and framework
- adoption progress snapshots for leadership

### 3. Communications and Reminder Channels

Reminders now exist inside the platform, but outbound delivery and communication administration are still not a product feature.

Next increment:

- mail delivery settings from the admin UI
- reminder channel control
- notification templates for operational follow-up
- evidence and assessment reminder delivery beyond in-app dispatch

## Immediate Execution Decision

Start with `multi-owner and assignment depth`.
