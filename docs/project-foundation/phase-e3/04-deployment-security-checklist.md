# 04 — Checklist de configuration et de sécurité du déploiement + bloqueurs

Checklist pour une **future** mise en ligne. Les défauts sont **documentés comme
bloqueurs**, **non corrigés** dans cette phase (interdiction de modifier le code).

## A. Configuration de l'environnement de production

- [ ] `APP_ENV=production` et **`APP_DEBUG=false`** (coupe l'affichage des traces —
  `public/index.php` L55-56, L163-165). **Mécanisme prêt (VERIFIED)**.
- [ ] Environnement **explicite** : la config vient des **variables de la plateforme**
  (Render dashboard / `.env` hors docroot), **pas** d'un `.env` commité. Le
  `Dockerfile` supprime `.env*` (L60). **VERIFIED**.

## B. Secrets et identifiants

- [ ] Secrets fournis **hors dépôt** (variables d'env) : `DB_*`, `APP_SECRET`.
- [ ] **Jamais** de mot de passe dans le code/commit. Aucun secret affiché ici.
- [ ] `.env` **protégé** : hors docroot ; `public/.htaccess` refuse les fichiers
  cachés `^\.` (L17-19). **VERIFIED** pour le docroot `public/`.

## C. 🔴 Bloqueur B1 — Incohérence `DB_PASSWORD` / `DB_PASS`

- Constat : `config/db.php` lit **`DB_PASS`** (L51) ; `.env`/`.env.example`
  définissent **`DB_PASSWORD`**. La connexion dev réussit uniquement car `root` a
  un mot de passe **vide**. **VERIFIED**.
- Risque déploiement : une base distante avec mot de passe **échouerait** (mauvais
  nom de variable) → erreur de connexion silencieuse.
- **Action de correction (après validation)** : harmoniser — soit lire
  `DB_PASSWORD` en plus de `DB_PASS` dans `db.php`, soit renommer la clé dans le
  `.env`. **Ne rien faire ici.**

## D. 🔴 Bloqueur B2 — TLS absent pour base distante

- Constat : `new PDO()` ne fixe **aucune** option SSL (`db.php` L69-73), et le DSN
  n'inclut ni `ssl-ca` ni `ssl-mode`. Les MySQL managés (TiDB, etc.) exigent le
  TLS. **VERIFIED (absence)**.
- **Action (après validation)** : ajouter `PDO::MYSQL_ATTR_SSL_CA` (et mode) ou un
  DSN `;ssl-mode=REQUIRED`, CA récupéré auprès du fournisseur. **Ne rien faire ici.**

## E. Utilisateur SQL et privilèges

- [ ] Créer un **utilisateur SQL dédié** (pas `root`), privilèges **minimaux** sur
  **une seule** base (`GRANT SELECT,INSERT,UPDATE,DELETE` ; DDL seulement pour la
  migration). 
- [ ] **Interdiction d'utiliser `root` en production.** En dev local, `root` à vide
  est un problème **documenté**, pas une cible de prod. **INFERRED** (bonne pratique).

## F. HTTPS et cookies de session

- [ ] HTTPS activé (Let's Encrypt GoogieHost / TLS managé Render). 
- [ ] `COOKIE_SECURE=true` (cookie émis uniquement sur TLS, `index.php` L63, L71) ;
  `COOKIE_SAMESITE=Lax` ; `httponly=true`. **Mécanisme prêt (VERIFIED)**.
- Note : sur Render free, le spin-down **vide les sessions fichiers** → reconnexion
  requise après veille. **DOCUMENTED** (doc 01 §6).

## G. Document root et routage

- [ ] **Docroot = `public/`** : Render Docker l'a fait (`Dockerfile` L31-35). Sur
  mutualisé, **le valider/pointer** (sinon restructuration FTP). **VERIFIED / à confirmer compte**.
- [ ] `mod_rewrite`/LiteSpeed + `AllowOverride All` → `public/.htaccess` route vers
  `index.php`. `FallbackResource /index.php` en secours (Dockerfile L40). **VERIFIED**.
- [ ] `APP_BASE_PATH` : vide si racine ; renseigner si sous-dossier (`index.php` L98-101).

## H. Port / démarrage (contrainte Render)

- [ ] Apache écoute en dur sur **80** (Dockerfile L67). Sur Render, indiquer
  **Service Port = 80**. L'adaptation `$PORT` dynamique **n'est pas** faite.
  Si Render impose un port autre, prévoir l'adaptation **après validation**. **VERIFIED**.

## I. 🔴 Bloqueur B3 — Persistance des uploads

- Constat : `Upload::image()` écrit sur le **filesystem** `public/assets/images/uploads`
  (L84, L101). Render free = **ephemeral**, **pas de disque persistant gratuit**
  → **perte des photos** au spin-down/redeploy. **VERIFIED + DOCUMENTED**.
- Options (à arbitrer, **non implémentées**) :
  1. Hébergeur à disque persistant gratuit (→ **scénario D**). 
  2. Rendre l'app **stateless** (stocker les binaires en **stockage objet** type
     S3/Cloudflare R2) → **nouvelle dépendance/fonctionnalité**, hors périmètre. 
  3. **Accepter** l'ephémérité pour la démo (ne pas montrer l'upload en ligne). 

## J. Migrations et initialisation du schéma

- 🔴 **Bloqueur B4** : `.dockerignore` **exclut `data/migrations/`** → les
  migrations ne sont **pas** dans l'image. Le schéma distant doit être créé **hors
  conteneur** (import `schema_mysql.sql` + migrations + seeds via client SQL/
  phpMyAdmin). **VERIFIED**.
- [ ] **Sauvegarde avant** toute migration ; migrations **contrôlées** (ordre
  chronologique `20261018_*`). 
- [ ] Attention à la **version MySQL** de la base distante (≥ 8.0.13 pour
  `DEFAULT (UUID())` + FULLTEXT). **NOT VERIFIED** jusqu'au compte.

## K. 🔴 Bloqueur B5 — Endpoint de santé inadéquat

- Constat : **aucune** route `/health`/`/status` n'existe (grep routes +
  controllers = 0). La PDO étant **paresseuse**, un HTTP 200 **ne prouve pas** que
  la base répond. **VERIFIED**.
- **Action (après validation)** : créer un endpoint de santé qui ouvre `getDb()` et
  fait `SELECT 1`, renvoie **503** si la base est KO (distinct d'un simple 200).
  **Ne rien implémenter ici.**

## L. Sauvegarde / restauration / retour arrière

- [ ] Export régulier de la base distante (`mysqldump`/phpMyAdmin) + **snapshot
  pré-démo**. 
- [ ] Plan de **rollback** : Render « rollbacks » limité aux **2 derniers deploys**
  sur free (DOCUMENTED) ; prévoir le dernier build sain. 
- [ ] Sauvegarde des uploads (copie du dossier `uploads/`) si disque persistant.

## Récapitulatif des bloqueurs (à traiter APRÈS validation de l'architecture)

| ID | Bloqueur | Gravité | Type d'action |
|----|----------|---------|---------------|
| B1 | `DB_PASS` ≠ `DB_PASSWORD` | 🔴 bloquant (connexion) | mini-fix config |
| B2 | TLS PDO absent | 🔴 bloquant (base distante TLS) | fix config + CA |
| B3 | Uploads éphémères | 🔴 fonctionnel | arbitrage archi (D/objet/accepter) |
| B4 | Migrations non embarquées | 🟠 process | import SQL hors conteneur |
| B5 | Pas d'endpoint de santé réel | 🟠 fiabilité | mini-feature (validée) |
| B6 | Docroot `/public` sur mutualisé | 🟠 config | procédure FTP/panneau |

> **Aucun de ces bloqueurs n'est corrigé dans la Phase E3** (audit seulement). Ils
> constituent la liste des travaux à autoriser explicitement avant tout déploiement.
