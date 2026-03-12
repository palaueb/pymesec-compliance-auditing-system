# Title

Plugin Release Pipeline v1

# Status

Draft

# Context

The PRD defines the platform as a `CORE + plugins` system where plugins are intended to evolve independently when compatibility is preserved. ADR-005 establishes the repository strategy:

- one main GitHub repository contains the core and official base plugins
- each distributable plugin also has its own GitHub repository
- the main repository is the coordinated development source of truth
- plugin repositories are the independent distribution and release units

The platform therefore needs a release pipeline specification that defines how plugin code moves from the coordinated source repository into plugin-specific distribution repositories with repeatable validation, tagging, and traceability.

# Specification

## 1. Source-of-Truth Repository Model

The release model uses two repository roles.

### Main Repository

The main repository is the coordinated development source of truth for:

- the core
- official plugins
- shared packages
- architecture and release policies
- integrated validation workflows

All release candidates for official plugins originate from reviewed commits in the main repository.

### Plugin Repository

Each distributable plugin has a dedicated GitHub repository.

The plugin repository is the authoritative distribution and release location for:

- plugin source snapshots intended for consumers
- plugin version tags
- plugin release metadata
- future published releases and artifacts

The plugin repository is downstream from the main repository for official plugins in v1.

## 2. Plugin Packaging Boundaries

A plugin release unit is defined by the plugin subtree under:

- `plugins/<plugin-id>/`

The release boundary includes only files that belong to that plugin’s distributable package.

Included by default:

- plugin manifest
- plugin-owned source files
- plugin-owned migrations
- plugin-owned translations
- plugin-owned configuration
- plugin-owned documentation intended to ship with the plugin

Excluded by default unless explicitly required by packaging policy:

- unrelated monorepo files
- core code
- other plugins
- monorepo-only CI configuration
- workspace-only development files

Shared reusable code that a plugin depends on must not be copied implicitly into the plugin package unless the packaging model explicitly defines that behavior. The preferred approach is for reusable shared logic to have its own separately managed package boundary.

## 3. Release Trigger Model

A plugin release pipeline may be triggered by:

- an explicit release request for a specific plugin and target version
- an approved release workflow acting on a prepared main-repository revision

The pipeline must identify:

- target plugin identifier
- target plugin version
- source commit in the main repository
- target plugin repository

The pipeline must not infer a release from arbitrary repository state without an explicit target plugin and version.

## 4. Split and Publish Workflow

The split/publish workflow in v1 is:

1. Select the approved source commit from the main repository.
2. Resolve the plugin subtree under `plugins/<plugin-id>/`.
3. Validate that the subtree contains the required packaging metadata and release inputs.
4. Materialize the distributable plugin content as a clean plugin package snapshot.
5. Publish that snapshot into the dedicated plugin repository.
6. Create the plugin version tag in the plugin repository.
7. Record release traceability metadata linking the main-repository source commit to the plugin repository tag.

Publishing requirements:

- the published content must correspond exactly to the approved source commit for that plugin subtree
- the plugin repository history must preserve a deterministic mapping back to the originating main-repository revision
- the publish step must be repeatable and auditable

## 5. Tag Generation Rules

Each plugin release must create a version tag in the plugin repository.

Rules:

- the tag version must match the plugin version declared in the plugin manifest
- the tag must be created only after release readiness checks pass
- the tag must identify one unique plugin repository commit
- the same plugin version tag must not be reused for different content

Recommended v1 tag format:

- `v<plugin-version>`

Alternative namespaced tag formats may be introduced later if needed, but v1 assumes one dedicated repository per plugin, so plugin-only semantic version tags are sufficient.

Examples:

- `v0.1.0`
- `v0.1.1`
- `v1.0.0`

## 6. Release Readiness Checks

Before a plugin can be published and tagged, the pipeline must verify release readiness.

Minimum required checks:

- plugin manifest validation
- version consistency validation
- packaging boundary validation
- plugin-specific automated test validation where available
- compatibility validation against the declared core version range
- changelog or release note presence according to release policy
- translation and required asset presence checks where applicable

The pipeline must fail closed:

- if a required check cannot run
- if a required check fails
- if the plugin manifest or packaging metadata is incomplete

## 7. Compatibility Validation Against the Core

Plugin releases must be validated against the core compatibility contract before tagging.

Compatibility validation must include:

- manifest-declared core compatibility range
- plugin contract compatibility with the target core contract version
- plugin dependency compatibility where required
- migration and upgrade validation where the plugin changes persistence behavior

Validation policy:

- a plugin may be released without a new core release if its declared compatibility with supported core versions remains valid
- compatibility must be checked against the intended supported core range, not assumed from branch naming alone
- if a plugin change depends on unreleased core contract changes, the plugin is not release-ready until those core changes are part of an allowed compatibility target

## 8. Changelog and Update Requirements

Each plugin release must include update information sufficient for consumers and maintainers to understand what changed.

Minimum v1 requirements:

- release version
- originating main-repository source reference
- summary of notable changes
- compatibility statement with core version range
- upgrade notes if migrations, manifest changes, configuration changes, or breaking changes are involved

Changelog policy:

- plugin repositories must maintain plugin-specific changelog continuity across tags
- changelog entries must describe the plugin release unit, not unrelated monorepo changes
- breaking changes must be clearly marked

## 9. Traceability Requirements

Every plugin release must be traceable back to its origin in the main repository.

At minimum, the release process must preserve:

- source main-repository commit SHA
- source plugin path within the main repository
- target plugin repository commit SHA
- generated plugin tag
- release timestamp

Recommended traceability metadata:

- pipeline run identifier
- release operator or automated workflow identity
- manifest version and plugin version used for release

Traceability goals:

- auditors and maintainers must be able to identify which monorepo revision produced a plugin release
- maintainers must be able to reproduce the package content from the recorded source revision
- compatibility investigations must be able to correlate plugin releases with the core state used during validation

## 10. Version Consistency Rules

The plugin version declared in the manifest is the release version source of truth for the plugin package.

Rules:

- the manifest version must match the generated plugin repository tag
- the pipeline must reject a release if the target version is unchanged but releasable content has changed
- the pipeline must reject a release if the version changes but changelog/update requirements are missing

## 11. Failure and Idempotency Rules

The release pipeline must be safe to rerun.

Rules:

- pre-tag validation steps should be idempotent
- publish steps must not silently overwrite an existing tag
- if publish succeeds but tag creation fails, the pipeline must surface a partial-release state for manual resolution or automated recovery
- if the target plugin repository already contains the exact release commit and exact tag, rerun behavior should be treated as a no-op or explicit success according to operational policy

## 12. Security and Governance Requirements

Release publication must be restricted to approved automation or authorized maintainers.

Governance requirements:

- only validated source revisions may be released
- tag creation must happen through controlled workflow permissions
- traceability records must not be mutable without audit visibility
- release workflows must distinguish development branches from releasable states

## 13. Operational Outputs

Successful pipeline outputs must include:

- published plugin repository commit
- generated plugin tag
- release traceability record
- readiness-check result summary
- compatibility validation summary

# Consequences

- Official plugins can be released independently without abandoning coordinated monorepo development.
- Consumers receive clean plugin-specific repositories and version tags.
- Compatibility and quality validation remain centralized before distribution.
- Release automation becomes a required part of platform operations, not an optional convenience.
- Strong traceability improves auditability and future debugging of compatibility or packaging issues.

# Open Questions

- What exact split mechanism should be used in implementation: subtree split, filtered export, or another deterministic publish strategy?
- Where should traceability metadata be stored: plugin repository commit metadata, release notes, a registry service, or all three?
- Should changelog generation be fully automated, manually curated, or hybrid in v1?
- What minimum automated test matrix is required before an official plugin may be tagged for release?

# Acceptance Constraints

- The spec must define the source-of-truth repository model.
- The spec must define plugin packaging boundaries.
- The spec must define split/publish workflow.
- The spec must define tag generation rules.
- The spec must define release readiness checks.
- The spec must define compatibility validation against the core.
- The spec must define changelog/update requirements.
- The spec must define traceability requirements between monorepo commits and plugin releases.
- The spec must not introduce implementation code.
