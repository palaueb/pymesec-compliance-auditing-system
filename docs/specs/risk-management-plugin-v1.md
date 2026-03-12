# Title

Risk Management Plugin v1

# Status

Draft

# Context

The PRD requires the platform to provide a risk register, risk acceptance, and traceability between risks, controls, assets, and evidence. These capabilities belong in plugins, not in the core. The core already provides the cross-cutting substrate needed for a first risk plugin:

- tenancy and scope context
- functional actors and assignments
- authorization
- workflow
- audit trail
- event bus
- notifications
- artifacts and attachments

# Scope

This v1 plugin defines the smallest useful risk-management module for the platform.

It should provide:

- a tenant-aware risk register
- inherent and residual risk scoring fields
- functional owner resolution through actor assignments
- a minimal workflow for assessment and acceptance
- artifact attachment through the core artifact subsystem
- traceability hooks to related assets and controls

It does not yet provide:

- quantitative scoring engines
- questionnaires
- advanced treatment plans
- risk matrix visualizations
- exception linkage
- residual trend analytics

# Minimal Model

Each risk record in v1 should have at least:

- stable risk identifier
- title
- category
- inherent score
- residual score
- linked asset reference
- linked control reference
- treatment summary
- organization and optional scope context

Ownership is not stored as an access role. Ownership is resolved from functional assignments through the core actor model.

# Workflow

The plugin should expose a minimal workflow such as:

- `identified`
- `assessing`
- `treated`
- `accepted`

Transitions remain plugin-defined and use the core workflow engine.

# UI

The plugin should contribute:

- a top-level risk register menu
- a secondary board or lifecycle view
- shell-native screens only

# Consequences

- The platform gets a second functional domain plugin that meaningfully exercises the core architecture.
- Risks can be linked to assets, controls, actors, workflow, and evidence without changing the core.
- The project gains a reusable pattern for future plugins such as findings, exceptions, and action plans.
