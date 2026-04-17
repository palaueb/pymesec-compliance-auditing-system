# Automation Catalog v1

## Goal

Introduce the first product-level automation catalog foundation as a reusable governed workspace.

This slice establishes:

- automation pack object model
- operator-facing catalog UI
- lifecycle controls (`install`, `enable`, `disable`)
- ownership and provenance metadata
- health and last-run posture fields
- output mapping registry per automation pack
- manual delivery execution for evidence refresh and workflow transitions
- external repository registration with signed index refresh (`repository.json` + `repository.sign`)
- discovered latest-release ingestion into the local pack catalog
- runtime execution baseline (`manual + CLI/CRON`) with persisted run history

It still does not install third-party runtime artifact code into an isolated executor.

## Architecture Boundary

The capability is delivered as a plugin:

- plugin id: `automation-catalog`
- type: `automation`
- runtime class: `PymeSec\Plugins\AutomationCatalog\AutomationCatalogPlugin`

Main files:

- `plugins/automation-catalog/plugin.json`
- `plugins/automation-catalog/src/AutomationCatalogPlugin.php`
- `plugins/automation-catalog/src/AutomationCatalogRepository.php`
- `plugins/automation-catalog/routes/web.php`
- `plugins/automation-catalog/routes/api.php`
- `plugins/automation-catalog/resources/views/index.blade.php`
- `plugins/automation-catalog/src/AutomationOutputMappingDeliveryService.php`
- `plugins/automation-catalog/src/AutomationPackageRepositorySyncService.php`
- `plugins/automation-catalog/src/AutomationPackRuntimeService.php`

## Data Model

Table: `automation_packs`

Core fields:

- identity: `id`, `pack_key`, `name`, `version`
- tenancy scope: `organization_id`, optional `scope_id`
- ownership/provenance: `owner_principal_id`, `provider_type`, `provenance_type`, `source_ref`
- lifecycle: `lifecycle_state`, `is_installed`, `is_enabled`, `installed_at`, `enabled_at`, `disabled_at`
- runtime schedule policy: `runtime_schedule_enabled`, optional `runtime_schedule_cron`, optional `runtime_schedule_timezone`, optional `runtime_schedule_last_slot`
- health posture: `health_state`, `last_run_at`, `last_success_at`, `last_failure_at`, `last_failure_reason`, `last_sync_at`

Current normalized state sets:

- lifecycle: `discovered`, `installed`, `enabled`, `disabled`
- health: `unknown`, `healthy`, `degraded`, `failing`
- provider type: `native`, `community`, `vendor`, `internal`
- provenance type: `plugin`, `marketplace`, `git`, `manual`

Table: `automation_pack_output_mappings`

Core fields:

- identity: `id`, `automation_pack_id`, `mapping_label`, `mapping_kind`
- target binding: `target_subject_type`, `target_subject_id`, `target_binding_mode`, `target_scope_id`, `target_selector_json`
- propagation policy: `posture_propagation_policy` (`disabled` or `status-only`)
- execution gate: `execution_mode` (`both`, `runtime-only`, `manual-only`)
- failure policy: `on_fail_policy` (`no-op`, `raise-finding`, `raise-finding-and-action`)
- evidence policy: `evidence_policy` (`always`, `on-fail`, `on-change`)
- runtime retry policy: `runtime_retry_max_attempts`, `runtime_retry_backoff_ms`
- runtime guardrails: `runtime_max_targets`, `runtime_payload_max_kb`
- workflow mapping fields: `workflow_key`, `transition_key`
- lifecycle: `is_active`, `last_applied_at`, `last_status`, `last_message`
- tenancy and ownership: `organization_id`, optional `scope_id`, `created_by_principal_id`, `updated_by_principal_id`

Current normalized state sets:

- mapping kind: `evidence-refresh`, `workflow-transition`
- last status: `never`, `success`, `failed`

Table: `automation_pack_repositories`

Core fields:

- identity: `id`, `label`
- tenancy scope: `organization_id`, optional `scope_id`
- source and trust: `repository_url`, `repository_sign_url`, `public_key_pem`, `trust_tier`
- operational state: `is_enabled`, `last_refreshed_at`, `last_status`, `last_error`
- ownership traceability: `created_by_principal_id`, `updated_by_principal_id`

Table: `automation_pack_releases`

Core fields:

- identity and source: `id`, `repository_id`, `pack_key`, `pack_name`, `version`, `is_latest`
- tenancy scope: `organization_id`, optional `scope_id`
- artifact metadata: `artifact_url`, `artifact_signature_url`, `artifact_sha256`, `pack_manifest_url`
- policy metadata: `capabilities_json`, `permissions_requested_json`, `raw_metadata_json`
- discovery posture: `discovered_at`

Table: `automation_pack_runs`

Core fields:

- identity and tenancy: `id`, `automation_pack_id`, `organization_id`, optional `scope_id`
- execution context: `trigger_mode` (`manual` or `scheduled`), initiator principal/membership
- execution posture: `status`, `started_at`, `finished_at`, `duration_ms`
- mapping counters: `total_mappings`, `success_count`, `failed_count`, `skipped_count`
- diagnostics: `summary`, `failure_reason`

Table: `automation_check_results`

Core fields:

- identity and linkage: `id`, `automation_pack_run_id`, `automation_pack_id`, optional `automation_output_mapping_id`
- tenancy and execution context: `organization_id`, optional `scope_id`, `trigger_mode`, `checked_at`
- check target and semantics: `mapping_kind`, optional `target_subject_type`, optional `target_subject_id`
- outcome posture: `status`, `outcome`, optional `severity`, optional `message`
- traceability: optional `artifact_id`, `evidence_id`, `finding_id`, `remediation_action_id`
- execution diagnostics: optional `idempotency_key`, `attempt_count`, `retry_count`

Table: `automation_failure_findings`

Core fields:

- identity and tenancy: `id`, `organization_id`, optional `scope_id`
- mapping and target linkage: `automation_pack_id`, optional `automation_output_mapping_id`, `target_subject_type`, `target_subject_id`
- dedupe and traceability: unique `fingerprint`, `finding_id`, optional `remediation_action_id`, `first_check_result_id`, `last_check_result_id`

Table: `automation_evidence_delivery_states`

Core fields:

- target identity: `automation_output_mapping_id`, `target_subject_type`, `target_subject_id`
- tenancy and delivery memory: `organization_id`, optional `scope_id`, `last_payload_fingerprint`, `last_check_outcome`, optional `last_artifact_id`, `last_delivered_at`

## UI Contract

Menu:

- `plugin.automation-catalog.root`

Screen:

- title key: `plugin.automation-catalog.screen.root.title`
- subtitle key: `plugin.automation-catalog.screen.root.subtitle`

Workspace behavior:

- browse installed packs in the main catalog list
- open one pack detail in focused mode (without rendering index panels above it)
- apply lifecycle actions
- execute one runtime run from pack detail (`Run now`)
- configure per-pack runtime schedule policy (enable flag, cron expression, timezone)
- update health posture and failure reason
- register output mappings (explicit object or scope resolver for asset/risk)
- configure runtime retry and guardrail policy per mapping
- execute evidence-refresh mappings by uploading output files or reusing artifact ids
- execute workflow-transition mappings against governed workflow subjects
- track per-mapping last delivery status and last message
- inspect runtime execution history per pack (manual and scheduled runs)
- execute runtime evidence-refresh mappings with generated payloads through the runtime executor contract
- persist per-target runtime check results and surface them in pack detail
- persist per-target runtime check results with direct traceability links to generated evidence/finding/remediation action records
- persist per-target retry/idempotency diagnostics for runtime troubleshooting
- optionally propagate runtime status posture into `asset` or `risk` targets when mapping policy is enabled
- posture propagation now records provenance links (`check_result_id` and `run_id`) on asset/risk posture fields
- optionally raise deduplicated findings on failed checks via mapping `on_fail_policy`
- optionally create planned remediation actions linked to those findings when `on_fail_policy` is `raise-finding-and-action`
- expose direct evidence/finding/action navigation from check results when runtime policies generate those records
- control evidence generation churn via `evidence_policy` (`on-change` skips unchanged payloads, `on-fail` skips pass outcomes)
- show repository onboarding by default when no repository is configured
- register external package repositories with trust tier and public key
- refresh repository indexes with signature validation before ingestion
- inspect latest external releases discovered from enabled repositories
- install discovered releases from the external catalog before they appear in the installed-pack list

CLI runtime operation:

- `php artisan automation:runs --organization_id=org-a --scope_id=scope-eu --trigger=scheduled`
- `php artisan automation:runs --organization_id=org-a --scope_id=scope-eu --trigger=scheduled --respect_schedule=1`
- target one pack with `--pack_id=<automation-pack-id>`

API v1 operation surface (MCP/OpenAPI-ready):

- base path: `/api/v1/automation-catalog`
- packs: list, create, install, enable, disable, uninstall, health update, schedule update, run
- repositories: list, save, install official, refresh
- output mappings: create and manual apply
- lookups: scopes, artifacts, target-subject options
- external catalog: latest release rows from enabled repositories

## Authorization

Permissions:

- `plugin.automation-catalog.packs.view`
- `plugin.automation-catalog.packs.manage`

Role set additions:

- `automation-viewer`
- `automation-operator`

## Current Limitations

Still outside this slice:

- isolated runtime sandbox for untrusted pack code
- package artifact runtime installation pipeline (`download -> verify -> install runtime payload`)
- pre-install manifest schema gate during install
- pre-install security policy gates and pack static inspection
- policy-driven posture propagation from check outcomes into asset/risk records
- per-pack scheduling policy and overlap/timeout controls
- idempotency and retry semantics for mapping/target delivery
- runtime observability depth (target-level counters/events)

These remain in the next automation phases.

## Follow-up Specs

Next-phase package and security definitions are tracked in:

- `docs/specs/automation-packages-repository-and-publish-v1.md`
- `docs/specs/automation-pack-security-policy-v1.md`
- `docs/specs/automation-runtime-binding-backlog-v1.md`
