# Dictionnaire des données — Mes Orchidées

> **Projet** : Mes Orchidées — DWWM Fil Rouge 2026/2027
> **SGBD cible** : MySQL 8.0+ / InnoDB / utf8mb4
> **Source** : `data/schema_mysql.sql` + migrations `data/migrations/20261018_*`
> **Version** : 1.0 — aligné HEAD `mvc-candidate-2026-10`

---

## Vue d'ensemble des relations

```
users
  ├──< collections >── orchids
  │       │
  │       └──< soins >── types_soin
  │
  ├──< notifications ── collections (SET NULL)
  │               └── types_soin (RESTRICT)
  │
  └── (FK proposed_by sur orchids, SET NULL)

conseils
  ├── >── orchids    (FK orchid_id, SET NULL)
  └── >── categories_conseil (FK id_categorie, SET NULL)

types_soin        ← table de lookup (vocabulaire FERMÉ, 5 valeurs)
categories_conseil ← table de lookup (vocabulaire OUVERT, upsert admin)
```

---

## Table `users`

Stocke les comptes de l'application. Remplace la gestion localStorage de `auth-service.js` (legacy front-only).

| Colonne | Type | Contraintes | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | **PK**, `DEFAULT (UUID())` | Identifiant UUID généré par MySQL 8 |
| `nom` | `VARCHAR(100)` | `NOT NULL` | Nom de famille |
| `prenom` | `VARCHAR(100)` | `NOT NULL` | Prénom |
| `email` | `VARCHAR(255)` | `NOT NULL`, `UNIQUE (uq_users_email)` | Identifiant de connexion — unique |
| `password_hash` | `VARCHAR(255)` | `NOT NULL` | Hash bcrypt (`password_hash(PASSWORD_DEFAULT)`). **Jamais en clair.** |
| `role` | `ENUM('user','admin','moderateur')` | `NOT NULL DEFAULT 'user'` | Rôle RBAC. `admin` n'est jamais créable via inscription publique. |
| `created_at` | `DATETIME` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | Date de création |
| `updated_at` | `DATETIME` | `NOT NULL ON UPDATE CURRENT_TIMESTAMP` | Dernière modification |

**Index** : PK(`id`), UNIQUE(`email`)
**FK entrantes** : collections.user_id, notifications.user_id, orchids.proposed_by
**Vérification sécurité** : `src/Service/AuthService.php:46` (hash), `:71` (verify), `:94` (session_regenerate_id)

---

## Table `orchids`

Catalogue botanique de l'encyclopédie. Correspond à `orchids.json` du legacy front-only.

| Colonne | Type | Contraintes | Description |
|---|---|---|---|
| `id` | `VARCHAR(200)` | **PK** | Slug texte (ex : `phalaenopsis_belle_agentica`) |
| `name` | `VARCHAR(200)` | `NOT NULL` | Nom scientifique complet |
| `vernacular` | `VARCHAR(200)` | | Nom commun en français |
| `botanical_order` | `VARCHAR(100)` | | Ordre taxonomique (préfixé pour éviter mot réservé MySQL) |
| `species` | `VARCHAR(200)` | | Espèce |
| `genre` | `VARCHAR(100)` | `NOT NULL` | Genre botanique |
| `family` | `VARCHAR(100)` | `NOT NULL DEFAULT 'Orchidaceae'` | Famille |
| `subfamily` | `VARCHAR(100)` | | Sous-famille |
| `tribu` | `VARCHAR(100)` | | Tribe |
| `subtribu` | `VARCHAR(100)` | | Sous-tribu |
| `behavior` | `VARCHAR(100)` | | Mode de croissance : Epiphyte / Terrestre / Hémiépiphyte |
| `discovered` | `VARCHAR(200)` | | Découvreur / année |
| `origin` | `TEXT` | | Zone géographique d'origine |
| `img` | `TEXT` | | Chemin relatif vers l'image de l'espèce |
| `short_desc` | `TEXT` | | Description courte (liste) |
| `long_desc` | `MEDIUMTEXT` | | Description complète (fiche détail) |
| `is_published` | `TINYINT(1)` | `NOT NULL DEFAULT 1` | 1 = visible publiquement |
| `moderation_status` | `ENUM('pending','approved','rejected')` | `NOT NULL DEFAULT 'approved'` | Workflow de modération |
| `proposed_by` | `CHAR(36)` | `NULL`, **FK** → `users(id)` ON DELETE SET NULL | Utilisateur proposeur (modération) |
| `created_at` | `DATETIME` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | |
| `updated_at` | `DATETIME` | `NOT NULL ON UPDATE CURRENT_TIMESTAMP` | |

**Index** : PK(`id`), `idx_orchids_behavior`, `idx_orchids_genre`, `idx_orchids_published`, `idx_orchids_moderation`, **FULLTEXT** `ft_orchids_search`(name, vernacular, short_desc, origin)
**FK sortantes** : `proposed_by` → `users.id` (SET NULL)
**Recherche** : `src/Repository/OrchidRepository::search()` — FULLTEXT + repli LIKE

---

## Table `collections`

Collection personnelle d'orchidées. Table de jointure **enrichie** entre `users` et `orchids` (N-N avec attributs).

| Colonne | Type | Contraintes | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | **PK**, `DEFAULT (UUID())` | UUID |
| `legacy_id` | `VARCHAR(100)` | | Ancienne clé JS `col-xxxxx` (migration localStorage) |
| `user_id` | `CHAR(36)` | `NOT NULL`, **FK** → `users(id)` ON DELETE CASCADE | Propriétaire |
| `orchid_id` | `VARCHAR(200)` | `NOT NULL`, **FK** → `orchids(id)` ON DELETE RESTRICT | Espèce dans la collection |
| `location` | `VARCHAR(200)` | | Emplacement chez le propriétaire (ex : « Fenêtre Est salon ») |
| `notes` | `TEXT` | | Notes libres du propriétaire |
| `temp` | `VARCHAR(50)` | | Température ambiante déclarée |
| `hygro` | `VARCHAR(50)` | | Hygrométrie déclarée |
| `light` | `VARCHAR(100)` | | Exposition lumineuse |
| `ventilation` | `VARCHAR(100)` | | Aération |
| `photo` | `VARCHAR(255)` | | Chemin relatif de la photo personnelle uploadée |
| `rappel_frequence_jours` | `SMALLINT` | `NOT NULL DEFAULT 7` | Période entre deux rappels [1 ; 365] |
| `rappel_actif` | `TINYINT(1)` | `NOT NULL DEFAULT 1` | Opt-in rappel : 1 = actif, 0 = désactivé |
| `added_at` | `DATETIME` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | Date d'ajout à la collection |
| `updated_at` | `DATETIME` | `NOT NULL ON UPDATE CURRENT_TIMESTAMP` | |

**Index** : PK(`id`), `idx_collections_user_id`, `idx_collections_orchid_id`, UNIQUE(`user_id`, `legacy_id`)
**FK** : `user_id` → `users.id` (CASCADE), `orchid_id` → `orchids.id` (RESTRICT)
**RG1 (isolation)** : Toute requête sur cette table joint obligatoirement `collections.user_id = :uid`
**Source code** : `src/Repository/CollectionRepository.php`, `src/Entity/Collection.php`

---

## Table `soins`

Historique des soins réalisés sur chaque plante de la collection. Extrait du `careHistory[]` du JSON legacy.

| Colonne | Type | Contraintes | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | **PK**, `DEFAULT (UUID())` | UUID |
| `legacy_id` | `VARCHAR(100)` | `UNIQUE` | Ancien id JS `care-xxxxx` |
| `collection_id` | `CHAR(36)` | `NOT NULL`, **FK** → `collections(id)` ON DELETE CASCADE | Plante concernée |
| `soin_date` | `DATE` | `NOT NULL DEFAULT (CURRENT_DATE)` | Date du soin |
| `id_type_soin` | `TINYINT` | `NOT NULL`, **FK** → `types_soin(id)` ON DELETE RESTRICT | Type de soin |
| `notes` | `TEXT` | | Observations libres |
| `engrais` | `TINYINT(1)` | `NOT NULL DEFAULT 0` | Flag booléen formulaire |
| `substrat` | `TINYINT(1)` | `NOT NULL DEFAULT 0` | Flag booléen formulaire |
| `ravageurs` | `TINYINT(1)` | `NOT NULL DEFAULT 0` | Flag booléen formulaire |
| `created_at` | `DATETIME` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | |

**Index** : PK(`id`), `idx_soins_collection_id`, `idx_soins_date`(DESC), `idx_soins_type`
**FK** : `collection_id` → `collections.id` (CASCADE), `id_type_soin` → `types_soin.id` (RESTRICT)
**Source code** : `src/Repository/SoinRepository.php`, `src/Entity/Soin.php`, `src/Controller/SoinController.php`

---

## Table `types_soin` (lookup — vocabulaire FERMÉ)

| Colonne | Type | Contraintes | Description |
|---|---|---|---|
| `id` | `TINYINT` | **PK**, `AUTO_INCREMENT` | |
| `label` | `VARCHAR(50)` | `NOT NULL`, `UNIQUE` | Nom du type |

**Valeurs seed** : arrosage, engrais, rempotage, substrat, ravageurs
**Migration** : `data/migrations/20261018_lookup_types_soin_categories_conseil.sql`
**3NF sélective** : voir `docs/architecture/00-arbitrage-denormalisation.md`

---

## Table `categories_conseil` (lookup — vocabulaire OUVERT)

| Colonne | Type | Contraintes | Description |
|---|---|---|---|
| `id` | `SMALLINT` | **PK**, `AUTO_INCREMENT` | |
| `label` | `VARCHAR(50)` | `NOT NULL`, `UNIQUE` | Libellé catégorie |

**Particularité** : upsert par l'admin au fil des créations de conseils — vocabulaire non figé.
**Source** : `src/Repository/CategorieConseilRepository.php`, `src/Entity/CategorieConseil.php`

---

## Table `conseils`

Fiches conseils généraux (`category`) et fiches par espèce (`species`). Correspond à `conseils.json` du legacy.

| Colonne | Type | Contraintes | Description |
|---|---|---|---|
| `id` | `VARCHAR(200)` | **PK** | Slug (ex : `conseils-base`, `fiche-acacalis_cyanea`) |
| `type` | `ENUM('category','species')` | `NOT NULL` | Nature de la fiche |
| `name` | `VARCHAR(200)` | `NOT NULL` | Titre |
| `img` | `TEXT` | | Image illustrative |
| `id_categorie` | `SMALLINT` | `NULL`, **FK** → `categories_conseil(id)` ON DELETE SET NULL | Catégorie éditoriale |
| `content` | `MEDIUMTEXT` | | Corps de la fiche |
| `care_cards` | `JSON` | | Carte de soins structurée (JSON natif MySQL 8) |
| `orchid_id` | `VARCHAR(200)` | `NULL`, **FK** → `orchids(id)` ON DELETE SET NULL | Espèce liée (fiches species) |
| `created_at` | `DATETIME` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | |
| `updated_at` | `DATETIME` | `NOT NULL ON UPDATE CURRENT_TIMESTAMP` | |

**Index** : PK(`id`), `idx_conseils_type`, `idx_conseils_orchid`, `idx_conseils_categorie`, **FULLTEXT** `ft_conseils_search`(name, content)
**FK** : `orchid_id` → `orchids.id` (SET NULL), `id_categorie` → `categories_conseil.id` (SET NULL)

---

## Table `notifications`

Notifications utilisateur : rappels de soins, alertes modération, informations système.

| Colonne | Type | Contraintes | Description |
|---|---|---|---|
| `id` | `CHAR(36)` | **PK**, `DEFAULT (UUID())` | UUID |
| `legacy_id` | `INT` | | Ancien id numérique du JSON seed |
| `user_id` | `CHAR(36)` | `NOT NULL`, **FK** → `users(id)` ON DELETE CASCADE | Destinataire |
| `collection_id` | `CHAR(36)` | `NULL`, **FK** → `collections(id)` ON DELETE SET NULL | Plante concernée (NULL = notification globale) |
| `type` | `VARCHAR(50)` | `NOT NULL DEFAULT 'rappel_soin'` | Catégorie : rappel_soin / validation / rejet / info / nouvelle_proposition |
| `message` | `TEXT` | `NOT NULL` | Contenu de la notification |
| `is_read` | `TINYINT(1)` | `NOT NULL DEFAULT 0` | Lu / non lu |
| `statut` | `ENUM('planifie','actif','traite','ignore')` | `NOT NULL DEFAULT 'actif'` | Cycle de vie du rappel (RG3) |
| `id_type_soin` | `TINYINT` | `NULL`, **FK** → `types_soin(id)` ON DELETE RESTRICT | Type du prochain soin attendu (NULL = global) |
| `notif_date` | `DATETIME` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | Date d'échéance du rappel |
| `created_at` | `DATETIME` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | |

**Index** : PK(`id`), `idx_notifications_user_id`, `idx_notifications_unread`(user_id, is_read), `idx_notifications_statut`(user_id, statut, notif_date)
**FK** : `user_id` → `users.id` (CASCADE), `collection_id` → `collections.id` (SET NULL), `id_type_soin` → `types_soin.id` (RESTRICT)
**Source** : `src/Repository/NotificationRepository.php`, `src/Entity/Notification.php`, `src/Service/RappelService.php`

---

## Vue `v_collection_complete`

Vue de jointure précalculée remplace le pattern JS `orchidsDatabase.find(o => o.id === item.orchidId)` du legacy.

```sql
-- Voir data/schema_mysql.sql:311-340
SELECT c.*, u.nom, u.prenom, u.email, o.name, o.vernacular, o.behavior, ...
FROM collections c
JOIN users   u ON u.id = c.user_id
JOIN orchids o ON o.id = c.orchid_id;
```

**Usage** : Dashboard collection (`CollectionController::index()`), export CSV.

---

## Migrations actives (2026-10-18)

| Fichier | Description |
|---|---|
| `20260826_collections_owner_scope.sql` | Ajout `rappel_frequence_jours`, `rappel_actif` sur collections |
| `20261018_collection_free_add.sql` | Assouplit contraintes ajout en collection |
| `20261018_gestion_rappels_soins.sql` | Ajout `statut`, `id_type_soin` sur notifications (RG3) |
| `20261018_lookup_types_soin_categories_conseil.sql` | Création `types_soin` + `categories_conseil` (3NF sélective) |
| `20261018_optin_rappel_soins.sql` | Champ `rappel_actif` sur collections |
| `20261018_rappel_type_soin.sql` | Lien rappels ↔ types_soin |

---

## Résumé des règles d'intégrité

| Règle | Implémentation |
|---|---|
| Un email = un compte | `UNIQUE uq_users_email` |
| Supprimer un utilisateur supprime sa collection | `FK CASCADE users → collections` |
| Supprimer un utilisateur supprime ses notifications | `FK CASCADE users → notifications` |
| Supprimer une collection supprime ses soins | `FK CASCADE collections → soins` |
| Impossible de supprimer un type de soin référencé | `FK RESTRICT types_soin → soins/notifications` |
| Impossible de supprimer une orchidée dans une collection | `FK RESTRICT orchids → collections` |
| Un proposant retiré ne casse pas l'orchidée | `FK SET NULL proposed_by` |
| Une catégorie supprimé nullifie les conseils | `FK SET NULL categories_conseil` |
| Fréquence rappel bornée [1 ; 365] | Validation PHP `SoinController::create()` |
| Opt-in rappel par collection | `collections.rappel_actif` TINYINT |
