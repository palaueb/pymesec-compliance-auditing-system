# Automation Runtime Binding Backlog v1

## Purpose

Track the remaining work required to fully bind automation checks to governed objects (assets, risks, controls, findings) with production-grade behavior.

This file is a delivery checklist for the current automation runtime track.

## Implemented Baseline (Already Done)

- Runtime execution model with persisted runs (`automation_pack_runs`), including manual and scheduled trigger modes.
- Output mapping execution from runtime for both workflow transitions and evidence refresh.
- Runtime executor contract with a generic executor and a hello-world executor.
- Scope-aware target resolution for output mappings:
  - `explicit` mode for one object id.
  - `scope` mode for `asset` and `risk` targets with optional tag selectors.
- Automatic runtime evidence generation path (executor payload -> artifact upload -> evidence promotion).
- First-class per-target check result storage (`automation_check_results`) with status/outcome tracking.

## Remaining Work

## 1. Check Result Object Model

Status: completed

- `automation_check_results` table added and populated by runtime per mapping/target execution.
- Outcome normalization now persisted (`pass`, `fail`, `not-applicable`) with status, severity, and message.
- Pack detail now exposes latest persisted check results.
- Check results now persist direct traceability ids to generated `artifact`, `evidence`, `finding`, and `remediation_action` records when produced.

## 2. Asset and Risk Posture Propagation Rules

Status: in progress

Implemented:
- Mapping-level `posture_propagation_policy` added (`disabled` or `status-only`).
- Runtime now updates `assets` and `risks` posture fields (`automation_posture`, timestamp, message) when policy is enabled.
- Runtime now writes posture provenance (`automation_posture_check_result_id`, `automation_posture_run_id`) on assets/risks when available.
- Deterministic mapping from runtime result status:
  - `success` -> `healthy`
  - `failed` -> `degraded`
  - `skipped` -> `unknown`

Remaining depth:
- Extend policy set beyond status-only (for example weighted score adjustments).

## 3. Mapping Policy Profiles

Status: in progress

Implemented:
- `execution_mode` policy added per mapping with options `runtime-only`, `manual-only`, `both`.
- Runtime now executes only mappings allowed for runtime.
- Manual mapping apply now rejects `runtime-only` mappings.
- Mapping UI exposes execution mode explicitly.
- `on_fail_policy` now supports `raise-finding` with deduplicated finding creation per mapping+target fingerprint.
- `on_fail_policy` now also supports `raise-finding-and-action`, creating one planned remediation action per deduplicated failure fingerprint.
- Latest check results now expose direct navigation to the generated finding when one exists.
- Latest check results now expose direct navigation to generated remediation actions when they exist.
- `evidence_policy` now supports `always`, `on-fail`, and `on-change` with per-target delivery state to suppress unchanged payload uploads.

Remaining depth:
- Extend `on_fail` policy beyond finding/action creation (for example custom workflow remediation escalation).
- Add evidence policy mode `on-change-with-window` (time-based refresh override) if operators need periodic proof refresh despite unchanged payload.

## 4. Runtime Scheduling Model

Status: in progress

Implemented:
- Per-pack schedule settings are now available (`runtime_schedule_enabled`, `runtime_schedule_cron`, `runtime_schedule_timezone`).
- CLI runtime can enforce schedule policy via `automation:runs --trigger=scheduled --respect_schedule=1`.
- Schedule dedupe by minute slot is persisted with `runtime_schedule_last_slot`.

Remaining depth:
- Add scheduler safety controls: overlap lock, timeout, max targets per run.
- Add scheduler health and invalid-cron diagnostics visible to operators.
- Document required CRON entries and failure monitoring expectations.

Why:
- Baseline scheduling exists, but production safety/operability controls are still incomplete.

## 5. Idempotency and Retry Semantics

Status: in progress

Implemented:
- Runtime now persists per-check-result `idempotency_key` (pack+mapping+target).
- Mapping runtime policy now supports bounded retries (`runtime_retry_max_attempts`, `runtime_retry_backoff_ms`).
- Check results now persist `attempt_count` and `retry_count` for runtime diagnostics.

Remaining depth:
- Add transient-classification strategy so retries can be selectively applied by failure class.
- Add duplicate-evidence protection semantics for cross-run retries beyond current evidence-policy behavior.

Why:
- Production automation must be safe under retries and operational restarts.

## 6. Resolver Coverage Expansion

Status: pending

- Extend scope resolver mode beyond `asset` and `risk` where appropriate (`control`, `finding`, `vendor-review`).
- Add typed selector parsers per subject type (not only tag key:value strings).

Why:
- Current resolver coverage is intentionally narrow; broader target classes are required to generalize the model.

## 7. Runtime Observability

Status: in progress

Implemented:
- Latest check results now include retry diagnostics (`attempt_count`, `retry_count`) and idempotency key persistence.
- Runtime check results now hold direct traceability links to generated evidence/finding/action records.

Remaining depth:
- Add structured runtime events and counters (`targets_resolved`, `deliveries_ok`, `deliveries_failed`, `duration_ms` by mapping).
- Add operator-facing runtime diagnostics panel with filterable failures by mapping and target.

Why:
- Existing run history is present, but troubleshooting still relies on textual messages.

## 8. Security and Guardrails for Runtime Payloads

Status: in progress

Implemented:
- Runtime now enforces allowed artifact types at automation payload delivery stage.
- Runtime now enforces payload size guardrail per mapping (`runtime_payload_max_kb`).
- Runtime now enforces max resolved targets per mapping (`runtime_max_targets`).
- Selector parsing now returns explicit guardrail failures for malformed/unsupported tags.

Remaining depth:
- Add guardrail telemetry rollups so operators can distinguish execution failure vs policy denial at a glance.
- Add configurable allowlists/denylists per pack for tighter policy partitioning.

Why:
- Runtime currently trusts executor payload shape too much for long-term hardening.

## Delivery Order Recommendation

1. Check result object model.
2. Posture propagation rules.
3. Mapping policy profiles.
4. Scheduling + idempotency/retry.
5. Resolver expansion + observability + hardening.
