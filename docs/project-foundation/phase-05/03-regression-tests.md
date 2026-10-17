# 03 — Stratégie de non-régression (Phase 05)

## 1. Outils déjà présents (vérifiés avant tout ajout)

- `composer.json` : `require.php >= 8.1`, **aucune `require-dev`**.
- Aucun `tests/`, aucun `phpunit.xml` dans l'application. Le seul `phpunit.xml.dist`
  présent concerne la **librairie du formateur** (`docs/support_formateur/router-librairie`),
  pas l'application.
- `vendor/bin/phpunit` absent. PHP CLI 8.3.33 et Composer 2.10.3 disponibles.

→ **Aucune suite automatisée n'existait** (limitation 04B confirmée).

## 2. Choix : harnais maison SANS dépendance (justifié)

**Décision :** ne pas ajouter PHPUnit.

**Justification (proportionnalité, coût de maintenance) :**
- L'application n'a **aucune** dépendance ; `composer require-dev phpunit` tirerait un
  arbre `vendor/` de test et casserait la logique `--no-dev` du `Dockerfile`.
- Taille du projet (student fil rouge) : une pile complète serait disproportionnée.
- Un petit runner PHP natif est rejouable avec **une seule commande**, sans réseau
  d'installation, et sans surcharge de maintenance.

**Contrat d'isolation :** les tests ne lisent **jamais** le `.env` réel et ne dépendent
d'aucun compte personnel. Les tests d'intégration base sont pilotés par des variables
d'environnement `TEST_DB_*` fournies au moment de l'exécution ; sans elles, ils sont
`SKIPPED` (jamais présentés à tort comme réussis).

## 3. Livrables de test

- `tests/bootstrap.php` : charge l'autoloader PSR-4 réel + `config/helpers.php` ;
  expose `TestableController` (révèle les méthodes CSRF protégées) et `test_pdo()`.
- `tests/run.php` : runner + suites ; code retour 0/1 ; bilan `PASS/FAIL/SKIP`.
- `composer.json` : script `"test": "php tests/run.php"` ajouté.

## 4. Commandes (documentées, rejouables)

```
# Tests unitaires seuls (aucune base requise) :
php tests/run.php

# Avec intégration RG1 sur une base de test ISOLÉE (jamais la base de prod) :
#   1) créer la base de test depuis le schéma (nom réécrit, ne touche pas mes_orchidees) :
#      CREATE DATABASE mes_orchidees_test;  puis importer data/schema_mysql.sql
#   2) fournir l'environnement de test puis exécuter :
TEST_DB_USER=root TEST_DB_HOST=127.0.0.1 TEST_DB_NAME=mes_orchidees_test php tests/run.php
```

## 5. Suites et couverture

| Groupe | Ce qui est testé | Priorité protocole |
|---|---|---|
| XSS / `e()` | neutralisation balise, ENT_QUOTES, réflexif attribut, multiligne | 4 (entrées/sorties) |
| CSRF | génération jeton (64 hex), stabilité, valide/invalide/absent, `assertCsrf` → `CsrfException` | 1 |
| Helpers url | `url()` racine/sous-répertoire, `asset()` | 5 |
| Session/rôles | `is_logged_in`, `current_user`, `has_role` (rôle, liste, refus) | 2 |
| RG1 (base) | un user ne lit pas la notif d'un autre ; `markRead` tiers → 0 ligne | 3 |

## 6. Résultats (VERIFIED — exécutés en Phase 05)

- **Unitaire seul :** `PASS=25 FAIL=0 SKIP=2` (exit 0).
- **Avec base de test isolée :** `PASS=27 FAIL=0 SKIP=0` (exit 0).
- Lint : `php -l tests/bootstrap.php` et `tests/run.php` → 0 erreur.
- Isolement vérifié après run : 0 fixture résiduelle dans `mes_orchidees_test` ;
  base de développement `mes_orchidees` intacte (21 orchidées, 3 users, 0 trace de test).

## 7. Limites restantes (honnêtes)

- **Non couvert par la suite automatisée :** les parcours HTTP complets
  (connexion réelle, 400 CSRF sur endpoint HTTP, 403/404 grants). Ils restent
  **REPORTED** depuis la Phase 04B (reproduits en HTTP à l'époque) et **VERIFIED**
  partiellement par le Docker run (GET `/` → 200). Un harnais HTTP (curl + sessions)
  serait l'étape suivante, au coût de maintenance supérieur.
- **RG1 :** seul `NotificationRepository` est testé en intégration ; `Collection`/`Soin`
  partagent le même motif `WHERE user_id` mais ne sont pas couverts par un test dédié
  (INFERRED sûrs d'après le code, non VERIFIED par test).
- Aucun test de performance/charge (hors périmètre).

## 8. Verdict

**PASS** pour la suite mise en place et exécutée ; les lacunes résiduelles sont
explicitement documentées (§7), pas masquées.
