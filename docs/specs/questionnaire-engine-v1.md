# Questionnaire Engine v1

## Goal

Extract the reusable questionnaire capability out of `third-party-risk` so future product areas can reuse the same questionnaire semantics through a transversal plugin.

This slice now moves both:

- the shared engine layer
- the first shared storage/template layer
- the first brokered collection layer

- response types and statuses
- answer validation rules
- section grouping for rendering
- shared questionnaire templates
- shared subject-bound questionnaire items

## Scope

The core keeps only extension contracts:

- `core/src/Questionnaires/Contracts/QuestionnaireEngineInterface.php`
- `core/src/Questionnaires/Contracts/QuestionnaireStoreInterface.php`

The implementation now lives in the transversal plugin:

- `plugins/questionnaires/plugin.json`
- `plugins/questionnaires/src/QuestionnairesPlugin.php`
- `plugins/questionnaires/src/QuestionnaireEngine.php`
- `plugins/questionnaires/src/QuestionnaireStore.php`

## Current Consumers

The first consumer remains `third-party-risk`.

That plugin now uses the questionnaires plugin for:

- validation of response types and statuses
- validation of external questionnaire answers
- label rendering for response types and statuses
- grouping questionnaire items into sections
- questionnaire template lookup
- questionnaire template item lookup
- review-bound questionnaire item storage
- template application into one concrete review

The questionnaires plugin stores its first generic records in:

- `questionnaire_templates`
- `questionnaire_template_items`
- `questionnaire_subject_items`
- `questionnaire_brokered_requests`

`third-party-risk` still owns vendor review profiles and review workflow semantics, but it no longer owns the questionnaire engine or questionnaire persistence.

## Current Limits

This extraction still does not yet provide:

- first-class UI outside the consuming domain plugins

Those remain later slices.

The plugin now also supports first review metadata on subject items:

- `review_notes`
- `reviewed_by_principal_id`
- `reviewed_at`

and a transversal review action for one subject-bound item.

The plugin now also supports first brokered collection records:

- brokered contact
- collection channel
- broker principal
- collection status
- broker notes
- lifecycle timestamps for requested, started, submitted, completed, and cancelled

The plugin now also supports first attachment semantics on questionnaire items:

- attachment mode per template item and subject item
- explicit artifact upload profile per item
- item-bound attachments stored on `questionnaire-subject-item`
- optional evidence-promotion eligibility per item

## Product Effect

The product now has one reusable questionnaire plugin instead of `third-party-risk` owning:

- the response type list
- the response status list
- external answer validation logic
- section grouping rules
- questionnaire template storage
- review-bound questionnaire item storage
- answer library storage and reuse hints
- brokered collection request storage
- attachment semantics and evidence-promotion flags for questionnaire items

That is the minimum useful boundary before broader reuse in:

- vendor reviews
- internal self-assessments
- future brokered questionnaires
- future external collaboration flows outside vendor risk
