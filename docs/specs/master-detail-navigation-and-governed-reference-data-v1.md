# Master-Detail Navigation and Governed Reference Data v1

## Purpose

Define a consistent interaction model for the application so users can understand:

- where to browse records
- where to edit a record
- where to manage related records
- which values are controlled by the system and which are free-form

This specification addresses two recurring usability problems:

1. list pages contain too many forms and actions
2. business-critical fields are stored as arbitrary text instead of governed references

## Product Rule

The application must follow a master-detail model.

- `index` pages are for browsing, filtering, and choosing records
- `detail` pages are for editing and maintenance of one selected record
- related child collections must be managed inside the detail page of the parent record
- list rows must not become full maintenance workspaces

## Interaction Model

### 1. Index Pages

An index page should contain:

- title and short explanation
- filters and search
- compact summary metrics when useful
- a primary action to create a new top-level record
- a list or table of records with compact status information
- one clear action to open the detail page

An index page must not contain:

- subtables for related records
- full maintenance forms for child collections
- multiple unrelated forms inside the same row
- dense action clusters that mix edit, workflow, attachments, and creation

### 2. Detail Pages

A detail page is the maintenance workspace for one record.

It should contain:

- record summary
- editable fields for the selected object
- workflow state and allowed transitions
- related child sections
- linked objects
- evidence or attachments if the object supports them
- activity or history where relevant

Each child section may have its own:

- table or cards
- create action
- edit action
- secondary workflow

This is acceptable in a detail page because the user is already in the context of one parent object.

### 3. Related Records

If a record has a one-to-many or many-to-many relationship with operational importance, that relationship must be managed from the parent detail page.

Examples:

- `Recovery Plan detail`
  - evidence
  - exercises
  - test runs
  - linked policy
  - linked finding
  - owners

- `Assessment detail`
  - checklist reviews
  - workpapers
  - linked findings
  - conclusions

- `Control detail`
  - linked requirements
  - evidence
  - review history
  - owners

The related records must not be embedded in the top-level list view of the parent collection.

### 4. Cross-Page Linking Flow

When a detail page needs a related object that does not yet exist, the UI should support:

1. `link existing`
2. `create new`
3. return to the original detail page with the newly created record preselected or linked

This avoids forcing users to open a separate module, lose context, and manually reconstruct the relationship.

## Governed Reference Data

## Rule

Any field that drives filtering, reporting, workflow, grouping, access, or governance must not be an arbitrary text field.

It must be one of:

- a reference to another object
- a governed option from a managed catalogue
- a constrained enumeration

Free text is only valid for:

- notes
- summaries
- descriptions
- rationale
- comments
- evidence annotations

## Anti-Pattern

The following pattern is not acceptable:

- the list shows a normalized business concept
- the edit form exposes a free-text field for the same concept
- different users can type different spellings for the same thing

This creates reporting chaos, broken filtering, and inconsistent workflow behavior.

## Asset Catalog Example

The current `Asset Catalog` illustrates the problem clearly.

Current issues:

- `Owner` in the list can come from actor assignment or free text
- the edit form exposes `Owner label` as arbitrary text
- `Type` is free text
- `Criticality` is free text
- `Classification` is free text
- `Owner actor` allows one linked actor but the model should support multiple accountable parties

Target model:

- `owners` must be managed as assignments to functional actors, not as free text labels
- `asset type` must come from a governed asset type catalogue
- `criticality` must come from a governed criticality scale
- `classification` must come from a governed classification scheme
- the list must show resolved labels from those references
- the detail page must use selectors, not raw text inputs, for governed values

`owner_label` may remain only as a legacy compatibility field during migration, not as the target editing model.

## Reference Data Categories

### 1. Object References

These point to real managed records.

Examples:

- owner actors
- linked policies
- linked findings
- linked controls
- linked requirements
- organization
- scope

These should use selectors, lookup dialogs, or relation pickers.

### 2. Governed Business Catalogues

These are managed option sets that behave like controlled vocabularies.

Examples:

- asset type
- asset classification
- asset criticality
- continuity exercise type
- assessment result
- finding severity

These should be editable only from their own governance location, not ad hoc from arbitrary record forms.

### 3. Workflow Enumerations

These are system-controlled values.

Examples:

- draft
- active
- closed
- approved
- retired

These must not be raw editable text fields.

## Ownership Model

Ownership and accountability must not be modeled as a single display label if the business concept can involve multiple parties.

The platform must support:

- zero, one, or many owner assignments
- assignment type where relevant
- actor-based ownership using functional actors

Examples of assignment types:

- accountable
- operational owner
- reviewer
- backup owner

The list view may show a compact owner summary, but the detail page is where ownership is managed.

## UI Contract

### List Rows

List rows should expose at most:

- `Open`
- one secondary action when essential, such as a simple transition

Everything else should move into the detail page.

### Detail Pages

Detail pages may expose:

- tabs or sections
- child tables
- create forms for related children
- workflow actions
- attachment actions

The user should always understand the parent object they are working on.

### Action Hierarchy

At any one visible level:

- one primary action
- a small number of secondary actions
- advanced actions grouped separately

## Implementation Guidance

### Phase 1

Adopt the master-detail structure for the heaviest pages:

1. `Recovery Plans`
2. `Assessments`
3. `Controls`
4. `Assets`

### Phase 2

Replace free-text business fields with governed references:

1. assets
2. continuity
3. assessments
4. risks and findings where needed

### Phase 3

Add return-to-origin linking flows for related record creation.

Examples:

- create a functional actor while editing an asset
- create a finding while reviewing an assessment control
- create a policy while editing a recovery plan

## Acceptance Criteria

- No top-level list page contains child-collection maintenance for the row item.
- Any record with evidence, history, child rows, or linked records has a dedicated detail page.
- Fields used for governance, reporting, or filtering are references or governed options, not arbitrary text.
- Ownership is modeled as assignments, not as a single editable label.
- New related records can be created without losing the context of the page that required them.

## Immediate Refactor Notes

### Asset Catalog

Must be refactored so that:

- `New Asset` moves behind a button
- row edit form moves to a dedicated detail page
- owners become actor assignments
- type, criticality, and classification become governed values

### Recovery Plans

Must be refactored so that:

- the list only shows plan summaries
- evidence, exercises, test runs, linked policy, linked finding, owners, and transitions move to plan detail

### Assessments

Must be refactored so that:

- the list shows campaign summaries
- checklist review work happens in assessment detail
- workpapers and findings are managed from assessment detail
