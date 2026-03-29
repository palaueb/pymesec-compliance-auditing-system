# Title

Object Access Governance UI v1

# Status

Draft

# Context

The runtime already enforces object-level visibility through functional actor assignments. What was still missing was a dedicated governance surface to inspect and govern those matrices without jumping across multiple plugin screens or relying on implicit knowledge of functional-actor internals.

# Specification

## 1. Objectives

This slice defines:

- a dedicated governance screen for object access inspection and correction
- a clear read path from principal to linked actors to governed objects
- direct assignment and removal flows for object-scoped access

## 2. Scope

The screen lives under `core.object-access` inside the delegated governance workspace area and uses the existing functional assignment model.

It provides:

- principal inspection
- domain visibility summaries
- current object assignment inspection
- assignment create and deactivate actions

It does not introduce a separate authorization model.

## 3. Governance Model

The governance UI continues to rely on:

- `principal_functional_actor_links`
- `functional_assignments`
- object-level visibility resolution from `ObjectAccessService`

The UI is therefore an operational surface over the same model already used by workspace filtering.

## 4. Permission Rules

The screen is governed by the same functional-actor permissions already used to manage ownership:

- view access requires `core.functional-actors.view`
- mutation requires `core.functional-actors.manage`

## 5. UX Rules

The screen must make three things explicit:

1. which actors are linked to a selected principal
2. whether each domain is in `scoped` mode or still in `broad fallback`
3. which actors currently own or review a selected object

The screen should keep the assign/remove workflow close to the inspection workflow so administrators can correct the matrix without leaving the page.

# Consequences

- object-level access becomes governable from a single workspace governance surface
- platform operators no longer need to infer visibility only from plugin detail screens
- the product moves closer to a fully explicit access-governance story without touching LDAP yet
