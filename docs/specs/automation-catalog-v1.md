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

It still does not execute scheduled automation runtimes autonomously and does not yet install artifact code into a runtime runner.

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
- `plugins/automation-catalog/resources/views/index.blade.php`
- `plugins/automation-catalog/src/AutomationOutputMappingDeliveryService.php`
- `plugins/automation-catalog/src/AutomationPackageRepositorySyncService.php`

## Data Model

Table: `automation_packs`

Core fields:

- identity: `id`, `pack_key`, `name`, `version`
- tenancy scope: `organization_id`, optional `scope_id`
- ownership/provenance: `owner_principal_id`, `provider_type`, `provenance_type`, `source_ref`
- lifecycle: `lifecycle_state`, `is_installed`, `is_enabled`, `installed_at`, `enabled_at`, `disabled_at`
- health posture: `health_state`, `last_run_at`, `last_success_at`, `last_failure_at`, `last_failure_reason`, `last_sync_at`

Current normalized state sets:

- lifecycle: `discovered`, `installed`, `enabled`, `disabled`
- health: `unknown`, `healthy`, `degraded`, `failing`
- provider type: `native`, `community`, `vendor`, `internal`
- provenance type: `plugin`, `marketplace`, `git`, `manual`

Table: `automation_pack_output_mappings`

Core fields:

- identity: `id`, `automation_pack_id`, `mapping_label`, `mapping_kind`
- target binding: `target_subject_type`, `target_subject_id`
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

## UI Contract

Menu:

- `plugin.automation-catalog.root`

Screen:

- title key: `plugin.automation-catalog.screen.root.title`
- subtitle key: `plugin.automation-catalog.screen.root.subtitle`

Workspace behavior:

- register new pack metadata
- browse current pack posture
- open one pack detail
- apply lifecycle actions
- update health posture and failure reason
- register output mappings
- execute evidence-refresh mappings by uploading output files or reusing artifact ids
- execute workflow-transition mappings against governed workflow subjects
- track per-mapping last delivery status and last message
- register external package repositories with trust tier and public key
- refresh repository indexes with signature validation before ingestion
- inspect latest external releases discovered from enabled repositories

## Authorization

Permissions:

- `plugin.automation-catalog.packs.view`
- `plugin.automation-catalog.packs.manage`

Role set additions:

- `automation-viewer`
- `automation-operator`

## Current Limitations

Still outside this slice:

- execution runtime and scheduler orchestration
- package artifact install pipeline (`download -> verify -> install runtime payload`)
- pre-install manifest schema gate during install
- pre-install security policy gates and pack static inspection
- run history per execution instance

These remain in the next automation phases.

## Follow-up Specs

Next-phase package and security definitions are tracked in:

- `docs/specs/automation-packages-repository-and-publish-v1.md`
- `docs/specs/automation-pack-security-policy-v1.md`
