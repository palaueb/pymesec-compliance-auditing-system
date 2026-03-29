# System Enums vs Business-Managed Catalogues UI v1

## Status

Implemented.

## Goal

Make the UI explicit about which selectable values are:

- business-managed catalogues that admins can govern through `Reference catalogs`
- system-controlled enums or workflow values enforced by the application

## Problem

Several workspaces already used governed values, but the distinction was still implicit. That made it too easy to confuse:

- a business vocabulary such as `asset type`, `risk category`, or `policy area`
- a workflow or program value such as `active`, `review`, `approved`, or `assessment result`

## UI Rule

Affected screens now render a visible note near the top of the page clarifying the distinction for that workspace.

The note must:

- name at least one business-managed catalogue used on the screen
- name at least one system-controlled enum or workflow value used on the screen
- point business-managed vocabularies back to `Reference catalogs` when applicable

## Covered Screens

This UI clarification now appears on:

- `Asset Catalog`
- `Risk Register`
- `Findings Register`
- `Continuity Services`
- `Recovery Plans`
- `Data Flows Register`
- `Processing Activities`
- `Assessment Campaigns`
- `Policies Register`
- `Reference catalogs`

## Classification Used

### Business-managed catalogues

Examples:

- asset type, criticality, classification
- risk category
- finding severity
- continuity impact tier and dependency kind
- privacy transfer type and lawful basis
- policy area

These are part of business vocabulary and may be organization-specific.

### System-controlled values

Examples:

- workflow states such as `draft`, `review`, `active`, `approved`, `closed`
- assessment status and review result
- remediation action status
- continuity exercise and execution values used as program-controlled operational states

These remain application-governed and are not edited through `Reference catalogs`.

## Outcome

This reduces ambiguity in the product and makes it easier for operators to understand:

- what they can tailor
- what the platform enforces
- where to go when a business label needs to change
