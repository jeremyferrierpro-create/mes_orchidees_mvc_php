# PHASE 01 — CADRAGE FONCTIONNEL ET PRÉPARATION DE L'ARCHITECTURE

**Projet :** MES ORCHIDEES (`docs/mes_orchidees_php/`)
**Protocole :** v1.0 — Phase 01
**Date :** 2026-10-17
**Sources d'autorité :** `prompt_maitre.txt` (§1 nomenclature), `.docs/USER_STORIES.md`
(88 stories), `data/schema_mysql.sql`, API existante (`api/**`), `.docs/MCD_MLD.md`.
**Actions réalisées :** D5 (sauvegarde Git) et D6 (`stack.md`) exécutées sous autorisation.

---

## 0. Actions préalables accomplies (traçabilité)

### 0.1 D5 — Sauvegarde Git
- Branche créée : **`backup/pre-mvc-baseline`**, commit **`c88eb26`**
  (« backup(Phase01-D5) : sauvegarde du travail préexistant… »), 9 fichiers / +3176 / −34.
- Contenu sauvegardé : les modifications préexistantes (`index.php`, `.htaccess`,
  `assets/css/*`, `orchid-service.js`) + doublons `.docs/mcd_mld.lo1/.loo`.
- Vérifié : `.env` **non** stagé (ignoré par `.gitignore`).
- `main` restauré propre à **`c031e12`** (baseline d'origine intacte, WIP conservé/réversible).

### 0.2 D6 — `stack.md`
- Réécrit dans les deux copies (`mes_orchidees_php/.agent/rules/stack.md` et racine) :
  **PHP 8.3 / MVC / Mithridatem Router / MySQL / SCSS / JS**, namespace `App\`, docroot cible
  `public/`. La contradiction Next.js/TypeScript est levée.

---

## 1. Rôles et acteurs (extraits de USER_STORIES + `role` ENUM)

| Rôle | Clé (`users.role`) | Accès | Preuve |
|---|---|---|---|
| **Visiteur** (non connecté) | — | Accueil, Encyclopédie (lecture/recherche), Conseils, mentions/confidentialité/accessibilité | DONE-08, DONE-25/26 |
| **Utilisateur connecté** | `user` | + Ma Collection, soins, proposer une orchidée, notifications | DONE-09, DONE-32 |
| **Modérateur** | `moderateur` | Modération des propositions (validation/refus) — *présent dans l'ENUM, à confirmer à l'usage* | schema l.49, DONE-10 |
| **Administrateur** | `admin` | CRUD orchidées & conseils, gestion des utilisateurs, stats | DONE-10, DONE-40, PROG-05 |

> Règle PROG-05 : « le serveur vérifie les droits, pas juste le CSS » → **autorisation
> serveur obligatoire** sur chaque route (via `GrantCheckerInterface` du router).

---

## 2. Entités CRUD (liste exacte)

Le modèle compte **6 entités persistées** + 1 workflow. Correspondance entité ↔ table ↔
service JS d'origine :

| # | Entité (singulier) | Table MySQL | Collection (route) | Rôle CRUD | Rôle lecture |
|---|---|---|---|---|---|
| E1 | **User** | `users` | `/users` | admin | admin (self en profil) |
| E2 | **Orchid** | `orchids` | `/orchids` | admin (+ proposition user) | visiteur |
| E3 | **Collection** | `collections` | `/collections` | user (owner) | owner uniquement |
| E4 | **Soin** | `soins` | `/soins` | user (owner, via sa collection) | owner |
| E5 | **Conseil** | `conseils` | `/conseils` | admin | visiteur |
| E6 | **Notification** | `notifications` | `/notifications` | système/user | owner |
| W1 | **Proposition / Modération** | `orchids.is_published` + `proposed_by` | `/moderation` | admin/modo | — |

### 2.1 Attributs par entité (issus de `schema_mysql.sql`, non inventés)

- **User** : `id (UUID)`, `nom`, `prenom`, `email` (unique), `password_hash` (**`PASSWORD_DEFAULT`**,
  **jamais en clair**), `role` ENUM(user|admin|moderateur), `created_at`, `updated_at`.
- **Orchid** : `id` (slug VARCHAR200, ex `acacalis_cyanea`), `name`, `vernacular`,
  `botanical_order`, `species`, `genre`, `family` (défaut 'Orchidaceae'), `subfamily`,
  `tribu`, `subtribu`, `behavior` (épiphyte/terrestre/hémi…), `discovered`, `origin`, `img`,
  `short_desc`, `long_desc`, `is_published`, `proposed_by (FK user)`, timestamps.
  → 13 champs taxonomiques (cf. DONE-27). Index FULLTEXT(nom, vernaculaire, short, origin).
- **Collection** : `id (UUID)`, `legacy_id` (clé JS `col-xxx`), `user_id` (FK), `orchid_id`
  (FK), `location`, `notes`, `temp`, `hygro`, `light`, `ventilation`, `added_at`, `updated_at`.
  UNIQUE(user_id, legacy_id) ; FK user ON DELETE CASCADE ; orchid ON DELETE RESTRICT.
- **Soin** : `id (UUID)`, `legacy_id`, `collection_id` (FK CASCADE), `soin_date`, `type`
  (arrosage/engrais/rempotage/ravageurs/substrat), `notes`, booléens `engrais/substrat/
  ravageurs`, `created_at`.
- **Conseil** : `id` (slug), `type` ENUM(category|species), `name`, `img`, `category`,
  `content`, `care_cards` (JSON), `orchid_id` (FK optionnel SET NULL), timestamps. FULLTEXT.
- **Notification** : `id (UUID)`, `legacy_id`, `user_id` (FK CASCADE), `collection_id`
  (FK SET NULL), `type` (défaut `rappel_soin`), `message`, `is_read`, `notif_date`, `created_at`.

### 2.2 Relations
- User **1—N** Collection ; Orchid **1—N** Collection (→ Collection = jointure enrichie).
- Collection **1—N** Soin.
- User **1—N** Notification ; Collection **0..1—N** Notification.
- User **1—N** Orchid proposé (`proposed_by`) ; Conseil **N—0..1** Orchid (fiches espèces).
- Vue `v_collection_complete` = Collection ⋈ User ⋈ Orchid (remplace le `find()` JS).

---

## 3. Règles de gestion métier (formalisées — sources user stories)

| ID | Règle | Source |
|---|---|---|
| RG1 | Une collection n'est visible/modifiable **que par son propriétaire** (isolation stricte). | TODO-21, PROG/memory isolation |
| RG2 | Suppression d'un utilisateur → cascade sur ses collections, soins (via collections) et notifications. | schema FK |
| RG3 | Un **soin** (ex. arrosage) génère une **notification de rappel J+7** (mécanisme à rendre fiable, côté serveur). | DONE-36, TODO-23 |
| RG4 | Un utilisateur connecté peut **proposer** une orchidée (`is_published=0`, `proposed_by`). | TODO-22 |
| RG5 | L'**admin/modérateur** valide ou refuse une proposition → `is_published=1` + **notification** à l'auteur. | TODO-22, DONE-10 |
| RG6 | CRUD **orchidées** et **conseils** réservé aux rôles privilégiés (serveur). | TODO-19, TODO-20 |
| RG7 | Ajout d'une orchidée déjà dans la collection du user = **doublon ignoré** (toast). | DONE-32 |
| RG8 | Mots de passe hachés via **`password_hash(..., PASSWORD_DEFAULT)`** (algorithme géré/tenus à jour par PHP, **pas de bcrypt en dur**), **aucun compte de démo en clair** ; vérif. via `password_verify()`. | TODO-16, recommandation formateur |
| RG9 | **CSRF**, validation serveur et anti-XSS sur toute écriture. | TODO-18 |
| RG10 | Recherche = à partir de 3 lettres ; sur `name/vernaculaire/short/origin` (FULLTEXT). | DONE-25/26, schema |
| RG11 | Le conseil est de type `category` (général) ou `species` (lié à une orchidée). | schema, DONE-29 |

> **Note — Hachage des mots de passe (recommandation formateur).** Le hachage se fait avec
> `password_hash($mdp, PASSWORD_DEFAULT)` et la vérification avec `password_verify()`,
> **sans** épingler `PASSWORD_BCRYPT` en dur. `PASSWORD_DEFAULT` suit l'algorithme recommandé
> par la version de PHP et **évolue automatiquement** (coûts/algo mis à jour), ce qui évite
> de devoir retoucher le code plus tard. Le hash stocké reste auto-descrit (algo + coût + sel
> inclus), donc les mots de passe existants continuent de se vérifier après une mise à jour
> de PHP. Colonne `password_hash VARCHAR(255)` inchangée.

---

## 4. Mapping complet des routes — Mithridatem Router

**Convention retenue** (celle formulée dans la demande Phase 01, alignée sur le §1 du
protocole) : liste au pluriel, fiche/création/suppression au singulier avec segment `{id}`,
mise à jour via `/update/{entite}`.

> ⚠ **Note d'ambiguïté documentée** : le `prompt_maitre.txt` donne deux formulations pour
> l'update (`/{entité}/update` *et* `/update/{entité}`) et pour la création (`/{entité}/new`).
> La demande Phase 01 tranche en faveur de `/update/{entite}` (cf. `/update/orchid`).
> Schéma ci-dessous = proposition harmonisée, **à valider avant génération du code**.

### 4.1 Authentification / session
| But | Route | Verbe | Handler | Grant |
|---|---|---|---|---|
| Formulaire + page connexion | `/login` | GET | `AuthController::loginForm` | public |
| Traiter connexion | `/login` | POST | `AuthController::login` | public |
| Formulaire inscription | `/register` | GET | `AuthController::registerForm` | public |
| Traiter inscription | `/register` | POST | `AuthController::register` | public |
| Déconnexion | `/logout` | POST | `AuthController::logout` | auth |
| Moi (session courante) | `/me` | GET | `AuthController::me` | auth |

### 4.2 Pages de contenu (vues)
| Route | Verbe | Contrôleur | Grant |
|---|---|---|---|
| `/` (accueil) | GET | `HomeController::index` | public |
| `/encyclopedie` | GET | `EncyclopedieController::index` | public |
| `/macollection` | GET | `CollectionController::dashboard` | auth |
| `/conseils` | GET | `ConseilController::index` | public |
| `/administration` | GET | `AdminController::dashboard` | admin |
| `/confidentialite`, `/mentions`, `/accessibilite` | GET | `PageController::*` | public |

### 4.3 CRUD par entité (API + actions)

**Orchids** (lecture publique, écriture admin, proposition user)
| Cas | Route | Verbe | Grant (router) |
|---|---|---|---|
| Liste | `/orchids` | GET | public |
| Recherche | `/orchids/search?q=` | GET | public |
| Fiche | `/orchid/{id}` | GET | public (si `is_published`) |
| Formulaire création | `/orchid/new` | GET | admin |
| Création | `/orchid/new` | POST | admin |
| Proposition (user) | `/orchid/propose` | POST | auth (→ `is_published=0`) |
| Update (form) | `/update/orchid/{id}` | GET | admin |
| Update (traitement) | `/update/orchid` | POST | admin |
| Suppression | `/orchid/delete/{id}` | POST | admin |

**Conseils** (identique, CRUD admin, lecture publique)
`/conseils` · `/conseil/{id}` · `/conseil/new` (GET/POST) · `/update/conseil(/{id})` · `/conseil/delete/{id}`

**Collections** (owner uniquement — RG1)
`/collections` (GET, filtrées owner) · `/collection/{id}` · `/collection/new` (POST : ajout
depuis orchidée) · `/update/collection` (POST) · `/collection/delete/{id}` (POST)

**Soins** (rattachés à une collection de l'owner)
`/soins?collection={id}` (GET) · `/soin/new` (POST) · `/update/soin` (POST) · `/soin/delete/{id}` (POST)

**Notifications** (owner ; lecture + marquage lu)
`/notifications` (GET) · `/notification/{id}` · `/notification/{id}/read` (POST) · `/notification/delete/{id}` (POST)

**Users** (admin)
`/users` (GET) · `/user/new` · `/user/{id}` · `/update/user` · `/user/delete/{id}`

**Modération / Workflow (W1)**
`/moderation` (GET liste `is_published=0`) · `/moderation/{id}/approve` (POST) ·
`/moderation/{id}/reject` (POST) — génère notification (RG5).

### 4.4 Déclaration Router (motif, **non écrit** — phase 02)
```php
$router->mapController('GET', '/orchid/{id}', OrchidController::class, 'show');
$router->mapController('POST', '/orchid/delete/{id}', OrchidController::class, 'delete')
       ->withGrant('admin');          // GrantCheckerInterface = autorisation serveur
```
- Segments `{id}` natifs ; wildcard `/*` possible pour pages statiques.
- `setBasePath()` à prévoir si l'app est servie sous un sous-répertoire (Render).

---

## 5. Schéma SQL cible — MySQL

**Base existante réutilisée :** `data/schema_mysql.sql` (6 tables, vue, seed partiel) est
**déjà conforme à la cible MySQL 8** (InnoDB, utf8mb4, FULLTEXT ngram, JSON, ENUM). Il sert
de **source** au schéma cible. Normalisations à appliquer en phase 02 (non faites ici) :

1. **FK en ligne invalides en MySQL.** `orchids.proposed_by CHAR(36) REFERENCES users(id)`
   et `conseils.orchid_id VARCHAR(200) REFERENCES orchids(id)` sont **ignorés par InnoDB**
   (MySQL n'accepte pas `REFERENCES` au niveau colonne). → les convertir en
   `CONSTRAINT fk_... FOREIGN KEY (...) REFERENCES ...` au niveau table.
2. **Cohérence charset/collation** des colonnes FK (`orchid_id` VARCHAR(200) même type que
   `orchids.id`) — vérifier utf8mb4_unicode_ci de part et d'autre (sinon FK rejetée).
3. **`DEFAULT (CURRENT_DATE)`** sur `soins.soin_date` : OK ≥ 8.0.13 ; sinon basculer sur
   `DEFAULT CURRENT_TIMESTAMP` côté application.
4. **Index de modération** : ajouter `KEY idx_orchids_published (is_published, proposed_by)`
   (liste `is_published=0`).
5. **MCD "Proposition"** : le workflow est **embarqué** dans `orchids` (RG4/RG5). Option
   alternative = table `propositions` dédiée. → **décision D-Prop** (§7). Recommandé :
   garder l'option embarquée (simple, colle au schéma actuel et à `is_published`).
6. **RLS PostgreSQL → applicatif.** MySQL n'ayant pas de RLS, l'isolation RG1 est portée
   par le **Repository** (filtre `user_id` systématique + contrôle contrôleur). Documenté.

> Les schémas **concurrents** (`schema_neon.sql`, `schema_supabase.sql`, `api/migration/schema.sql`)
> sont **écartés** au profit de `schema_mysql.sql` (décision D3 tranchée : **MySQL**, cf. D6).

### 5.1 Ordre de création (dépendances FK)
`users` → `orchids` → `conseils` → `collections` → `soins` → `notifications` → vue.

---

## 6. Correspondance Migration HTML→PHP (existant → cible MVC)

| Aujourd'hui | Cible MVC |
|---|---|
| Pages `.php` racine + `includes/` | Vues `templates/` servies par contrôleurs |
| Peuplement client via `assets/js/data/*.json` | Lecture serveur via **Repositories → PDO MySQL** (RG1 isolation) |
| `api/*/*.php` endpoints monolithiques | Contrôleurs + routes Router |
| `api/middleware/auth.php` (fonctions globales) | `GrantCheckerInterface` + service Auth (sessions/CSRF) |
| `HomeController`-du-cours (switch) | Front-controller `public/index.php` + dispatch Router |

---

## 7. Décisions restantes AVANT la phase 02 (implémentation)

- **D1** — Mode d'intégration du router (Packagist / VCS-chemin / vendoring) : recommande
  **VCS/chemin** en local. → **à confirmer**.
- **D-Prop** — Modération : table dédiée `propositions` vs **embarqué** (`is_published`).
  → recommande **embarqué**.
- **D-Auth** — Persistance de session : **cookies de session PHP** vs **JWT** actuel. → à
  trancher (le routeur fournit `getBearerToken` mais les pages sont du HTML server-side).
- **D-Rappels** — Génération des notifications J+7 : plan/cron vs calcul à la lecture.
- **D-Rôles** — Faut-il un rôle `moderateur` distinct ou admin suffit ? (ENUM le prévoit).

---

## 8. Critères d'acceptation de la Phase 01
- ✅ Liste d'entités exacte + attributs sourcés (non inventés). — §2
- ✅ Règles de gestion formalisées et tracées aux user stories. — §3
- ✅ Mapping de routes complet selon la nomenclature imposée. — §4
- ✅ Schéma MySQL cible décrit + corrections identifiées. — §5
- ✅ D5/D6 exécutés sous autorisation. — §0
- ⏸ **Aucun** `composer.json`, **aucun** déplacement `public/`, **aucun** code produit
  (conformément à la consigne d'arrêt).

---

**ARRÊT.** En attente d'instruction explicite pour : valider la nomenclature de routes
(§4), trancher les décisions §7, puis autoriser la création de `composer.json` et du
front-controller `public/index.php` (Phase 02).
