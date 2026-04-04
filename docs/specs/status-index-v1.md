# Status Index v1

Single source of truth for execution status across the current product evolution wave.

If status appears inconsistent between documents, this file and `longer-term-execution-todo-v1.md` take precedence.

## Canonical Sources

- High-level status: `docs/specs/status-index-v1.md` (this file)
- Execution-level checklist: `docs/specs/longer-term-execution-todo-v1.md`
- Planning/backlog expansion: `docs/specs/project-todo-after-all-current-work.md`

`docs/specs/project-todo.md` is intentionally high-level and should mirror this status at category level.

## Current Snapshot (2026-04-04)

### Completed

- Third-party risk / vendor review workspace
- Questionnaire engine baseline (transversal plugin + shared storage + review semantics)
- Collaboration layer baseline (comments, requests, handoff, mentions, shared drafts)
- Management reporting depth for assessments, evidence, risks, findings, and vendors
- Secure external collaboration generalized through a transversal model with external collaborator lifecycle controls
- Automation catalog foundations plus output mapping into evidence refresh and workflow transitions
- External package repository ingestion in automation catalog with signed `repository.json` refresh
- First installable sample automation pack (`utility.hello-world`) with publish pipeline (`src -> deploy`)

### Active Pending

- Package artifact install pipeline (`download -> verify -> register -> enable`)
- Automation pack install-time gates (manifest schema validation + static inspection + capability approval)
- Brokered runtime enforcement and per-pack secret/config controls
- Run-level execution history and telemetry beyond pack-level posture

## Delivery Order From This Point

1. Ship package artifact install pipeline from the external catalog.
2. Apply install-time and runtime security policy gates (schema, static inspection, capability broker).
3. Extend execution telemetry from pack-level posture into run-level tracking.

## Synchronization Rule

When closing a slice:

1. Update execution status in `longer-term-execution-todo-v1.md`.
2. Reflect high-level state in `project-todo.md`.
3. If milestone boundaries changed, update this status index.
4. Keep HELP/spec updates aligned with the implementation.
