# PHASE E3 — Audit de faisabilité du déploiement Render & choix de la base distante (jury DWWM)

Audit **en lecture seule** : aucun code modifié, aucune base touchée, aucun
déploiement, aucun compte créé, aucun secret affiché, aucun processus arrêté.

Budget cible : **0 €/mois**. Toute option payante est marquée comme telle et ne
peut être retenue sans validation explicite.

## Question centrale

Comment rendre le site **accessible en ligne** au jury DWWM, avec une base
**persistante**, **sans perte de données** et **sans coût imprévu** ?

## Réponse courte (détaillée en doc 06)

- Le site est **MySQL/MariaDB-exclusif** dans le code (FULLTEXT + `ON DUPLICATE
  KEY`, `DEFAULT (UUID())`) — revalidé en Phase E2. **VERIFIED**.
- **Render ne propose AUCUNE base MySQL** (seulement Postgres), et sa base free
  **expire au bout de 30 jours** sans backup. **DOCUMENTED**.
- **Render free = filesystem éphémère** : les **photos uploadées** de la
  collection sont **perdues** à chaque redéploiement/arrêt/mise en veille, et le
  **disque persistant est réservé aux offres payantes**. **DOCUMENTED** (bloquant).
- Render free « spin-down » après 15 min d'inactivité (~1 min de reprise) →
  **latence/floche au moment du jury**. **DOCUMENTED**.

**Classement des scénarios** (justifications en `02-` et `06-`) :

| Scénario | Classement |
|----------|-----------|
| A — Render (app) + MySQL externe gratuit (ex. TiDB Starter) | **POSSIBLE WITH CONDITIONS** |
| B — Render + PostgreSQL/Neon | **NOT RECOMMENDED** (exige une migration de code) |
| C — Render + MySQL dans un conteneur + disque persistant | **BLOCKED** (disque = payant) |
| D — Hébergement PHP/MySQL gratuit (type GoogieHost) | **RECOMMENDED (hors Render)** |

> Le meilleur scénario **strictement Render** à 0 € = **A**, mais il reste
> fragile (TLS à configurer, uploads éphémères, spin-down). Le meilleur scénario
> **global** pour le jury à 0 € avec persistance = **D**.

## Navigation

| Doc | Contenu |
|-----|---------|
| [`01-deployment-baseline.md`](./01-deployment-baseline.md) | État réel (Dockerfile, PDO, docroot, uploads/sessions, migrations, santé) |
| [`02-render-architecture-options.md`](./02-render-architecture-options.md) | Comparaison A/B/C/D confrontée au code |
| [`03-cost-and-provider-evidence.md`](./03-cost-and-provider-evidence.md) | Prix/limites + sources officielles citées |
| [`04-deployment-security-checklist.md`](./04-deployment-security-checklist.md) | Checklist config/sécurité + bloqueurs |
| [`05-jury-demonstration-plan.md`](./05-jury-demonstration-plan.md) | Plan de démo, données fictives, sauvegarde, secours |
| [`06-final-recommendation.md`](./06-final-recommendation.md) | Décision recommandée + questions à trancher |

## Verdict de l'audit

**PASS WITH LIMITATIONS** — les faits du dépôt et les limites Render sont établis
avec preuve ; les caractéristiques exactes des bases externes gratuites (support
FULLTEXT/TLS par compte) restent **à vérifier dans un compte** (**NOT VERIFIED**),
la création de compte étant interdite ici.

## Étiquettes de preuve

**VERIFIED** (code/exécution) · **DOCUMENTED** (doc officielle citée, avec URL et
date) · **INFERRED** (déduction justifiée) · **NOT VERIFIED** (non vérifiable ici)
· **BLOCKED** (action/validation extérieure requise).
