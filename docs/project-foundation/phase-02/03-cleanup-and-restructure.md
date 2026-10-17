# PHASE 02.5 — Nettoyage & restructuration de l'arborescence

> **Projet :** MES ORCHIDEES (fil rouge DWWM) · **Auteur :** Jérémy Ferrier
> **Date :** 2026-10-17 · **Base de travail :** `docs/mes_orchidees_php/`
> **Prérequis :** Phase 02 (architecture MVC, routeur Mithridatem, auth session, schéma MySQL) validée.

---

## 1. Objectif

Avant d'attaquer le contenu métier de la Phase 03, la racine du projet devait refléter une
**architecture MVC stricte** : un unique point d'entrée web sous `public/`, aucun fichier
hérité (pages `.php` monolithiques, `api/`, `includes/`) à la racine, et les assets servis
correctement. Les anciens fichiers ne sont **pas perdus** : ils sont archivés sous
`archive/legacy/` (déplacements `git mv`, donc l'historique et les *renames* sont conservés).

## 2. Déplacements effectués (git mv → historiques conservés)

| Élément d'origine (racine) | Nouvelle position | Rôle |
|----------------------------|-------------------|------|
| `assets/` (css, js, images, scss) | `public/assets/` | accessible au serveur web (docroot) |
| `manifest.json` | `public/manifest.json` | PWA, servi à la racine web |
| `sw.js` | `public/sw.js` | service worker (scope `/`) |
| `api/` (endpoints JSON legacy) | `archive/legacy/api/` | **archivé** — SQL/PDO réécrit dans les repositories |
| `includes/` (head, sidebar, header, footer…) | `archive/legacy/includes/` | **archivé** — réécriture en `templates/components/` |
| `index.php`, `encyclopedie.php`, `macollection.php`, `conseils.php`, `administration.php`, `authentification.php`, `deconnexion.php`, `confidentialite.php`, `mentions.php`, `accessibilite.php` | `archive/legacy/pages/` | **archivé** — HTML extrait vers les templates MVC |
| `.htaccess` (racine) | `archive/legacy/root.htaccess` | remplacé par `public/.htaccess` |

## 3. Racine après nettoyage

```
mes_orchidees_php/
├── public/          ← SEUL point d'entrée web (index.php, .htaccess, assets/, manifest.json, sw.js)
├── src/             ← code métier (Controller, Entity, Repository, Security, Service)
├── templates/       ← vues + composants (layout, pages/, components/)
├── config/          ← env.php, db.php, helpers.php, routes.php
├── lib/             ← routeur Mithridatem (vendored)
├── data/            ← schema_mysql.sql + migrations + seeds
├── archive/legacy/  ← api/, includes/, pages/, root.htaccess (référence, hors service)
├── vendor/          ← autoload Composer
├── composer.json / .lock, .env(.example), Dockerfile, README.md
```

**Vérification :** plus aucun `.php` applicatif ni dossier `assets/`, `api/`, `includes/` à la
racine ; `public/` est l'unique surface exposée.

## 4. Refonte des composants de gabarit

Les `includes/*.php` legacy (HTML + `require_once`) ont été réécrits en composants PHP MVC
consommant les helpers globaux (`url()`, `asset()`, `e()`, `is_logged_in()`, `has_role()`) :

- `templates/layout.php` — gabarit global (injecte `head`, `sidebar`, `header`, footer, zone
  `main` + messages flash, la variable `$content` des vues).
- `templates/components/head.php` — `<head>` SEO/OG/Twitter, CSP, manifest, polices, styles.
- `templates/components/sidebar.php` — navigation, bloc admin (rôles), bloc session (connexion/déconnexion avec CSRF).
- `templates/components/header.php` — entête + bouton menu mobile.
- `templates/components/footer.php` — liens légaux + conteneur toasts + `app.js`.
- `templates/components/orchid_card.php` — carte d'orchidée (réutilisée par la grille, cf. Phase 03).

L'ancien `<head>` (variables `$title`/`$description`) est passé à un contrat propre exposé par
`AbstractController::render()` : `$pageTitle`, `$content`, `$flashes`, `$pageData`.

## 5. Helpers & front-controller

- **`config/helpers.php`** (nouveau) : `base_path()`, `url()`, `asset()`, `e()`,
  `is_logged_in()`, `current_user()`, `has_role()` — protégés par `function_exists`.
  Gère le **sous-répertoire** (Render) via `APP_BASE_PATH`.
- **`public/index.php`** : câblage du chargeur `.env` → **`db.php`** → `helpers.php`
  (ajout de `require config/db.php` — correction d'un oubli Phase 02 : `getDb()` n'était pas
  chargé, d'où les 500 sur les routes appelant un repository).

## 6. `.env` local

Le `.env` local était resté en configuration **Neon/PostgreSQL + JWT** (abandonnée en Phase 02).
Réaligné sur **MySQL/MariaDB** :

```
DB_DSN / DB_HOST / DB_PORT / DB_NAME / DB_USER / DB_PASS
APP_ENV, APP_DEBUG, COOKIE_DOMAIN, COOKIE_SECURE, COOKIE_SAMESITE
```

- Suppression de `NEON_DSN` et `JWT_SECRET` (non utilisés).
- En dev Laragon, `COOKIE_DOMAIN=` **vide** (cookie *host-only*) pour que la session fonctionne
  sur `127.0.0.1` **et** `localhost` ; `COOKIE_SAMESITE=Lax`.
- ⚠️ Le `.env` n'est pas committé (`.gitignore`). Le `.env.example` documente les clés MySQL.

## 7. Normalisation MySQL des seeds (legacy PostgreSQL)

Les fichiers `data/seeds/*.sql` étaient restés en syntaxe **PostgreSQL** (`public.`,
`gen_random_uuid()`, `ON CONFLICT … DO UPDATE`, blocs `DO $$ … $$`, hashs argon2id factices),
incompatibles avec MySQL/MariaDB. Convertis (au moins pour l'incrément 1) :

- `01_seed_users.sql` : `INSERT INTO users …` (colonne `id` = `DEFAULT uuid()`),
  upsert `ON DUPLICATE KEY UPDATE`, **mots de passe = hashs `PASSWORD_DEFAULT` (bcrypt)** réels
  pour les comptes de démo (`demoadmin` / `demouser`), vérification par `SELECT CONCAT(...)`.
- `02_seed_orchids.sql` : retrait de `public.`, `ON CONFLICT` → `ON DUPLICATE KEY UPDATE`,
  suppression du bloc `DO $$`, en-tête mis à jour (MySQL 8 / MariaDB, index FULLTEXT).

*(Les seeds `conseils`, `collections`, `soins`, `notifications` restent à convertir lors de
leurs incréments respectifs.)*

## 8. Contrôle qualité

- `php -l` sur **16 fichiers** (contrôleurs, entité, repository, helpers, layout, 6 composants,
  vues encyclopédie/fiche/formulaire, accueil, erreur) → **0 erreur de syntaxe**.
- Smoke test serveur (PHP intégré, `php -S`, docroot `public/`) — voir rapport Phase 03 §Tests.

## 9. Points d'attention / suite

- `public/assets/js/app.js` et ses services pointent encore vers `api/*.php` (archivés) avec
  **repli sur le JSON local** (`assets/js/data/*.json`). Non bloquant : les pages sont désormais
  **rendues côté serveur**. La réorientation de l'API JS vers les routes MVC JSON (`/orchids`,
  `/orchids/search`) est un chantier de *progressive enhancement* à mener plus tard.
- Le conteneur de grille serveur (`#orchid-grid-server`) est volontairement distinct du
  `#orchid-grid-container` legacy pour éviter que le peuplement JS n'écrase le rendu serveur.

## 10. Bilan

Racine **assainie** (un seul docroot `public/`), héritage **archivé sans perte** (renames git),
gabarit **refondu en composants MVC**, configuration **.env + seeds alignées MySQL**. Le projet
est prêt pour le développement métier incrémental de la Phase 03 (voir
`../phase-03/01-orchids-increment.md`).
