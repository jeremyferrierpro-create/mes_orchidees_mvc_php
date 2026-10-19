# PHASE E2 — Audit de la configuration réelle des bases de données

Audit **en lecture seule** : aucun code applicatif modifié, aucune écriture en
base, aucun processus arrêté, aucun déploiement, aucun secret révélé.

## Contexte et objectif

Aider le porteur du projet (formation DWWM, budget 0 €/mois) à trancher une
question d'architecture : le site tourne-t-il réellement sur MySQL/MariaDB,
existe-t-il une compatibilité PostgreSQL, peut-on l'héberger gratuitement sur
GoogieHost, faut-il du multi-moteurs, et comment le présenter au jury ?

## Navigation des livrables

| Doc | Contenu |
|-----|---------|
| [`01-real-database-configuration.md`](./01-real-database-configuration.md) | Connexions réelles, séparation dev/test, environnement XAMPP actif |
| [`02-sql-engine-compatibility.md`](./02-sql-engine-compatibility.md) | Dépendances SQL propres au moteur, état des schémas PostgreSQL |
| [`03-hosting-googiehost-assessment.md`](./03-hosting-googiehost-assessment.md) | Faisabilité et limites de l'offre gratuite GoogieHost |
| [`04-architecture-options.md`](./04-architecture-options.md) | Comparaison A/B/C et recommandation argumentée |
| [`05-jury-demo-plan.md`](./05-jury-demo-plan.md) | Plan de démonstration au jury + solution de secours |
| [`06-final-decision.md`](./06-final-decision.md) | Décisions, questions ouvertes, recommandation finale |

## Réponse courte aux 6 questions de la mission

1. **Base réellement utilisée** : MariaDB 10.4.32 (MySQL-compatible), base
   `mes_orchidees`. **VERIFIED** par connexion de diagnostic (doc 01).
2. **dev / test / distant** : dev = `mes_orchidees`, tests = `mes_orchidees_test`
   (isolation par injection `DB_DSN` avant `env.php`) ; aucune base distante
   configurée localement. **VERIFIED** (doc 01).
3. **Compatibilité PostgreSQL** : **NON** active. Le code PHP est réécrit pour
   MySQL depuis la Phase 02 ; les schémas `neon`/`supabase` existent mais sont
   **obsolètes** et inutilisés ; `pdo_pgsql` n'est pas installé. **VERIFIED** (doc 02).
4. **GoogieHost gratuit** : **compatible sous conditions** (hôte MySQL + PHP 8.x,
   mais attention version MySQL, docroot `/public`, `.env`, inactivité). **VERIFIED partiel / DOCUMENTED** (doc 03).
5. **Mono ou multi-moteur** : **recommandation = mono-moteur MySQL/MariaDB** ;
   le multi-moteurs est coûteux et non justifié pour un jury DWWM à 0 € (doc 04).
6. **Démonstration jury** : hébergement gratuit MySQL **ou** local + plan de
   secours hors-ligne détaillé (doc 05).

## Verdict de l'audit

**Verdict : PASS WITH LIMITATIONS** — les faits demandés sont établis avec
preuve ; certaines caractéristiques GoogieHost exactes (version MySQL du compte,
cron, nombre de BDD) restent **à vérifier dans le compte** (non vérifiables sans
en créer un, ce que la mission interdit).

## Étiquette de preuve

Chaque constat porte : **VERIFIED** (observé dans le code/exécution),
**DOCUMENTED** (doc officielle citée), **INFERRED** (déduction justifiée),
**NOT VERIFIED** (non vérifiable ici), **BLOCKED** (action extérieure requise).
