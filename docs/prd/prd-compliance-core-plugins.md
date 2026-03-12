# PRD — Plataforma Open Source d’Auditoria de Compliment

## 1. Resum executiu

Es proposa una plataforma open source d’auditoria de compliment construïda amb una arquitectura **CORE + plugins**, orientada a permetre el desplegament progressiu de funcionalitats sense comprometre l’estabilitat del nucli. El producte ha de servir com a base comuna per a múltiples marcs normatius i necessitats organitzatives, amb especial focus en:

* ISO/IEC 27001
* ENS
* NIS2
* RGPD / LOPDGDD
* marcs interns i personalitzats

L’objectiu del producte és proporcionar traçabilitat completa entre requisits, controls, evidències, riscos, troballes i accions de remediació, amb capacitat d’extensió per part de tercers mitjançant plugins.

## 2. Visió del producte

### 2.1 Visió

Crear el “WordPress del compliance”: un nucli robust, estable i segur, ampliable per una comunitat que pugui afegir marcs, connectors, automatitzacions, informes i workflows sense trencar el sistema base.

### 2.2 Proposta de valor

La plataforma ha de permetre:

* centralitzar el compliment normatiu
* modelar controls i requisits de forma reusable
* recollir evidència manual i automàtica
* executar avaluacions i auditories
* gestionar riscos, excepcions i plans d’acció
* generar informes i dashboards
* estendre funcionalitats mitjançant plugins desacoblats

### 2.3 Principis de producte

* **Core mínim però potent**
* **Everything is pluggable**
* **API-first**
* **Auditability by design**
* **Secure by default**
* **Open source first**
* **Multi-framework and multi-tenant ready**
* **Multi-language by design**
* **Identity as a plugin capability**
* **Core agnostic to business domains**
* **Separació estricta entre identitat d’accés i rols funcionals de domini**

## 3. Objectius

### 3.1 Objectius de negoci

* construir una base open source reusable
* facilitar adopció en homelab, pime i entorns tècnics
* crear ecosistema de plugins de comunitat
* reduir dependència de productes GRC tancats
* permetre monetització futura via suport, hosting o plugins premium sense trencar el model open source

### 3.2 Objectius de producte

* disposar d’un MVP funcional de compliance generic
* suportar marcs i controls custom
* garantir traçabilitat completa entre objectes del sistema
* oferir extensibilitat formal i documentada
* mantenir compatibilitat entre versions del CORE i plugins

### 3.3 No objectius inicials

* certificació automàtica de compliment
* substitució d’assessorament legal o auditoria formal
* cobertura completa de tots els sectors regulats al llançament
* marketplace complet en la primera versió

## 4. Usuari objectiu

### 4.1 Perfils principals

* administrador de seguretat / sysadmin
* responsable de compliance
* auditor intern
* consultor extern
* DPO / responsable de privacitat
* equip tècnic DevOps / plataforma
* comunitat open source desenvolupadora de plugins

### 4.2 Casos d’ús principals

* crear un catàleg de controls i mapar-lo a múltiples marcs
* recollir evidències manualment o via connectors
* executar una assessment trimestral o anual
* registrar findings i generar plans d’acció
* modelar riscos i excepcions
* generar un informe d’estat per direcció
* afegir un plugin nou sense tocar el core

## 5. Arquitectura de producte

## 5.1 Enfocament general

El sistema es divideix en:

1. **CORE**
2. **Plugins funcionals**
3. **SDK / contractes d’extensió**
4. **API pública i events interns**

## 5.2 Responsabilitats del CORE

El CORE només ha d’incloure allò imprescindible per sostenir la plataforma.

### Distinció conceptual obligatòria

El sistema ha de separar explícitament dues capes diferents:

1. **Identitat i accés a la plataforma**

   * qui pot entrar al sistema
   * com s’autentica
   * quin nivell d’accés té a la UI, API i operacions administratives
   * quina pertinença té dins d’una organització o tenant

2. **Rols funcionals de domini**

   * qui és propietari d’un actiu
   * qui és risk owner
   * qui és control owner
   * qui és approver
   * qui és auditor
   * qui és DPO
   * qui és responsable d’una acció correctiva

Aquests dos conceptes no s’han de confondre. Un actor pot existir només com a referència funcional dins del domini sense ser necessàriament un usuari amb accés a la plataforma. Igualment, un usuari amb accés a la plataforma pot no tenir cap responsabilitat funcional sobre actius, riscos o controls.

### El CORE ha d’incloure:

* motor de plugins
* registre de plugins, dependències i compatibilitat
* contractes d’extensió i SDK base
* sistema d’events / hooks
* contenidor d’aplicació i service registry
* persistència base i abstraccions de dades compartides
* audit trail
* scheduler i job queue base
* sistema de configuració
* internacionalització base
* localització base (idioma, locale, timezone, format de dates i números)
* sistema d’internacionalització basat en fitxers de traducció propis del core i de cada plugin
* storage base per adjunts i artefactes
* API base i contractes públics
* UI shell / admin shell
* routing extensible
* sistema de menús extensible
* motor de permisos i polítiques, desacoblat del provider d’identitat
* abstracció de principal d’accés i memberships
* abstraccions per actors de domini i assignacions funcionals
* motor de workflows mínim
* notificacions base
* health checks i observabilitat base
* multi-tenant / multi-organització base
* model base d’entitats compartides no vinculades a cap domini funcional

## 5.3 Què ha d’anar a plugins

Tot allò específic de domini o vertical funcional, així com les capacitats que es vulguin poder substituir o evolucionar independentment del nucli:

* gestió d’usuaris i identitat
* autenticació concreta (local auth, LDAP, OIDC, SAML, Google, GitHub, etc.)
* gestió d’equips i directoris
* model funcional de participants, responsables i ownership de domini
* framework packs (ISO 27001, ENS, NIS2, RGPD)
* controls i requisits
* gestió de riscos
* findings i remediació
* privacitat / RGPD
* third-party risk
* connectors d’evidència automàtica
* dashboards avançats
* informes especialitzats
* qüestionaris
* workflows avançats
* scoring engines alternatius
* imports / exports especials
* marketplace / repositori de plugins

## 5.4 Principis de plugin architecture

* els plugins no poden modificar el CORE directament
* tota extensió s’ha de fer via contractes, events i APIs públiques
* cada plugin declara dependències, permisos, migracions i versions compatibles
* el CORE ha de poder desactivar plugins sense corrompre dades base
* el CORE ha de poder aïllar plugins fallits
* les dades de plugins han de tenir namespace propi
* el sistema ha de suportar plugins UI i plugins backend
* el sistema ha de suportar plugins “headless”
* el CORE no ha d’assumir cap implementació concreta d’identitat
* el CORE ha de poder operar amb diferents identity providers a través d’un contracte comú
* el sistema ha de permetre substituir plugins d’identitat sense redissenyar la resta de dominis
* totes les funcionalitats de domini han de poder-se desacoblar com a plugins sense trencar compatibilitat

## 5.5 Tipus de plugins

### A. Identity plugins

* local users
* LDAP/AD
* OIDC
* SAML
* Google Workspace auth
* GitHub auth
* user directory
* teams and memberships

### B. Domain actor plugins

* asset owners
* risk owners
* control owners
* approvers
* auditors
* DPO roles
* action assignees
* organizational stakeholders

### C. Domain plugins

* controls
* risks
* findings
* privacy
* vendors
* assets
* assessments
* evidence

### D. Framework plugins

* ISO 27001 pack
* ENS pack
* NIS2 pack
* RGPD pack

### E. Connector plugins

* LDAP/AD sync
* Google Workspace sync
* GitHub/GitLab
* Wazuh
* osquery/Fleet
* OpenSCAP
* AWS/Azure/GCP
* Docker/Kubernetes/Proxmox

### F. Reporting plugins

* executive reports
* audit reports
* maturity dashboards
* export bundles

### G. Automation plugins

* rules engine
* reminders
* evidence refresh
* escalations

### H. UI plugins

* custom dashboards
* widgets
* admin panels
* domain-specific forms

## 5.6 Contractes tècnics del plugin system

Cal definir:

* manifest del plugin
* cicle de vida: install, enable, disable, upgrade, uninstall
* migracions pròpies
* seeds pròpies
* registre de rutes
* registre de menús UI
* registre de permisos
* subscripció a events
* publicació d’events
* tasks programades
* APIs exposades
* polítiques de compatibilitat

## 6. Requisits funcionals per blocs

## 6.1 Bloc CORE

### Entregables

* plugin manager
* event bus
* service registry
* contractes públics del core
* API base
* shell UI
* routing extensible
* sistema de menús extensible
* configuració global
* internacionalització i localització base
* suport per fitxers de traducció del core i de cada plugin
* idiomes inicials oficials: anglès, castellà, francès i alemany
* multi-organització i scopes
* motor de permisos desacoblat de la identitat
* abstracció de principals d’accés
* abstracció d’actors funcionals de domini
* audit log
* storage d’adjunts
* scheduler i jobs asíncrons

### Històries d’usuari clau

* com a admin vull activar o desactivar plugins
* com a admin vull definir idioma i locale per organització o usuari
* com a sistema vull registrar events auditables de totes les operacions sensibles
* com a plugin vull subscriure’m a events del core
* com a plugin d’identitat vull integrar-me amb el motor de permisos del core
* com a plugin de domini vull referenciar actors funcionals sense dependre d’un usuari autenticable

### Criteris d’acceptació

* es poden instal·lar plugins sense modificar codi del core
* els canvis auditables queden registrats
* els permisos són aplicables per organització i abast
* el sistema suporta jobs asíncrons
* el core funciona sense un provider d’identitat hardcodejat
* la UI i l’API suporten multiidioma des del nucli
* cada component (core o plugin) manté els seus propis fitxers de traducció en formats simples i fàcils de gestionar, com JSON o similars

## 6.2 Bloc Controls i Requisits

### Entregables

* llibreria de controls
* catàleg de requisits
* mapping many-to-many
* estat de compliment
* applicability / justificacions
* versionat de controls

### Històries clau

* com a compliance manager vull crear controls reutilitzables
* com a auditor vull veure quins requisits cobreix cada control
* com a usuari vull marcar un control com no aplicable i justificar-ho

### Criteris d’acceptació

* un control pot mapar a múltiples marcs
* un requisit pot estar cobert per múltiples controls
* hi ha historial de canvis i versions

## 6.3 Bloc Evidències

### Entregables

* repositori d’evidències
* metadades d’evidència
* hashing
* caducitat
* validació
* chain of custody

### Històries clau

* com a responsable vull adjuntar evidència a un control
* com a auditor vull saber si l’evidència és vigent
* com a sistema vull avisar si una evidència caduca

### Criteris d’acceptació

* cada fitxer té hash registrat
* es registra qui l’ha pujat i quan
* hi ha estat de validació
* es pot marcar com expirada

## 6.4 Bloc Assessments i Auditories

### Entregables

* campanyes d’avaluació
* checklists
* conclusions per control
* workpapers
* sign-off

### Històries clau

* com a auditor vull obrir una assessment amb abast i dates
* com a auditor vull registrar proves i conclusions
* com a direcció vull veure el resultat agregat d’una assessment

### Criteris d’acceptació

* una assessment pot incloure controls, evidències i findings
* hi ha estat i workflow de tancament
* es pot exportar informe resum

## 6.5 Bloc Findings i Remediació

### Entregables

* registre de findings
* classificació per severitat
* accions correctives
* estat i re-test

### Històries clau

* com a auditor vull crear una no conformitat major
* com a responsable vull assignar una acció correctiva
* com a revisor vull validar el tancament

### Criteris d’acceptació

* un finding es pot vincular a controls, riscos i evidències
* hi ha traçabilitat del seu cicle de vida

## 6.5 bis Bloc Actors funcionals i ownership

### Entregables

* model d’actors funcionals
* assignacions de responsabilitat
* ownership d’actius, riscos, controls i accions
* rols funcionals configurables de domini
* referències a persones internes o externes sense login obligatori

### Històries clau

* com a responsable de compliance vull assignar un risk owner encara que no tingui accés a la plataforma
* com a auditor vull veure qui és el propietari funcional d’un control
* com a sistema vull diferenciar permisos d’accés i responsabilitats de negoci

### Criteris d’acceptació

* un actiu, risc, control o acció pot tenir responsable funcional sense requerir usuari amb login
* els rols funcionals no impliquen permisos d’accés automàtics
* els permisos d’accés no impliquen ownership funcional automàtic

## 6.6 Bloc Riscos

### Entregables

* registre de riscos
* model inherent/residual
* tractament
* acceptació de risc
* relació risc-control

### Històries clau

* com a responsable vull valorar impacte i probabilitat
* com a compliance manager vull associar controls mitigadors
* com a direcció vull veure riscos residuals oberts

### Criteris d’acceptació

* els riscos poden vincular-se a actius, controls i accions
* hi ha historial de revisió

## 6.7 Bloc Actius i Scope

### Entregables

* inventari bàsic d’actius
* processos
* classificació
* scopes

### Criteris d’acceptació

* es pot limitar una assessment a un scope
* els actius poden tenir normativa aplicable

## 6.8 Bloc Privacitat

### Entregables

* registre d’activitats de tractament
* DPIA/PIA
* drets dels interessats
* bretxes de dades

### Criteris d’acceptació

* els tractaments poden vincular-se a controls i riscos
* les incidències de privacitat tenen workflow propi

## 6.9 Bloc Tercers

### Entregables

* registre de proveïdors
* due diligence
* score de tercer
* evidències i qüestionaris

## 6.10 Bloc Qüestionaris

### Entregables

* formularis configurables
* lògica condicional
* plantilles
* scoring

## 6.11 Bloc Informes i Dashboards

### Entregables

* dashboard operatiu
* dashboard executiu
* informe d’auditoria
* exports PDF/CSV/JSON

## 6.12 Bloc Automatització

### Entregables

* motor de regles
* recordatoris
* reavaluacions programades
* escalat d’excepcions i venciments

## 6.13 Bloc Connectors

### Entregables

* framework de connectors
* connector SDK
* jobs programats
* evidència automàtica
* health monitoring de connectors

## 7. Model de dades d’alt nivell

Entitats base del CORE:

* Organization
* Scope
* Role
* Permission
* PrincipalReference
* MembershipReference
* FunctionalActorReference
* FunctionalAssignment
* Plugin
* PluginVersion
* PluginSetting
* AuditLog
* Attachment
* Notification
* Job
* EventSubscription
* LocaleSetting
* TranslationResource

Entitats de domini inicial via plugins:

* User
* Team
* DomainActorProfile
* Framework
* Requirement
* Control
* ControlMapping
* Evidence
* Assessment
* AssessmentResult
* Finding
* ActionPlan
* Risk
* RiskTreatment
* Asset
* PolicyDocument
* Exception
* Questionnaire
* QuestionnaireResponse
* Vendor
* ProcessingActivity
* PrivacyIncident

## 8. Requisits no funcionals

### 8.0 Internacionalització

* idioma principal del producte: anglès
* idiomes oficials inicials: anglès, castellà, francès i alemany
* cada idioma ha d’estar separat en fitxers independents
* el core ha de tenir els seus propis fitxers de traducció
* cada plugin ha de tenir els seus propis fitxers de traducció
* els fitxers han de ser en formats simples i fàcils de gestionar, com JSON o similars
* les traduccions del core i dels plugins han de seguir una convenció comuna d’estructura i claus
* les claus de traducció han de ser estables i semàntiques
* la comunitat ha de poder corregir textos editant aquests fitxers de traducció
* s’ha d’evitar incrustar textos literals al codi de negoci o a plantilles difícils de mantenir

### 8.1 Seguretat

* MFA
* SSO OIDC/SAML
* CSRF protection
* escaping output
* prepared statements
* signed file access
* xifrat de secrets
* xifrat de camps sensibles
* rate limiting
* segregació multi-tenant

### 8.2 Rendiment

* paginació obligatòria
* cues per processos pesats
* cache d’elements freqüents
* jobs de connectors asíncrons

### 8.3 Escalabilitat

* desplegament monolític modular inicial
* suport per workers separats
* storage object-compatible
* possibilitat futura de separar serveis

### 8.4 Compatibilitat i upgrade

* semver al core
* semver als plugins
* cada plugin ha de declarar compatibilitat amb versions del core
* contractes estables
* deprecació amb calendari
* migracions reversibles quan sigui possible

### 8.5 Observabilitat

* logs estructurats
* mètriques
* health checks
* traces bàsiques

### 8.6 Qualitat

* cobertura mínima de tests
* tests de contracte de plugins
* tests d’upgrade
* tests de permisos

### 8.7 Repositoris i releases

* hi ha un repositori GitHub principal que conté el CORE i els plugins base oficials
* el repositori principal és la font de veritat de desenvolupament coordinat
* cada plugin distribuïble ha de tenir també el seu propi repositori GitHub
* cada repositori de plugin és una unitat independent de distribució i release
* un plugin es desenvolupa primer al repositori principal, però quan està llest s’ha de poder publicar al seu repositori dedicat
* ha d’existir un pipeline capaç de fer split/publicació del codi del plugin des del repositori principal cap al repositori dedicat
* aquest pipeline ha de generar un tag de versió propi del plugin
* els tags del plugin s’han d’utilitzar posteriorment per publicar releases del plugin
* els plugins s’han de poder publicar independentment del core si passen els checks de compatibilitat i qualitat
* les releases de plugins han de ser traçables i auditables
* la relació entre commit d’origen al repositori principal i release del plugin s’ha de poder reconstruir

## 9. Arquitectura tècnica proposada

### Stack recomanat

* PHP 8.x
* Laravel o Symfony
* PostgreSQL
* Redis
* MinIO/S3 per storage
* queue workers
* OpenAPI per API
* Vue/React o Blade + components per UI

### Recomanació d’enfocament

Per velocitat d’arrencada i ecosistema, **Laravel** és una opció especialment bona si es vol construir ràpid, tenir queues, jobs, events, policies, migrations i package ecosystem. Symfony també és sòlid si es prioritza desacoblament extrem.

## 10. Roadmap Agile per hites

## Hita 0 — Descobriment i arquitectura

### Objectiu

Definir el producte, el core i els contractes de plugin.

### Entregables

* PRD validat
* arquitectura lògica
* ADRs inicials
* mapa d’entitats
* plugin manifest spec v1
* convencions de codi
* estratègia de versionat
* estratègia de repositoris i releases
* estratègia de permisos
* estratègia de migracions

### Resultat esperat

Base de disseny estable per començar implementació.

## Hita 1 — Foundation CORE

### Objectiu

Posar en marxa el nucli executiu del sistema.

### Entregables

* bootstrap aplicació
* multi-organització i scopes
* motor de permisos base
* audit log
* file storage base
* event bus
* plugin manager inicial
* admin shell
* API base
* internacionalització i localització base
* suport per fitxers de traducció del core i de cada plugin
* contracte de provider d’identitat
* contracte d’actors funcionals de domini

### Definition of Done

* plugins poden registrar-se
* operacions sensibles generen audit log
* el core suporta multiidioma
* el core permet connectar un plugin d’identitat funcional
* el core permet referenciar actors funcionals separats de la identitat d’accés
* existeixen fitxers base de traducció en anglès, castellà, francès i alemany

## Hita 2 — Plugins bàsics de plataforma

### Objectiu

Activar les primeres capacitats essencials com a plugins sobre el core.

### Entregables

* plugin d’identitat local (usuaris, equips, sessions)
* plugin RBAC UI / administració de membres
* plugin d’actors funcionals / ownership
* plugin base de compliance (frameworks, requirements, controls, mappings)
* plugin d’actius bàsics

### Definition of Done

* es poden crear usuaris a través del plugin d’identitat
* el motor de permisos del core funciona amb el plugin d’identitat
* es poden assignar responsables funcionals sense login obligatori
* es pot crear un marc, requisits i controls
* es poden mapar i consultar

## Hita 3 — Evidències i Assessments

### Objectiu

Permetre verificació real i campanyes d’avaluació.

### Entregables

* evidències amb hashing
* caducitat i validació
* assessments
* workpapers bàsics
* conclusions
* export resum

### Definition of Done

* una assessment pot completar-se amb evidències i conclusions
* el sistema genera alertes de venciment d’evidència

## Hita 4 — Findings, Actions i Exceptions

### Objectiu

Tancar el loop operatiu de desviació i remediació.

### Entregables

* findings
* action plans
* excepcions
* workflows bàsics de revisió
* notificacions

### Definition of Done

* es pot obrir, assignar i tancar un finding
* una excepció pot ser aprovada i caducar

## Hita 5 — Riscos

### Objectiu

Afegir el model de risc per donar sentit de gestió.

### Entregables

* registre de riscos
* valoració inherent/residual
* tractament
* acceptació
* relació risc-control

### Definition of Done

* cada risc pot vincular-se a controls, actius i plans

## Hita 6 — SDK i Plataforma de Plugins v1

### Objectiu

Obrir el producte a contribució externa seriosa.

### Entregables

* SDK de plugins
* documentació de contractes
* exemple de plugin backend
* exemple de plugin UI
* test harness de plugins
* compatibilitat i validacions d’instal·lació
* pipeline de split/publicació de plugins cap a repositoris dedicats
* estratègia de tagging i traçabilitat de releases per plugin

### Definition of Done

* un tercer pot desenvolupar un plugin sense tocar el core
* un plugin oficial es pot publicar des del repositori principal al seu repositori dedicat
* el procés genera un tag de versió del plugin i conserva la traçabilitat amb el commit d’origen

## Hita 7 — Plugins oficials de domini

### Objectiu

Cobrir els principals blocs funcionals com a plugins desacoblats.

### Entregables

* plugin Risk oficial
* plugin Privacy oficial
* plugin Vendor oficial
* plugin Questionnaire oficial
* plugin Reporting oficial

## Hita 8 — Framework packs oficials

### Objectiu

Publicar paquets de marcs reutilitzables.

### Entregables

* ISO 27001 pack
* ENS pack
* NIS2 pack
* RGPD/LOPDGDD pack
* mapejos inicials entre marcs

### Notes

Els packs han d’incloure controls, requisits, plantilles, notes i informes base, però han de ser versionats independentment del core.

## Hita 9 — Connectors i automatització

### Objectiu

Aportar evidència tècnica automàtica.

### Entregables

* connector SDK
* connector Wazuh
* connector Fleet/osquery
* connector OpenSCAP
* connector GitHub/GitLab
* scheduler d’execució
* rule engine inicial

## Hita 10 — Hardening, observabilitat i preparació comunitat

### Objectiu

Fer el producte fiable per adopció pública.

### Entregables

* hardening review
* observabilitat
* test matrix
* política de compatibilitat
* guia de contribució
* plantilla de plugin
* documentació d’arquitectura pública

## 11. Enfocament Scrum / Agile

### Cadència recomanada

* sprints de 2 setmanes
* demos al final de cada sprint
* backlog grooming setmanal
* ADRs per decisions tècniques rellevants

### Streams de treball paral·lels

* Producte / domini
* Plataforma / core
* UI/UX
* Seguretat
* Documentació / DX
* Plugins oficials

### Artefactes

* backlog de producte
* roadmap per hites
* Definition of Ready
* Definition of Done
* ADR log
* changelog semàntic

## 12. Epics inicials

### Epic A — Core Platform

Inclou orgs, scopes, plugin manager, audit log, storage, API, UI shell, i18n/l10n, event bus i permission engine.

### Epic B — Identity Plugin System

Inclou contracte d’identitat, plugin local users, equips, memberships i sessions.

### Epic C — Functional Actors & Ownership

Inclou model d’actors funcionals, ownership i assignacions de responsabilitat desacoblades dels permisos d’accés.

### Epic D — Compliance Domain Base

Inclou frameworks, requirements, controls, mappings i scopes.

### Epic E — Evidence & Assessments

Inclou evidències, validació, caducitat i auditories.

### Epic F — Findings & Remediation

Inclou findings, accions i excepcions.

### Epic G — Risk Management

Inclou risc inherent/residual i tractament.

### Epic H — Plugin SDK

Inclou contractes, scaffolding i documentació.

### Epic I — Official Plugin Packs

Inclou plugins de domini i framework packs.

### Epic J — Connectors & Automation

Inclou connectors i evidència automàtica.

## 13. Backlog inicial prioritzat

### P0

* multiidioma al core
* suport per fitxers de traducció del core i de cada plugin
* organitzacions i scopes
* permission engine
* audit log
* plugin manager mínim
* contracte d’identitat
* contracte d’actors funcionals
* plugin local users
* plugin ownership / domain actors
* frameworks
* controls
* requirements
* mappings
* evidències
* assessments

### P1

* findings
* action plans
* notificacions
* estratègia de repositoris i releases
* pipeline de publicació de plugins
* riscos
* exceptions
* informes bàsics

### P2

* privacy
* vendors
* questionnaires
* connectors
* rules engine
* dashboards avançats

## 14. Criteris d’acceptació globals del producte

* el core funciona sense plugins opcionals
* els plugins poden ampliar funcionalitat sense hacks sobre el core
* totes les operacions sensibles deixen rastre auditable
* les dades de compliment tenen traçabilitat completa
* el sistema permet exportació de dades i informes
* hi ha documentació suficient per a tercers desenvolupadors

## 15. Riscos de projecte

### Riscos tècnics

* excés d’acoblament entre core i plugins
* model de permisos insuficient
* migracions incompatibles entre versions
* plugin API inestable massa aviat

### Riscos de producte

* voler cobrir massa marcs massa d’hora
* fer massa domini dins del core
* construir checklists en lloc d’un sistema traçable
* UX massa complexa per a pimes i homelab

### Mitigacions

* core minimalista
* contractes explícits
* backlog durament prioritzat
* packs oficials com a referència d’arquitectura

## 16. Decisió estratègica recomanada

La recomanació és construir primer un **monòlit modular amb plugin architecture formal**, no microserveis. Això maximitza velocitat, simplicitat operativa i contribució externa. El desacoblament s’ha de fer a nivell de domini, contractes, events i paquets, no a nivell d’infraestructura des del dia 1.

## 17. Entregable per a Codex

Aquest PRD ha de servir de base per generar immediatament:

1. ADR-001 Arquitectura CORE + Plugins
2. ADR-002 Stack Laravel/Symfony
3. esquema de base de dades inicial
4. especificació Plugin Manifest v1
5. especificació Event Bus v1
6. especificació Permission Model v1
7. backlog d’epics i stories en format implementable
8. esquelet de repositori

## 18. Properes peces recomanades

Després d’aquest PRD, el següent que convé preparar és:

* esquema de domini i ERD inicial
* estructura de repositoris i paquets
* contracte tècnic del plugin system
* backlog de 8-12 sprints inicials
* plantilles d’issues per a la comunitat
