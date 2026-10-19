# 03 — Faisabilité et limites de l'offre gratuite GoogieHost

Confrontation des **exigences réelles du dépôt** avec l'offre gratuite GoogieHost.
Aucun compte créé, aucun fichier téléversé, aucun déploiement (interdits par la
mission).

## 1. Exigences réelles du projet (rappel, VERIFIED doc 01-02)

| Exigence | Constat dépôt |
|----------|---------------|
| PHP | CLI 8.3.33 ; `composer.json` exige `>=8.1` |
| Extensions | `pdo_mysql` (utilisée), `mbstring`/`json` implicites ; **aucune** dépendance externe (vendor = autochargeur seul) |
| Base | MySQL/MariaDB, DSN `mysql:` codé en dur |
| Schéma | utilise `FULLTEXT`, `DEFAULT (UUID())` → **nécessite MySQL 8.0.13+** ou MariaDB ≥10.2 |
| Routage | front-controller `public/index.php` + `public/.htaccess` (mod_rewrite/LiteSpeed) |
| HTTPS | cookies `COOKIE_SECURE=true` attendus en prod (`.env.production.example` L46-48) |
| Cron | **BESOIN RÉEL ? NON** — les rappels sont calculés à la volée (requêtes `notif_date <= NOW()`), aucun mécanisme de tâche planifiée n'existe dans le code. **VERIFIED** |

## 2. Ce qui est confirmé par la documentation officielle GoogieHost

**DOCUMENTED** (googiehost.com/freephphosting, /freehosting, reviews citées) :
- PHP multi-versions dont **8.x (7.4 → 8.3, « PHP 8.4 ready »)** → couvre le besoin `>=8.1`. 
- **MySQL** + **phpMyAdmin** inclus. 
- **Free SSL** (Let's Encrypt) disponible. 
- Panneau **DirectAdmin** (pas cPanel), **NVMe SSD**, bandeau « unmetered bandwidth ».
- Plan gratuit : ~**1000 MB (1 GB) NVMe**, **1 site**, sous-domaine gratuit, sans CB.

## 3. Confrontation exigence ↔ offre

| Critère | Verdict | Détail / condition |
|---------|---------|--------------------|
| Version PHP | **COMPATIBLE** | 8.x disponible ≥ 8.1. *Mais* il faut sélectionner la version dans DirectAdmin. |
| Extensions PDO | **À VÉRIFIER** | `pdo_mysql` quasi certain sur hôte MySQL ; `pdo_pgsql` **inutile** (app = MySQL). Étendue exacte = **NOT VERIFIED** (dépend du compte). |
| Moteur MySQL | **COMPATIBLE SOUS CONDITION** | GoogieHost fournit MySQL ; **condition bloquante** : la version doit être **≥ 8.0.13** (ou MariaDB récent) pour `DEFAULT (UUID())` + `FULLTEXT`. Si le compte expose du MySQL 5.7, le `schema_mysql.sql` **refusera de s'importer tel quel**. → **à vérifier dans le compte** (NOT VERIFIED). |
| Réécriture d'URL | **COMPATIBLE** | DirectAdmin/LiteSpeed accepte `.htaccess` + `mod_rewrite`. **Mais** le docroot par défaut est `public_html`, or le webroot du projet est **`/public`**. |
| Structure docroot | **OBSTACLE CONFIG** | Deux options sans toucher au code : (a) pointer le domaine vers `…/public` si DirectAdmin autorise un sous-dossier de racine ; (b) sinon déplacer le contenu de `public/` à la racine `public_html` et remonter `src/config/data/vendor/templates` **hors** de la zone web (via FTP dans un dossier parent). **INFERRED** — faisable, mais procédure à valider sur le compte. |
| SSL/HTTPS | **COMPATIBLE** | Free SSL fourni → cookies `COOKIE_SECURE=true` OK. |
| Variables d'env | **À VÉRIFIER / contournable** | DirectAdmin free n'offre généralement **pas** de variables d'env applicatives. `config/env.php` lit un **fichier `.env`** à la racine → c'est la voie prévue (poser `.env` hors docroot). `getenv()` sert de secours. **VERIFIED** (mécanisme) ; **NOT VERIFIED** (UI du compte). |
| Import/export BDD | **COMPATIBLE** | phpMyAdmin fourni → import du `.sql`, export (sauvegarde). Limites de taille = 1 GB global ; base de démo modeste. |
| Restriction stockage | **COMPATIBLE (serré)** | 1 GB : acceptable pour du PHP + quelques images d'orchidées. Les images importées (ex. `vanilla_imperialis.jpg`) comptent. |
| Exécution / fichiers | **À VÉRIFIER** | Certains hôtes free bloquent `exec`, certains binaires, ou imposent `open_basedir`. L'app n'utilise pas `exec` → probablement OK. **NOT VERIFIED**. |
| Inactivité / suspension | **RISQUE RÉEL** | Les offres free peuvent **suspendre** un site inactif ou saturer les ressources (« unmetered » ≠ illimité en pratique). **DOCUMENTED/INFERRED** (conditions générales à relire). |
| Cron | **NON BLOQUANT** | Le projet **n'en a pas besoin** (§1). Sa présence/absence sur GoogieHost est **sans impact**. |

## 4. Verdict GoogieHost

> **COMPATIBLE SOUS CONDITIONS.**

Conditions à lever **avant** d'y présenter le projet (toutes vérifiables dans le
compte, **BLOCKED** ici car aucune création de compte n'est autorisée) :
1. **Version MySQL ≥ 8.0.13** (sinon adapter le schéma : remplacer
   `DEFAULT (UUID())` — l'app générant déjà les UUID en PHP, un simple
   `DEFAULT ''`/NULL retiré suffit côté DDL ; garder `FULLTEXT`).
2. **Accès au docroot `/public`** ou restructuration FTP décrite §3.
3. **Dépôt d'un `.env`** hors zone web (puisqu'il n'y a probablement pas de
   variables d'environnement managées).
4. Accepter le **risque de suspension/inactivité** → voir le plan de secours doc 05.

Ces conditions concernent l'**hébergement**, pas une réécriture applicative
majeure : **pas de changement de moteur, pas de refactor SQL** requis pour
GoogieHost (contrairement à PostgreSQL). C'est le point décisif pour le choix
d'architecture (doc 04, option A).

## 5. Inconnues assumées (à vérifier dans un compte)

Nombre exact de bases MySQL/allouées, version MySQL précise du compte gratuit,
politique de suspension pour inactivité, `open_basedir`/extensions, possibilité
de changer le répertoire racine du domaine. Aucune ne peut être **VERIFIED** sans
ouvrir un compte → **NOT VERIFIED / BLOCKED** (la mission interdit de créer un
compte). Elles ne remettent pas en cause la compatibilité de principe « MySQL +
PHP 8 + .htaccess + SSL », qui est, elle, **DOCUMENTED**.
