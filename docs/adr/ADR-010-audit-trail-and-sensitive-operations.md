# Title

ADR-010: Core Audit Trail for Sensitive Operations

# Status

Accepted

# Context

The PRD makes auditability a product principle and requires:

- an audit trail as a core responsibility
- auditable records for sensitive operations
- traceability across plugin lifecycle, compliance data, and platform administration

ADR-001 places audit trail in the core as shared platform infrastructure. ADR-004 requires sensitive authorization administration to be auditable. ADR-006 makes plugin lifecycle core-governed. ADR-008 introduces public events and hooks but leaves open the separate question of what must become durable audit evidence.

The platform therefore needs an architectural decision that makes audit logging a first-class core contract rather than an implementation convenience.

# Decision

The platform will implement a `core audit trail` as the authoritative append-only record of sensitive operations performed by the core and by plugins.

The audit trail is separate from:

- debug or application logs
- metrics
- user-facing domain history
- workflow notes or comments

Audit records must capture, at minimum:

- what happened
- when it happened
- which component originated the action
- which principal, membership, system process, or job triggered it
- which organization and scope were involved when applicable
- which target object or capability was affected
- whether the operation succeeded or failed

Minimum audit coverage in v1 includes:

- organization and scope lifecycle changes
- membership, role, and permission administration changes
- plugin lifecycle operations
- configuration changes with security or tenancy impact
- sensitive attachment operations
- identity-to-functional-actor linkage changes
- plugin-defined sensitive actions emitted through core audit contracts

Safety rules:

- the audit trail is append-only in normal operation
- secrets, tokens, passwords, and raw sensitive content must not be stored in audit payloads
- correction, purge, or archival operations must themselves be governed and auditable

Integration rule:

- plugins must use the core audit contract for platform-significant sensitive operations
- plugin-specific audit event names are allowed, but they remain part of one common audit system

# Consequences

- The platform gains one durable traceability model across core and plugin operations.
- Sensitive administrative changes can be investigated without depending on transient logs.
- Auditability remains tenant-aware because organization and scope context are part of the contract.
- Plugins can contribute auditable behavior without fragmenting traceability into isolated logging systems.
- The project will need explicit audit write APIs, retention policy, query permissions, and redaction guidance.

# Rejected Alternatives

1. Relying on application logs as the audit system

This was rejected because operational logs are not a stable or sufficient governance record.

2. Each plugin maintaining a separate private audit store

This was rejected because the platform requires unified traceability and common access-control policy for audit data.

3. Storing full secrets or raw confidential content for maximum forensic detail

This was rejected because accountability must not come at the cost of leaking sensitive data.

4. Using user-facing domain history as the only traceability mechanism

This was rejected because business history and security-grade audit evidence are different concerns.

5. Allowing silent deletion or rewrite of audit records in normal operation

This was rejected because it would undermine trust, traceability, and compliance evidence.

# Open Questions

- Which exact event catalog must be mandatory audit coverage in v1?
- What retention, archival, and export policy should apply by default?
- How should failed attempted operations be represented when no state change occurred?
- Which audit queries and exports require stronger permissions than simple audit-log viewing?
