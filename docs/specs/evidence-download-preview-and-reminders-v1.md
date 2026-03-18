# Evidence Download, Preview, and Reminder Workflows v1

## Purpose

Deepen the evidence repository so that evidence follow-up is operationally usable, not only structurally governed.

## Goals

- download the backing artifact directly from the evidence detail view
- preview supported evidence files inline when storage is available
- surface a work queue for evidence that needs review or renewal soon
- queue review and expiry reminders for evidence owners
- support scheduled reminder generation from the console

## Main Additions

### Artifact Access

Evidence detail now exposes artifact actions when the backing file exists:

- `Preview artifact` for previewable media
- `Download artifact` for the original file

Supported preview media in v1:

- `text/*`
- `image/*`
- `application/pdf`
- `application/json`

If the stored file is missing, the evidence record still renders normally but preview and download actions are hidden.

### Reminder State

Evidence records now track reminder state separately for:

- review due reminders
- expiry soon reminders

These timestamps reset automatically if the relevant due date changes.

### Reminder Queue

The evidence library now includes a `Review and renewal queue` section showing records that need follow-up soon.

Queue criteria in v1:

- `review_due_on <= today + 30 days`
- `valid_until <= today + 30 days`

### Reminder Delivery

Reminder notifications can be queued in two ways:

- manually from evidence detail
- in bulk with `php artisan evidence:queue-reminders`

Initial delivery behavior:

- notifications are created as due notifications
- existing `notifications:dispatch-due` handles dispatch
- reminders target the evidence owner principal recorded on the evidence record

Owner selection in v1:

- `updated_by_principal_id`
- fallback to `created_by_principal_id`

## UI Changes

### Evidence Detail

- artifact preview/download actions
- reminder state panel
- `Queue review reminder`
- `Queue expiry reminder`

### Evidence Library

- review and renewal queue section
- existing metrics still show expiring and due records, but now the queue gives a usable follow-up list

## Console

New command:

```bash
php artisan evidence:queue-reminders --organization_id=org-a --scope_id=scope-eu
```

This queues reminders but does not dispatch them. Dispatch still runs through:

```bash
php artisan notifications:dispatch-due
```

## Out of Scope

- reminder templates configurable from UI
- recurring reminder policies
- email channel integration for evidence reminders
- document annotation and full browser document viewers

## Test Coverage

Required checks:

- evidence artifacts can be previewed and downloaded
- manual reminder queueing creates notifications
- console reminder queueing creates review and expiry notifications
- reminder routes preserve authorization expectations
