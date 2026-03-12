# Title

ADR-013 Core Artifacts and Attachments Storage

# Status

Accepted

# Context

The PRD requires the core to provide base storage for attachments and artifacts. Multiple functional areas depend on this capability:

- controls and evidence
- risks and supporting documentation
- findings and remediation evidence
- privacy records and exports
- continuity and recovery artifacts

ADR-001 establishes that shared infrastructure belongs in the core while domain semantics remain in plugins. ADR-010 requires evidence-related actions to be auditable. The system therefore needs a common artifact substrate before domain plugins grow more complex.

# Decision

The platform will implement a generic artifact and attachment subsystem in the core.

The core owns:

- private storage of artifact files
- artifact metadata persistence
- tenancy-aware artifact queries
- integrity metadata such as hashes and file size
- audit and public-event integration for artifact creation

Plugins own:

- the meaning of each artifact
- subject semantics
- evidence workflows, review rules, and attachment policies
- UI flows for attaching or displaying artifacts in their domain

The core stores only generic subject references:

- `subject_type`
- `subject_id`

Artifacts are stored privately by default and are never exposed directly by plugin-managed paths.

# Consequences

- Domain plugins can attach evidence without inventing their own incompatible storage model.
- The core remains domain-agnostic because it stores generic subject-bound artifacts rather than control-specific or risk-specific records.
- Audit and event systems can observe evidence creation consistently across the platform.
- Later download, retention, scanning, and versioning work can be layered on top of one stable storage contract.

# Rejected Alternatives

## Let each plugin manage its own file storage

This was rejected because it would fragment evidence handling, break traceability expectations, and make later governance features such as audit, export, and retention much harder.

## Put business-specific evidence tables into the core

This was rejected because it would violate the core-versus-plugin boundary and hardcode compliance domains into the platform substrate.
