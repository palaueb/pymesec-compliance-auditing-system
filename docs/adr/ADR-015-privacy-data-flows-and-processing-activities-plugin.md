# Title

ADR-015: Privacy Data Flows and Processing Activities as a Domain Plugin

# Status

Accepted

# Context

ADR-001 places privacy capabilities in plugins rather than in the core. ADR-003, ADR-004, ADR-009, ADR-010, ADR-012, and ADR-013 now provide enough substrate for a first real privacy vertical:

- tenancy and scope
- permissions and persistent role grants
- functional actors and ownership
- audit trail
- workflow engine
- artifacts and evidence storage

The repository already includes domain plugins for controls, risks, findings, and policies. The next coherent vertical from the PRD is privacy operations focused on data flows and processing activities.

# Decision

The platform will implement `data flows and privacy` as a domain plugin, not as a core subsystem.

The plugin owns the privacy domain records:

- `data flows`
- `processing activities`

In v1, the plugin provides:

- organization-aware and optional scope-aware records
- create and edit flows inside the shell
- create and edit processing activities inside the shell
- workflow states `draft`, `review`, `active`, and `retired`
- evidence attachments through the core artifact service
- ownership through functional actors
- links to risks, policies, findings, and stable asset identifiers

The core remains responsible only for the shared infrastructure used by the plugin:

- authorization
- tenancy
- workflow mechanics
- artifacts
- audit
- menus and screens

The plugin does not implement a complete privacy legal suite in v1. It is intentionally limited to the first usable operational register layer.

Because `asset-catalog` is not yet persisted as a database-backed domain model, privacy records may link to asset identifiers as stable strings until a stronger asset registry contract exists.

# Consequences

- Privacy behavior stays modular and aligned with the core-plus-plugins architecture.
- The platform can evolve toward RoPA, DPIA, transfer assessments, or privacy incidents without bloating the core.
- Privacy records can already interoperate with risks, policies, findings, workflow, and evidence.
- Some links remain intentionally shallow in v1, especially asset linkage, until adjacent domain plugins mature further.

# Rejected Alternatives

1. Implement privacy records directly in the core

This was rejected because privacy is domain behavior, not shared infrastructure.

2. Delay privacy until every adjacent plugin is fully mature

This was rejected because the current substrate is already sufficient for a first usable vertical and waiting would only delay integration learning.

3. Build full GDPR and RoPA coverage in the first version

This was rejected because it would add too much legal and workflow complexity before the operational substrate is proven.

4. Model privacy only as findings, policies, or risks

This was rejected because data flows and processing activities are first-class privacy records with their own lifecycle and evidence.

# Open Questions

- When the asset plugin becomes fully persistent, should asset links move from stable IDs to a stronger shared reference contract?
- Should DPIA and transfer assessment objects become sub-resources of this plugin or separate privacy plugins?
- Which privacy-specific events should be published for cross-plugin automation in the next iteration?
