# Third-Party Risk Vendor Review Workspace v1

## Goal

Define the first competitive third-party risk product slice for Pymesec.

This slice should turn the existing governance, evidence, findings, workflow, and reporting baseline into a usable vendor review loop:

`vendor intake -> inherent risk -> questionnaire / evidence request -> review -> decision -> follow-up`

## Product Position

This first slice is not a procurement suite and not a full supplier master-data replacement.

It is a focused security/compliance vendor review workspace designed to compete on:

- clear review flow
- secure external collaboration
- evidence traceability
- findings and remediation continuity
- repeatable periodic reassessment

## Scope

### 1. Vendor Register

The product needs a dedicated vendor register with:

- vendor legal name
- commercial or short name
- owner
- requester
- business service summary
- primary external contacts
- status:
  - prospective
  - under review
  - approved
  - approved with conditions
  - rejected
  - inactive

The register should clearly distinguish:

- prospective vendors not yet approved
- active vendors already onboarded

### 2. Inherent Risk / Criticality Intake

Every vendor review starts with a small intake used for tiering.

v1 intake should capture:

- service category
- data sensitivity involved
- production or privileged access
- network/system integration depth
- regulatory or privacy exposure
- business criticality / continuity impact

The output should produce:

- a tier such as `low / medium / high / critical`
- a rationale snapshot
- the review profile to apply next

The tier should drive:

- which questionnaire template to use
- which evidence requests are mandatory
- review depth
- reassessment cadence

### 3. Vendor Review Workspace

The product needs one workspace per vendor review.

The review workspace should keep together:

- overview
- tiering snapshot
- questionnaire state
- collaboration comments
- follow-up requests
- evidence requests
- uploaded artifacts and promoted evidence
- findings raised from the review
- approval / rejection decision
- timeline / audit trail

This workspace should be the main operational surface for vendor review, not the vendor list.

### 4. Questionnaire Layer

The review must support questionnaire-based collection.

v1 should allow:

- choosing a questionnaire template from the review profile
- sending the questionnaire externally or completing it internally
- capturing answers per question
- attaching supporting documents
- tracking status:
  - draft
  - sent
  - submitted
  - under review
  - accepted
  - needs follow-up

Answers should remain review-bound, even if a later answer library is introduced.

The current implementation slice starts with internal questionnaire items bound directly to one review:

- prompt
- response type
- response status
- answer text
- follow-up notes

This is intentionally narrower than a full template engine, but it keeps the review contract stable for later external collection modes.

The current implementation now also includes:

- persisted review profiles
- persisted questionnaire templates linked to one review profile
- template items that can be copied into a concrete review without duplicating already-applied template questions
- review-level selection of profile and template on create and edit
- section titles on questionnaire template items and review-bound questionnaire items
- grouped questionnaire rendering in both the internal workspace and the external vendor portal
- questionnaire engine and storage now provided by the transversal `questionnaires` plugin instead of being owned directly by `third-party-risk`
- opt-in answer library suggestions for reusable reviewer responses on questionnaire items
- dedicated internal review / accept actions on questionnaire responses with review notes and reviewer metadata
- brokered collection requests for off-platform answer gathering without granting external portal access
- item-level attachment requirements with explicit upload profiles and optional promotion of questionnaire attachments into governed evidence
- reassessment-focused register filters for:
  - decision pending
  - due soon
  - overdue

### 5. Evidence and Artifact Handling

The vendor review must support both:

- raw review artifacts
- promoted governed evidence records

Examples:

- SOC 2 report
- ISO certificate
- penetration test summary
- security policy excerpts
- questionnaire attachments
- business continuity or privacy documents

v1 should keep a clear distinction:

- artifacts are uploaded source files or attachments
- evidence is governed and reusable where the operator promotes it

### 6. Findings and Remediation

The review should be able to raise:

- findings
- actions
- follow-up requests

These should link directly into the existing findings/remediation model wherever possible.

The vendor review should not invent a second issue-management subsystem if the current one is already sufficient.

The current implementation now also includes:

- linked-finding continuity inside the vendor review workspace
- rendering of the linked finding summary inside the review
- rendering of linked remediation actions inside the same workspace so follow-up can be reviewed without leaving the vendor review
- record comments rendered as a first-class collaboration section inside the review
- follow-up requests rendered inside the same workspace with status, priority, handoff stage, actor assignment, and due date
- a first-class timeline/activity section in the review workspace
- unified rendering of review creation, workflow transitions, questionnaire updates, evidence uploads, external collaboration events, and collaboration events

### 7. Decision and Approval

Every review should end in an explicit decision.

Minimum outcomes:

- approved
- approved with conditions
- rejected

The decision record should preserve:

- approver
- date/time
- rationale
- required follow-ups
- next review due date where applicable

### 8. Periodic Reassessment

Approved vendors should support scheduled reassessment.

v1 should allow:

- review cadence by tier
- due-soon and overdue indicators
- reopening a new review cycle for an approved vendor
- preserving prior review history

## Secure External Collaboration

External vendor participation is a first-class supported mode.

However, the external access model must remain:

- explicit
- object-scoped
- revocable
- auditable

External vendor users should only see:

- the review they were invited to
- the questionnaire assigned to them
- evidence requests explicitly shared with them
- findings or actions explicitly exposed for follow-up

They must not receive implicit access to:

- the internal asset inventory
- unrelated risks
- unrelated findings
- other vendor records

The current implementation slice now includes:

- external review links bound to one concrete vendor review
- expirable and revocable token-based portal access
- explicit per-link capabilities for:
  - questionnaire answer submission
  - artifact upload
- two delivery modes for new links:
  - manual-only link copy
  - outbound email invitation at issue time through the organization SMTP connector
- per-link delivery state tracking:
  - manual-only
  - sent
  - failed
  - not-configured
- public routes that expose only the shared review portal, not the internal workspace
- audit events for issue, access, questionnaire submission, artifact submission, and revocation

## Information Architecture

The product should separate these surfaces:

### 1. Vendor List

Purpose:

- browse vendors
- filter by status, tier, owner, review state
- open a vendor or open the active review

### 2. Vendor Review Workspace

Purpose:

- perform one concrete review
- exchange questionnaires and documents
- raise findings
- record a decision

### 3. Management Reporting

Purpose:

- summarize vendor review load and exposure
- not replace the review workspace itself

## Suggested v1 Data Model

The exact schema can evolve, but the product needs concepts equivalent to:

- `vendors`
- `vendor_contacts`
- `vendor_reviews`
- `vendor_review_tiering_snapshots`
- `vendor_questionnaire_assignments`
- `vendor_questionnaire_answers`
- `vendor_review_artifacts`
- links from vendor review to:
  - evidence
  - findings
  - remediation actions

The key principle is:

- vendor identity and lifecycle are separate from one specific review instance

## Recommended First Implementation Slice

The first implementation slice should be:

1. vendor register
2. prospective vendor intake
3. tiering result
4. vendor review workspace shell
5. internal-only questionnaire capture
6. document request / upload
7. review decision

External portal flows can follow immediately after this slice, but the internal review contract should exist first.

## Non-Goals

This v1 does not need:

- procurement approvals
- contracts repository
- payment or spend analytics
- external intelligence feeds
- risk exchange networks
- AI-generated answers or AI review decisions

## Dependencies

This slice depends on:

- evidence and artifact handling
- secure upload validation rules
- external access model
- notification/reminder flows
- findings/remediation integration

## Consequences

- Pymesec gets a strong first product wedge beyond internal compliance management.
- The current core and plugin baseline gets reused instead of bypassed.
- The platform can compete on a clean vendor-review loop before expanding into broader questionnaires, automation, and external collaboration patterns.
