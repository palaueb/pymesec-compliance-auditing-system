# Title

ADR-005: Repository and Release Strategy

# Status

Accepted

# Context

The PRD defines the platform as a `CORE + plugins` system with formal plugin contracts, independent plugin compatibility, and a modular monolith implementation strategy. The same PRD also expects an ecosystem where plugins can evolve independently from the core as long as compatibility is preserved.

That creates two competing needs:

- coordinated development across the core and official plugins
- independent distribution and release of plugins as separate deliverables

If all code lives only in one repository, plugin release boundaries become weak and distribution becomes harder. If each plugin is developed only in its own repository, coordinated architecture changes across the core and official plugins become slower and harder to validate. The project therefore needs a repository and release strategy that supports both coordinated development and independent plugin publishing.

# Decision

The project will use a `coordinated main repository + split plugin distribution repositories` strategy.

## Main Repository Role

There will be one main GitHub repository that contains:

- the `CORE`
- official base plugins
- shared documentation
- shared development workflows
- shared compatibility and quality validation

This main repository is the coordinated development source of truth for the platform. Cross-cutting architectural changes, contract evolution, compatibility work, and synchronized development across the core and official plugins happen here first.

## Plugin Repository Role

Each distributable plugin must also have its own GitHub repository.

A plugin repository is the independent distribution and release unit for that plugin. It is the repository from which plugin consumers will later obtain:

- plugin source releases
- version tags
- release artifacts
- plugin-specific changelog and distribution metadata

Official plugin repositories are downstream publication targets of the main repository, not separate primary sources for coordinated architecture work.

## Source Layout Principle

Within the main repository:

- the core remains under `core/`
- each official plugin remains under `plugins/<plugin-id>/`
- shared reusable support remains under `packages/`

This preserves the architectural separation defined in the PRD while allowing one coordinated CI and review workflow.

## Split and Publish Pipeline

A release pipeline must be able to split and publish plugin code from the main repository into the corresponding plugin repository.

For each releasable plugin, the pipeline must be able to:

- identify the plugin source subtree in the main repository
- publish that subtree to the matching plugin GitHub repository
- create a plugin version tag in the plugin repository
- prepare that tag for later release publication workflows

The split pipeline is required platform infrastructure, not an optional convenience.

## Versioning and Release Units

The core and each plugin are separate versioned units.

Rules:

- the core has its own version history
- each plugin has its own version history
- plugin version tags are created in the plugin repository
- plugin releases do not require a new core release if compatibility and quality checks pass

Plugin releases are allowed independently from the core when:

- declared compatibility with the target core range is satisfied
- plugin quality checks pass
- plugin packaging and manifest validation pass
- required contract and upgrade checks pass

## Release Governance

The coordinated development flow is:

1. changes are developed and reviewed in the main repository
2. compatibility and quality checks run in the main repository context
3. approved releasable plugin changes are split and published to the plugin repository
4. the pipeline creates the plugin version tag in that plugin repository
5. downstream release publication may later use that tag to create formal releases

This keeps architectural review centralized while keeping plugin delivery decentralized.

# Consequences

- The main repository becomes the authoritative place for coordinated platform development and cross-plugin validation.
- Official plugins remain distributable and releasable as independent units.
- The release process can respect plugin compatibility boundaries without forcing unnecessary core releases.
- Shared CI in the main repository can validate changes against current core contracts before distribution.
- Additional release automation is required to split plugin subtrees and publish them reliably.
- Tagging and changelog discipline become more important because each plugin now has its own release stream.
- Contributors must understand that development origin and distribution origin are intentionally different.

# Rejected Alternatives

1. Single repository only, with no separate plugin repositories

This was rejected because plugins are intended to be independent distribution units. Keeping everything in one repository only would weaken plugin release autonomy and make plugin-specific distribution and version tagging less clean.

2. Fully separate repositories for the core and every official plugin as the primary development model

This was rejected because it would make coordinated architecture work, contract changes, and compatibility validation slower and more fragmented during the modular monolith phase.

3. Plugin releases always tied to core releases

This was rejected because the PRD requires plugins to evolve independently when compatibility is preserved. Forcing lockstep releases would undermine the plugin-first model.

4. Manual copy-and-paste publication of plugin code into separate repositories

This was rejected because it is error-prone, hard to audit, and incompatible with reliable repeatable release automation.

5. Plugin repositories as mirrors with no version tags of their own

This was rejected because each distributable plugin must have its own release identity and independently usable version tags.

# Open Questions

- What exact branch strategy should the split/publish pipeline use in plugin repositories?
- Should plugin repositories contain only distributable plugin content, or also generated metadata such as changelogs and release manifests?
- What minimum compatibility and quality gates must pass before a plugin tag can be created?
- How should backports be handled when a plugin needs a patch release compatible with an older core range?
