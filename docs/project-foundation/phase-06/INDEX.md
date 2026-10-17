# PHASE 06 — DÉCISIONS D'INFRASTRUCTURE, AUDIT DE PUBLICATION & GO/NO-GO

**Projet :** MES ORCHIDÉES
**Rôle :** Lead Dev PHP / architecte hébergement / DevOps / conseiller technique
**Verdict :** `READY FOR HUMAN DECISION`
**Date :** 2026-10-17
**Autorité décisionnelle :** Utilisateur humain (aucun choix de fournisseur fait ici)

---

## 1. Résumé exécutif

Dossier de décision fondé sur le dépôt réel (Phase 06) : l'audit de publication des
**5 commits locaux non poussés est propre** (aucun secret, `.env` correctement ignoré) ;
les exigences techniques réelles de la base (MySQL 8/MariaDB, **FULLTEXT InnoDB**,
`UUID()`) permettent d'**écarter TiDB Serverless** (FULLTEXT « parsed and ignored » —
documenté) et de comparer PlanetScale (payant ≥ Base, MySQL 8/Vitess) et Railway
(MySQL/MariaDB communautaires, ~5 $/mois). Côté plateforme : le conteneur écoute
**uniquement sur 80** (aucune gestion de `$PORT`), les logs sortent sur stdout
(**VERIFIED**), et **la route `/` renvoie 200 même sans base** → bonne sonde de
livabilité, **mauvaise sonde de préparation** (readiness). Les décisions D1–D6 restent
ouvertes ; la matrice go/no-go (§`05`) liste les autorisations à obtenir.

## 2. Statuts

`VERIFIED` (preuve 06) · `REPORTED` (phases antérieures) · `INFERRED` (déduit) ·
`NOT VERIFIED` (preuve insuffisante) · `BLOCKED` (décision/dépendance manquante).

## 3. Contenu du dossier

| Doc | Objet |
|---|---|
| [01-git-publication-audit.md](./01-git-publication-audit.md) | État Git + audit du diff de publication |
| [02-db-provider-comparison.md](./02-db-provider-comparison.md) | Exigences base + comparaison fournisseurs sourcée |
| [03-platform-and-https-requirements.md](./03-platform-and-https-requirements.md) | Port, health check, logs, HTTPS/cookies |
| [04-http-test-strategy-and-legacy-schemas.md](./04-http-test-strategy-and-legacy-schemas.md) | Stratégie de tests HTTP + sort des schémas hérités |
| [05-go-no-go-matrix.md](./05-go-no-go-matrix.md) | Matrice go/no-go et décisions attendues |

Références amont (non dupliquées) : `../phase-05/04-production-readiness.md`,
`../phase-05/05-deployment-runbook.md`, `../phase-05/06-open-decisions.md`.

## 4. Verdict

`READY FOR HUMAN DECISION` — le dossier est complet et les preuves locales sont
obtenues ; les points dépendant d'un provisionnement réel sont marqués
`NOT VERIFIED`/`BLOCKED` et listés dans la matrice. **Aucun push, aucun déploiement,
aucune création de service externe** pendant cette phase.

**ARRÊT EN FIN DE PHASE 06.** En attente des décisions humaines §05.
