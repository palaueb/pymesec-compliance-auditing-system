# PRD — Open Source Compliance Auditing Platform

## 1. Executive Summary

An open source compliance auditing platform is proposed, built around a **CORE + plugins** architecture designed to support progressive delivery of functionality without compromising the stability of the core. The product should serve as a common foundation for multiple regulatory frameworks and organizational needs, with special focus on:

* ISO/IEC 27001
* ENS
* NIS2
* GDPR / LOPDGDD
* internal and custom frameworks

The goal of the product is to provide complete traceability between requirements, controls, evidence, risks, findings, and remediation actions, while allowing third parties to extend the platform through plugins.

## 2. Product Vision

### 2.1 Vision

Create the "WordPress of compliance": a robust, stable, and secure core that a community can extend with frameworks, connectors, automations, reports, and workflows without breaking the base system.

### 2.2 Value Proposition

The platform must allow users to:

* centralize regulatory compliance
* model reusable controls and requirements
* collect manual and automated evidence
* run assessments and audits
* manage risks, exceptions, and action plans
* generate reports and dashboards
* extend functionality through decoupled plugins

### 2.3 Product Principles

* **Minimal but powerful core**
* **Everything is pluggable**
* **API-first**
* **Auditability by design**
* **Secure by default**
* **Open source first**
* **Multi-framework and multi-tenant ready**
* **Multi-language by design**
* **Identity as a plugin capability**
* **Core agnostic to business domains**
* **Strict separation between access identity and functional domain roles**
* **Domain-first user language**

## 3. Objectives

### 3.1 Business Objectives

* build a reusable open source foundation
* facilitate adoption in homelab, SMB, and technical environments
* create a community plugin ecosystem
* reduce dependence on closed GRC products
* enable future monetization through support, hosting, or premium plugins without breaking the open source model

### 3.2 Product Objectives

* deliver a functional generic compliance MVP
* support custom frameworks and controls
* guarantee full traceability between system objects
* provide formal and documented extensibility
* maintain compatibility between CORE and plugin versions

### 3.3 Initial Non-Goals

* automatic compliance certification
* replacing legal advice or formal auditing
* full coverage of all regulated sectors at launch
* a complete marketplace in the first release

## 4. Target User

### 4.1 Primary Profiles

* security administrator / sysadmin
* compliance manager
* internal auditor
* external consultant
* DPO / privacy officer
* DevOps / platform technical team
* plugin-developing open source community

### 4.2 Main Use Cases

* create a catalog of controls and map it to multiple frameworks
* collect evidence manually or through connectors
* run a quarterly or annual assessment
* record findings and generate action plans
* model risks and exceptions
* generate a status report for management
* add a new plugin without touching the core

## 5. Product Architecture

## 5.1 General Approach

The system is divided into:

1. **CORE**
2. **Functional plugins**
3. **SDK / extension contracts**
4. **Public API and internal events**

## 5.2 CORE Responsibilities

The CORE must include only what is strictly necessary to sustain the platform.

### Mandatory Conceptual Distinction

The system must explicitly separate two different layers:

1. **Identity and access to the platform**

   * who can enter the system
   * how they authenticate
   * what level of access they have to the UI, API, and administrative operations
   * what membership they have within an organization or tenant

2. **Functional domain roles**

   * who owns an asset
   * who is the risk owner
   * who is the control owner
   * who is the approver
   * who is the auditor
   * who is the DPO
   * who is responsible for a corrective action

These two concepts must not be confused. An actor may exist only as a functional reference within the domain without necessarily being a user with access to the platform. Likewise, a user with access to the platform may have no functional responsibility over assets, risks, or controls.

### The CORE must include:

* plugin engine
* plugin registry, dependencies, and compatibility
* extension contracts and base SDK
* event / hooks system
* application container and service registry
* base persistence and shared data abstractions
* audit trail
* base scheduler and job queue
* configuration system
* base internationalization
* base localization (language, locale, timezone, date and number formats)
* an internationalization system based on translation files owned by the core and by each plugin
* base storage for attachments and artifacts
* base API and public contracts
* UI shell / admin shell
* extensible routing
* extensible menu system
* permission and policy engine decoupled from the identity provider
* access principal and memberships abstraction
* abstractions for domain actors and functional assignments
* minimal workflow engine
* base notifications
* base health checks and observability
* base multi-tenant / multi-organization support
* base model of shared entities not tied to any functional domain

## 5.3 What Must Go into Plugins

Anything that is domain-specific or functionally vertical, as well as capabilities that should be replaceable or evolve independently from the core:

* user and identity management
* concrete authentication implementations (local auth, LDAP, OIDC, SAML, Google, GitHub, etc.)
* team and directory management
* functional model for participants, accountable owners, and domain ownership
* framework packs (ISO 27001, ENS, NIS2, GDPR)
* controls and requirements
* risk management
* findings and remediation
* privacy / GDPR
* third-party risk
* automated evidence connectors
* advanced dashboards
* specialized reports
* questionnaires
* advanced workflows
* alternative scoring engines
* specialized imports / exports
* plugin marketplace / repository

## 5.4 Plugin Architecture Principles

* plugins cannot modify the CORE directly
* all extension must happen through contracts, events, and public APIs
* each plugin declares dependencies, permissions, migrations, and compatible versions
* the CORE must be able to disable plugins without corrupting base data
* the CORE must be able to isolate failed plugins
* plugin data must have its own namespace
* the system must support UI plugins and backend plugins
* the system must support headless plugins
* the CORE must not assume any concrete identity implementation
* the CORE must be able to operate with different identity providers through a common contract
* the system must allow replacing identity plugins without redesigning the rest of the domains
* all domain features must be able to be decoupled as plugins without breaking compatibility

## 5.5 Plugin Types

### A. Identity Plugins

* local users
* LDAP/AD
* OIDC
* SAML
* Google Workspace auth
* GitHub auth
* user directory
* teams and memberships

### B. Domain Actor Plugins

* asset owners
* risk owners
* control owners
* approvers
* auditors
* DPO roles
* action assignees
* organizational stakeholders

### C. Domain Plugins

* controls
* risks
* findings
* privacy
* vendors
* assets
* assessments
* evidence

### D. Framework Plugins

* ISO 27001 pack
* ENS pack
* NIS2 pack
* GDPR pack

### E. Connector Plugins

* LDAP/AD sync
* Google Workspace sync
* GitHub/GitLab
* Wazuh
* osquery/Fleet
* OpenSCAP
* AWS/Azure/GCP
* Docker/Kubernetes/Proxmox

### F. Reporting Plugins

* executive reports
* audit reports
* maturity dashboards
* export bundles

### G. Automation Plugins

* rules engine
* reminders
* evidence refresh
* escalations

### H. UI Plugins

* custom dashboards
* widgets
* admin panels
* domain-specific forms

## 5.6 Technical Contracts of the Plugin System

The following must be defined:

* plugin manifest
* lifecycle: install, enable, disable, upgrade, uninstall
* plugin-owned migrations
* plugin-owned seeds
* route registration
* UI menu registration
* permission registration
* event subscription
* event publication
* scheduled tasks
* exposed APIs
* compatibility policies

## 6. Functional Requirements by Block

## 6.1 CORE Block

### User-Facing Language and Terminology

The application UI must speak in domain and operational terms, not in internal architecture terms.

Rules:

* user-facing copy, titles, menus, empty states, help text, and workflows must describe compliance work, governance, risks, evidence, access, organizations, scopes, and related business concepts
* the application must not speak to end users about the `core`, `plugins`, internal runtime architecture, or other self-referential implementation details
* technical architecture vocabulary is allowed in developer-facing documentation such as READMEs, ADRs, specs, contribution guides, and similar internal or developer materials
* when a technical capability must be exposed to the user, it should be translated into an operational concept that makes sense in the compliance product domain

### Deliverables

* plugin manager
* event bus
* service registry
* public core contracts
* base API
* UI shell
* extensible routing
* extensible menu system
* global configuration
* base internationalization and localization
* support for translation files owned by the core and by each plugin
* official initial languages: English, Spanish, French, and German
* multi-organization and scopes
* permission engine decoupled from identity
* access principal abstraction
* functional domain actor abstraction
* audit log
* attachment storage
* scheduler and asynchronous jobs

### Key User Stories

* as an admin, I want to enable or disable plugins
* as an admin, I want to define the language and locale per organization or user
* as the system, I want to record auditable events for all sensitive operations
* as a plugin, I want to subscribe to core events
* as an identity plugin, I want to integrate with the core permission engine
* as a domain plugin, I want to reference functional actors without depending on an authenticable user

### Acceptance Criteria

* plugins can be installed without modifying core code
* auditable changes are recorded
* permissions apply by organization and scope
* the system supports asynchronous jobs
* the core works without a hardcoded identity provider
* the UI and API support multiple languages from the core
* each component, whether core or plugin, maintains its own translation files in simple and easy-to-manage formats such as JSON or similar

## 6.2 Controls and Requirements Block

### Deliverables

* control library
* requirements catalog
* many-to-many mapping
* compliance status
* applicability / justifications
* control versioning

### Key Stories

* as a compliance manager, I want to create reusable controls
* as an auditor, I want to see which requirements each control covers
* as a user, I want to mark a control as not applicable and justify it

### Acceptance Criteria

* one control can map to multiple frameworks
* one requirement can be covered by multiple controls
* there is change history and versioning

## 6.3 Evidence Block

### Deliverables

* evidence repository
* evidence metadata
* hashing
* expiration
* validation
* chain of custody

### Key Stories

* as an accountable owner, I want to attach evidence to a control
* as an auditor, I want to know whether the evidence is still valid
* as the system, I want to warn when evidence expires

### Acceptance Criteria

* each file has a recorded hash
* the uploader and upload date are recorded
* there is a validation status
* evidence can be marked as expired

## 6.4 Assessments and Audits Block

### Deliverables

* assessment campaigns
* checklists
* conclusions per control
* workpapers
* sign-off

### Key Stories

* as an auditor, I want to open an assessment with scope and dates
* as an auditor, I want to record tests and conclusions
* as management, I want to see the aggregated result of an assessment

### Acceptance Criteria

* an assessment can include controls, evidence, and findings
* there is state handling and a closing workflow
* a summary report can be exported

## 6.5 Findings and Remediation Block

### Deliverables

* findings registry
* severity classification
* corrective actions
* status and re-test

### Key Stories

* as an auditor, I want to create a major nonconformity
* as an accountable owner, I want to assign a corrective action
* as a reviewer, I want to validate the closure

### Acceptance Criteria

* a finding can be linked to controls, risks, and evidence
* its lifecycle is fully traceable

## 6.5 bis Functional Actors and Ownership Block

### Deliverables

* functional actor model
* responsibility assignments
* ownership of assets, risks, controls, and actions
* configurable functional domain roles
* references to internal or external people without mandatory login access

### Key Stories

* as a compliance owner, I want to assign a risk owner even if they do not have platform access
* as an auditor, I want to see who is the functional owner of a control
* as the system, I want to distinguish access permissions from business responsibilities

### Acceptance Criteria

* an asset, risk, control, or action can have a functional owner without requiring a login-enabled user
* functional roles do not imply automatic access permissions
* access permissions do not imply automatic functional ownership

## 6.6 Risks Block

### Deliverables

* risk register
* inherent/residual model
* treatment
* risk acceptance
* risk-control relationship

### Key Stories

* as an accountable owner, I want to assess impact and likelihood
* as a compliance manager, I want to associate mitigating controls
* as management, I want to see open residual risks

### Acceptance Criteria

* risks can be linked to assets, controls, and actions
* there is review history

## 6.7 Assets and Scope Block

### Deliverables

* basic asset inventory
* processes
* classification
* scopes

### Acceptance Criteria

* an assessment can be limited to a scope
* assets can have applicable regulations attached

## 6.8 Privacy Block

### Deliverables

* record of processing activities
* DPIA/PIA
* data subject rights
* data breaches

### Acceptance Criteria

* processing activities can be linked to controls and risks
* privacy incidents have their own workflow

## 6.9 Third Parties Block

### Deliverables

* vendor register
* due diligence
* third-party score
* evidence and questionnaires

## 6.10 Questionnaires Block

### Deliverables

* configurable forms
* conditional logic
* templates
* scoring

## 6.11 Reports and Dashboards Block

### Deliverables

* operational dashboard
* executive dashboard
* audit report
* PDF/CSV/JSON exports

## 6.12 Automation Block

### Deliverables

* rules engine
* reminders
* scheduled reassessments
* escalation of exceptions and expirations

## 6.13 Connectors Block

### Deliverables

* connector framework
* connector SDK
* scheduled jobs
* automated evidence
* connector health monitoring

## 7. High-Level Data Model

CORE base entities:

* Organization
* Scope
* Role
* Permission
* PrincipalReference
* MembershipReference
* FunctionalActorReference
* FunctionalAssignment
* Plugin
* PluginVersion
* PluginSetting
* AuditLog
* Attachment
* Notification
* Job
* EventSubscription
* LocaleSetting
* TranslationResource

Initial domain entities via plugins:

* User
* Team
* DomainActorProfile
* Framework
* Requirement
* Control
* ControlMapping
* Evidence
* Assessment
* AssessmentResult
* Finding
* ActionPlan
* Risk
* RiskTreatment
* Asset
* PolicyDocument
* Exception
* Questionnaire
* QuestionnaireResponse
* Vendor
* ProcessingActivity
* PrivacyIncident

## 8. Non-Functional Requirements

### 8.0 Internationalization

* primary product language: English
* official initial languages: English, Spanish, French, and German
* each language must be separated into independent files
* the core must have its own translation files
* each plugin must have its own translation files
* files must use simple and easy-to-manage formats such as JSON or similar
* core and plugin translations must follow a common structure and key convention
* translation keys must be stable and semantic
* the community must be able to correct texts by editing those translation files
* embedding literal text in business code or hard-to-maintain templates must be avoided

### 8.1 Security

* MFA
* SSO OIDC/SAML
* CSRF protection
* output escaping
* prepared statements
* signed file access
* secret encryption
* sensitive field encryption
* rate limiting
* multi-tenant segregation

### 8.2 Performance

* mandatory pagination
* queues for heavy processes
* caching of frequently used elements
* asynchronous connector jobs

### 8.3 Scalability

* initial modular monolith deployment
* support for separate workers
* object-compatible storage
* future possibility of separating services

### 8.4 Compatibility and Upgrades

* semver for the core
* semver for plugins
* each plugin must declare compatibility with core versions
* stable contracts
* deprecation with a defined schedule
* reversible migrations where possible

### 8.5 Observability

* structured logs
* metrics
* health checks
* basic traces

### 8.6 Quality

* minimum test coverage
* plugin contract tests
* upgrade tests
* permission tests

### 8.7 Repositories and Releases

* there is one main GitHub repository containing the CORE and the official base plugins
* the main repository is the source of truth for coordinated development
* each distributable plugin must also have its own GitHub repository
* each plugin repository is an independent distribution and release unit
* a plugin is developed first in the main repository, but when it is ready it must be publishable to its dedicated repository
* there must be a pipeline capable of splitting and publishing plugin code from the main repository to the dedicated repository
* this pipeline must generate a plugin-specific version tag
* plugin tags must later be used to publish plugin releases
* plugins must be publishable independently of the core if they pass compatibility and quality checks
* plugin releases must be traceable and auditable
* the relationship between the originating commit in the main repository and the plugin release must be reconstructible

## 9. Proposed Technical Architecture

### Recommended Stack

* PHP 8.x
* Laravel or Symfony
* PostgreSQL
* Redis
* MinIO/S3 for storage
* queue workers
* OpenAPI for the API
* Vue/React or Blade + components for the UI

### Recommended Approach

For startup speed and ecosystem support, **Laravel** is an especially strong option if the goal is to build quickly while having queues, jobs, events, policies, migrations, and a package ecosystem. Symfony is also solid if extreme decoupling is the priority.

## 10. Agile Roadmap by Milestones

## Milestone 0 — Discovery and Architecture

### Objective

Define the product, the core, and the plugin contracts.

### Deliverables

* validated PRD
* logical architecture
* initial ADRs
* entity map
* plugin manifest spec v1
* code conventions
* versioning strategy
* repository and release strategy
* permission strategy
* migration strategy

### Expected Outcome

Stable design baseline to start implementation.

## Milestone 1 — CORE Foundation

### Objective

Launch the executable core of the system.

### Deliverables

* application bootstrap
* multi-organization and scopes
* base permission engine
* audit log
* base file storage
* event bus
* initial plugin manager
* admin shell
* base API
* base internationalization and localization
* support for translation files owned by the core and by each plugin
* identity provider contract
* functional domain actor contract

### Definition of Done

* plugins can register
* sensitive operations generate audit logs
* the core supports multiple languages
* the core allows connecting a functional identity plugin
* the core allows referencing functional actors separately from access identity
* base translation files exist in English, Spanish, French, and German

## Milestone 2 — Basic Platform Plugins

### Objective

Enable the first essential capabilities as plugins on top of the core.

### Deliverables

* local identity plugin (users, teams, sessions)
* RBAC UI / membership administration plugin
* functional actors / ownership plugin
* base compliance plugin (frameworks, requirements, controls, mappings)
* basic assets plugin

### Definition of Done

* users can be created through the identity plugin
* the core permission engine works with the identity plugin
* functional owners can be assigned without mandatory login access
* a framework, requirements, and controls can be created
* mappings can be created and queried

## Milestone 3 — Evidence and Assessments

### Objective

Enable real verification and assessment campaigns.

### Deliverables

* evidence with hashing
* expiration and validation
* assessments
* basic workpapers
* conclusions
* summary export

### Definition of Done

* an assessment can be completed with evidence and conclusions
* the system generates evidence expiration alerts

## Milestone 4 — Findings, Actions, and Exceptions

### Objective

Close the operational loop for deviation and remediation.

### Deliverables

* findings
* action plans
* exceptions
* basic review workflows
* notifications

### Definition of Done

* a finding can be opened, assigned, and closed
* an exception can be approved and expire

## Milestone 5 — Risks

### Objective

Add the risk model to provide management context.

### Deliverables

* risk register
* inherent/residual assessment
* treatment
* acceptance
* risk-control relationship

### Definition of Done

* each risk can be linked to controls, assets, and plans

## Milestone 6 — SDK and Plugin Platform v1

### Objective

Open the product to serious external contribution.

### Deliverables

* plugin SDK
* contract documentation
* backend plugin example
* UI plugin example
* plugin test harness
* compatibility and installation validations
* plugin split/publish pipeline to dedicated repositories
* tagging and release traceability strategy per plugin

### Definition of Done

* a third party can develop a plugin without touching the core
* an official plugin can be published from the main repository to its dedicated repository
* the process generates a plugin version tag and preserves traceability to the originating commit

## Milestone 7 — Official Domain Plugins

### Objective

Cover the main functional blocks as decoupled plugins.

### Deliverables

* official Risk plugin
* official Privacy plugin
* official Vendor plugin
* official Questionnaire plugin
* official Reporting plugin

## Milestone 8 — Official Framework Packs

### Objective

Publish reusable framework packs.

### Deliverables

* ISO 27001 pack
* ENS pack
* NIS2 pack
* GDPR/LOPDGDD pack
* initial mappings between frameworks

### Notes

The packs must include controls, requirements, templates, notes, and base reports, but they must be versioned independently from the core.

## Milestone 9 — Connectors and Automation

### Objective

Provide automated technical evidence.

### Deliverables

* connector SDK
* Wazuh connector
* Fleet/osquery connector
* OpenSCAP connector
* GitHub/GitLab connector
* execution scheduler
* initial rules engine

## Milestone 10 — Hardening, Observability, and Community Readiness

### Objective

Make the product reliable for public adoption.

### Deliverables

* hardening review
* observability
* test matrix
* compatibility policy
* contribution guide
* plugin template
* public architecture documentation

## 11. Scrum / Agile Approach

### Recommended Cadence

* 2-week sprints
* demos at the end of each sprint
* weekly backlog grooming
* ADRs for relevant technical decisions

### Parallel Workstreams

* Product / domain
* Platform / core
* UI/UX
* Security
* Documentation / DX
* Official plugins

### Artifacts

* product backlog
* roadmap by milestones
* Definition of Ready
* Definition of Done
* ADR log
* semantic changelog

## 12. Initial Epics

### Epic A — Core Platform

Includes orgs, scopes, plugin manager, audit log, storage, API, UI shell, i18n/l10n, event bus, and permission engine.

### Epic B — Identity Plugin System

Includes the identity contract, local users plugin, teams, memberships, and sessions.

Current continuation after the first usable local identity baseline:

* `identity-local` remains mandatory as the administrative fallback
* platform authentication should use passwordless email link + token flows
* each organization may connect `local + one external directory provider`
* external directory support should start with a single connector path such as LDAP or AD
* directory provisioning should be mixed: scheduled sync plus manual sync
* externally synchronized users may remain unassigned until memberships or role grants are applied

### Epic C — Functional Actors & Ownership

Includes the functional actor model, ownership, and responsibility assignments decoupled from access permissions.

### Epic D — Compliance Domain Base

Includes frameworks, requirements, controls, mappings, and scopes.

### Epic E — Evidence & Assessments

Includes evidence, validation, expiration, and audits.

### Epic F — Findings & Remediation

Includes findings, actions, and exceptions.

### Epic G — Risk Management

Includes inherent/residual risk and treatment.

### Epic H — Plugin SDK

Includes contracts, scaffolding, and documentation.

### Epic I — Official Plugin Packs

Includes domain plugins and framework packs.

### Epic J — Connectors & Automation

Includes connectors and automated evidence.

## 13. Prioritized Initial Backlog

### P0

* multi-language support in the core
* support for translation files owned by the core and by each plugin
* organizations and scopes
* permission engine
* audit log
* minimal plugin manager
* identity contract
* functional actor contract
* local users plugin
* ownership / domain actors plugin
* frameworks
* controls
* requirements
* mappings
* evidence
* assessments

### P1

* findings
* action plans
* notifications
* repository and release strategy
* plugin publishing pipeline
* risks
* exceptions
* basic reports
* passwordless identity sessions through email link + token
* external directory connector with `local + one provider` coexistence
* mixed directory sync model with scheduled and manual synchronization
* unassigned external users until memberships and grants are applied

### P2

* privacy
* vendors
* questionnaires
* connectors
* rules engine
* advanced dashboards
* additional external identity connectors beyond the first LDAP/AD path

## 14. Global Product Acceptance Criteria

* the core works without optional plugins
* plugins can extend functionality without hacks against the core
* all sensitive operations leave an auditable trail
* compliance data has complete traceability
* the system allows data and report export
* there is sufficient documentation for third-party developers
* user-facing UI language is domain-first and avoids self-referential technical architecture terms such as `core` or `plugin`

## 15. Project Risks

### Technical Risks

* excessive coupling between core and plugins
* insufficient permission model
* incompatible migrations across versions
* unstable plugin API too early

### Product Risks

* trying to cover too many frameworks too early
* putting too much domain logic into the core
* building checklists instead of a traceable system
* UX that is too complex for SMBs and homelab users

### Mitigations

* minimalist core
* explicit contracts
* aggressively prioritized backlog
* official packs as architecture references

## 16. Recommended Strategic Decision

The recommendation is to build a **modular monolith with a formal plugin architecture** first, not microservices. This maximizes speed, operational simplicity, and external contribution. Decoupling should happen at the level of domains, contracts, events, and packages, not at the infrastructure level from day one.

## 17. Deliverable for Codex

This PRD should serve as the basis to immediately generate:

1. ADR-001 CORE + Plugins Architecture
2. ADR-002 Laravel/Symfony Stack
3. initial database schema
4. Plugin Manifest v1 specification
5. Event Bus v1 specification
6. Permission Model v1 specification
7. backlog of epics and stories in an implementable format
8. repository skeleton

## 18. Recommended Next Pieces

After this PRD, the next items that should be prepared are:

* initial domain schema and ERD
* repository and package structure
* technical contract for the plugin system
* backlog for the first 8-12 sprints
* community issue templates
