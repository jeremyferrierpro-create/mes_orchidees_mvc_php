# PHASE 02.5 — Extraction à la racine, grand nettoyage MVC & migration MySQL

**Rapport d'actions — 2026-10-17**
Auteur : Jérémy Ferrier · Projet : MES ORCHIDÉES (fil rouge DWWM)

---

## 1. Objectif

L'application ne devait plus résider dans un sous-dossier de documentation
(`docs/mes_orchidees_php/`) mais constituer le **projet applicatif à la racine**
du workspace, parfaitement exécutable dans le navigateur via le routeur du
formateur (Mithridatem), avec un schéma et des seeds **MySQL validés**.

---

## 2. Extraction & restructuration de l'arborescence

### Avant → Après
| | Emplacement |
|---|---|
| **Avant** | `c:\Users\jerem\Desktop\mes_orchidees\docs\mes_orchidees_php\` (dépôt git = ce sous-dossier) |
| **Après** | `c:\Users\jerem\Desktop\mes_orchidees\` (racine = projet applicatif, dépôt git re-localisé) |

### Structure finale (cible MVC)
```
mes_orchidees/                 ← racine applicative + dépôt git
├── public/                    ← SEUL point d'entrée exposé au web (DocumentRoot)
│   ├── index.php              ← front-controller unique (Mithridatem Router)
│   ├── .htaccess              ← réécriture d'URL → index.php (Apache)
│   ├── assets/  manifest.json  sw.js
├── src/  (Controller/ Entity/ Repository/ Security/ Service/)
├── templates/  (layout.php  pages/  components/)
├── config/  (routes.php  db.php  env.php  helpers.php)
├── lib/Mithridatem/Router/    ← routeur autonome du formateur
├── data/  (schema_mysql.sql  seeds/  migrations/)
├── vendor/  archive/          ← archive = legacy .php/api/includes HORS web
├── server.php                 ← routeur de dev pour `php -S` (non utilisé en prod)
├── composer.json/.lock  Dockerfile  README.md  .env(.example)  .gitignore  .gitattributes
```

### Collisions traitées lors de la remontée
- **`.agent/`** : le workspace possédait déjà son propre `.agent/`. Le doublon
  interne à l'app a été **supprimé** ; le `.agent/` racine est conservé et
  **désindexé du dépôt** (`git rm -r --cached .agent`) + ignoré (`.gitignore`).
- **`docs/`** : les rapports du projet ont été **fusionnés** dans le dossier
  canonique `docs/project-foundation/` (phase-00 → phase-03) situé hors de
  l'app ; le matériel de cours (`MODULES/`, `site_html/`,
  `support_formateur/`) est **ignoré par git** pour ne pas polluer le dépôt
  applicatif.

### Nettoyage confirmé
- Aucun ancien `.php` monolithique, aucun `api/`, aucun `includes/` à la racine
  ni dans `public/` : tout est relocalisé dans `archive/` (hors docroot).
- `public/` ne contient que l'entry point et les ressources statiques.
- La commande `git status` ne fait plus remonter le matériel de cours.

---

## 3. Correction du schéma MySQL (`data/schema_mysql.sql`)

Le schéma visait déjà MySQL 8 (InnoDB, `utf8mb4_unicode_ci`, clés étrangères
**explicites au niveau table** `CONSTRAINT fk_…`). Corrections apportées :

1. **Portabilité du moteur FULLTEXT** — suppression des deux directives
   `WITH PARSER ngram` (syntaxe **MySQL-8-only** que le serveur local
   **MariaDB 10.4** rejette). Le parser par défaut suffit pour des termes
   botaniques latins et reste valide sur MySQL 8 **et** MariaDB.
2. **Inline `REFERENCES`** — déjà remplacées par des `CONSTRAINT` de table.
3. **Types des colonnes FK conformes aux PK cibles** : `orchid_id`
   `VARCHAR(200)` ↔ `orchids.id VARCHAR(200)` ; `user_id` / `collection_id` /
   `proposed_by` `CHAR(36)` ↔ PK `CHAR(36)` — même charset/collation.
4. **Le schéma ne contient plus que de la DDL** : l'`INSERT users` interne
   (hashs factices `$2y$12$HASH_A_REMPLACER`) a été **retiré** au profit des
   scripts `data/seeds/`.

Vérification : `DROP DATABASE` + rejoué à blanc sur **MariaDB 10.4.32** →
**6 tables + la vue `v_collection_complete` créées sans erreur**.

---

## 4. Seeds MySQL (100 % valides)

| Seed | État | Conversion |
|---|---|---|
| `01_seed_users` | déjà MySQL | hash bcrypt **vérifiés** par `password_verify()` |
| `02_seed_orchids` | déjà MySQL | 21 espèces |
| `03_seed_conseils` | PostgreSQL → MySQL | `public.`/`ON CONFLICT`/`EXCLUDED`/`NOW()`/`DO $$` retirés |
| `04_seed_collections` | PostgreSQL → MySQL | `gen_random_uuid()` → `DEFAULT (UUID())`, offsets `+01/+02` retirés, sous-requêtes conservées, `ON DUPLICATE KEY UPDATE` idempotent |
| `05_seed_soins` | PostgreSQL → MySQL | idem, `legacy_id` UNIQUE → upsert |
| `06_seed_notifications` | PostgreSQL → MySQL | `DO $$ … ASSERT` → `DELETE … IN(1..6)` + `INSERT` idempotent |

Mots de passe de seed générés/validés avec `password_hash(…, PASSWORD_DEFAULT)`
(`01_seed_users.sql`) :
- `jeremy.ferrierpro@gmail.com` / **`demoadmin`** → rôle **admin** ✔ vérifié
- `jessica.amateur@gmail.com` / **`demouser`** → rôle user ✔ vérifié
- `admin@mesorchidees.fr` / **`demouser`** → rôle user (proposition/modo)

Rejeu intégral des 6 seeds dans l'ordre → comptes de contrôle OK :
`3 users · 21 orchidées · 6+10 conseils · 6 collections · 4 soins · 6 notifications`.

---

## 5. Validation & tests navigateur

- **`php -l`** sur **20 fichiers** (`src/`, `config/`, `public/`) + `server.php` → **0 erreur**.
- **Serveur** : `php -S 127.0.0.1:8000 -t public server.php` (PHP 8.3.33).

### Nomenclature du routeur (Mithridatem) — statut HTTP
| Route (GET sauf mention) | Attendu | Obtenu |
|---|---|---|
| `/` (accueil) | 200 | **200** |
| `/encyclopedie` | 200 (21 cartes serveur) | **200** · 21 `.orchid-card` |
| `/encyclopedie?search=vanille` | 200 | **200** |
| `/orchid/{id}` (`acacalis_cyanea`, `vanilla_planifolia`) | 200 | **200** |
| `/orchid/nope` | 404 | **404** |
| `/orchids` (JSON) | 200 · 21 items | **200** · JSON valide, 21 |
| `/orchids/search?q=vanille` (JSON) | 200 | **200** |
| `/login` · `/register` | 200 (forme + `csrf_token`) | **200** |
| `/orchid/new` · `/update/orchid/{id}` (non connecté) | 403 | **403** |
| `/orchid/delete/{id}` en GET | 405 | **405** |
| asset statique `/assets/css/style.css` | 200 | **200** |

### Parcours authentifié (admin) — flux complet
1. `POST /login` (csrf + bcrypt seed) → **302**.
2. Après connexion : `/orchid/new`, `/update/orchid/{id}`, `/me` passent de **403 → 200**.
3. **Cycle d'écriture** contre la base régénérée : `POST /orchid/new` crée
   `zz_test_extract` → `GET /orchid/zz_test_extract` = **200** →
   `POST /orchid/delete/zz_test_extract` → ligne supprimée, total redevenu **21**.

**Aucune erreur PDO, aucun 500.**

---

## 6. Comment tester dans le navigateur

```bash
# depuis la racine du projet
php -S localhost:8000 -t public server.php
# ouvrir http://localhost:8000
```
Sous Apache/Laragon (DocumentRoot = `public/`), le `public/.htaccess` assure la
réécriture — `server.php` n'est alors pas nécessaire.

Comptes de démonstration : voir §4 (`demoadmin` / `demouser`).

---

## 7. État & suites

- ✅ App extraite à la racine, `public/` unique entry point, legacy archivé.
- ✅ Schéma + 6 seeds **MySQL validés et rejoués** sur MariaDB 10.4.
- ✅ Routeur Mithridatem opérationnel, CRUD orchidées (lecture/écriture) vérifié.
- ⚠️ Les modifications (dossier git re-localisé, suppressions/archives, nouveaux
  fichiers) sont **présentes sur le disque mais non committées** (aucun commit
  sans demande explicite).
- 🔜 Prochains incréments Phase 03 (non démarrés) : Collection/Soin/Conseil/User
  (entités + contrôleurs), **Modération** (écran d'approbation/rejet), et les
  écrans `/administration`, `/macollection`, `/conseils` aujourd'hui en 501.

**Conclusion : l'application est prête à être testée directement en navigateur.**
