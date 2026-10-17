# 01 — Inventaire technique du dépôt (§7)

Périmètre analysé : principalement `docs/mes_orchidees_php/` (base de travail imposée),
plus la librairie router et l'exemple MVC de référence.

---

## 1. Nature du dépôt racine

- `C:\Users\jerem\Desktop\mes_orchidees\` : **non versionné** (aucun `.git`). Espace de
  travail contenant l'archive de formation (`docs/MODULES/`) et plusieurs sous-projets
  Git indépendants. `VERIFIED`.
- 25 sous-dépôts Git détectés (voir `04-baseline.md` §1). Seuls deux sont dans le
  périmètre projet : `docs/mes_orchidees_php/` et `docs/support_formateur/router-librairie/`.

## 2. Langages et technologies réellement présents

| Technologie | Présent ? | Preuve | Usage actuel |
|---|---|---|---|
| PHP 8 | Oui | `index.php`, `api/**/*.php` | Gabarits de pages + API |
| HTML | Oui | Toutes les pages `.php` | Structure de rendu |
| SCSS → CSS | Oui | `assets/scss/`, `assets/css/style.css(.min)` | Compilation manuelle |
| JavaScript (ES modules) | Oui | `assets/js/**` (core, services, features) | Logique front + données |
| PostgreSQL (Neon) | Oui | `api/config/db.php`, `data/schema_neon.sql` | Persistance API |
| JWT (maison) | Oui | `api/middleware/auth.php` | Auth API |
| PWA | Oui | `sw.js`, `manifest.json` | Hors-ligne |
| Docker | Oui | `Dockerfile` (php:8.2-apache) | Conteneur |
| Composer | **Non** | aucun `composer.json` dans `mes_orchidees_php` | — |
| Mithridatem Router | **Non installé** | présent uniquement dans `support_formateur/` | — |

## 3. Architecture observée (état réel, ≠ MVC)

**Modèle de pages :** fichiers `.php` à la racine (`index.php`, `encyclopedie.php`,
`macollection.php`, `conseils.php`, `administration.php`, `authentification.php`,
`confidentialite.php`, `mentions.php`, `deconnexion.php`, `accessibilite.php`).
Chaque page :
- inclut des partials via `require_once 'includes/*.php'` (`head`, `header`, `sidebar`,
  `footer`, `orchid-modal`, etc.) ;
- laisse le JS client (`assets/js/**`) remplir le contenu depuis `assets/js/data/*.json`.

> Preuve : `encyclopedie.php` l.2 — commentaire « Page branchée sur /assets/js/data/*.json
> via db.js (simulation Supabase) ». Les pages ne consomment **pas** l'API `api/*.php`
> ni la base : le peuplement reste JavaScript/localStorage/JSON.

**API :** endpoints PHP monolithiques par fichier, sans routeur ni dispatcher :
`api/auth/{login,logout,register,me}.php`, `api/orchids/{index,search}.php`,
`api/collections/{index,upsert}.php`. Chacun appelle directement `getDb()` (PDO).

**Persistance :** `api/config/db.php` (PDO Neon, parse DSN, SSL+SNI endpoint),
`api/config/env.php` (parseur `.env` maison). Schémas multiples :
`data/schema_{mysql,neon,supabase}.sql`, `api/migration/schema.sql`,
migration `data/migrations/20260826_collections_owner_scope.sql`, seeds `data/seeds/0*.sql`.

**Middleware :** `api/middleware/{auth,http}.php` — fonctions globales (`requireAuth()`,
`verifyJWT()`, `getCurrentUser()`), **pas** une pipeline de routeur.

**Sécurité transport :** `.htaccess` bloque listage, fichiers `^\.` et `.(env|sql|md|lock)`,
interdit `api/migration/`, force MIME JS/manifest.

## 4. Frontends / points d'entrée

- Docroot = **racine du projet** (pages `.php` à la racine). Ce n'est **pas** le motif
  `public/` exigé par l'exemple MVC et par un déploiement propre du router.
- Pas de point d'entrée unique / front-controller.

## 5. Modèle de données (entités CRUD candidats)

D'après `data/seeds/` et les services JS (non vérifié côté schéma SQL complet) :
`users`, `orchids` (21 espèces), `conseils` (+ soins), `collections` (propriétaire scopé),
`soins`, `notifications`. → Correspond aux futures routes CRUD de la mandate.

## 6. Tests existants

- **Aucun** test PHP (pas de `phpunit.xml`, pas de dossier `tests/`) dans `mes_orchidees_php`.
- Des skills de tests existent dans `/agent/skills` (unit, e2e) mais non appliqués au projet.
- La librairie router, elle, est testée (`router-librairie/tests/`, `phpunit.xml.dist`).

## 7. CI/CD, build, scripts

- `mes_orchidees_php` : aucun workflow CI, aucun script Composer (pas de composer.json).
  Build CSS = compilation SCSS manuelle (fichiers `.min`/`.map` commités).
- `router-librairie` : workflow GitHub Actions (`.github/workflows/`), scripts `test`/`check`.

## 8. Composants historiques / incohérents

- Trois schémas SQL concurrents (mysql, neon, supabase) → source de Vérité non tranchée.
- `README.md` du projet décrit un état « JS vanilla + localStorage, pas de BDD » alors que
  l'arborescence contient déjà PHP + PDO + Neon → **document obsolète vs code réel**.
- Dossiers d'exercices PHP (`MODULES/PHP/{structure_mvc, old_structure_mvc, structure_mvc - de base- vierge}`)
  présents : références de cours, non composants du projet.

## 9. Limites de l'analyse

- Schémas SQL et seeds **inventoriés mais non lus intégralement** (volume) → le modèle
  de données détaillé reste `NOT-VERIFIED`.
- `assets/js/**` (logique applicative front) inventorié, non audité ligne à ligne.
- Aucun fichier PDF (cours, présentation projet) lu (format non supporté par l'agent).
