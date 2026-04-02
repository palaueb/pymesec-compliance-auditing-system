# Project TODO After All Current Work

This file captures larger product gaps and future-facing opportunities that should be considered after the current active backlog is completed.

## 1. Missing Connectors (APIs)

This is currently the most serious product gap compared with modern compliance tooling.

What is missing:
- Cloud connectivity to AWS, Azure, and Google Cloud in order to inspect real security posture, such as whether databases are encrypted.
- Identity connectivity to platforms such as Okta, Azure AD, or Google Workspace in order to know who joins or leaves the company.
- Code and development platform connectivity to GitHub or GitLab in order to inspect repository posture and vulnerabilities.

Current consequence:
- PymeSec still depends on manual user input instead of connecting directly to the real operating environment.

## 2. Automated Evidence Collection

Evidence handling is still too manual for audit-heavy use cases.

What is missing:
- Automatic screenshot capture for controls and system states.
- Automatic retrieval of security logs and other machine-produced artifacts.

Current consequence:
- Users still need to do manual copy-paste work and upload PDFs or files themselves to prove that a control is active.

## 3. Asset Discovery

The product still does not discover assets on its own.

What is missing:
- A network or host discovery capability that detects new computers, servers, and SaaS tools.
- Detection support for unsanctioned or untracked Shadow IT.

Current consequence:
- The asset inventory becomes stale quickly unless somebody keeps it updated manually every day.

## 4. Continuous Monitoring and Drift Detection

The product still behaves more like a governance workspace than a continuous controls monitor.

What is missing:
- Real-time or near-real-time alerting when a control drifts out of compliance.
- Detection of risky operational changes, such as exposure of insecure ports or broken baseline settings.

Current consequence:
- Non-compliance is usually discovered only when somebody returns to the workspace and checks manually.

## 5. Dynamic Risk Management

Risk remains mostly a governed internal register and not yet a living external-threat-aware system.

What is missing:
- Automatic risk posture recalculation based on external threat intelligence.
- Integration of market threat signals such as CVEs and active vulnerability pressure into the risk model.

Current consequence:
- Risk analysis remains too static and behaves like a point-in-time form rather than an adaptive model.

## 6. Vendor Risk Management Portal

The current third-party risk capability still needs a more complete external collaboration surface.

What is missing:
- A supplier-facing portal where vendors can upload their own certifications and supporting evidence, such as ISO or SOC 2 documents.

Current consequence:
- Vendor certifications and supporting files are still too dependent on email-based exchange and manual operator upload.

## 7. Remediation Workflow Integration

Remediation is still too isolated from the day-to-day delivery tools used by technical teams.

What is missing:
- Automatic ticket creation in systems such as Jira or Linear when a finding or gap requires technical remediation.

Current consequence:
- Handoffs between audit, compliance, and IT remain manual and happen outside the product.

## 8. Legislative and Standards Push Updates

Framework content still depends on deliberate product updates instead of continuous legal or standards change intake.

What is missing:
- A push-based update model for legal or framework changes, for example when a new ENS version is published.

Current consequence:
- Organizations may continue working against framework content that is no longer current unless the product is updated manually.

## Positioning Note

These items should be treated as post-current-work product evolution areas, not as immediate backlog slices. They mostly belong to a later competitive maturity phase focused on automation, real environment connectivity, and continuous assurance.
