# Note d'arbitrage — 3NF sélective

**Projet** : MES ORCHIDÉES — **Auteur** : Jérémy Ferrier — **Date** : 2026-10-18
**Fichier lié** : [`01-mcd-mld-aligne.md`](01-mcd-mld-aligne.md) · migration appliquée [`20261018_lookup_types_soin_categories_conseil.sql`](../../data/migrations/20261018_lookup_types_soin_categories_conseil.sql)

---

## 1. Contexte et chiffres mesurés

La base applicative en production comptait, avant l'arbitrage, **6 tables** (users, orchids, collections, soins, conseils, notifications) pour 21 fiches d'orchidées seed.

| Colonne dénormalisée | Cardinalité observée sur la base réelle | Nature |
|---|---:|---|
| `orchids.genre` | **15** genres distincts | descriptive, jamais enrichie |
| `orchids.family` | **1** (`Orchidaceae` — tous les seed) | triviale |
| `orchids.behavior` | **5** comportements | descriptive |
| `soins.type` (avant migration) | **5** valeurs (table vide en production) | **vocabulaire fermé** |
| `conseils.category` (avant migration) | **11** labels (6 éditoriaux + 5 botaniques) | **vocabulaire ouvert** |
| `collections.location` | non bornée (texte libre) | **texte utilisateur** |

Ces chiffres constituent le critère décisionnel : une table de lookup n'a de sens que si (a) son contenu est partagé entre plusieurs lignes métier, (b) elle porte ou est susceptible de porter des attributs propres (auteur, date de description, image, hiérarchie), (c) son vocabulaire est appelé à être administré.

## 2. Alternatives envisagées

### Option B — 3NF stricte (14 tables, rejetée)
Le schéma hérité du cahier imposait 8 tables supplémentaires (`genres`, `families`, `behaviors`, `locations`, `types_soin`, `categories_conseil`, `auth_sessions`, `type_notification`). Rejetée pour trois raisons convergentes :

- **Aucune valeur ajoutée sur les lookup descriptives** : `genres`, `families`, `behaviors` n'ont pas d'attributs propres ; la jointure ajouterait un `JOIN` pour récupérer une seule colonne `label`. Sur 21 fiches × 3 colonnes = **63 lignes de lookup** pour 6 tables.
- **Casse fonctionnelle** : `locations` en FK imposerait un vocabulaire partagé et bloquerait la saisie libre (« Fenêtre Est salon », « Bureau nord ») explicitement requise par le cahier (§ conditions de culture personnalisées).
- **Tables décoratives ou mal orientées** : `auth_sessions` est redondant avec les sessions PHP natives (`session_start()` + cookie `PHPSESSID`) ; le MCD collé relie `type_notification` par une FK **sur le mauvais côté** (une notification ne peut pas porter la FK de son type, c'est l'inverse).

Coût chiffré de la migration complète : **2 à 4 jours** de refactor (7 entités + 7 repositories + 10 templates + ~15 checks de tests HTTP), pour un bénéfice fonctionnel nul.

### Option C — dénormalisation pure (rejetée aussi)
Rester sur 6 tables sans aucune lookup aurait été **peu lisible pédagogiquement** : le vocabulaire des types de soin (arrosage / engrais / rempotage / substrat / ravageurs) est manifestement une référence, pas un texte libre. L'absence de table de lookup sur ce cas précis aurait pu être lue comme une faute de modélisation plutôt que comme un arbitrage.

### Option A retenue — 3NF sélective
Normaliser uniquement ce qui est **vraiment** une référence partagée :

| Table ajoutée | Vocabulaire | Politique | FK | ON DELETE |
|---|---|---|---|---|
| `types_soin` (id TINYINT PK, label VARCHAR(50) UNIQUE) | **Fermé** — 5 labels posés par la migration, non extensible par HTTP | insertions réservées à la migration | `soins.id_type_soin` NOT NULL | RESTRICT |
| `categories_conseil` (id SMALLINT PK, label VARCHAR(50) UNIQUE) | **Ouvert** — upsert à la création d'un conseil avec un label inédit | `CategorieConseilRepository::findOrCreateLabel()` | `conseils.id_categorie` NULL | SET NULL |

**Coût réel** : une demi-journée (2 entités, 2 repositories, 2 contrôleurs refactorés, 2 templates, 1 migration, 3 checks D10b ajoutés à la suite HTTP).

## 3. Bénéfice mesuré de la dénormalisation conservée

Pour les trois colonnes `genre`, `family`, `behavior` restées en `VARCHAR` :

- `SELECT DISTINCT genre FROM orchids` sur 21 lignes avec `KEY idx_orchids_genre (genre)` = **O(log n)** (scan d'index, pas de table lookup), **aucun JOIN**.
- L'accès passe par `OrchidRepository::distinctValues($column)` — **liste blanche stricte** des trois colonnes autorisées pour éliminer tout risque d'injection (`$column` ne transite jamais dans le SQL sans validation).
- L'ajout d'une nouvelle valeur (nouveau genre botanique, nouveau comportement) se fait **sans migration** ni seed lookup : la fiche orchidée est insérée, le `DISTINCT` l'intègre au filtre.

## 4. Cohérence modèle ↔ implémentation

| Élément | Emplacement | Garantit |
|---|---|---|
| DDL canonique | `data/schema_mysql.sql` | Le schéma documenté est celui exécuté en base |
| Migration | `data/migrations/20261018_lookup_types_soin_categories_conseil.sql` | Idempotente (information_schema + DELIMITER), appliquée sur `mes_orchidees` **et** `mes_orchidees_test` |
| Seeds | `data/seeds/05_seed_soins.sql`, `03_seed_conseils.sql` | Utilisent des sous-requêtes `(SELECT id FROM types_soin WHERE label=…)` : pas d'ID en dur |
| Entités | `src/Entity/{Soin,Conseil,TypeSoin,CategorieConseil}.php` | Propriété transitoire `typeLabel` / `category` préservée pour la rétro-compatibilité des templates |
| Repositories | `src/Repository/{TypeSoin,CategorieConseil}Repository.php` | Résolution label → id centralisée |
| Contrôleurs | `SoinController::fromRequest()`, `ConseilController::fromRequest()` | Repli défensif : `findIdByLabel() ?? defaultId()` ; upsert catégorie côté conseils |
| Vues | `templates/pages/collection/show.php`, `templates/pages/conseils/form.php` | `<select>` peuplé depuis `TypeSoinRepository::findAll()` ; `<datalist>` peuplé depuis `CategorieConseilRepository::findAll()` |

## 5. Vérification

Suite HTTP verte après migration : **234 PASS / 0 FAIL / 0 BLOCKED** sur 247 appels réseau (`php tests/http/run.php`).

Trois checks D10b verrouillent l' invariant :
- `types_soin` contient exactement 5 lignes (vocabulaire fermé non extensible) ;
- le label `'arrosage'` est résolvable en id ;
- la catégorie `'Arrosage'` soumise par le POST D10 a été créée à la volée dans `categories_conseil` (preuve du fonctionnement `findOrCreateLabel`).

## 6. Ce que cela change pour le jury

- Le MCD/MLD livré **correspond à la base qui tourne** (voir `01-mcd-mld-aligne.md`).
- La dénormalisation restante est **assumée et justifiée** par les chiffres (cardinalités) et le cahier des charges, non par manque de temps.
- L'arbitrage démontre la capacité à distinguer **3NF utile** (vocabulaires fermés partagés) et **3NF décorative** (tables de lookup sans attributs propres sur 15 à 21 lignes).
