# PHASE 04 — STABILISATION, AUDIT DE SÉCURITÉ & PRÉPARATION À LA LIVRAISON

**Projet :** MES ORCHIDEES (PHP MVC, DWWM fil rouge)
**Phase :** 04 — Stabilisation & Préparation livraison
**Périmètre d'action :** correctifs de sécurité + configuration de déploiement
**Déploiement :** NON EXÉCUTÉ (interdit sans autorisation distincte)
**Auteur :** Jérémy Ferrier — exécution assistée par IA
**Date :** 2026-10-17

---

## 0. Résumé exécutif

| Axe | Verdict |
|---|---|
| Commit incréments 4 & 5 | ✅ `f00981b` |
| Protection CSRF (100 % des POST) | ✅ 22/22 actions POST protégées (faille `logout` corrigée) |
| Échappement anti-XSS (vues) | ✅ 100 % (toute sortie dynamique via `e()`/`htmlspecialchars`) |
| Isolation multi-tenant RG1 | ✅ collections/soins/notifications filtrés `user_id` |
| Conformité authentification | ✅ `PASSWORD_DEFAULT`, `session_regenerate_id`, cookie `httponly`/`samesite`/`secure` |
| Routes en 501 | ✅ **0** (balance anonyme + connectée) |
| Défectuosité découverte & corrigée | ✅ rejet CSRF renvoyait **500** → maintenant **400** propre |
| Dockerfile Render (Docroot `public/`) | ✅ réécrit |
| `.env.production.example` | ✅ créé |
| Assets compilés | ✅ présents sous `public/assets/` |

Modifications de la Phase 04 **non committées** (en attente d'instruction) : voir §7.

---

## 1. Commit des incréments 4 & 5

Commande :

```
git commit -m "feat(phase03): increments notifications (RG3/RG5) et administration/moderation"
```

Résultat : **`f00981b`** — 18 fichiers changés, 1 609 insertions(+), 9 suppressions(-).
Arbre de travail propre au moment du commit.

Historique courant :

```
f00981b feat(phase03): increments notifications (RG3/RG5) et administration/moderation
89717f0 feat(phase03): increments conseils et collection/soins avec isolation RG1
0a9872a refactor(mvc): extraction du projet a la racine, schema MySQL et socle encylopedie
```

---

## 2. Audit de sécurité et qualité

### 2.1 CSRF — protection des formulaires POST

**Contrat de routes (`config/routes.php`) : 22 actions POST.** Toutes appellent
`AbstractController::assertCsrf($_POST)`.

| Contrôleur | Actions POST protégées |
|---|---|
| AuthController | `login`, `register`, **`logout`** |
| OrchidController | `create`, `propose`, `update`, `delete` |
| ConseilController | `create`, `update`, `delete` |
| CollectionController | `create`, `update`, `delete` |
| SoinController | `create`, `update`, `delete` |
| NotificationController | `markRead`, `delete` |
| UserController | `update`, `delete` |
| ModerationController | `approve`, `reject` |

**Correctif appliqué :** `AuthController::logout()` ne vérifiait pas le jeton
(21/22 couverts). Ajout de `$this->assertCsrf($_POST)` → **22/22**. Le formulaire
de déconnexion de la sidebar envoie déjà le champ caché `csrf_token`, donc aucun
impact sur le parcours existant.

**Couverture des formulaires :** 19 balises `<form method="post">` dans les vues,
**19** portent un champ caché `csrf_token` (via `e($csrf)` ou `$_SESSION['csrf_token']` ;
`auth_login`/`auth_register` utilisent `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`,
équivalent sécurisé). Aucune form POST sans jeton.

### 2.2 Échappement anti-XSS dans les vues

Toute sortie dérivée de la base ou de l'utilisateur est échappée :

- Encodage systématique par `e()` (wrapper `htmlspecialchars`), y compris les
  champs rédactionnels stockés (`longDesc`, `shortDesc`, `content`, `message`,
  `notes`, `category`, `origin`, `vernacular`…) — vérifié dans
  `orchids/show.php`, `conseils/show.php` (`nl2br(e($conseil->content))`),
  `encyclopedie`, `error.php`, `layout.php` (flashes `e($flash['message'])`).
- Les fermetures de champ des formulaires (`$field`, `$textarea` dans
  `conseils/form.php`, `orchids/form.php`) échappent interne/label/value via `e()`.
- Les attributs `href`/`action`/`src` sont construits par `url()`/`asset()` puis
  passés dans `e()` ; les identifiants d'URL sont `rawurlencode()`-és.

Balance des expressions `<?= $… ?>` non enveloppées dans une fonction : **11
occurrences**, toutes sûres — soit des **entiers** issus de `count()`/`(int)`
(`$nbPlantes`, `$nbEnAttente`, `$total`, `$nbNonLues`, `$stats['orchids_pending']`),
soit des **booléens rendus en littéraux statiques** (`isPublished ? 'checked' : ''`,
`isSpecies() ? '…' : e(…)`, `isUnread() ? ' class…' : ''`, comparaison `=== $selfId`).
**Aucune donnée utilisateur/DB n'est imprimée en brut.** Verdict : **100 %**.

### 2.3 Isolation multi-tenant RG1

Vérification que chaque requête sur les données privées est filtrée par
`user_id`, et que l'identifiant du destinataire provient **uniquement** de la
session (`current_user()['id']`), jamais de la requête.

| Repository | Filtrage RG1 |
|---|---|
| `CollectionRepository` | toutes lectures/écriture/suppression `WHERE … user_id = :uid` (`findByIdForUser`, `findByUser`, `updateForUser`, `deleteForUser`) |
| `SoinRepository` | jointure `collections c` + `WHERE c.user_id = :uid` sur **toutes** les requêtes (lecture `findByUser`/`findByCollectionForUser`/`findByIdForUser`, garde `collectionOwnedBy`, `UPDATE…JOIN…WHERE c.user_id`, `DELETE s FROM soins s JOIN…WHERE c.user_id`) |
| `NotificationRepository` | toutes requêtes `WHERE user_id = :uid` (`findByUser`, `findByIdForUser`, `unreadCount`, `markReadForUser`, `deleteForUser`) ; l'`id` destinataire est injecté depuis la session |

Le lien d'un soin à son utilisateur passe par sa collection (RG1 respecté même
indirectement). Un soin/notif/collection rattaché à un tiers est **invisible et
inaltérable** via ces repositories. Verdict : **conforme**.

### 2.4 Authentification & gestion de session (`public/index.php`, `AuthService`)

| Exigence | État | Emplacement |
|---|---|---|
| Hachage mot de passe `PASSWORD_DEFAULT` | ✅ | `AuthService` (inscription + création user admin) |
| `session_regenerate_id(true)` à la connexion | ✅ | `AuthService::loginUser()` (anti-fixation de session) |
| Cookie `httponly` | ✅ | `session_set_cookie_params([... 'httponly' => true])` |
| Cookie `samesite` | ✅ configurable, défaut `Strict` | `COOKIE_SAMESITE` (`Lax` recommandé en prod, cf. §3.2) |
| Cookie `secure` | ✅ configurable (`COOKIE_SECURE`) | à forcer `true` en production (HTTPS) |
| Jeton CSRF de session | ✅ créé à la volée (`bin2hex(random_bytes(32))`) | `public/index.php` |
| Aucune création de rôle `admin` via le public (RG6) | ✅ | `AuthController::register()` force le rôle `user` |

### 2.5 Défaut de rejet CSRF corrigé (500 → 400)

**Constat :** `assertCsrf()` fixait `http_response_code(400)` **puis levait** une
`RuntimeException`. Le front-controller, dans son `catch (\Throwable)` générique,
re-écrasait le statut en **500** (et, en debug, rejettait l'exception). Conséquence :
**tout rejet CSRF** (jeton absent/expiré) sur **n'importe quelle** action POST
remontait un **500 trompeur** au lieu d'un 4xx client.

**Correctif (sécurité/qualité, sans impact métier) :**
1. Nouvelle exception applicative `App\Exception\CsrfException` (`src/Exception/CsrfException.php`).
2. `assertCsrf()` lève désormais `CsrfException` (sans toucher au code HTTP).
3. `public/index.php` intercepte `CsrfException` **avant** `catch (\Throwable)` et
   renvoie un **400** homogène (`render_error(400, …)`), **y compris en debug**.

**Test probant** (session admin connectée) :

```
POST /logout            csrf BAD      => 400
POST /logout            csrf absent   => 400
POST /update/orchid     csrf BAD      => 400
POST /logout            csrf valide   => 302 (redirect /login)
GET  /administration  après logout    => 403 (session détruite)  ✅
```

---

## 3. Préparation du déploiement Render & Docker

### 3.1 `Dockerfile` (php:8.2-apache)

Réécrit pour la livraison. Points clés :

- **DocumentRoot = `/var/www/html/public`** : un `<VirtualHost *:80>` dédié est
  généré. `src/`, `config/`, `templates/`, `data/` restent **hors docroot**
  (inaccessibles par le web — décision D4). Le front-controller `public/index.php`
  est le seul point d'entrée.
- **`mod_rewrite` activé** (`a2enmod rewrite`) + **`AllowOverride All`** sur le
  répertoire public → le `public/.htaccess` (réécriture vers `index.php`) est bien
  pris en compte. **`FallbackResource /index.php`** ajouté en secours.
- **Extensions PHP** : `pdo_mysql` uniquement. Les pilotes PostgreSQL/Neon
  (`pdo_pgsql`, `pgsql`, `libpq-dev`) de l'ancienne architecture sont **retirés**
  (cible MySQL/MariaDB actée en décision D3 ; surface réduite).
- **Autoloader** : `composer dump-autoload --optimize --no-dev` (aucune dépendance
  externe, juste la carte PSR-4 locale `App\`→`src/`, `Mithridatem\Routing\`→`lib/`) ;
  évite de dépendre du rechargeur de secours de développeur en production.
- **Aucun secret embarqué** : `.env`, `.env.example`, `.env.production.example`
  sont supprimés de l'image ; la configuration vient des variables Render.
- Permissions durcies : `www-data` propriétaire, répertoires `755`, fichiers `644`.
- `EXPOSE 80`, `CMD ["apache2-foreground"]`.

### 3.2 `.env.production.example` (nouveau)

Gabarit documentant **toutes** les variables attendues en production Render :

- `APP_ENV=production`, `APP_DEBUG=false` (coupe l'affichage des traces).
- Base de données : mode **DSN complet** (`DB_DSN=mysql:host=…;port=…;dbname=…;charset=utf8mb4`)
  ou **variables discrètes** (`DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASS`).
  ⚠️ **Render ne fournit pas de MySQL managé** (PostgreSQL uniquement) → prévoir un
  MySQL/MariaDB **externe** (PlanetScale, JAWSDB, ClearDB, Railway, VPS).
- Durcissement session : `COOKIE_SECURE=true` (**obligatoire** en HTTPS),
  `COOKIE_SAMESITE=Lax`, `COOKIE_DOMAIN=` (vide → `*.onrender.com`).
- `APP_BASE_PATH=` (vide, app servie à la racine).
- `APP_SECRET=` — 64 caractères aléatoires (`php -r "echo bin2hex(random_bytes(32));"`).

**`.gitignore`** : ajout de `!.env.production.example` pour versionner ce gabarit
(il était ignoré par la règle `.env.*`). Les `.env` réels restent ignorés.

### 3.3 Assets compilés

`public/assets/` contient les ressources buildées prêtes à servir :

- CSS : `style.css`, `style.min.css` (+ 3 `.map`).
- JS : 23 modules ES6 (`core/`, `data/`, `features/`, `services/`).
- Images : 38 `.png`, 7 `.jpg`, 5 `.webp` (`images/orchids/`, `images/site/`).
- Sources SCSS conservées sous `public/assets/scss/` (non requises au runtime).

Verdict : assets présents et compilés, servis directement par Apache.

---

## 4. Validation — aucune route en 501 & non-régression

Environnement : `php -S 127.0.0.1:8000 -t public server.php`, MariaDB locale.

### 4.1 Syntaxe

```
php -l sur 62 fichiers (src/, templates/, config/, public/) => 0 erreur
```

### 4.2 Balance HTTP anonyme

**Publiques (attendu 200, ou 404 contrôlé) :**

| Route | Code | | Route | Code |
|---|---|---|---|---|
| `/` | 200 | | `/login` | 200 |
| `/encyclopedie` | 200 | | `/register` | 200 |
| `/conseils` | 200 | | `/confidentialite` | 200 |
| `/orchids` | 200 | | `/mentions` | 200 |
| `/orchids/search?q=…` | 200 | | `/accessibilite` | 200 |
| `/orchid/{id inconnu}` | 404 (contrôleur) | | `/conseil/{id inconnu}` | 404 (contrôleur) |

**Protégées en anonyme (attendu 403 par le grant) :** `/macollection`, `/collections`,
`/soins`, `/notifications`, `/me`, `/administration`, `/users`, `/user/new`,
`/moderation`, `/orchid/new`, `/conseil/new`, `/collection/abc`, `/notification/abc`,
`/user/abc`, `/update/orchid/abc`, `/update/conseil/abc` → **toutes 403**.

### 4.3 Balance connectée (session admin) — preuve d'exécution des méthodes

Un 403 anonyme bloque **avant** la résolution du contrôleur : seul un accès
authentifié prouve l'absence de 501. Connecté en admin :

| Route | Code | Interprétation |
|---|---|---|
| `/macollection`, `/collections`, `/soins`, `/notifications`, `/me` | 200 | méthode exécutée |
| `/administration`, `/users`, `/user/new`, `/moderation` | 200 | méthode exécutée |
| `/orchid/new`, `/conseil/new` | 200 | méthode exécutée |
| `/collection/abc`, `/notification/abc`, `/user/abc`, `/update/orchid/abc`, `/update/conseil/abc` | 404 | contrôleur atteint, ressource inexistante (≠ 501) |

**Nombre de routes en 501 : 0. Nombre de routes en 5xx : 0.**

### 4.4 Investigation `501` résiduels potentiels (conclusion : aucun)

- `GET /deconnexion`, `/api/orchids`, `/search` renvoient la **page 404** de l'app :
  ce **n'étaient pas** des routes du contrat `routes.php` (les homologues réels sont
  `/orchids` et `/orchids/search` ; la déconnexion est portée par `POST /logout`).
  Un 404 de route non définie ≠ 501. **Aucun code applicatif mort n'a été ajouté.**

---

## 5. Fonctionnalités métier inchangées

Les correctifs de la Phase 04 sont strictement **transverses** (CSRF, statut HTTP,
conteneur d'exception, Docker/env/gitignore) : aucune entité, repository métier,
service ou vue de parcours n'a été modifié. Les incréments 1→5 (encyclopédie,
conseils, collection/soins + RG3, notifications, administration/modération + RG5)
restent opérationnels (validés §4).

---

## 6. Checklist de déploiement Render (pour instruction ultérieure)

1. Créer un service **Docker** (le `Dockerfile` est prêt) — Region au choix.
2. Provisionner un **MySQL/MariaDB externe** (Render n'a pas de MySQL managé) et y
   importer `data/schema_mysql.sql` + les seeds (`data/seeds/`).
3. Renseigner les variables d'environnement (modèle dans `.env.production.example`) :
   `DB_DSN`/`DB_*`, `APP_ENV=production`, `APP_DEBUG=false`, `COOKIE_SECURE=true`,
   `COOKIE_SAMESITE=Lax`, `APP_SECRET` (64 car.).
4. Configurer le **Server Port** Render = `80` (le conteneur écoute sur Apache).
5. Ajouter un **health check** sur une route publique (ex. `/`).
6. **Ne pas** committer/push de `.env` réel ; les secrets via le tableau de bord.
7. Vérifier la première connexion admin après migration (comptes seedés).

> ⚠️ **Aucune action de déploiement n'a été tentée** — conformément à la consigne,
> en attente d'une autorisation explicite distincte.

---

## 7. État Git — modifications Phase 04 / 04B

Sélection **explicite** pour le commit de stabilisation `chore(phase04): harden
security and prepare deployment` (aucun `git add .` ; le `.env` réel est ignoré et
non ajouté ; artefacts de build supprimés) :

```
 M .gitignore                            # + !.env.production.example
 M Dockerfile                            # Docroot public/, rewrite, pdo_mysql + mbstring, autoload, .env hors image
 M public/index.php                      # catch CsrfException => 400 propre
 M src/Controller/AbstractController.php # assertCsrf lève CsrfException
 M src/Controller/AuthController.php     # logout : + assertCsrf
?? .dockerignore                         # exclut .env/vendor/docs/ISO du contexte d'image (04B)
?? .env.production.example               # gabarit Render
?? src/Exception/CsrfException.php       # exception rejet CSRF
?? docs/project-foundation/phase-04/…    # le présent rapport
```

Aucun secret, `.env` réel ou artefact local n'est inclus. Le commit des incréments
4 & 5 (`f00981b`) reste inchangé (aucune anomalie bloquante démontrée).

---

## 8. Vérification indépendante — Phase 04B

Reproduite **pendant cette phase** sur l'état réel du dépôt, avec preuve d'exécution.
Verdict global de la section : **PASSED WITH LIMITATIONS**.

### 8.1 Contrôles directement reproduits (preuve obtenue en 04B)

| Contrôle | Méthode (04B) | Statut |
|---|---|---|
| Présence de `f00981b` dans l'historique | `git merge-base --is-ancestor` (exit 0) | **PASS** |
| `assertCsrf` appliqué sur les POST | grep : 22 sites d'appel + 1 déf. ; `logout` inclus | **PASS** |
| Rejet CSRF jeton absent/invalide | HTTP réel : `/notification/{id}/read`, `/moderation/{id}/approve`, `/collection/new`, `/logout` → **400** | **PASS** |
| Jeton CSRF valide → traitement prévu | `POST /logout` valide → 302 + session détruite ; `GET /administration` → **403** | **PASS** |
| `CsrfException` → 400 (et non 500) dans `public/index.php` | `catch (CsrfException)` avant `catch (\Throwable)` | **PASS** |
| Isolation RG1 / IDOR | HTTP réel : notif **propre** → 200 ; notif d'**un tiers** → **404** ; uid issu de la session | **PASS** |
| `PASSWORD_DEFAULT` / `session_regenerate_id(true)` / cookie `httponly`+`samesite`+`secure` | grep + lecture `AuthService`/`public/index.php` | **PASS** |
| Compatibilité MySQL réelle (code + schéma) | grep ILIKE/RETURNING/gen_random_uuid/SERIAL/jsonb/ON CONFLICT/`::` → **que des commentaires doc** ; SQL runtime = MySQL | **PASS** |
| FULLTEXT / `MATCH … AGAINST` | MariaDB 10.4.32 réel : index `ft_orchids_search` présent ; `/orchids/search`, `/conseils` → 200 | **PASS** |
| Build Docker réelle | `docker build` **réussi** (49 classes autoload, vhost, site activé, `.env` hors image) | **PASS** |
| Run Docker | `php -m` → mbstring+pdo_mysql ; `GET :8081/` → **200** HTML MES ORCHIDÉES (Docroot `public/`) | **PASS** |
| Lint PHP | `php -l` sur 34 fichiers → **0 erreur** | **PASS** |
| Balance de routes (501 / 5xx) | balayage anonyme + connecté admin → **0×501, 0×5xx** | **PASS** |

### 8.2 Défauts découverts et corrigés pendant la Phase 04B

1. **`mbstring` absent du Dockerfile** — le code utilise `mb_strlen`
   (`OrchidRepository`, `ConseilRepository`) et `mb_strtoupper` (`OrchidController`),
   mais `php:8.2-apache` ne fournit pas `mbstring`. Corrigé :
   `docker-php-ext-install pdo_mysql mbstring`.
2. **Échec de build de `mbstring` (oniguruma)** — le build Docker a échoué
   (`configure: error: Package 'oniguruma' … not found`). Corrigé : installation de
   `libonig-dev` (apt) **avant** `docker-php-ext-install`. Second build **réussi**.
3. **Absence de `.dockerignore`** — `COPY .` embarquait tout le workspace (docs, ISO,
   vidéos, `node_modules`) et faisait transiter `.env` dans une couche intermédiaire.
   Corrigé : création de `.dockerignore` (exclut `.git`, `.env*` hors gabarit,
   `vendor/`, docs, artefacts lourds, logs).
4. **Précision documentaire `.env.production.example`** — l'en-tête liste désormais les
   variables **réellement lues** par le code (`APP_DEBUG`, `COOKIE_*`, `APP_BASE_PATH`,
   `DB_DSN`/`DB_HOST`…`DB_PASS`) et signale `APP_ENV`/`DB_CHARSET`/`APP_SECRET` comme
   **non consommées** à ce stade (`db.php` utilise `DB_PASS`, pas `DB_PASSWORD`).

### 8.3 Traitement des exceptions — vérification anti-« erreurs trompeuses »

Le front-controller distingue désormais `CsrfException` (→ **400**, erreur applicative
légitime de l'client) du `catch (\Throwable)` générique (→ 500, erreur serveur). Un rejet
CSRF n'est donc plus dégradé en 500. Vérifié par exécution HTTP réelle (§8.1).

### 8.4 Limitations et tests non reproductibles

- **Suite de tests automatisée : `NOT AVAILABLE`** — aucun `tests/` ni `phpunit.xml`
  dans le projet ; les contrôles reposent sur lint + exécution HTTP + build Docker.
- **Balance exhaustive `e()` sur 100 % des sorties : `NOT VERIFIED`** au sens strict —
  le périmètre « 100 % » du rapport de phase précédente n'a pas être re-vérifié ligne par
  ligne ligne en 04B ; les contrôles XSS ponctuels clés (attributs, champs multilignes
  `nl2br(e(...))`, `count()`/`(int)`/booléens) restent conformes.
- **Port Render / MySQL externe : décision ouverte** — Render n'expose pas de MySQL
  managé ; le Server Port (80) et la base externe restent à confirmer au déploiement.
- **Schémas hérités** — `schema_neon.sql` et `schema_supabase.sql` sont présents mais
  **non utilisés** par le code (legacy) ; seul `schema_mysql.sql` fait foi.

### 8.5 Verdict partiel 04B

**PASSED WITH LIMITATIONS** — contrôles critiques de sécurité et de configuration
**réussis et prouvés** ; limitations = absence de suite automatisée et vérification XSS
exhaustive non reproduite intégralement. Aucun échec bloquant ouvert.
