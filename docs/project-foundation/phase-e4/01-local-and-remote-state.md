# 01 — Photographie de l'état local et du dépôt distant

Audit lecture seule du 2026-10-19. Toutes les sorties sont des observations
directes (`VERIFIED`) sauf mention contraire.

## 1. Identité du dépôt local

| Élément | Valeur | Niveau |
|---------|--------|--------|
| Racine réelle (`git rev-parse --show-toplevel`) | `C:/Users/jerem/Desktop/mes_orchidees` | VERIFIED |
| Branche courante | `main` | VERIFIED |
| HEAD | `ca40ef5` — « docs(phase06): infrastructure decision dossier… » (2026-10-17 20:11) | VERIFIED |
| Distant | `origin` → `https://github.com/jeremyferrierpro-create/mes_orchidees_php.git` (fetch+push) | VERIFIED |
| Index (stagé) | **vide** — `git diff --cached --stat` ne renvoie rien | VERIFIED |
| Stash | **vide** — `git stash list` ne renvoie rien | VERIFIED |

## 2. Branches et références locales

```
  backup/pre-mvc-baseline  c88eb26  (2026-10-17 14:16) sauvegarde pré-migration MVC (baseline Phase 00)
* main                     ca40ef5  [origin/main: ahead 6]
  remotes/origin/HEAD      -> origin/main
  remotes/origin/dev       3dcc3c1  mise a jour dokerfiles
  remotes/origin/main      c031e12  Update : Correction fetch sur neon
```

`VERIFIED` — `git branch -a -vv`, `git log -1` par référence.

## 3. État du distant (vérifié en direct, sans fetch)

`git ls-remote origin` (2026-10-19) :

```
c031e12ed91a356ca573934d1bcf03ead9e05ae5  HEAD
3dcc3c14e3037121d085f5280c51641df8876f17  refs/heads/dev
c031e12ed91a356ca573934d1bcf03ead9e05ae5  refs/heads/main
```

- Les refs locales `origin/main`/`origin/dev` sont **à jour** du distant (aucun
  fetch nécessaire, aucune écriture). `VERIFIED`
- `main` local : **6 commits d'avance, 0 de retard** sur `origin/main`
  (`git rev-list --left-right --count` = `0 6`). `VERIFIED`
- `origin/dev` vs `main` local : `9 13` — **et `git merge-base main origin/dev`
  est vide** : les deux branches n'ont **aucun ancêtre commun**. Le dépôt
  GitHub contient deux histoires complètes et incompatibles du projet. `VERIFIED`
- Le premier commit de `main` (racine locale) est `f520c7b` (« Correction des
  fichiers php + correction du responsive ») ; `main` compte **13 commits** au
  total. La racine de `dev` est `d31967d` (« Initial commit »). `VERIFIED`
- Hypothèse argumentée (`INFERRED`) : `main` a été **re-poussée avec une
  historique tronquée/réécrite** lors de la refonte (commit `0a9872a`
  « refactor(mvc): extraction du projet a la racine »), laissant `dev` comme
  témoin de la ligne historique complète.

## 4. Inventaire du travail non commité (arbre de travail vs HEAD)

`git status --porcelain -uall` = **119 entrées** (identique en début et fin d'audit) :

| Catégorie | Nombre | Détail |
|-----------|--------|--------|
| Modifications indexées | **0** | index vide |
| Modifications non indexées (suivies) | **46 `M`** | controllers, entities, repositories, templates, assets, config/routes.php, schema_mysql.sql, seeds |
| Suppressions suivies | **2 `D`** | `public/assets/scss/style.css`, `public/assets/scss/style.css.map` (artefacts SCSS déplaqués — non restaurés, conformément à la consigne) |
| Fichiers non suivis | **71 `??`** | voir répartition ci-dessous |
| Volume du diff non indexé | 48 fichiers, **+6422 / −3428** | `git diff --stat` |

### Répartition des 71 non-sus

- **Code applicatif neuf (fonctionnalités post-`ca40ef5`)** : `src/Controller/RappelController.php`,
  `src/Service/RappelService.php`, `src/Service/Upload.php`,
  `src/Entity/{CategorieConseil,TypeSoin}.php`,
  `src/Repository/{CategorieConseil,TypeSoin}Repository.php`,
  `templates/components/rappels_modal.php`, `templates/pages/rappels/{_liste,index,modal}.php`,
  `public/assets/js/features/{rappels-modal,rappels-toast}.js`,
  8 partiels SCSS nouveaux (`_badges/_fields/_flash/_tables/_fiches/_mvc-*`) — `VERIFIED` (liste porcelain).
- **Migrations SQL non versionnées (5)** : `data/migrations/20261018_{collection_free_add,gestion_rappels_soins,lookup_types_soin_categories_conseil,optin_rappel_soins,rappel_type_soin}.sql` — `VERIFIED`.
- **Tests** : `tests/http/{client,fixtures,run,server-router}.php`,
  `tests/js/{rappels-modal,router}.test.mjs` — `VERIFIED`.
- **Documentation de phases** : `docs/project-foundation/{phase-c1,phase-d,phase-e,phase-e2,phase-e3,phase-07}/*` (33 fichiers), `docs/architecture/*` (3 fichiers) — `VERIFIED`.
- **Données binaires / divers** : `public/assets/images/orchids/vanilla_imperialis.jpg`,
  `public/assets/images/uploads/.gitkeep` **et une photo uploadée de démo**
  `photo-e7c9c86f11f17476d5a3dc30.jpg` (artefact de test à ne pas publier),
  `.vscode/settings.json` — `VERIFIED`.

### Éléments d'origine/objectif non déterminables

- La photo uploadée `photo-e7c9…jpg` provient vraisemblablement d'un parcours
  de test d'upload local (`INFERRED`) ; son origine exacte n'est pas prouvée.
- `backup/pre-mvc-baseline` (`c88eb26`) : son lien d'ascendance avec la ligne
  `main` actuelle n'a pas été vérifié commit à commit (hors périmètre utile) —
  `NOT VERIFIED`, sans impact sur la stratégie.

## 5. Divergences structurelles local ↔ GitHub

`git diff --name-only origin/main HEAD` = **361 fichiers** différents : la
refonte MVC n'existe **nulle part sur le GitHub publié**. `VERIFIED`

Arbre `origin/main` (version publiée, à plat, legacy) :
`accessibilite.php … mentions.php` à la racine, `api/`, `includes/`, `assets/`,
`data/`, `Dockerfile`, `.htaccess` racine. `VERIFIED` (`git ls-tree`)

Arbre `HEAD` local (MVC) : `config/`, `lib/Mithridatem/Router/`, `public/`
(docroot), `src/{Controller,Entity,Repository,Service,Security}`, `templates/`,
`tests/`, `data/{migrations,seeds}`, `archive/legacy/`, `server.php`,
`composer.json`, `Dockerfile`, `.dockerignore`, `.env.production.example`. `VERIFIED`

Aucun `render.yaml`, aucun workflow `.github/` n'existe sur une des trois
références (recherché par `git ls-tree -r` + motif `render|yml|yaml`) :
**le déploiement Render n'est piloté par aucun fichier versionné visible** —
s'il existe, il est configuré dans le dashboard Render. `VERIFIED` (absence dans les arbres) / `NOT VERIFIED` (ce que le dashboard contient).

## 6. Fichiers de configuration réellement présents (local)

| Fichier | Contenu vérifié | Niveau |
|---------|-----------------|--------|
| `.env` (non lu, clés seules) | `APP_ENV APP_DEBUG DB_HOST DB_PORT DB_NAME DB_USER DB_PASSWORD DB_CHARSET APP_SECRET` — **pas de `DB_PASS`, pas de `DB_DSN`** | VERIFIED (noms de clés uniquement, valeurs jamais affichées) |
| `.env.example` | mêmes clés que `.env` | VERIFIED |
| `.env.production.example` | `DB_DSN, DB_USER, DB_PASS, COOKIE_SECURE, COOKIE_SAMESITE, COOKIE_DOMAIN, APP_BASE_PATH…` — attendu Render | VERIFIED |
| `Dockerfile` (local, 70 l.) | `php:8.2-apache`, `pdo_mysql`+`mbstring` seuls, docroot `public/`, `EXPOSE 80`, `.env` retiré de l'image, commentaire « pilotes PostgreSQL/Neon retirés » | VERIFIED |
| `.dockerignore` | exclut `.env*`, `vendor/`, `docs/`, **`data/migrations/`** | VERIFIED |
| `config/db.php` | DSN `mysql:` assemblé en dur (L65), lit `DB_PASS` (L51), **aucune option TLS PDO** | VERIFIED |
| `README.md` (local, == publié) | décrit la version « JavaScript vanilla + localStorage », « pas besoin de XAMPP », Supabase comme avenir lointain — **obsolète de deux générations** ; non modifié (consigne : n'y pas toucher) | VERIFIED |

## 7. Blocage config confirmé (rappel, sans correction)

`VERIFIED` — `.env` définit `DB_PASSWORD` ; `config/db.php` lit `DB_PASS`.
Le DSN ne posant pas de mot de passe via `DB_PASSWORD`, la connexion locale ne
fonctionne que si le compte utilisé admet un mot de passe vide (root MariaDB
local — `INFERRED`, cohérent avec le rapport E3 §4). Bloqueur pour tout
déploiement ; **non corrigé dans cette phase** (INTERDIT).
