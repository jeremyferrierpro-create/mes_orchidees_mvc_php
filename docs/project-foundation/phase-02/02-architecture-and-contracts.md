# PHASE 02 — ARCHITECTURE, RESTRUCTURATION MVC ET CONTRATS

**Projet :** MES ORCHIDEES (`docs/mes_orchidees_php/`)
**Protocole :** v1.0 — Phase 02
**Date :** 2026-10-17
**Autorisation :** arbitrages d'architecture validés par l'humain (demande explicite).
**Périmètre :** integration Router/Composer, bascule docroot `public/`, arborescence MVC,
contrat de routes (§4 Phase 01), service d'authentification (sessions + `PASSWORD_DEFAULT`),
normalisation du schéma MySQL. **Aucun code métier d'entités** (réservé aux incréments CRUD
Phase 03+), conformément à la consigne d'arrêt.

---

## 1. Intégration du Router & Composer (vendoring)

### 1.1 Choix : duplication de la librairie dans le projet (option C — vendoring)
La librairie **Mithridatem Router** a été **copiée** dans le projet (et non pointée par un
dépôt externe `path`), afin que le projet soit **autonome et déployable** sur Render sans
dépendance au chemin `../support_formateur/`.

```
lib/Mithridatem/Router/
├── LICENSE                      (MIT — conservé)
└── src/                          (19 fichiers PHP, namespace Mithridatem\Routing)
    ├── Router.php  Route.php  RouteCollection.php  RouteMatch.php
    ├── Attribute/  Auth/  Context/  Controller/  Discovery/  Exception/  Handler/  Loader/
```

Copie effectuée depuis `docs/support_formateur/router-librairie/src` → `lib/Mithridatem/Router/src`.
La copie source **reste** intacte (référence formateur) ; le projet utilise désormais **sa propre
copie**.

### 1.2 `composer.json` (racine `docs/mes_orchidees_php/`)
```json
{
    "name": "mes-orchidees/app",
    "require": { "php": ">=8.1" },
    "autoload": {
        "psr-4": {
            "App\\": "src/",
            "Mithridatem\\Routing\\": "lib/Mithridatem/Router/src/"
        }
    },
    "scripts": { "serve": "php -S localhost:8000 -t public public/index.php" }
}
```
- **PSR-4 double** : `App\` → `src/` (code d'app) et `Mithridatem\Routing\` → `lib/…/src/` (router vendorisé).
- Plus de bloc `repositories` ni de `require mithridatem/router` : la librairie est **dans** le projet.
- `composer install` / `composer update` exécutés → `vendor/autoload.php` généré, `composer.lock`
  régularisé (aucune dépendance externe, plateforme `php >=8.1`).

---

## 2. Restructuration vers l'architecture MVC cible

### 2.1 Arborescence créée
```
mes_orchidees_php/
├── public/                 ← NOUVEAU DOCROOT (seul exposé au web)
│   ├── index.php           front-controller (point d'entrée unique)
│   └── .htaccess           réécriture Apache → index.php (Options -Indexes)
├── config/                 ← NEW
│   ├── env.php             parseur .env (déplacé depuis api/config/)
│   ├── db.php              fabrique PDO MySQL (voir §4)
│   └── routes.php          CONTRAT de routes (§4 Phase 01)
├── lib/Mithridatem/Router  ← router vendorisé (§1)
├── src/                    ← NEW (code d'app, namespace App\)
│   ├── Controller/         AbstractController + Home/Page/Auth + pages de contenu
│   ├── Entity/             (VIDE — créé, rempli Phase 03)
│   ├── Repository/         UserRepositoryInterface (contrat) + UserRepository (subset auth)
│   ├── Security/           SessionGrantChecker (impl. GrantCheckerInterface)
│   └── Service/            AuthService (sessions + PASSWORD_DEFAULT)
├── templates/              ← NEW (vues PHP)
│   ├── layout.php          gabarit global (nav, flashes, footer)
│   ├── pages/              home · auth_login · auth_register · placeholder ·
│   │                       confidentialite · mentions · accessibilite · error
│   └── components/         (VIDE — créé, rempli Phase 03)
├── data/schema_mysql.sql   ← normalisé (§4)
└── api/, *.php (racine)    LEGS hors docroot : conservés, non supprimés (migration Phase 03)
```

### 2.2 Bascule du docroot
- Le **docroot passe de la racine à `public/`** : les sources (`src/`, `config/`, `templates/`,
  `data/`, `.env`) sont **au-dessus** du docroot et donc **inaccessibles par le web** (décision D4).
- `public/.htaccess` redirige toute requête non-fichier/non-répertoire vers `index.php`.
- Pour Apache/Docker (`Dockerfile` = `php:apache`), il faudra pointer `DOCUMENT_ROOT` sur
  `.../public` (à faire au packaging Render — Phase 03/déploiement). **Non supprimé** : les anciennes
  pages `.php` à la racine restent sur le disque (référence de migration), mais ne sont plus servies.

### 2.3 Front-controller `public/index.php` — chaîne de traitement
1. **Autoloader** : `vendor/autoload.php` si présent, sinon **repli PSR-4** `spl_autoload_register`
   (mappe `App\`→`src/`, `Mithridatem\Routing\`→`lib/…/src/`) → l'app tourne **même sans Composer**.
2. **Env** : `require config/env.php` (remplit `$_ENV` depuis `.env`).
3. **Debug** : `display_errors` piloté par `APP_DEBUG`.
4. **Session native** : `session_set_cookie_params([httponly, samesite, secure, path=/])` puis
   `session_start()` (valeurs lues depuis `.env` : `COOKIE_SECURE`, `COOKIE_SAMESITE`, `COOKIE_DOMAIN`).
5. **CSRF** : un jeton de session est garanti présent (créé si absent) → tous les formulaires l'ont.
6. **Routeur** : `new Router()` + `setGrantChecker(new SessionGrantChecker())` + `setBasePath()`
   optionnel (`APP_BASE_PATH`, pour Render en sous-répertoire).
7. **Routes** : `require config/routes.php` puis `register_routes($router)`.
8. **Dispatch** : `$router->dispatch()` ; le résultat `string` (HTML/JSON) est écouté ; les
   redirections (`exit`) ne renvoient rien.
9. **Erreurs HTTP homogènes** : `RouteNotFoundException`→404, `MethodNotAllowedException`→405,
   `UnauthorizedException`→403, `RouterException` contenant « not found »→**501 (Phase 03)**,
   autre `RouterException`→500, `Throwable`→500 (trace en debug).

---

## 3. Authentification & sécurité

### 3.1 Sémantique des grants — `App\Security\SessionGrantChecker`
Implémente `Mithridatem\Routing\Auth\GrantCheckerInterface::isGranted(array $required): bool` :

| Grants déclarés sur la route | Règle d'accès |
|---|---|
| `[]` (vide) | **Public** — tout le monde. |
| `['authenticated']` | **Utilisateur connecté** (n'importe quel rôle). |
| `['admin']` | Rôle **admin** uniquement. |
| `['admin','moderateur']` | Rôle **admin OU modérateur**. |

L'utilisateur courant est lu dans `$_SESSION['user']['role']` (positionné par `AuthService`).
**Non connecté + grants non vides → refus** (le Router lève `UnauthorizedException` → 403).
> Vérifié à l'exécution : `GET /macollection` (grant `authenticated`) sans session → **HTTP 403**.

### 3.2 `App\Service\AuthService` (sessions natives)
- `attempt(email, password)` : charge l'utilisateur via le **contrat** `UserRepositoryInterface`,
  vérifie avec **`password_verify()`**, puis `loginUser()`.
- `register(data)` : valide la présence des champs, hache avec
  **`password_hash($mdp, PASSWORD_DEFAULT)`**, délègue la création au repository. Le rôle reste
  `user` à l'inscription publique (**jamais** de création admin via le site — RG6).
- `loginUser(user)` : **`session_regenerate_id(true)`** (anti *session fixation*), ne stocke en
  session que `{id, nom, prenom, role}` (jamais le hash).
- `logout()` : vide `$_SESSION`, invalide le cookie, `session_destroy()`.
- `user()`, `isAuthenticated()`, `hasRole()` : lecture de la session.

### 3.3 `PASSWORD_DEFAULT` (recommandation formateur — actu Phase 01)
Hachage **sans** épingler `PASSWORD_BCRYPT` : l'algorithme suit les mises à jour de PHP ; le hash
stocké est auto-descrit, donc `password_verify()` continue de valider après upgrade PHP. Colonne
`users.password_hash VARCHAR(255)` inchangée.

### 3.4 CSRF
`AbstractController` fournit `getCsrfToken()` (session, `bin2hex(random_bytes(32))`),
`isCsrfTokenValid()` (`hash_equals`, comparaison temps constant), `assertCsrf()` (400 si invalide).
Chaque formulaire `POST` (login/register/logout) transporte `csrf_token`. Le cookie de session est
`SameSite` + `HttpOnly` (défense en profondeur).

---

## 4. Base de données MySQL

### 4.1 Normalisation de `data/schema_mysql.sql`
Corrections appliquées (MySQL/InnoDB **ignore** le `REFERENCES` au niveau colonne) :

| # | Avant | Après |
|---|---|---|
| 1 | `orchids.proposed_by CHAR(36) REFERENCES users(id) ON DELETE SET NULL` (inline, ignoré) | colonne `NULL COMMENT …` + **`CONSTRAINT fk_orchids_proposed_by FOREIGN KEY (proposed_by) REFERENCES users(id) ON DELETE SET NULL`** (niveau table) |
| 2 | `conseils.orchid_id VARCHAR(200) REFERENCES orchids(id) ON DELETE SET NULL` (inline, ignoré) | colonne `NULL COMMENT …` + **`CONSTRAINT fk_conseils_orchid FOREIGN KEY (orchid_id) REFERENCES orchids(id) ON DELETE SET NULL`** |
| 3 | — (modération sans index) | **`KEY idx_orchids_published (is_published, proposed_by)`** (liste des propositions `is_published=0`) |
| 4 | commentaires SEED « bcrypt en dur » | remplacés par la consigne **`PASSWORD_DEFAULT`** + `php -r "echo password_hash(...)"` |

**Déjà conformes (inchangés) :** FK explicites de `collections` (`fk_collections_user` CASCADE,
`fk_collections_orchid` RESTRICT), `soins` (`fk_soins_collection` CASCADE), `notifications`
(`fk_notifications_user` CASCADE, `fk_notifications_collection` SET NULL).

**Cohérence des types de FK vérifiée :** `orchids.id` VARCHAR(200) ⇔ `collections.orchid_id` /
`conseils.orchid_id` VARCHAR(200) ; `users.id` CHAR(36) ⇔ `proposed_by` / `user_id` CHAR(36).
Charset/collation homogènes : toutes les tables en **`utf8mb4` / `utf8mb4_unicode_ci`** (condition
*sine qua non* d'acceptation des FK). Ordre de création préservé : `users → orchids → conseils →
collections → soins → notifications → vue v_collection_complete`.

> ⚠️ Point d'attention (non bloquant) : `soins.soin_date DATE DEFAULT (CURRENT_DATE)` exige
> MySQL **≥ 8.0.13**. À confirmer sur l'instance cible Render ; sinon basculer sur une valeur par
> défaut applicative (Phase 03).

### 4.2 `config/db.php` — PDO MySQL
- Fabrique `getDb()` (singleton de requête), options : `ERRMODE_EXCEPTION`,
  `FETCH_ASSOC`, **`EMULATE_PREPARES=false`** (vraies requêtes préparées → anti-injection).
- Deux modes de config : **`DB_DSN`** complet, ou variables discrètes
  `DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASS` (DSN `mysql:…;charset=utf8mb4` assemblé).
- Message d'erreur **sans fuite** hors debug.
- `.env.example` mis à jour : section **MySQL** (`DB_*`), l'ancien `NEON_DSN` est commenté en
  historique ; `JWT_SECRET` retiré (auth par **sessions**, décision D-Auth tranchée Phase 01).
- `config/env.php` déplacé depuis `api/config/` (racine = `dirname(__DIR__)`).

### 4.3 Repository — contrat vs implémentation
- `App\Repository\UserRepositoryInterface` (**contrat**) : `findById`, `findByEmail`, `create`.
- `App\Repository\UserRepository` (**subset auth**, PDO) : implémente ces 3 méthodes (UUID v4 généré
  en PHP ; requêtes préparées ; **connexion paresseuse** — un affichage de formulaire de connexion
  n'ouvre pas la BDD). Le **CRUD complet** (liste, update, delete, isolation RG1 multi-tenant) et les
  repositories des autres entités = **Phase 03**.

---

## 5. Contrat de routes (§4 Phase 01) — `config/routes.php`

Toutes les routes validées sont **déclarées** sur le Router (`mapController(méthode, chemin,
classe, action, grants)`), nomenclature retenue : liste au pluriel, fiche/création/suppression au
singulier avec `{id}`, mise à jour via `/update/{entite}`. Les routes **statiques sont déclarées
avant les dynamiques** (`/orchid/new` avant `/orchid/{id}`) — le `RouteCollection` applique le
**premier match gagné**. Segments `{id}` passés en **arguments nommés** au contrôleur.

**Implémentées et fonctionnelles en Phase 02 :** `/` (Home), `/login` GET/POST, `/register` GET/POST,
`/logout`, `/me`, `/confidentialite`, `/mentions`, `/accessibilite`, `/encyclopedie`, `/conseils`,
`/macollection` (dashboard), `/administration` (dashboard).

**Contrat déclaré, contrôleur non implémenté → réponse 501 « Phase 03 »** (classes `OrchidController`,
`UserController`, `SoinController`, `NotificationController`, `ModerationController` et les **actions
CRUD** de `CollectionController`/`ConseilController` hors `dashboard`/`index`) :
`/orchids` · `/orchids/search` · `/orchid/{id}` · `/orchid/new` · `/orchid/propose` · `/update/orchid*` ·
`/orchid/delete/{id}` ; conseils CRUD ; collections CRUD ; soins ; notifications ; users ; `/moderation*`.

Constantes de grant dédiées : `GRANT_PUBLIC`, `GRANT_AUTH`, `GRANT_ADMIN`, `GRANT_MODO`.

---

## 6. Contrôle de fumée (serveur embarqué `php -S -t public`)

| Requête | HTTP attendu | Résultat | Ce que ça prouve |
|---|---|---|---|
| `GET /` | 200 | ✅ 200 | Rendu vue via layout |
| `GET /login`, `/register` | 200 | ✅ 200 (jeton `csrf_token` présent) | Front-controller + formulaire + CSRF |
| `GET /conseils`, `/encyclopedie` | 200 | ✅ 200 | Pages de contenu (placeholder) |
| `GET /confidentialite`, `/mentions`, `/accessibilite` | 200 | ✅ 200 | Pages légales |
| `GET /macollection` (sans session) | 403 | ✅ 403 | **Autorisation serveur** (grant `authenticated`) |
| `GET /orchids` | 501 | ✅ 501 | Contrat déclaré, contrôleur **Phase 03** |
| `GET /n-existe-pas` | 404 | ✅ 404 | `RouteNotFoundException` gérée |

`php -l` : **aucune erreur de syntaxe** sur les 16 fichiers PHP produits.

---

## 7. Décisions confirmées / restent ouverts

**Confirmés en Phase 02 :**
- **D1** — Intégration router : **vendoring** dans `lib/` (autonome, déployable).
- **D3/D6** — SGBD cible : **MySQL 8** (`config/db.php` PDO MySQL).
- **D-Auth** — Persistance : **sessions PHP natives** (pas de JWT).
- **Hachage** : `PASSWORD_DEFAULT` (pas de bcrypt en dur).
- **D4** — Docroot `public/` isolé.

**Restent à traiter (sans blocage) :**
- **D-Version** — `Dockerfile` = `php:8.2-apache` alors que la stack actée est PHP 8.3 ; à aligner au
  packaging. Le `composer.json` requiert `>=8.1` (compat des deux).
- **D-Deploy** — Câblage Apache/Render vers `public/` (`DOCUMENT_ROOT`, `APP_BASE_PATH` si sous-répertoire,
  `COOKIE_SECURE=true` en prod) + **servir les assets** (`assets/` hors docroot → à copier/exposer sous
  `public/` à la Phase 03/packaging ; les vues Phase 02 utilisent un CSS de secours intégré).
- **D-Seed** — Générer de vrais hash `PASSWORD_DEFAULT` pour le seed `users` (placeholders à remplacer).
- **D-Prop / D-Rôles / D-Rappels** — inchangés depuis Phase 01 (workflow embarqué, rôle `moderateur`,
  notifications J+7) → mise en œuvre Phase 03.

---

## 8. Fichiers produits / modifiés (Phase 02)

**Créés :** `composer.json`, `composer.lock`, `lib/Mithridatem/Router/**` (copie),
`public/index.php`, `public/.htaccess`, `config/env.php`, `config/db.php`, `config/routes.php`,
`src/Controller/{AbstractController,HomeController,PageController,AuthController,EncyclopedieController,ConseilController,CollectionController,AdminController}.php`,
`src/Security/SessionGrantChecker.php`, `src/Service/AuthService.php`,
`src/Repository/{UserRepositoryInterface,UserRepository}.php`,
`templates/layout.php`, `templates/pages/{home,auth_login,auth_register,placeholder,confidentialite,mentions,accessibilite,error}.php`,
dossiers vides `src/Entity/`, `templates/components/`.

**Modifiés :** `data/schema_mysql.sql` (FK/index/notes), `.env.example` (MySQL).

**Non touchés (volontairement) :** `api/**`, pages `.php` racine, `includes/` (migrés en Phase 03) ;
`.env` (secret, hors Git).

---

## 9. Critères d'acceptation Phase 02
- ✅ Router intégré (vendoring) + `composer.json` PSR-4 (`App\`, `Mithridatem\Routing\`) — §1
- ✅ Docroot `public/` + front-controller + arborescence MVC — §2
- ✅ Contrat de routes §4 déclaré sur le Router (statiques avant dynamiques) — §5
- ✅ Sessions natives + `PASSWORD_DEFAULT` + CSRF + `GrantCheckerInterface` (rôles) — §3
- ✅ Schéma MySQL normalisé (`CONSTRAINT … FOREIGN KEY`, index modération, types/charset cohérents) — §4
- ✅ Preuve d'exécution (smoke test 200/403/404/501) + `php -l` clean — §6
- ⏸ **Aucun** code métier d'entité (CRUD/repos/services entities) — réservé **Phase 03**

---

**ARRÊT.** Architecture et contrats de base en place et vérifiés. **En attente de validation
explicite** avant d'entamer la **Phase 03** (incréments CRUD : entités, repositories, contrôleurs
d'actions, peuplement des vues, migration des pages legacy).
