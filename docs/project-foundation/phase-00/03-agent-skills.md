# 03 — Skills `/agent/` (§6)

Deux copies quasi identiques de la méthode `.agent/` existent : à la **racine** du
workspace et dans **`mes_orchidees_php/.agent/`** (37 skills chacune). Liste `VERIFIED`
par inventaire de répertoire. Les `SKILL.md` n'ont **pas** été exécutés (Phase 00 = lecture
seule, §6.3).

## 1. Applicabilité pour la migration PHP MVC (§6.2)

### 1.1 Applicables dès la phase 01 (cadrage / architecture)
| Skill | Usage futur | Effet potentiel |
|---|---|---|
| `backend-architect` | Conception MVC, découpage couches | Rédaction/plan |
| `api-design-principles` | Contrats des endpoints REST | Lecture + plan |
| `database-architect`, `postgresql` | Modèle de données, schéma Neon | Plan SQL |
| `auth-implementation-patterns` | Refonte JWT/sessions en MVC | Plan sécurité |
| `mermaid-expert` | Diagrammes de classes/séquences | Docs |

### 1.2 Applicables en implémentation (phases 03+)
`javascript-pro`, `frontend-developer`, `sql-pro`, `sql-optimization-patterns`,
`database-admin`, `unit-testing-test-generate`.

### 1.3 Applicables en audit/stabilisation
`security-owasp`, `security-auditor`, `backend-security-coder`, `frontend-security-coder`,
`threat-modeling-expert`, `sast-configuration`,
`security-scanning-security-{sast,dependencies}`, `e2e-testing-patterns`.

### 1.4 Accessibilité / conformité (contexte RGAA/RGPD du projet)
`rgaa-accessibility`, `wcag-audit-patterns`, `accessibility-compliance-accessibility-audit`,
`screen-reader-testing`, `rgpd-cnil-audit`.

### 1.5 NON applicables / redondants / hors-stack (§6.2 justification)
| Skill | Justification |
|---|---|
| `nextjs-app-router-patterns` | Stack Next.js/React — **hors PHP MVC** |
| `typescript-pro` | Projet en PHP/JS, pas TS |
| `nodejs-backend-patterns` | Backend PHP, pas Node |
| `tailwind-design-system`, `design-system-selector` | Projet en SCSS, design déjà figé |
| `conductor-*`, `track-management` | Méta-orchestration de « tracks » — non utilisé ici |
| duplication racine ↔ projet | 2 copies → risque de divergence, à consolider |

## 2. Règles de sécurité (§6.3)

- Plusieurs skills peuvent **écrire des fichiers / installer des dépendances / contacter
  des services externes** (ex. `sast-configuration`, `security-scanning-*`, `database-admin`,
  `unit-testing-test-generate`). **Aucun** n'a été invoqué en Phase 00.
- Avant tout usage futur d'un skill à effet externe/destructif : vérifier comportement +
  autorisations, comme pour tout outil.
- La simple **présence** d'un skill ne vaut pas autorisation d'agir.

## 3. Stratégie d'utilisation future

1. Consolider **une seule** source de vérité `.agent/` (éviter la duplication).
2. Neutraliser/archiver les skills hors-stack (Next.js/TS/Node) pour ce projet.
3. Activer progressivement : architecture (01) → sécurité & données (02/03) →
   tests & audit (stabilisation).
4. Aucun skill utilisé en Phase 00 au-delà de l'inspection en lecture seule.
