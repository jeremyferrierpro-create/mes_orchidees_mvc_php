# Phase 03 — Incréments 2 & 3 : Conseils, Ma Collection & Soins

**Projet** : MES ORCHIDEES (fil rouge DWWM) — migration PHP MVC.
**Auteur** : Jérémy Ferrier.
**Date** : 2026-10-17.
**Moteur validé** : MariaDB 10.4.32 (client MySQL 8.4.3), PHP 8.3.33.

---

## 0. Objet

Suite à la restructuration (extraction du projet à la racine) et à la migration MySQL
validées, ce document trace :

1. le **commit de stabilisation Git** ;
2. l'**incrément 2 — Conseils de culture** (entité, repository, contrôleur, vues) ;
3. l'**incrément 3 — Ma Collection & Soins** (entités, repositories, contrôleurs, vues)
   avec **isolation stricte par utilisateur (RG1)** ;
4. le **rapport de test navigateur** prouvant que `/conseils` et `/macollection`
   ne répondent plus **501** et sont pleinement fonctionnels.

---

## 1. Commit de stabilisation Git

```
refactor(mvc): extraction du projet a la racine, schema MySQL et socle encylopedie
```

- **Commit** : `0a9872a` (message amendé via `git commit --amend -m`, intégralement
  ASCII — aucune corruption d'encodage PowerShell). Périmètre inchangé : extraction à
  la racine + `public/` + routeur + base MySQL + **socle encyclopédie** (incrément 1).
- **Contenu** : 315 fichiers (3 988 insertions, 17 245 suppressions).
- **Inclus** : déplacements (`git mv`/archive du legacy `api/`, `includes/`, `.php`
  monolithes), le dossier `public/` (front controller, `.htaccess`, assets), le
  routeur (`lib/Mithridatem/Router` + `server.php`), le schéma MySQL
  (`data/schema_mysql.sql`) et tous les seeds, la structure MVC (`src/`,
  `templates/`, `config/`) de l'incrément 1 (orchidées), et l'ignore du matériel de
  cours + `.agent/`.
- **Vérifié** : `.env` et `vendor/` bien ignorés ; arbre de travail propre après commit.

> Les incréments 2 & 3 (ci-dessous) sont produits **au-dessus** de ce commit de
> stabilisation et laissés **non commités** pour relecture (aucun commit n'a été
> demandé pour cette partie).

---

## 2. Incrément 2 — Conseils de culture

### 2.1 Seed MySQL
`data/seeds/03_seed_conseils.sql` est en syntaxe MySQL 100 % valide (déjà converti
lors de la migration) : `INSERT … ON DUPLICATE KEY UPDATE`, `care_cards` en JSON natif,
aucun reste PostgreSQL. Exécution sur base neuve :

```
SEED 03 OK : 6 categories + 10 fiches espece en base
```

### 2.2 Code produit

| Fichier | Rôle |
|---|---|
| `src/Entity/Conseil.php` | Entité (id slug, type, name, img, category, content, `careCards` décodé du JSON, orchidId). `fromRow`, `imageUrl`, `isSpecies`, `toArray`. |
| `src/Repository/ConseilRepository.php` | Accès PDO/MySQL table `conseils` : `findAll`, `findByType`, `search` (FULLTEXT + repli LIKE), `findById`, `create`, `update`, `delete`, `exists`. `care_cards` encodé/décodé dans `bind()`. |
| `src/Controller/ConseilController.php` | Lecture publique + CRUD admin. |
| `templates/pages/conseils/index.php` | Grille + recherche (GET). |
| `templates/pages/conseils/show.php` | Fiche conseil + carte de soins + actions admin. |
| `templates/pages/conseils/form.php` | Formulaire création/édition (admin). |

### 2.3 Routes couvertes (contrat `config/routes.php`)

| Méthode | Route | Action | Grant |
|---|---|---|---|
| GET | `/conseils` | `index` (liste + recherche) | public |
| GET | `/conseil/{id}` | `show` | public |
| GET | `/conseil/new` | `newForm` | admin |
| POST | `/conseil/new` | `create` | admin |
| GET | `/update/conseil/{id}` | `editForm` | admin |
| POST | `/update/conseil` | `update` | admin |
| POST | `/conseil/delete/{id}` | `delete` | admin |

---

## 3. Incrément 3 — Ma Collection & Soins

### 3.1 Seeds MySQL
`04_seed_collections.sql` et `05_seed_soins.sql` (convertis, syntaxe MySQL pure,
sous-requêtes résolvent les UUID par email/legacy_id, `ON DUPLICATE KEY UPDATE`) :

```
SEED 04 OK : 6 entree(s) de collection en base
SEED 05 OK : 4 soin(s) enregistre(s)
```

### 3.2 Code produit

| Fichier | Rôle |
|---|---|
| `src/Entity/Collection.php` | Entité `collections` + champs transitifs d'affichage issus de la jointure `orchids` (`orchidName`, `orchidImg`, `orchidVernacular`). |
| `src/Entity/Soin.php` | Entité `soins` (date, type, notes, booléens engrais/substrat/ravageurs). |
| `src/Repository/CollectionRepository.php` | **RG1** : toute méthode filtre `WHERE … user_id = :uid` (`findByUser`, `findByIdForUser`, `existsForUser`, `updateForUser`, `deleteForUser`). `create()` génère l'UUID en PHP (v4) et l'insère explicitement (sinon `DEFAULT (UUID())` de MySQL ne serait pas récupérable pour une colonne `CHAR`). |
| `src/Repository/SoinRepository.php` | **RG1** : jointure `collections c` + filtre `c.user_id = :uid` sur lecture/écriture/suppression (`findByUser`, `findByCollectionForUser`, `findByIdForUser`, `collectionOwnedBy`, `create`, `updateForUser`, `deleteForUser`). |
| `src/Controller/CollectionController.php` | `/macollection` (dashboard), `/collections` (JSON), `/collection/{id}`, ajout / MAJ / suppression. |
| `src/Controller/SoinController.php` | `/soins` (JSON), enregistrement / MAJ / suppression d'un soin. |
| `templates/pages/collection/dashboard.php` | Tableau de bord + formulaire d'ajout. |
| `templates/pages/collection/show.php` | Fiche plante (conditions, historique soins, ajout soin, suppression). |

### 3.3 Routes couvertes

| Méthode | Route | Action | Grant | Note RG1 |
|---|---|---|---|---|
| GET | `/macollection` | `dashboard` | authentifié | `findByUser(uid)` |
| GET | `/collections` | `index` (JSON) | authentifié | `findByUser(uid)` |
| GET | `/collection/{id}` | `show` | authentifié | `findByIdForUser(id,uid)` → 404 si non owner |
| POST | `/collection/new` | `create` | authentifié | `user_id` pris de la **session**, jamais du POST |
| POST | `/update/collection` | `update` | authentifié | `updateForUser(…,uid)` |
| POST | `/collection/delete/{id}` | `delete` | authentifié | `deleteForUser(id,uid)` |
| GET | `/soins` | `index` (JSON) | authentifié | `findByUser(uid)` |
| POST | `/soin/new` | `create` | authentifié | garde `collectionOwnedBy(cid,uid)` |
| POST | `/update/soin` | `update` | authentifié | `updateForUser(…,uid)` |
| POST | `/soin/delete/{id}` | `delete` | authentifié | `deleteForUser(id,uid)` |

### 3.4 Isolation RG1 — principes appliqués
- Le **propriétaire est dérivé de la session** (`current_user()['id']`), jamais d'une
  entrée client.
- **Lecture et écriture** passent toutes deux par une clause `WHERE user_id` (collection)
  ou une jointure `collections.user_id` (soins) **au niveau repository** : un identifiant
  d'un autre utilisateur renvoie `null` / `false` → **404** ou no-op.

---

## 4. Validation

- **`php -l`** : 14 nouveaux fichiers (3 entités, 3 repositories, 3 contrôleurs,
  5 vues) → **0 erreur**.
- **Base** : `DROP DATABASE` → schéma (6 tables + vue) → 6 seeds, tous `SEED … OK`.
- **Serveur** : `php -S 127.0.0.1:8000 -t public server.php` (routeur de dev), journaux
  sans warning ni erreur PHP.

### 4.1 Routes — avant / après

| Route | Avant (Phase 02) | Maintenant |
|---|---|---|
| `/conseils` | **501** (action CRUD absente) | **200** — grille de 16 conseils |
| `/conseil/{id}` | **501** | **200** |
| `/macollection` | **501** (coquille) | **403** hors connexion / **200** connecté |
| `/collection/{id}`, `/collections`, `/soins` | **501** | **200** connecté / **403** hors connexion |

### 4.2 Tests navigateur (sondes HTTP réelles)

**Lecture publique / grants (hors connexion)**

```
GET /conseils                     -> 200  len=19974   (16 cartes)
GET /conseil/conseils-base        -> 200
GET /conseil/fiche-acacalis_cyanea-> 200
GET /conseils?search=epiphyte     -> 200  (FULLTEXT + LIKE)
GET /macollection                 -> 403  (grant authentifié)
GET /collections  /soins          -> 403
GET /conseil/new                  -> 403  (grant admin)
GET /inexistant-404               -> 404
```

**CRUD Conseil (admin = jeremy.ferrierpro@gmail.com / demoadmin)**

```
GET  /conseil/new                 -> 200 (formulaire)
POST /conseil/new (catégorie)      -> 200 ; GET /conseil/zz_conseil_test -> 200
POST /conseil/delete/zz_conseil_test -> 200 ; GET /conseil/zz_conseil_test -> 404
GET  /conseil/new (user Jessica)   -> 403 (rôle insuffisant)
```

**Collection & soins — cycle d'écriture (Jean‑Marc, owns col‑001/002/003)**

```
login admin@mesorchidees.fr/demouser -> /macollection 200, /collections = 3
GET  /collection/<id JM>            -> 200 (propriétaire)
POST /soin/new                       -> /soins 2 -> 3 (soin ajouté, propriété OK)
POST /collection/new (aerangis_kirkii)-> /collections 3 -> 4 (UUID généré côté PHP)
POST /collection/delete/<id>         -> /collections 4 -> 3
```

**Isolation RG1 — tentative croisée (Jessica, owns col‑004/005/006)**

```
GET  /collection/<id de Jean-Marc>       -> 404   (invisible)
GET  /collections                        -> 3, aucun id commun avec Jean-Marc
POST /collection/delete/<id Jean-Marc>   -> no-op (collections_total reste 6)
POST /soin/new sur <collection Jean-Marc>-> rejeté (0 soin « attaque isolation » en base)
```

**État de base après tests (remis au niveau des seeds)** : 3 users, 21 orchidées,
6 catégories + 10 fiches espèce, 6 collections, 4 soins — aucune ligne de test résiduelle.

---

## 5. Comment relancer

```powershell
# Base neuve
mysql -u root -e "DROP DATABASE IF EXISTS mes_orchidees;"
mysql -u root --default-character-set=utf8mb4 -e "source data/schema_mysql.sql"
0.. seeds -> mysql -u root --default-character-set=utf8mb4 mes_orchidees -e "source data/seeds/0X_….sql"

# Serveur de dev (DocumentRoot = public, routeur server.php)
php -S localhost:8000 -t public server.php
```

Comptes de démo : `jeremy.ferrierpro@gmail.com / demoadmin` (admin) ;
`admin@mesorchidees.fr / demouser` et `jessica.amateur@gmail.com / demouser` (users).

---

## 6. Résultat

`/conseils` et `/macollection` **ne répondent plus 501** : ils sont rendus côté serveur,
alimentés par MySQL, protégés par les grants du routeur, et l'**isolation RG1 est
vérifiée** en lecture comme en écriture. Les incréments 2 et 3 sont fonctionnels.

**Reste (phases ultérieures, toujours en 501 par conception)** : Notifications
(`/notifications`, `/notification/{id}`), Utilisateurs (`/users`), Modération
(`/moderation`) et `/administration`.
