# PHASE 05 — QUALIFICATION PRÉPRODUCTION & PRÉPARATION OPÉRATIONNELLE

**Projet :** MES ORCHIDÉES (PHP MVC, DWWM fil rouge)
**Rôle :** Lead Dev PHP / QA / auditeur sécurité / préparation production
**Verdict de phase :** `PASSED WITH LIMITATIONS`
**Verdict de mise en production :** `BLOCKED — en attente de décisions humaines d'infrastructure`
**Date :** 2026-10-17
**Autorité décisionnelle :** Utilisateur humain

---

## 1. Résumé exécutif

La Phase 05 consolide la Phase 04B : elle **ferme les deux limitations ouvertes**
(bilan XSS exhaustif, absence de suite de tests automatisée) et **documente les
prérequis réels de production**, **sans déployer ni pousser**.

- État Git **VERIFIED** : HEAD `64c6cf0`, arbre propre, `64c6cf0` et `f00981b`
  ancêtres, 4 commits locaux d'avance sur `origin/main`, **aucun push**.
- Audit XSS reproductible sur les 28 gabarits : verdict **PASS** (périmètre défini).
- Suite de non-régression **sans dépendance** ajoutée et exécutée :
  **PASS=27 / FAIL=0 / SKIP=0** (RG1 sur base de test isolée `mes_orchidees_test`).
- Docker **re-validé** en 05 : build OK, conteneur servi `GET / → 200`,
  extensions `mbstring`/`pdo_mysql` présentes.
- Base : moteur **MySQL/MariaDB** réel, schéma `data/schema_mysql.sql`, FULLTEXT
  vérifié sur MariaDB 10.4.32 ; schémas hérités Neon/Supabase **documentés comme archives**.
- Hébergement MySQL externe + port/health check Render : **décisions humaines ouvertes**.

## 2. Légende des statuts (utilisée dans tous les livrables 05)

| Statut | Sens |
|---|---|
| `VERIFIED` | Preuve directement obtenue pendant la Phase 05 |
| `REPORTED` | Issu d'un rapport de phase antérieur (non reproduit ici) |
| `INFERRED` | Conclusion déduite d'indices |
| `NOT VERIFIED` | Preuve insuffisante / périmètre non couvert |
| `BLOCKED` | Empêché par une dépendance ou une décision humaine manquante |

## 3. Périmètre couvert / non couvert

- **COUVERT (VERIFIED) :** état Git et historique ; audit de toutes les sorties
  dynamiques des gabarits ; suite de tests unitaires + intégration RG1 exécutée ;
  démarrage Docker (build + run + HTTP) ; configuration lue par le code ; procédure
  d'initialisation d'une base vierge ; critères de qualification de l'hébergeur.
- **NON COUVERT / BLOCKED :** choix du fournisseur MySQL/MariaDB ; validation du
  Server Port et de l'health check **sur la plateforme réellement retenue** ; tests
  en conditions de production (HTTPS, `COOKIE_SECURE=true`) ; aucun service externe
  créé (interdit par le protocole).

## 4. Verdict

`PASSED WITH LIMITATIONS` — La qualification préproduction est menée à son terme et
les limitations de la Phase 04B sont levées avec preuve. La **mise en production
effective reste `BLOCKED`** : elle exige des décisions humaines d'infrastructure
(fournisseur de base, port/health check Render) et une autorisation explicite.

## 5. Livrables

- [INDEX.md](./INDEX.md) — le présent document
- [01-git-and-baseline.md](./01-git-and-baseline.md) — état Git et commits
- [02-security-and-xss.md](./02-security-and-xss.md) — audit XSS et verdict
- [03-regression-tests.md](./03-regression-tests.md) — stratégie, suite, résultats
- [04-production-readiness.md](./04-production-readiness.md) — Docker, base, config
- [05-deployment-runbook.md](./05-deployment-runbook.md) — livraison et retour arrière
- [06-open-decisions.md](./06-open-decisions.md) — décisions humaines requises

## 6. Preuves principales (chemins)

- Tests : `tests/bootstrap.php`, `tests/run.php` (exécutés `php tests/run.php`)
- Sécurité : `config/helpers.php` (`e()`), `src/Controller/AbstractController.php`
  (CSRF), `src/Repository/*Repository.php` (RG1, `WHERE user_id`)
- Conteneur : `Dockerfile`, `.dockerignore`, `.env.production.example`, `config/db.php`
- Base : `data/schema_mysql.sql` (FULLTEXT `ft_orchids_search`) ; hérités :
  `data/schema_neon.sql`, `data/schema_supabase.sql` (non utilisés)

---

**ARRÊT EN FIN DE PHASE 05.** Aucun push, aucun déploiement, aucune création de
ressource externe, aucune opération sur une base de production. En attente
d'autorisation explicite avant toute mise en production.
