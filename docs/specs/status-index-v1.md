# Status Index v1

Archived status snapshot document.

Canonical TODO is now:
- `docs/specs/project-todo.md`

This file is kept only as historical context.

## Last Snapshot

## Current Snapshot (2026-04-05)

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
- Runtime safety controls (overlap lock + timeout outcomes)
- Runtime observability depth (structured counters/events + operator diagnostics panel)

## Delivery Order From This Point

1. Ship package artifact install pipeline from the external catalog.
2. Apply install-time and runtime security policy gates (schema, static inspection, capability broker).
3. Ship runtime overlap/timeout safety and diagnostics telemetry depth.

## Synchronization Rule

When closing a slice:

1. Update `project-todo.md` only.
2. Keep HELP/spec updates aligned with the implementation.
