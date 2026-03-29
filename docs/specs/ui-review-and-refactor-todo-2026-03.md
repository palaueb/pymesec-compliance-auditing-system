# UI Review and Refactor TODO 2026-03

## Purpose

Track the full usability review and refactor of the application UI following:

- `master-detail-navigation-and-governed-reference-data-v1.md`
- `support-catalog-v1.md`
- the product rule that the app must speak in business terms, not internal architecture terms

This document is the execution backlog for reviewing every current page and the main object types handled by the platform.

## Review Criteria

Each page and object must be checked against these questions:

1. Is the page an `index`, a `detail`, or a `governance` page?
2. Does the page mix browsing and full maintenance in the same table row?
3. Are child records being edited from the list instead of from the parent detail page?
4. Are governed values being entered as free text?
5. Are relationships using proper selectors and linked objects?
6. Does the page expose one clear primary action?
7. Is the copy understandable to a non-technical business user?

## Cross-Cutting TODO

- [x] Add first-class support in the shell for contextual detail pages without polluting the main sidebar navigation.
- [ ] Define a reusable page contract for `index`, `detail`, and `governance` screens.
- [ ] Standardize `Open` / `View details` as the main action from list pages.
- [ ] Remove child collection maintenance from list rows across the product.
- [ ] Normalize governed fields so reporting and filtering do not depend on free text.
- [ ] Define return-to-origin flows for `create related record` actions.

## Governed Reference Data TODO

### Shared

- [ ] Introduce managed vocabularies for business fields that are currently free text.
- [ ] Distinguish system workflow enums from business catalogues.
- [ ] Add selectors or lookup widgets for all governed references.

### Asset Domain

- [x] Govern `asset type`
- [x] Govern `asset criticality`
- [x] Govern `asset classification`
- [x] Replace `owner_label` editing with actor assignments
- [ ] Support multiple accountable parties for assets

### Continuity Domain

- [x] Govern `impact tier`
- [x] Govern dependency kinds
- [ ] Govern exercise types and execution types if they remain business-level concepts
- [ ] Replace single owner semantics with assignment-based ownership where needed

### Assessment Domain

- [ ] Govern assessment results as system values
- [ ] Govern finding severity via existing controlled values
- [ ] Keep requirements and controls linked by object, not by copied text

### Privacy Domain

- [x] Govern `transfer type`
- [x] Govern `lawful basis`
- [ ] Review linked object fields for multi-select governance where needed

## Execution Batches

### Batch 1: Highest Pain / Highest Leverage

- [x] `plugins/continuity-bcm/resources/views/plans.blade.php`
- [ ] `plugins/asset-catalog/resources/views/catalog.blade.php`
- [ ] `plugins/assessments-audits/resources/views/index.blade.php`
- [x] shell support for contextual detail navigation

### Batch 2: Domain Register Cleanup

- [ ] `plugins/controls-catalog/resources/views/catalog.blade.php`
- [ ] `plugins/risk-management/resources/views/register.blade.php`
- [ ] `plugins/findings-remediation/resources/views/register.blade.php`
- [ ] `plugins/policy-exceptions/resources/views/register.blade.php`
- [ ] `plugins/data-flows-privacy/resources/views/register.blade.php`
- [ ] `plugins/continuity-bcm/resources/views/register.blade.php`

### Batch 3: Secondary Domain Views

- [ ] `plugins/controls-catalog/resources/views/reviews.blade.php`
- [ ] `plugins/risk-management/resources/views/board.blade.php`
- [ ] `plugins/findings-remediation/resources/views/board.blade.php`
- [ ] `plugins/policy-exceptions/resources/views/exceptions.blade.php`
- [ ] `plugins/data-flows-privacy/resources/views/activities.blade.php`
- [ ] `plugins/actor-directory/resources/views/assignments.blade.php`
- [ ] `plugins/asset-catalog/resources/views/lifecycle.blade.php`

### Batch 4: Identity and Administration

- [ ] `plugins/identity-local/resources/views/users.blade.php`
- [ ] `plugins/identity-local/resources/views/memberships.blade.php`
- [ ] `plugins/identity-ldap/resources/views/directory.blade.php`
- [ ] `core/resources/views/tenancy.blade.php`
- [ ] `core/resources/views/roles.blade.php`
- [ ] `core/resources/views/plugins.blade.php`
- [ ] `core/resources/views/functional-actors.blade.php`

### Batch 5: Review-Only / Stable Pages

- [ ] `core/resources/views/dashboard.blade.php`
- [ ] `core/resources/views/support.blade.php`
- [ ] `core/resources/views/platform-overview.blade.php`
- [ ] `core/resources/views/permissions.blade.php`
- [ ] `core/resources/views/audit.blade.php`
- [ ] `plugins/identity-local/resources/views/login.blade.php`
- [ ] `plugins/identity-local/resources/views/verify.blade.php`
- [ ] `plugins/identity-local/resources/views/setup.blade.php`

## Page-by-Page TODO

### Core Shell

File:
- `core/resources/views/shell.blade.php`

Issues:
- no first-class contextual detail navigation contract
- too much responsibility in one shared layout
- action hierarchy is still too flat in some cases

TODO:
- [x] support `index -> detail` flows cleanly in shell navigation
- [x] support contextual back links to parent list pages
- [ ] standardize placement of page-level primary actions

### Dashboard

Files:
- `core/resources/views/dashboard.blade.php`

Issues:
- needs eventual deeper personalization by owned objects and responsibilities

TODO:
- [ ] keep as review item after detail pages exist
- [ ] re-evaluate dashboard cards once object-level detail pages are in place

### Support

Files:
- `core/resources/views/support.blade.php`

Issues:
- support content should reflect the new navigation model

TODO:
- [ ] update help entries as each module moves to master-detail

### Tenancy

Files:
- `core/resources/views/tenancy.blade.php`

Issues:
- still partially list-plus-edit
- organizations and scopes should eventually have cleaner detail/governance split

TODO:
- [ ] keep as governance page, not operational list page
- [ ] simplify row editing once governance patterns are standardized

### Roles / Permissions / Plugins / Functional Actors

Files:
- `core/resources/views/roles.blade.php`
- `core/resources/views/plugins.blade.php`
- `core/resources/views/functional-actors.blade.php`
- `core/resources/views/permissions.blade.php`

Issues:
- still heavy in inline forms
- governance pages need a dedicated pattern, different from operational pages

TODO:
- [ ] define `governance page` interaction pattern
- [ ] keep dense admin operations out of main application UX

### Asset Catalog

Files:
- `plugins/asset-catalog/resources/views/catalog.blade.php`
- `plugins/asset-catalog/resources/views/lifecycle.blade.php`

Issues:
- creation form always visible
- row-level edit forms inline
- `type`, `criticality`, `classification` are free text
- `owner_label` is a legacy free-text field
- ownership model is too narrow for real accountability

TODO:
- [ ] move asset editing to `Asset Detail`
- [ ] keep list page focused on browse/filter/open
- [ ] replace governed business fields with managed reference data
- [ ] replace `owner_label` editing with actor assignments
- [ ] support multiple owners / accountability assignments

### Controls Catalog

Files:
- `plugins/controls-catalog/resources/views/catalog.blade.php`
- `plugins/controls-catalog/resources/views/reviews.blade.php`

Issues:
- frameworks, requirements, mappings, artifacts, transitions, and edit forms all compete on one page

TODO:
- [ ] split `Control List` from `Control Detail`
- [ ] keep `Framework Governance` and `Requirement Governance` as dedicated sections or screens
- [ ] move artifacts and requirement mappings to control detail

### Risk Management

Files:
- `plugins/risk-management/resources/views/register.blade.php`
- `plugins/risk-management/resources/views/board.blade.php`

Issues:
- register still includes creation and edit clutter
- lifecycle actions and maintenance are mixed

TODO:
- [ ] move risk maintenance to `Risk Detail`
- [ ] keep board focused on workflow and state

### Findings and Remediation

Files:
- `plugins/findings-remediation/resources/views/register.blade.php`
- `plugins/findings-remediation/resources/views/board.blade.php`

Issues:
- findings, artifacts, actions, transitions, and edit forms are crowded together

TODO:
- [ ] move action management and evidence to `Finding Detail`
- [ ] keep register as browse/filter/open
- [ ] keep board focused on workflow state

### Policy Exceptions

Files:
- `plugins/policy-exceptions/resources/views/register.blade.php`
- `plugins/policy-exceptions/resources/views/exceptions.blade.php`

Issues:
- policies and exceptions still carry too many inline forms

TODO:
- [ ] move exception management to policy detail or exception detail as appropriate
- [ ] simplify list pages

### Privacy

Files:
- `plugins/data-flows-privacy/resources/views/register.blade.php`
- `plugins/data-flows-privacy/resources/views/activities.blade.php`

Issues:
- records still mix browse and maintenance

TODO:
- [ ] move attachments and advanced maintenance to detail pages
- [ ] keep list pages focused on record navigation

### Continuity Services

Files:
- `plugins/continuity-bcm/resources/views/register.blade.php`

Issues:
- dependencies and artifacts are still edited from the list

TODO:
- [ ] move service dependencies and artifacts to `Continuity Service Detail`
- [ ] keep service register focused on service summaries

### Recovery Plans

Files:
- `plugins/continuity-bcm/resources/views/plans.blade.php`

Issues:
- evidence, exercises, test runs, transitions, creation, and edit are embedded in the list
- this is the clearest example of list rows acting as full workspaces

TODO:
- [x] create `Recovery Plan Detail`
- [x] move evidence, exercises, test runs, workflow transitions, linked records, and edit form there
- [x] keep plan list as summaries plus `Open`

Status:
- [x] Detail-first implementation in place

### Assessments and Audits

Files:
- `plugins/assessments-audits/resources/views/index.blade.php`

Issues:
- checklist work, findings, workpapers, export, and edit all exist inside the list

TODO:
- [ ] create `Assessment Detail`
- [ ] move checklist reviews, workpapers, linked findings, and summary actions to detail
- [ ] keep campaign list focused on perimeter and result summary

### Actor Directory

Files:
- `plugins/actor-directory/resources/views/directory.blade.php`
- `plugins/actor-directory/resources/views/assignments.blade.php`

Issues:
- assignments should align with the new ownership and relationship model

TODO:
- [ ] review once asset and continuity ownership refactor is underway

### Identity Local / LDAP

Files:
- `plugins/identity-local/resources/views/users.blade.php`
- `plugins/identity-local/resources/views/memberships.blade.php`
- `plugins/identity-ldap/resources/views/directory.blade.php`

Issues:
- some inline editing remains acceptable for admin tasks, but still needs consistency
- identities and memberships are different concerns and should stay visually separated

TODO:
- [ ] standardize admin/governance editing pattern
- [ ] reduce visual clutter where multiple admin forms compete

## Start Order

1. Recovery plan detail
2. Asset detail plus governed asset fields
3. Assessment detail
4. Control detail

## Current Start

This review starts with:

- documenting the full refactor backlog
- implementing `Recovery Plan Detail` as the first real master-detail conversion
