# Title

ADR-003: Separation of Platform Access Identity and Functional Domain Actors

# Status

Accepted

# Context

The PRD requires a strict separation between:

- identity and access to the platform
- functional domain roles and business responsibility

The PRD also states that:

- identity is a plugin-capable concern
- the core must remain identity-provider-agnostic
- functional actors may exist as domain references without login access
- access permissions must not automatically imply business ownership

ADR-001 establishes a minimal, domain-agnostic core with plugin-first functional capabilities. ADR-002 selects Laravel as the implementation framework but does not change the architectural boundary between core abstractions and plugin-delivered capabilities.

This ADR defines the conceptual and architectural separation needed to prevent access control, ownership, approvals, and business accountability from being collapsed into a single user model.

# Decision

The platform will model `platform access identity` and `functional domain actors` as separate architectural concerns.

`Platform access identity` covers:

- authentication into the platform
- session or token-backed access
- memberships within organizations or scopes
- permission evaluation for UI, API, and administrative operations
- linkage to external or local identity providers

`Functional domain actors` cover:

- ownership of assets, controls, risks, findings, actions, or assessments
- business accountability such as approver, auditor, DPO, or risk owner
- internal or external stakeholders referenced by domain workflows
- responsibility assignments that may be organizational, named, or external to the platform

The core will remain identity-agnostic by exposing abstractions rather than a hardcoded user model. The core will define shared contracts for:

- access principals
- memberships
- permission and policy evaluation
- functional actor references
- functional assignments between actors and domain objects

Concrete identity implementations such as local users, LDAP, OIDC, SAML, Google Workspace, or GitHub authentication must be provided by plugins. Concrete functional actor models and ownership workflows must also be provided by plugins, using the core abstractions for reference and assignment.

Permissions and functional ownership are separate and must be evaluated independently:

- permissions answer whether a principal may access or execute an operation in the platform
- functional ownership answers who is accountable for a domain object or business outcome

The system must not infer one from the other by default.

A functional actor may exist without login access because business responsibility often belongs to a person, team, or external stakeholder who must be referenced in compliance workflows but does not need direct platform access. Examples include:

- a control owner tracked for accountability but not given a system account
- an external DPO or consultant referenced in records and approvals
- a business manager responsible for remediation but operating outside the platform

A platform user may have no functional ownership role because access to the system may be granted for technical, administrative, review, or support reasons that do not imply domain accountability. Examples include:

- a system administrator managing platform configuration
- an integration operator monitoring jobs and connectors
- a read-only auditor or reviewer with inspection rights but no ownership assignments

Where a real-world person has both access identity and functional responsibility, the relationship must be explicit and linkable, but not structurally merged. The platform may allow mappings between an access principal and one or more functional actor records, yet each side retains its own lifecycle, permissions, and domain meaning.

# Consequences

- The core remains compatible with multiple identity providers without redesigning business domains.
- Domain plugins can assign ownership and responsibility even when no authenticable user exists for that actor.
- Access permissions stay clearer because they are not overloaded with business semantics.
- Identity plugins and functional actor plugins can evolve independently.
- Data modeling becomes more explicit because references between principals and actors must be intentional rather than assumed.
- UI and workflows will need clear language to distinguish access roles from business responsibility assignments.
- Some integrations will require mapping logic when a single human is represented both as a platform principal and as a functional actor.

# Rejected Alternatives

1. Single user model for authentication, permissions, and business ownership

This was rejected because it conflates access control with domain responsibility and directly contradicts the PRD requirement for strict separation between identity and functional roles.

2. Hardcoded core user entity as the source of all ownership references

This was rejected because it would make the core identity-provider-dependent and would block cases where ownership must exist without login access.

3. Automatic permission grant from functional ownership

This was rejected because being accountable for a control, risk, or action does not automatically mean the actor should receive platform access or operational privileges.

4. Automatic functional ownership inferred from membership or access role

This was rejected because administrative or technical access does not imply business accountability for assets, controls, risks, or remediation activities.

5. Identity-only plugins with ownership logic embedded in domain modules ad hoc

This was rejected because it would produce inconsistent ownership semantics across plugins and weaken the common abstraction model expected from the core.

# Open Questions

- What minimum core fields are required for `PrincipalReference`, `MembershipReference`, `FunctionalActorReference`, and `FunctionalAssignment` in v1?
- Should the first official functional actor plugin support both person-based and team-based actors from the start?
- What mapping rules are needed when one platform principal is linked to multiple functional actor records across organizations?
- Which audit events must be emitted when links between access principals and functional actors are created, changed, or removed?
