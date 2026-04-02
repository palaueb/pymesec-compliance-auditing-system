# Collaboration Layer v1

## Goal

Introduce a transversal collaboration capability that domain plugins can reuse instead of each plugin inventing its own local comments or follow-up task model.

This first slice is intentionally narrow and operational:

- shared drafts for continuity between users
- record comments
- follow-up requests / task objects
- assignment to functional actors
- handoff states between review, remediation, approval, and closed-loop follow-up
- mention / assignment cues bound to functional actors linked to the current principal
- timeline visibility through audit events

## Architecture Boundary

The core keeps only the shared extension contracts:

- `core/src/Collaboration/Contracts/CollaborationEngineInterface.php`
- `core/src/Collaboration/Contracts/CollaborationStoreInterface.php`

The concrete implementation lives in the transversal plugin:

- `plugins/collaboration/plugin.json`
- `plugins/collaboration/src/CollaborationPlugin.php`
- `plugins/collaboration/src/CollaborationEngine.php`
- `plugins/collaboration/src/CollaborationStore.php`

This matches the same architectural pattern already used for `questionnaires`:

- core defines the contract
- the transversal plugin implements the reusable capability
- domain plugins consume it

## Generic Data Model

This slice introduces three generic tables:

- `collaboration_drafts`
- `collaboration_comments`
- `collaboration_requests`

Both are scoped by:

- `owner_component`
- `subject_type`
- `subject_id`
- `organization_id`
- optional `scope_id`

That makes the model reusable for:

- vendor reviews
- future assessment workspaces
- future privacy or continuity records
- future external collaboration follow-up surfaces

### Collaboration Draft

A collaboration draft stores:

- draft type (`comment` or `request`)
- optional title
- body or details
- request-oriented priority / handoff metadata when relevant
- optional mentioned actors
- optional assigned actor
- optional due date
- last editor principal
- timestamps

This is the continuity primitive that allows one user to start a collaboration record and another user to finish or publish it later.

### Collaboration Comment

A collaboration comment stores:

- author principal
- body
- optional mentioned actors
- timestamps

This is the lightweight discussion primitive.

### Collaboration Request

A collaboration request stores:

- title
- details
- status
- priority
- handoff state
- optional mentioned actors
- assigned actor
- requester principal
- due date
- completion / cancellation timestamps

This is the first transversal follow-up object.

## Current Status Model

Request statuses:

- `open`
- `in-progress`
- `waiting`
- `done`
- `cancelled`

Request priorities:

- `low`
- `normal`
- `high`
- `urgent`

Handoff states:

- `review`
- `remediation`
- `approval`
- `closed-loop`

## First Consumer

The first consumer is `third-party-risk`.

Vendor review now uses the collaboration plugin for:

- shared drafts that can later be promoted into published comments or follow-up requests
- subject-bound comments
- subject-bound follow-up requests
- handoff-stage tracking on follow-up requests
- mention cues and assignment cues based on functional actor linkage
- actor-bound follow-up ownership
- audit-backed activity timeline entries for collaboration events

## Product Effect

The product now has its first reusable collaboration primitive instead of keeping follow-up discussion purely implicit in notes, findings, or external communication.

This does not yet complete the whole collaboration backlog.

Still missing after this slice:

- generalized external collaborator lifecycle beyond vendor review links
