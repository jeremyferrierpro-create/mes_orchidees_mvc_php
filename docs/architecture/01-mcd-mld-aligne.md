# MCD / MLD aligné sur la base réelle

**Projet** : MES ORCHIDÉES — **Auteur** : Jérémy Ferrier — **Date** : 2026-10-18
**Statut** : document **canonique** décrivant les 8 tables réellement présentes en base après la migration [`20261018_lookup_types_soin_categories_conseil.sql`](../../data/migrations/20261018_lookup_types_soin_categories_conseil.sql).
**Source DDL** : [`data/schema_mysql.sql`](../../data/schema_mysql.sql) · **Justification de l'arbitrage** : [`00-arbitrage-denormalisation.md`](00-arbitrage-denormalisation.md)

---

## 1. Schéma relationnel (MCD textuel)

```
              ┌──────────────┐
              │    users     │  id CHAR(36) PK
              └──────┬───────┘                     ┌────────────────────┐
       ┌─────────────┼──────────────┐              │   types_soin       │  id TINYINT PK
       │             │              │              │   label UNIQU(50)  │  ← vocabulaire FERMÉ
       │ 1:N (CR)    │ 1:N (SET N)  │ 1:N (CR)     └─────────┬──────────┘
       ▼             ▼              ▼                        │ N:1 (RESTRICT)
┌─────────────┐ ┌──────────┐  ┌──────────────┐        ┌─────┴────┐
│ collections │ │ orchids  │  │notifications │        │  soins   │
│  id UUID PK │◄┤ id slug  │  │  id UUID PK  │        │ id UUID  │
└──────┬──────┘ │ PK UNIQU │  └──────────────┘        └────┬─────┘
       │ N:1    └────┬─────┘                               │ N:1 (CR)
       │ (RESTRICT)  │ 1:N (SET N)                    1:N  │
       │             ▼                                     ▼
       │        ┌──────────┐                    collections (id UUID PK)
       │        │ conseils │                              ▲
       │        │id slug PK│                              │
       │        └────┬─────┘                              │
       │             │ N:1 (SET N)                        │
       │             ▼                                    │
       │  ┌─────────────────────┐                         │
       └──┤ categories_conseil  │  id SMALLINT PK         │
  (CR)    │ label UNIQU(50)     │  ← vocabulaire OUVERT   │
         └─────────────────────┘                          │
                                                          │
      CR = CASCADE RESTRICT côté enfant (suppression      │
      en cascade de collections → soins)                  │
```

**Vues** : `v_collection_complete` (jointure collections × users × orchids exposant la fiche complète d'une plante en collection).

## 2. Tables cœur (6)

### 2.1 `users`
| Colonne | Type | Contrainte | Rôle |
|---|---|---|---|
| id | CHAR(36) | PK, DEFAULT UUID() | identifiant |
| nom, prenom | VARCHAR(100) | NOT NULL | identité affichée |
| email | VARCHAR(255) | NOT NULL, UNIQUE | identifiant de connexion |
| password_hash | VARCHAR(255) | NOT NULL | hash bcrypt/argon2 |
| role | ENUM('user','admin','moderateur') | NOT NULL, DEFAULT 'user' | contrôle d'accès |
| created_at / updated_at | DATETIME | NOT NULL DEFAULT CURRENT_TIMESTAMP | audit |

### 2.2 `orchids`
| Colonne | Type | Contrainte | Rôle |
|---|---|---|---|
| id | VARCHAR(200) | **PK** (slug, ex. `acacalis_cyanea`) | URL stable |
| name, vernacular | VARCHAR(200) | NOT NULL / NULL | libellés |
| botanical_order, species, subfamily, tribu, subtribu, discovered | VARCHAR | NULL | taxonomie descriptive |
| **genre** | VARCHAR(100) | NOT NULL, KEY `idx_orchids_genre` | ⚠ **dénormalisé** — voir §4 |
| **family** | VARCHAR(100) | NOT NULL DEFAULT 'Orchidaceae' | ⚠ **dénormalisé** — voir §4 |
| **behavior** | VARCHAR(100) | NULL, KEY `idx_orchids_behavior` | ⚠ **dénormalisé** — voir §4 |
| origin | TEXT | NULL | origine géographique (texte libre) |
| img | TEXT | NULL | chemin relatif |
| short_desc, long_desc | TEXT / MEDIUMTEXT | NULL | descriptions |
| is_published | TINYINT(1) | NOT NULL DEFAULT 1 | vitrine publique |
| moderation_status | ENUM('pending','approved','rejected') | NOT NULL DEFAULT 'approved' | workflow de modération |
| proposed_by | CHAR(36) | NULL, FK `fk_orchids_proposed_by` → users(id) ON DELETE SET NULL | auteur de la proposition |
| FULLTEXT `ft_orchids_search` | (name, vernacular, short_desc, origin) | | recherche |

### 2.3 `collections`
| Colonne | Type | Contrainte | Rôle |
|---|---|---|---|
| id | CHAR(36) | PK | identifiant |
| legacy_id | VARCHAR(100) | UNIQUE avec user_id | clef JS historique (col-xxxxx) |
| user_id | CHAR(36) | NOT NULL, FK → users ON DELETE **CASCADE** | propriétaire |
| orchid_id | VARCHAR(200) | NOT NULL, FK → orchids ON DELETE **RESTRICT** | espèce rattachée |
| **location** | VARCHAR(200) | NULL | ⚠ **dénormalisé** (texte libre) — voir §4 |
| notes | TEXT | NULL | notes personnelles |
| **temp, hygro, light, ventilation** | VARCHAR(50/100) | NULL | ⚠ **dénormalisés** (texte libre) — voir §4 |
| photo | VARCHAR(255) | NULL | photo uploadée par le propriétaire |
| **rappel_frequence_jours** | SMALLINT | NOT NULL DEFAULT 7 | RG3 étendu — fréquence personnalisable du rappel (bornée [1 ; 365] côté contrôleur) |

### 2.4 `soins`
| Colonne | Type | Contrainte | Rôle |
|---|---|---|---|
| id | CHAR(36) | PK | identifiant |
| legacy_id | VARCHAR(100) | UNIQUE | clef JS (care-xxxxx) |
| collection_id | CHAR(36) | NOT NULL, FK → collections ON DELETE CASCADE | plante concernée |
| soin_date | DATE | NOT NULL DEFAULT CURRENT_DATE | date du soin |
| **id_type_soin** | TINYINT | **NOT NULL**, FK → `types_soin(id)` ON DELETE **RESTRICT**, KEY `idx_soins_type` | **3NF** — référence au vocabulaire fermé |
| notes | TEXT | NULL | commentaire libre |
| engrais, substrat, ravageurs | TINYINT(1) | NOT NULL DEFAULT 0 | booléens du formulaire modale |

### 2.5 `conseils`
| Colonne | Type | Contrainte | Rôle |
|---|---|---|---|
| id | VARCHAR(200) | **PK** (slug) | URL stable |
| type | ENUM('category','species') | NOT NULL, KEY `idx_conseils_type` | type de fiche |
| name | VARCHAR(200) | NOT NULL | titre |
| img | TEXT | NULL | visuel |
| **id_categorie** | SMALLINT | **NULL**, FK → `categories_conseil(id)` ON DELETE **SET NULL**, KEY `idx_conseils_categorie` | **3NF** — référence au vocabulaire ouvert |
| content | MEDIUMTEXT | NULL | corps de la fiche |
| care_cards | JSON | NULL | carte de soins structurée |
| orchid_id | VARCHAR(200) | NULL, FK → orchids ON DELETE SET NULL | espèce rattachée (fiches species) |
| FULLTEXT `ft_conseils_search` | (name, content) | | **réduit** depuis la migration |

### 2.6 `notifications`
| Colonne | Type | Contrainte | Rôle |
|---|---|---|---|
| id | CHAR(36) | PK | identifiant |
| legacy_id | INT | NULL | clef numérique seed |
| user_id | CHAR(36) | NOT NULL, FK → users ON DELETE CASCADE | destinataire |
| collection_id | CHAR(36) | NULL, FK → collections ON DELETE SET NULL | plante concernée |
| **type** | VARCHAR(50) | NOT NULL DEFAULT 'rappel_soin' | ⚠ **dénormalisé** — voir §4 |
| message | TEXT | NOT NULL | contenu |
| is_read | TINYINT(1) | NOT NULL DEFAULT 0 | accusé de lecture |
| **statut** | ENUM('planifie','actif','traite','ignore') | NOT NULL DEFAULT 'actif' | **cycle de vie RG3** — un rappel `planifie` a une échéance future, `actif` = dû maintenant, `traite`/`ignore` = consommé |
| notif_date | DATETIME | NOT NULL DEFAULT CURRENT_TIMESTAMP | **sémantique double** : horodatage de création pour les notifs non-rappel, **date d'échéance** pour les rappels (positionnée par `SoinController::genererRappelSoin()` à `soin_date + rappel_frequence_jours`) |
| KEY `idx_notifications_statut` | (user_id, statut, notif_date) | | vue « Rappels à venir » (tript par échéance) |
| KEY `idx_notifications_unread` | (user_id, is_read) | | compteur du cloche |

## 3. Tables de lookup (2)

### 3.1 `types_soin` — vocabulaire **FERMÉ**
| Colonne | Type | Contrainte |
|---|---|---|
| id | TINYINT | PK AUTO_INCREMENT |
| label | VARCHAR(50) | NOT NULL, UNIQUE |

Contenu posé par la migration (ordre inséré = ordre d'affichage) : `arrosage`, `engrais`, `rempotage`, `substrat`, `ravageurs`. **Aucune écriture HTTP** : `TypeSoinRepository` n'expose que `findAll()`, `findIdByLabel()` et `defaultId()` — pas de `create()`. FK child : `soins.id_type_soin` avec `ON DELETE RESTRICT`.

### 3.2 `categories_conseil` — vocabulaire **OUVERT**
| Colonne | Type | Contrainte |
|---|---|---|
| id | SMALLINT | PK AUTO_INCREMENT |
| label | VARCHAR(50) | NOT NULL, UNIQUE |

Six labels éditoriaux seedés par la migration (Conseils de base, Pour les epiphytes, Pour les terrestres, Pour les hemi-epiphytes, Apres achat, Sortie de flask), **extensible à la volée** par `CategorieConseilRepository::findOrCreateLabel()` (upsert `INSERT … ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)`). FK child : `conseils.id_categorie` avec `ON DELETE SET NULL` (un conseil peut rester non catégorisé).

## 4. Colonnes volontairement dénormalisées

| Colonne | Type retenu | Alternative rejetée | Justification |
|---|---|---|---|
| `orchids.genre` | VARCHAR(100) + KEY BTREE | table `genres(id,label,…)` | 15 valeurs sur 21 fiches, **aucun attribut propre** (pas d'auteur, pas de date de description). `SELECT DISTINCT genre FROM orchids` exploitant l'index = O(log n), **0 JOIN**. |
| `orchids.family` | VARCHAR(100) DEFAULT 'Orchidaceae' | table `families(id,label,…)` | **Cardinalité 1** dans l'état actuel (toutes les Orchidaceae). Une lookup table n'a aucun sens sous cardinalité 2. |
| `orchids.behavior` | VARCHAR(100) + KEY BTREE | table `behaviors(id,label,…)` | 5 valeurs purement descriptives, jamais enrichies. |
| `collections.location` | VARCHAR(200) texte libre | FK `id_location` | Le cahier des charges impose des **conditions de culture personnalisées** (« Fenêtre Est salon », « Bureau nord »). Une lookup imposerait un vocabulaire partagé et casserait la liberté de saisie. |
| `collections.temp`, `hygro`, `light`, `ventilation` | VARCHAR(50/100) texte libre | colonnes typées (SMALLINT, DECIMAL, ENUM) | Idem : le propriétaire saisit sa réalité, pas une valeur normalisée. Ex. `"18-22C"`, `"Lumiere tamisee"`. |
| `notifications.type` | VARCHAR(50) + liste blanche dans `App\Entity\Notification::TYPES` | table `type_notification(id,label)` | Rôle strictement identique, **zéro jointure** sur la liste de notifications. Le MCD d'origine était de plus **mal orienté** (FK placée sur la notification au lieu du type). |
| ~~`auth_sessions`~~ | **non créée** | table `auth_sessions(id, user_id, token, expires_at)` | Sessions PHP natives (`session_start()` + cookie `PHPSESSID`). Rien dans le cahier n'exige de révocation à distance ni de journal des connexions. |

**Règle d'or retenue** : on normalise ce qui est un **vocabulaire partagé et administrable** ; on garde en VARCHAR ce qui est **texte libre saisi par l'utilisateur** ou **attribut purement descriptif sans vie propre**.

## 5. Accès et liste blanche

Pour éviter toute injection SQL sur les colonnes dénormalisées exposées en filtre, `OrchidRepository::distinctValues($column)` applique une **liste blanche stricte** des trois colonnes autorisées (`genre`, `family`, `behavior`). La valeur retournée transitant dans le SQL n'est **jamais** une chaîne utilisateur non validée.

## 6. Contrats d'intégrité référentielle

| Contrainte | Cible | ON DELETE | Sémantique |
|---|---|---|---|
| `fk_collections_user` | users | CASCADE | Supprimer un utilisateur emporte sa collection. |
| `fk_collections_orchid` | orchids | RESTRICT | Une espèce référencée en collection ne peut pas être supprimée. |
| `fk_soins_collection` | collections | CASCADE | Supprimer une plante retire l'historique des soins. |
| **`fk_soins_type`** | **types_soin** | **RESTRICT** | **Impossible de retirer un type encore utilisé en historique.** |
| `fk_conseils_orchid` | orchids | SET NULL | Une fiche espèce supprimée laisse le conseil orphelin. |
| **`fk_conseils_categorie`** | **categories_conseil** | **SET NULL** | **Un conseil peut rester sans catégorie ; la suppression d'un label n'abîme pas le contenu.** |
| `fk_notifications_user` | users | CASCADE | Supprimer un utilisateur emporte ses notifications. |
| `fk_notifications_collection` | collections | SET NULL | Supprimer une plante désolidarise la notification, qui reste consultable. |
| `fk_orchids_proposed_by` | users | SET NULL | Le retrait d'un proposant ne casse pas la modération. |

## 7. Différentiel avec le MCD d'origine (14 tables)

| Table héritée du MCD collé | Décision | Motif |
|---|---|---|
| `genres` | ❌ non créée | 3NF décorative (§4) |
| `families` | ❌ non créée | cardinalité 1 |
| `behaviors` | ❌ non créée | 3NF décorative |
| `locations` | ❌ non créée | texte libre (§4) |
| `types_soin` | ✅ **créée** | vocabulaire fermé partagé |
| `categories_conseil` | ✅ **créée** | vocabulaire ouvert administrable |
| `auth_sessions` | ❌ non créée | sessions PHP natives |
| `type_notification` | ❌ non créée | VARCHAR + liste blanche équivalent, FK d'origine inversée |

**Bilan** : 8 tables réellement utilisées, 0 table décorative, 0 jointure superflue, 0 fonctionnalité perdue.
