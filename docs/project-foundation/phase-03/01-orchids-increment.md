# PHASE 03 — Incrément 1 : Encyclopédie & Orchidées

> **Projet :** MES ORCHIDEES (fil rouge DWWM) · **Auteur :** Jérémy Ferrier
> **Date :** 2026-10-17 · **Prérequis :** Phase 02 (MVC/routeur/auth) + Phase 02.5 (nettoyage).
> **Portée :** 1er incrément métier — modèle `Orchid`, persistance MySQL/PDO, contrôleur CRUD,
> vues **rendues côté serveur**. Les autres entités (Collection, Soin, Conseil, Notification,
> Utilisateur, Modération) suivront dans les incréments suivants.

---

## 1. Contrat de routes (rappel, `config/routes.php`)

| Méthode | Route | Action | Grant | Rendu |
|--------|-------|--------|-------|-------|
| GET | `/orchids` | `index` | public | **JSON** liste publiée |
| GET | `/orchids/search` | `search` | public | **JSON** résultats (`?q=` / `?search=`) |
| GET | `/encyclopedie` | `EncyclopedieController::index` | public | **HTML** grille serveur |
| GET | `/orchid/{id}` | `show` | public | **HTML** fiche (publiée only, sinon 404) |
| GET | `/orchid/new` | `newForm` | admin | **HTML** formulaire création |
| POST | `/orchid/new` | `create` | admin | enregistre **publiée** → redirect fiche |
| POST | `/orchid/propose` | `propose` | authentifié | enregistre **non publiée** (modération) → redirect |
| GET | `/update/orchid/{id}` | `editForm` | admin | **HTML** formulaire pré-rempli |
| POST | `/update/orchid` | `update` | admin | maj (bascule `is_published`) → redirect fiche |
| POST | `/orchid/delete/{id}` | `delete` | admin | suppression → redirect encyclopédie |

Nomenclature respectée : liste au pluriel, fiche/action au singulier, MAJ via `/update/{entite}`.
Ordre de déclaration : routes **statiques avant dynamiques** (`/orchid/new` avant `/orchid/{id}`).
Le routeur passe les paramètres `{id}` en **arguments nommés** (`$callable(...$params)`), d'où
les signatures `show(string $id)`, `delete(string $id)`, `editForm(string $id)`.

## 2. Entité — `src/Entity/Orchid.php`

- Propriétés **camelCase** mappées sur les colonnes **snake_case** MySQL (`botanical_order`,
  `short_desc`, `long_desc`, `is_published`, `proposed_by`…).
- `id` = **slug texte** (ex. `acacalis_cyanea`), convention héritée du `orchids.json` d'origine.
- `fromRow(array)` : hydratation depuis une ligne PDO (normalise `is_published` TINYINT → bool).
- `imageUrl()` : normalise un chemin stocké (`./assets/…`) via l'helper `asset()` (compatible
  sous-répertoire Render).
- `scientificName()` : `genre + espèce` (repli sur `name`).
- `toArray()` : représentation JSON de l'API (clé `order` pour rester compatible avec le front).

## 3. Repository — `src/Repository/OrchidRepository.php`

Accès PDO **MySQL** à la table `orchids`, **traduction serveur** des anciens endpoints
`api/orchids/*.php` qui étaient en PostgreSQL/Neon (`public.`, `to_tsquery`, `ILIKE`) :

| Méthode | SQL |
|---------|-----|
| `findAllPublished()` | `SELECT … WHERE is_published = 1 ORDER BY name` |
| `search(string)` | index **FULLTEXT** `MATCH(name, vernacular, short_desc, origin) AGAINST(… IN NATURAL LANGUAGE MODE)` **OR** repli `LIKE` sur nom/vernaculaire, tri par score, LIMIT 30 |
| `findById(id, publishedOnly)` | fiche ; `$publishedOnly=true` → n'admet que les publiées |
| `create / update(Orchid)` | `INSERT` / `UPDATE` via `bind()` (paramètres nommés) |
| `delete(id)` | `DELETE` + `rowCount()` |
| `exists(id)` | détection de doublon (slug) |

Sécurité & robustesse : **connexion paresseuse** (`pdo()` → `getDb()` à la 1re requête),
**requêtes préparées** partout (`ATTR_EMULATE_PREPARES=false`), `family` par défaut `Orchidaceae`,
booléen → `1/0` pour MySQL.

## 4. Contrôleurs

### `OrchidController` (constructeur sans argument, dépendance composée : `new OrchidRepository()`)
- Lecture : `index()` / `search()` → `json()` ; `show()` → vue `pages/orchids/show`.
- Écriture : `newForm()` / `create()` (admin, publiée) / `propose()` (user, non publiée,
  `proposed_by` = id de session) / `editForm()` / `update()` / `delete()`.
- Chaque POST : **`assertCsrf($_POST)`** ; `redirect` + **message flash** après action.
- Hydratation formulaire : `fromRequest()` (trim, `name` mis en majuscules, `null` si vide),
  `slugify()` pour fabriquer l'`id` (translittération accents → `_`), `strOrNull()`.
- `show()` et `editForm()` renvoient une **404** homogène (vue `pages/error`) si absente/non publiée.

### `EncyclopedieController`
- Injecte `OrchidRepository`. `index()` : catalogue publié, ou résultats filtrés si
  `?search=`/`?q=` présent. Rendu HTML serveur → **remplace le peuplement JS** d'origine.

## 5. Vues (`templates/`)

- `pages/encyclopedie.php` — en-tête + **formulaire de recherche GET** (reload serveur) +
  grille `#orchid-grid-server` de cartes ; message « aucun résultat ».
- `components/orchid_card.php` — carte réutilisant la CSS legacy `.orchid-card`
  (`card-img`, `card-info`, `h3`, `.vernacular-name`, `.short-desc`, `.card-btn`), le bouton
  pointant vers la fiche durable `/orchid/{id}`.
- `pages/orchids/show.php` — fiche complète (image, titres, badge « en attente », grille des
  caractéristiques botaniques, description longue, actions) ; **modifier/supprimer** visibles
  seulement pour admin/modérateur (les `data-confirm` remplacent les gestionnaires inline,
  conformes à la **CSP** `script-src 'self'`).
- `pages/orchids/form.php` — formulaire création/édition (fieldsets groupés, réutilise les
  valeurs de l'entité, case `is_published` en mode édition, champ caché `id` en édition).

Toutes les sorties dynamiques passent par `e()` (échappement) — anti-XSS systématique.

## 6. Corrections apportées pendant la mise au point

1. **`public/index.php`** : ajout de `require config/db.php` — `getDb()` n'était pas chargé
   (cause des 500 sur `/orchids`, `/encyclopedie`, `/orchid/{id}`).
2. **`OrchidRepository::search()`** : le placeholder `:like` était **réutilisé deux fois** ;
   avec `EMULATE_PREPARES=false`, MySQL/MariaDB rejette la réutilisation d'un paramètre nommé
   → « Invalid parameter number ». Corrigé en `:like1` / `:like2`.
3. **`.env` / seeds** : voir rapport Phase 02.5 (MySQL, bcrypt, cookie host-only).

## 7. Tests (smoke test, `php -S` docroot `public/`, MariaDB `mes_orchidees` 21 orchidées)

**Lecture publique**
| Requête | Attendu | Obtenu |
|---------|---------|------|
| `GET /` | 200 (accueil hero) | **200** |
| `GET /encyclopedie` | 200, grille 21 cartes | **200** (20 500 o) |
| `GET /encyclopedie?search=aerangis` | 200, filtré | **200** |
| `GET /orchid/acacalis_cyanea` | 200 fiche | **200** |
| `GET /orchids` | 200 JSON | **200**, 21 objets |
| `GET /orchids/search?q=vanille` | 200 JSON | **200** → `vanilla_planifolia` |
| `GET /orchid/nope` | 404 | **404** |

**ADMIN (journalisé : connexion → CRUD complet)**
- `POST /login` (admin) → **302** ; `GET /orchid/new` → **200**.
- `POST /orchid/new` → **302** `/orchid/testea_smokea` ; `GET` fiche → **200** (présente).
- `GET /update/orchid/{id}` → **200** ; `POST /update/orchid` → **302**, modification relue.
- `POST /orchid/delete/{id}` → **302** ; re-`GET` → **404**. Données de test supprimées.

**PROPOSITION utilisateur**
- `GET /orchid/new` (rôle user) → **403** (admin-only, correct).
- `POST /orchid/propose` → **302** (fiche **non publiée**) : invisible dans la recherche publique,
  `GET /orchid/{slug}` public → **404**. ⚠️ *Donnée de test nettoyée.*

**Grants** : routes admin sans session → **403** (`/orchid/new`, `/update/orchid/{id}`,
`/macollection`). **`php -l`** : 16 fichiers, 0 erreur.

## 8. Limites connues / prochaines itérations

- **API JSON vs front JS** : `/orchids` et `/orchids/search` sont en place, mais `app.js`
  consomme encore son JSON local (repli). Réorientation vers les routes MVC = *progressive
  enhancement* à planifier.
- **Modération** : la proposition crée bien une fiche `is_published=0` + `proposed_by`, mais
  l'**écran de modération** (`ModerationController`, approbation/rejet) est un incrément dédié.
- **Téléversement d'image** : le champ `img` est saisi par chemin ; l'upload (admin) sera traité
  avec l'incrément médias/sécurité.
- **Friches de l'URL d'image** : les images des fiches pointent sous `/assets/images/orchids/…` ;
  l'absence d'un fichier correspondant n'est pas encore signalée (placeholder CSS `card-img--placeholder`).

## 9. Bilan

Incrément 1 **livré et vérifié** : entité + repository MySQL/PDO + contrôleur CRUD complet
(lecture publique, écriture admin, proposition user) + **vues serveur** (encyclopédie, fiche,
formulaire) conformes au contrat de routes et à la charte sécurité (CSRF, `e()`, requêtes
préparées, grants). Le socle Phase 03 est posé pour les entités restantes.
