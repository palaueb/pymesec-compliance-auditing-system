# Title

ADR-016: Continuity and BCM as a Domain Plugin

# Status

Accepted

# Context

The PRD requires support for continuity-related artifacts and workflows, but ADR-001 keeps domain behavior out of the core. The platform now already provides the substrate needed by a first BCM implementation:

- tenancy and memberships
- persistent authorization roles and grants
- functional actors and assignments
- audit trail
- workflow engine
- artifacts and evidence storage

That makes continuity a suitable next vertical plugin rather than a core concern.

# Decision

The platform will implement `continuity / BCM` as a domain plugin.

In v1, the plugin owns:

- continuity services
- recovery plans

The plugin provides:

- organization-aware and optional scope-aware records
- create and edit forms inside the shell
- workflow states `draft`, `review`, `active`, and `retired`
- evidence attachments through the core artifact service
- ownership through functional actors
- links to risks, findings, policies, and stable asset identifiers

The core remains responsible for:

- authorization
- tenancy
- workflow mechanics
- artifacts
- audit
- menu and screen composition

Because the asset catalog is not yet a database-backed shared model, continuity records may link to asset identifiers as stable strings until a stronger shared contract exists.

# Consequences

- BCM stays modular and consistent with the core-plus-plugins architecture.
- Continuity services, plans, evidence, and reviews can already interoperate with risks, findings, policies, and artifacts.
- Future recovery exercises, dependency graphs, and resilience analytics can evolve inside the plugin without bloating the core.

# Rejected Alternatives

1. Put continuity plan records into the core

This was rejected because continuity is a domain capability, not shared infrastructure.

2. Model continuity only as policies or risks

This was rejected because continuity services and plans are first-class records with their own workflow and evidence.

3. Wait for a full asset dependency engine before starting BCM

This was rejected because the current substrate is enough for a first useful continuity register and plan workflow.

# Open Questions

- Should service dependency mapping become a sub-resource of this plugin or a separate resilience plugin later?
- When the asset catalog becomes persistent, should continuity linkage move to a stronger shared reference contract?
- What exercise and test history model should be added in the next BCM iteration?
