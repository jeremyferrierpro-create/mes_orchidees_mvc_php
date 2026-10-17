# 04 — Qualification des prérequis de production (Phase 05)

Examen de la configuration **réelle**, sans contacter aucun service de production.

## 1. Application et conteneur

| Point | Constat | Statut |
|---|---|---|
| Démarrage dans Docker | build OK (`mes-orchidees:phase05`), `docker run` → `GET /` **200** (6821 o HTML) | VERIFIED |
| DocumentRoot | `<VirtualHost *:80>` → `/var/www/html/public` (src/config/templates hors docroot) | VERIFIED |
| Extensions PHP | `php -m` dans le conteneur : **mbstring, PDO, pdo_mysql** (+ pdo_sqlite de base) | VERIFIED |
| Réécriture Apache | `a2enmod rewrite` + `AllowOverride All` sur `public/` + `FallbackResource /index.php` | VERIFIED |
| Permissions | `chown -R www-data:www-data`, dossiers 755 / fichiers 644 | VERIFIED |
| Assets | `style.css`, `style.min.css`, `app.js`, `core/router.js`, `manifest.json`, `favicon.png` présents | VERIFIED |
| Secrets dans l'image | `.env`, `.env.example`, `.env.production.example` supprimés à la build ; `.dockerignore` exclut `.env*` du contexte | VERIFIED |
| Autoload | `composer dump-autoload --optimize --no-dev` (49 classes, aucune dépendance externe) | VERIFIED |

### 1.1 Variables d'environnement effectivement lues par le code

- `config/db.php` : `DB_DSN` **ou** `DB_HOST`/`DB_PORT`/`DB_NAME`/`DB_USER`/**`DB_PASS`**, `APP_DEBUG`.
- `public/index.php` : `APP_DEBUG`, `COOKIE_SECURE`, `COOKIE_SAMESITE`, `COOKIE_DOMAIN`.
- `config/helpers.php` : `APP_BASE_PATH`.
- **Non consommées** à ce stade (présentes dans le gabarit par anticipation) :
  `APP_ENV`, `DB_CHARSET` (charset codé en dur `utf8mb4`), `APP_SECRET`.

> ⚠️ Le nom réel est `DB_PASS` (et non `DB_PASSWORD`) — à respecter chez l'hébergeur.

### 1.2 Comportements de défaillance (VERIFIED par lecture du code)

- **Variable DB obligatoire absente :** `getDb()` lève `RuntimeException`
  « Configuration MySQL absente… » → interceptée par le front-controller →
  `render_error(500, 'Erreur interne…')` **générique**, sans fuite de détail.
- **Mode debug désactivé (défaut) :** `ini_set('display_errors','0')` ;
  les messages d'erreur sont génériques. En `APP_DEBUG=true`, le détail PDO est
  révélé (uniquement pour le diagnostic local).

## 2. Base de données

| Aspect | Constat | Statut |
|---|---|---|
| Moteur réel | **MySQL/MariaDB** (PDO `mysql:` ; aucun SQL PostgreSQL/Neon au runtime) | VERIFIED |
| Version minimale | MariaDB 10.4.32 testée ; MySQL 8 compatible. Nécessite `UUID()` par défaut et **FULLTEXT** | VERIFIED |
| Schéma de référence | `data/schema_mysql.sql` (7 tables, InnoDB, utf8mb4_unicode_ci) | VERIFIED |
| Index FULLTEXT | `ft_orchids_search (name, vernacular, short_desc, origin)` — parser **par défaut** (portable MySQL 8 + MariaDB) | VERIFIED |
| Clés étrangères | `users ← collections/notifications`, `collections ← soins`, `orchids ← …` ; `ON DELETE CASCADE/SET NULL` | VERIFIED |
| Initialisation base vierge | `mysql < data/schema_mysql.sql` puis seeds `data/seeds/` (21 orchidées) | VERIFIED |
| Migrations | scripts présents sous `data/migrations/` ; pas de runner automatisé (procédure manuelle) | INFERRED |
| Sauvegarde/restauration | à assurer par l'hébergeur (`mysqldump`/snapshot) — non implémenté dans le dépôt | BLOCKED (opérateur) |

### 2.1 Schémas hérités — décision de conservation

- `data/schema_neon.sql` et `data/schema_supabase.sql` : hérités de l'architecture
  PostgreSQL initiale. **Aucune référence** dans le code PHP runtime (grep : les
  occurrences Postgres sont des commentaires de doc). → **Recommandation :** les
  documenter comme **archives** (statut « legacy, non utilisé »). **Aucune suppression**
  effectuée dans cette phase (pas de nécessité démontrée + autorisation requise).

## 3. Hébergement — critères pour le fournisseur MySQL/MariaDB externe

Render n'offrant **pas** de MySQL managé (PostgreSQL seulement), la base doit être
**externe**. Critères à satisfaire (le choix de fournisseur reste une **décision
humaine**, voir `06-open-decisions.md`) :

1. **Compatibilité moteur/index** : MySQL 8 **ou** MariaDB ≥ 10.4, avec support
   **FULLTEXT** InnoDB et fonction `UUID()`. Vérifier que le FULLTEXT par défaut est
   activé (certains prestataires mutualisés le restreignent).
2. **Connectivité réseau** : accès TCP 3306/33060 autorisé depuis les IP de Render
   (ou liste blanche / tunnel). TLS si proposé (`ssl_ca`) — à activer si requis.
3. **Gestion des secrets** : identifiants injectés via variables Render (jamais en
   clair dans le dépôt) ; rotation possible sans redéploiement de code.
4. **Sauvegardes/restauration** : snapshots automatiques + point de restauration
   vérifiable ; procédure de restore testée avant mise en production.
5. **Stockage persistant** : données durables (pas l'instance éphémère Render).
6. **Coûts/limites** : taille BDD, connexions max, bande passante — à valider.
7. **Rotation des identifiants** : capacité à changer mot de passe/hôte et rejouer le
   démarrage sans rebuild d'image (12-factor).

> Le port applicatif (80 dans le `Dockerfile`) et l'health check **doivent être
> confirmés sur la plateforme réellement retenue** : ne pas présumer que 80 convient
> à Render (qui surcharge parfois `PORT`). Voir `05-deployment-runbook.md` §5.
