# Title

Support Catalogue v1

# Status

Draft

# Context

PymeSec needs one support page that explains the application in practical terms for everyday users, while still fitting the modular architecture. The content must not live only in one handwritten core page because concepts are introduced by plugins and should remain removable with plugin lifecycle.

The platform therefore needs a support catalogue model where:

- the core renders one support experience
- the core contributes its own foundational concepts
- enabled plugins can contribute support content in a structured format
- the final page always includes a full index of all declared concepts

# Specification

## 1. Objectives

The support system must provide:

- a practical usage guide
- a complete concept index
- a reference entry for each concept
- visible relationships between concepts

## 2. Source Model

Support content is contributed through JSON documents.

Sources:

- `core/resources/support/<locale>.json`
- `plugins/<plugin-id>/resources/support/<locale>.json`

Plugin support files must be declared in the plugin manifest.

## 3. Manifest Declaration

Plugins may declare:

- `support.path`
- `support.supported_locales`

If a plugin does not declare support metadata, the core must ignore support content for that plugin.

## 4. Document Structure

Each support JSON document may declare:

- `guide`
- `concepts`

### 4.1 `guide`

Guide entries are short narrative sections intended for ordinary users.

Each guide entry should declare:

- `id`
- `title`
- `summary`
- `body` as an array of short paragraphs or steps
- `concept_ids`
- `order`

### 4.2 `concepts`

Concept entries are the reference layer.

Each concept entry should declare:

- `id`
- `label`
- `category`
- `summary`
- `why_it_exists`
- `how_to_use`
- `relations`
- `order`

Each relation should declare:

- `type`
- `target`
- optional `summary`

## 5. Aggregation Rules

The runtime must aggregate:

- core concepts
- concepts from enabled and booted plugins only

Rules:

- the final support page must always render a complete index of all aggregated concepts
- duplicate concept ids should be treated as a catalogue issue
- the page may still render if issues exist, but issues must be visible

## 6. Locale Rules

The runtime should load the requested locale first and fall back to English if that locale is not available for a given support source.

## 7. UI Expectations

The support screen must render at least:

- a concept index
- a usage guide
- a concept reference section
- a relationship map

The support page belongs to the application workspace at `/app`, not to platform-only administration.
