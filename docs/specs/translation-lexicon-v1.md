# Translation Lexicon v1

This note captures the canonical wording used for recurring product concepts in `es`, `fr`, and `de`.

The goal is consistency across core, plugins, support guides, and framework packs. Use the same term for the same concept unless a local legal or domain convention clearly requires otherwise.

## Canonical Terms

| English | Spanish | French | German | Notes |
| --- | --- | --- | --- | --- |
| Organization | organización | organisation | Organisation | Main tenant boundary |
| Scope | ámbito | périmètre | Bereich | Use the product term when the UI already does |
| Principal | principal | principal | Principal | Access identity |
| Membership | membresía | adhésion | Mitgliedschaft | Organization access binding |
| Role | rol | rôle | Rolle | Access bundle |
| Functional Actor | actor funcional | acteur fonctionnel | funktionaler Akteur | Responsibility / accountability |
| Asset | activo | actif | Asset | Business or technical subject |
| Control | control | contrôle | Kontrolle | Safeguard or governance control |
| Risk | riesgo | risque | Risiko | Risk record |
| Finding | hallazgo | constat | Befund | Confirmed issue / nonconformity |
| Evidence | evidencia | preuve | Nachweis | Proof material |
| Artifact | artefacto | artefact | Artefakt | Stored attachment or file |
| Remediation Action | acción de remediación | action de remédiation | Abhilfemaßnahme | Follow-up task for a finding |
| Policy | política | politique | Richtlinie | Governed statement of practice |
| Policy Exception | excepción de política | exception de politique | Ausnahme | Approved deviation |
| Data Flow | flujo de datos | flux de données | Datenfluss | Privacy flow record |
| Processing Activity | actividad de tratamiento | activité de traitement | Verarbeitungstätigkeit | GDPR/privacy activity |
| Data Flow Detail | detalle del flujo de datos | détail du flux de données | Detail des Datenflusses | Detail view used to manage a privacy flow |
| Processing Activity Detail | detalle de la actividad de tratamiento | détail de l'activité de traitement | Detail der Verarbeitungsaktivität | Detail view used to manage a processing activity |
| Lawful Basis | base jurídica | base juridique | Rechtsgrundlage | GDPR/legal basis for processing |
| Transfer Type | tipo de transferencia | type de transfert | Transferart | Business-managed privacy flow classification |
| Workflow | flujo de trabajo | workflow / flux de travail | Workflow | State machine / transition model |
| Review Due | fecha de revisión | date de revue | Prüftermin | Scheduled review date |
| Mandate | mandato | mandat | Mandat | Leadership approval or instruction record |
| Readiness Snapshot | resumen de preparación | aperçu de préparation | Bereitschaftsübersicht | Framework readiness summary |
| Control Review Board | panel de revisión de controles | tableau de revue des contrôles | Kontrollprüfungsboard | Review queue for control workflow activity |
| Framework Adoption | adopción de marcos | adoption de référentiels | Rahmenwerkeinführung | Framework onboarding / adoption workspace |
| Continuity Service | servicio de continuidad | service de continuité | Kontinuitätsservice | Service continuity record |
| Recovery Plan | plan de recuperación | plan de reprise | Wiederherstellungsplan | Continuity recovery plan |
| Questionnaire Template | plantilla de cuestionario | modèle de questionnaire | Fragebogenvorlage | Reusable questionnaire definition |
| Questionnaire Item | ítem de cuestionario | élément de questionnaire | Fragebogeneintrag | Subject-bound item |
| Answer Library Entry | entrada de biblioteca de respuestas | entrée de bibliothèque de réponses | Antwortbibliothekseintrag | Reusable answer snippet |
| Brokered Request | solicitud intermediada | demande relayée | vermittelte Anfrage | Internal broker collection record |
| Draft | borrador | brouillon | Entwurf | Unfinished shared collaboration record |
| Comment | comentario | commentaire | Kommentar | Lightweight discussion note |
| Follow-up Request | solicitud de seguimiento | demande de suivi | Folgeanfrage | Tracked next-step item |
| In Progress | en curso | en cours | in Bearbeitung | Active work state for a tracked item |
| Under Review | en revisión | en cours de revue | in Prüfung | Review-state label for workflow items |
| Accepted | aceptado | accepté | akzeptiert | Approved or accepted response state |
| Needs Follow-up | requiere seguimiento | nécessite un suivi | Nachverfolgung erforderlich | Action still required after review |
| Yes / No | sí / no | oui / non | ja / nein | Binary questionnaire response |
| Supporting Document | documento de apoyo | document d'appui | Begleitdokument | Attachment requested as supporting material |
| Supporting Evidence | evidencia de apoyo | preuve d'appui | unterstützender Nachweis | Attachment requested as evidence |
| Control Framework | marco de control | référentiel de contrôle | Kontrollrahmen | Framework or standard |
| Control Requirement | requisito de control | exigence de contrôle | Kontrollanforderung | Clause or requirement in a framework |
| Control Coverage Mapping | mapeo de cobertura de controles | correspondance de couverture des contrôles | Zuordnung der Kontrollabdeckung | Explicit control-to-requirement traceability |
| Vendor Review | revisión de proveedor | revue fournisseur | Lieferantenprüfung | Third-party risk review workspace |
| Vendor Register | registro de proveedores | registre des fournisseurs | Lieferantenregister | Third-party vendor registry |
| External Review Portal | portal de revisión externa | portail de revue externe | externes Prüfportal | External collaboration space for a review |
| Brokered Request | solicitud intermediada | demande relayée | vermittelte Anfrage | Internal collection path without direct portal access |
| Follow-up Request | solicitud de seguimiento | demande de suivi | Folgeanfrage | Tracked next-step item inside a review |

## Usage Rules

- Prefer the same term inside UI text, support guides, and framework descriptions.
- Keep the wording simple and audit-friendly.
- Avoid local synonyms when they would fragment search, labels, or reporting language.
- When a term is already established in the product UI, keep support copy aligned with that product term unless it becomes misleading.
