# File Upload Security and Type Validation v1

## Goal

Define a mandatory platform-wide upload security baseline for every file upload flow in Pymesec.

This specification exists to prevent the platform from accepting arbitrary executable or dangerous file types in places where the product expects governed business documents such as:

- PDF reports
- spreadsheets
- word-processing documents
- CSV imports
- images
- plain text evidence

The goal is not to block useful evidence collection. The goal is to ensure every upload flow accepts only the file families it is designed to handle.

## Scope

This applies to all upload entry points, including:

- artifact uploads
- evidence uploads
- questionnaire attachments
- workpapers
- review attachments
- CSV / tabular imports
- any future external collaboration upload portals

## Core Rule

Uploads must be validated against an explicit allowed file profile.

No upload flow may accept a generic `any file` input by default.

Every upload flow must declare:

- the upload purpose
- the allowed file families
- the maximum size
- whether multi-file upload is allowed

## Validation Rules

### 1. Validate by both extension and detected content type

The platform must not trust:

- the browser-provided MIME type alone
- the filename extension alone

Validation should use both:

- normalized extension allowlist
- server-side detected MIME or content signature

The two must be compatible with the expected upload profile.

### 2. Reject dangerous executable or active-content formats by default

The platform should reject uploads such as:

- `.php`
- `.phtml`
- `.phar`
- `.js`
- `.mjs`
- `.html`
- `.htm`
- `.svg`
- `.exe`
- `.dll`
- `.so`
- `.bat`
- `.cmd`
- `.sh`
- `.ps1`
- `.jar`
- `.apk`

These should remain blocked even if the uploader renames them misleadingly.

### 3. Archives are not allowed by default

Compressed archives such as:

- `.zip`
- `.7z`
- `.rar`
- `.tar`
- `.gz`

should be rejected unless a future upload profile explicitly permits them.

v1 should default to `disallow`.

### 4. Each upload purpose gets an allowed file profile

Examples:

- `document_bundle`
  - pdf
  - doc
  - docx
  - odt
- `spreadsheet`
  - xls
  - xlsx
  - ods
  - csv
- `pdf_only`
  - pdf
- `image_evidence`
  - png
  - jpg
  - jpeg
  - webp
- `plain_text_review_note`
  - txt
  - md
  - csv only if explicitly intended

The platform may be flexible in what it allows, but the allowlist must be explicit.

### 5. Store privately and outside executable web paths

Even valid uploads must be stored safely.

Rules:

- uploaded files must be stored on private storage
- uploads must not be written to a web-executable path
- generated storage paths must come from the platform, never from the user
- original filenames are metadata only

### 6. Download and preview safely

Rules:

- default download should use safe attachment semantics
- preview should be enabled only for known-safe previewable types
- previewable does not mean executable
- unknown or untrusted formats should download only, not inline render automatically

### 7. Size limits are mandatory

Each upload profile must define a maximum size.

This protects against:

- abuse
- storage exhaustion
- oversized uploads through external portals

### 8. Rejections should be explicit and user-readable

When an upload is rejected, the product should say why.

Examples:

- unsupported file type
- detected content does not match the expected document type
- file too large
- empty or malformed upload

## Recommended v1 Allowed File Families

This is the initial platform baseline:

### General governed documents

- `.pdf`
- `.doc`
- `.docx`
- `.odt`
- `.txt`
- `.md`

### Spreadsheets / tabular evidence

- `.xls`
- `.xlsx`
- `.ods`
- `.csv`

### Images

- `.png`
- `.jpg`
- `.jpeg`
- `.webp`

Anything outside these families should require an explicit future decision.

## Product Integration Rules

### 1. Artifact uploads

Artifact uploads should require a file profile tied to:

- artifact type
- upload purpose
- subject context if needed

### 2. Evidence uploads

Evidence upload flows should default to governed-document or spreadsheet profiles, not unrestricted files.

### 3. Questionnaire attachments

Questionnaire responses from external participants should only allow the file profiles requested by that question or evidence request.

### 4. Import flows

Imports such as people import should only allow:

- csv
- tsv
- xlsx if explicitly supported

They should also reject binary or malformed content.

## Future Extensions

Later versions may add:

- antivirus scanning
- malware sandboxing
- archive inspection
- DLP classification
- image / PDF parsing
- signature validation for signed documents

These are valuable, but they do not replace the v1 allowlist and type-validation rules.

## Consequences

- The platform reduces the chance of arbitrary active or executable content entering the system.
- Internet-facing and external-collaboration uploads become safer by default.
- Upload behavior becomes predictable and auditable across all plugins.
