# 01 — État réel du projet et prérequis de déploiement

Tout constat est **observé dans les fichiers** (VERIFIED) sauf mention contraire.
La configuration de production n'est **pas déduite** du seul Dockerfile : elle est
confrontée au code (`config/*`, `public/index.php`) et aux scripts.

## 1. État du dépôt (préservé)

- Branche `main`, HEAD `ca40ef5`, **index vide** (rien de stagé), `git status
  --porcelain` = **84 entrées** = les 83 des phases antérieures **+1** (le
  dossier `phase-e2/`). **Aucune modification de code applicatif** dans cette
  phase. Les travaux non commités des phases précédentes sont **intacts**.
  **VERIFIED**.

## 2. Image applicative — `Dockerfile` (70 lignes)

| Élément | Valeur observée | Ligne |
|---------|-----------------|-------|
| Base | `php:8.2-apache` | L15 |
| Extensions | `pdo_mysql`, `mbstring` (compilée contre `libonig-dev`) | L24-27 |
| Réécriture | `a2enmod rewrite` | L28 |
| DocumentRoot | `/var/www/html/public` (front-controller) | L31-35 |
| Routing secours | `FallbackResource /index.php` (si `.htaccess` ignoré) | L40 |
| Autoload | `composer dump-autoload --optimize --no-dev` (aucune dépendance) | L55-56 |
| Secrets | `.env*` **supprimés** de l'image (L60) ; config attendue **en variables d'env** | L12-13, L60 |
| Port | `EXPOSE 80` ; note « renseigner Server Port = 80 (ou adapter via `$PORT`) » | L65-67 |
| Démarrage | `CMD ["apache2-foreground"]` | L69 |

Points de vigilance relevés (VERIFIED) :
- Apache écoute en dur sur **80**. Render route le trafic vers le « Service Port »
  configuré ; si l'on laisse 80, il faut que le dashboard Render accepte 80.
  L'adaptation `$PORT` dynamique **n'est pas** implémentée → à traiter au déploiement.
- Le pilote **PostgreSQL a été retiré** de l'image (décision D3). **VERIFIED**.

## 3. `.dockerignore` — ce qui N'EST PAS dans l'image

**VERIFIED** : exclus = `.git`, `.env*` (sauf `.env.production.example`),
`vendor/`, `composer.lock`, `node_modules/`, `docs/`, `archive/`,
**`data/migrations/`**, et les fichiers lourds (`*.iso *.mp4 *.zip *.pdf …`).

➜ **Conséquence majeure** : les **migrations SQL ne sont pas embarquées**. Le
schéma d'une base distante doit être créé **hors conteneur** (import SQL manuel
phpMyAdmin/`mysql` client), puis la base alimentée par les `data/seeds/*.sql`.
**VERIFIED** (lecture du `.dockerignore`).

## 4. Connexion & configuration applicative (revalidation Phase E2)

- `public/index.php` charge `config/env.php` (L51) puis `config/db.php` (L52) ;
  la connexion PDO est **paresseuse** (aucun `getDb()` au bootstrap → une page
  qui s'affiche **ne prouve pas** que la base répond). **VERIFIED** (L131-138).
- `config/db.php` : DSN assemblé en **`mysql:`** codé en dur (L65), lit
  `DB_DSN` **ou** `DB_HOST/DB_PORT/DB_NAME/DB_USER/**DB_PASS**`. **VERIFIED**.
- ⚠️ **Incohérence confirmée** : `.env` définit `DB_PASSWORD`, `db.php` lit
  `DB_PASS`. En dev, la connexion ne fonctionne que parce que `root` a un mot de
  passe **vide**. À corriger **avant** tout déploiement (voir `04-`). **VERIFIED**.
- ⚠️ **Aucune option TLS/SSL PDO n'est configurée** : les options de `new PDO()`
  sont uniquement `ERRMODE`, `FETCH_ASSOC`, `EMULATE_PREPARES` (L69-73) ; ni
  `PDO::MYSQL_ATTR_SSL_CA`, ni `ssl-ca`/`ssl-mode` dans le DSN. Or beaucoup de
  MySQL managés **exigent** le TLS → **bloqueur** pour les scénarios A/B avec base
  distante. **VERIFIED**.

## 5. Chargement de la config en production

- `config/env.php` (`loadEnv`, L26-56) lit un fichier `.env` **à la racine** et
  **ne surcharge pas** une clé déjà présente (L48) → les variables injectées par
  la plateforme (Render dashboard) **priment**. `getenv()` sert de secours dans
  `db.php` (L41). **VERIFIED** : le mécanisme attendu est bien « variables d'env
  Render », pas un `.env` commité.
- Gestion d'erreurs sans divulgation : `display_errors` piloté par `APP_DEBUG`
  (L55-56) ; exceptions → pages d'erreur homogènes (404/405/403/500/400) sans
  technique exposée (L139-167). Cookies durcis `secure/httpOnly/sameSite`
  (L67-74). **VERIFIED** (bon en l'état).

## 6. Données persistantes — le vrai point bloquant

| Donnée | Stockage observé | Effet sur hébergement éphémère |
|--------|-----------------|--------------------------------|
| **Photos uploadées** (collection) | **filesystem** `public/assets/images/uploads/` via `move_uploaded_file()` (`src/Service/Upload.php` L76-89, L97-101) | **PERDUES** à chaque redéploiement/restart/spin-down si pas de disque persistant. **VERIFIED** |
| **Sessions** | fichiers natifs PHP (`session_start()`, save_path par défaut, index.php L62-75) | Cookie de session invalidé quand l'instance redémarre (déconnexion). **VERIFIED** |
| **Journaux** | Apache `error.log`/`access.log` vers stdout (Dockerfile L42-43) | OK (collectés par la plateforme). **VERIFIED** |
| **Contenu métier** (plantes, soins, rappels…) | base MySQL/MariaDB | persistant **si** base externe/disque. **VERIFIED** |

➜ **Les uploads sont la donnée non relationnelle qui ne survit pas à Render free**
(qui n'offre pas de disque persistant gratuit). C'est **le** critère qui départage
les scénarios (doc 02). Aucune des solutions « 0 € » ne le résout sans compromis
(hébergeur à disque persistant, stockage objet externe, ou acceptation de la
perte). Le projet n'a **pas** de stockage objet (S3) intégré → **VERIFIED** (aucun
code d'object storage dans `src/`).

## 7. Prérequis réels de déploiement (synthèse)

- PHP ≥ 8.1 (ici 8.2 image / 8.3 CLI local), `pdo_mysql`, `mbstring`, `fileinfo`
  (utilisé par `Upload.php` L70 → `finfo`) ; aucune dépendance Composer externe.
- Web server Apache/LiteSpeed avec `mod_rewrite` + `AllowOverride All` (`.htaccess`).
- Docroot pointé sur **`public/`** ; le reste (`src/config/data/templates`) hors web.
- Base **MySQL/MariaDB** à créer **hors conteneur** (migrations non embarquées).
- Variables d'env : `DB_DSN` (ou le jeu `DB_*`, y compris **`DB_PASS`**, et non
  `DB_PASSWORD`), `APP_ENV=production`, `APP_DEBUG=false`, `COOKIE_SECURE=true`.
- **Pas** de tâche cron nécessaire (rappels calculés à la volée — Phase E2).

## 8. Ce qui manque pour un déploiement serein (→ bloqueurs en doc 04)

1. Corriger l'incohérence `DB_PASS`/`DB_PASSWORD`.
2. Ajouter la configuration **TLS** PDO (CA/mode) pour une base distante.
3. Fournir un **endpoint de santé** qui teste réellement la base (aucun n'existe).
4. Résoudre la **persistance des uploads** (disque/objet/ou compromis).
5. Prévoir un **document root** `/public` (Render Docker OK ; mutualisé → à valider).
