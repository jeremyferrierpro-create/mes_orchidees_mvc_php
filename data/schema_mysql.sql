-- ===========================================================================
-- SCHEMA MySQL 8.0+ — Mes Orchidees
-- ===========================================================================
-- Auteur   : Jeremy Ferrier
-- Projet   : Mes Orchidees — Fil Rouge
-- BDD cible: MySQL 8.0+ (InnoDB, utf8mb4, FULLTEXT natif)
-- Version  : 1.0 — Phase 3 alternative (migration depuis localStorage / db.js)
--
-- Ce schema est la traduction du Pattern Repository de core/db.js vers MySQL.
-- Differences vs Supabase/PostgreSQL :
--   - UUID via UUID() + CHAR(36) (pas de gen_random_uuid())
--   - FULLTEXT sur colonnes TEXT (remplace tsvector PostgreSQL)
--   - Pas de GENERATED ALWAYS AS STORED pour FULLTEXT (index separe)
--   - Pas de RLS natif : securite geree par l application ou vues filtrees
--   - ENUM pour les types fixes (role, type soin, type conseil)
--   - JSON natif MySQL 8 pour care_cards (equivalent JSONB Supabase)
--   - ON UPDATE CURRENT_TIMESTAMP pour updated_at
-- ===========================================================================

-- ---------------------------------------------------------------------------
-- CONFIG GLOBALE
-- ---------------------------------------------------------------------------
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS mes_orchidees
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE mes_orchidees;

-- ---------------------------------------------------------------------------
-- 1. TABLE : users
-- ---------------------------------------------------------------------------
-- Correspond a /assets/js/data/users.json
-- Champs inferes : id, nom, prenom, email, password, role, created, modified
-- Note : en production, le mot de passe est hache (bcrypt/argon2), jamais en clair.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS users (
    id          CHAR(36)     NOT NULL DEFAULT (UUID()),
    nom         VARCHAR(100) NOT NULL,
    prenom      VARCHAR(100) NOT NULL,
    email       VARCHAR(255) NOT NULL,
    -- En production : stocker le hash (bcrypt), JAMAIS le mot de passe en clair
    password_hash VARCHAR(255) NOT NULL COMMENT 'Hash bcrypt/argon2 du mot de passe. JAMAIS en clair.',
    -- role : 'user' | 'admin' | 'moderateur'
    -- Correspond a la verification dans administration.js : currentUser.role !== "admin"
    role        ENUM('user', 'admin', 'moderateur') NOT NULL DEFAULT 'user',
    -- Remplace les champs created/modified en format dd/MM/yyyy du JSON
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Utilisateurs de l application. Correspond a users.json. Remplace la gestion de session localStorage dans auth-service.js.';

-- ---------------------------------------------------------------------------
-- 2. TABLE : orchids
-- ---------------------------------------------------------------------------
-- Correspond a /assets/js/data/orchids.json (21 especes seed)
-- Champs inferes : id (slug TEXT), name, vernacular, order, species, genre,
--                  family, subfamily, tribu, subtribu, behavior, discovered,
--                  origin, img, shortDesc, longDesc
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS orchids (
    -- id en slug texte (ex: "acacalis_cyanea") — conserve la convention du JSON
    id              VARCHAR(200) NOT NULL,
    name            VARCHAR(200) NOT NULL,
    vernacular      VARCHAR(200),
    -- "order" est un mot reserve MySQL, on prefixe botanical_
    botanical_order VARCHAR(100),
    species         VARCHAR(200),
    genre           VARCHAR(100) NOT NULL,
    family          VARCHAR(100) NOT NULL DEFAULT 'Orchidaceae',
    subfamily       VARCHAR(100),
    tribu           VARCHAR(100),
    subtribu        VARCHAR(100),
    -- Ex: "Epiphyte", "Terrestre / Lithophyte", "Hemiepiphyte / Grimpante"
    behavior        VARCHAR(100),
    discovered      VARCHAR(200),
    origin          TEXT,
    -- Chemin relatif vers l image (ex: ./assets/images/orchids/xxx.png)
    img             TEXT,
    short_desc      TEXT,
    long_desc       MEDIUMTEXT,
    -- Moderation : orchidee validee par admin ou en attente
    is_published    TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1 = publiee, 0 = ni publiee ni visible (pending/rejected)',
    -- Filet de moderation : etat du flux proposition -> validation.
    --   pending  = proposee par un utilisateur, en attente de decision
    --   approved = validee par un admin/moderateur (alors is_published = 1)
    --   rejected = refusee : conservee pour le proprietaire, jamais publiee
    moderation_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'
                       COMMENT 'Etat du workflow de moderation',
    proposed_by     CHAR(36)     NULL COMMENT 'Utilisateur a l origine de la proposition (moderation)',
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_orchids_behavior (behavior),
    KEY idx_orchids_genre    (genre),
    KEY idx_orchids_published (is_published, proposed_by),
    KEY idx_orchids_moderation (moderation_status),
    -- Cle etrangere explicite au niveau table (MySQL/InnoDB IGNORE le REFERENCES inline colonne)
    CONSTRAINT fk_orchids_proposed_by FOREIGN KEY (proposed_by) REFERENCES users(id) ON DELETE SET NULL,
    -- FULLTEXT remplace searchOrchids() (includes() JS) et le GIN index Supabase
    -- Parser par defaut (pas de "WITH PARSER ngram") : portable MySQL 8 + MariaDB.
    FULLTEXT KEY ft_orchids_search (name, vernacular, short_desc, origin)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Catalogue botanique. Correspond a orchids.json. FULLTEXT remplace searchOrchids() dans orchid-service.js.';

-- ---------------------------------------------------------------------------
-- 3. TABLE : collections
-- ---------------------------------------------------------------------------
-- Correspond a /assets/js/data/collections.json (vide au seed)
-- Champs inferes depuis buildCollectionItem() dans features/collection.js :
--   collectionId, orchidId, user_id, location, notes, temp, hygro, light,
--   ventilation, addedAt, careHistory (-> table soins)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS collections (
    id          CHAR(36)     NOT NULL DEFAULT (UUID()),
    -- Ancienne cle string JS (col-xxxxx) pour la migration depuis localStorage
    legacy_id   VARCHAR(100)           COMMENT 'Cle JS originale (col-xxxxx). Migration localStorage.',
    user_id     CHAR(36)     NOT NULL,
    orchid_id   VARCHAR(200) NOT NULL,
    -- Donnees contextuelles personnalisees par le proprietaire
    location    VARCHAR(200)           COMMENT 'Ex: Fenetre Est salon',
    notes       TEXT,
    temp        VARCHAR(50)            COMMENT 'Ex: 18-22C (saisi librement)',
    hygro       VARCHAR(50)            COMMENT 'Ex: 65%',
    light       VARCHAR(100)           COMMENT 'Ex: Lumiere tamisee',
    ventilation VARCHAR(100),
    -- Photo personnelle du proprietaire (upload lors de l'ajout a la collection).
    -- Independante de l'image de l'espece : visible uniquement dans la collection.
    photo       VARCHAR(255)           COMMENT 'Chemin relatif de la photo uploadee (perso)',
    -- RG3 etendu : frequence personnalizable du rappel automatique, exprimee en
    -- jours. Lue par SoinController::genererRappelSoin() a chaque soin enregistre
    -- pour caler la date d'echeance du rappel associe a la plante.
    rappel_frequence_jours SMALLINT    NOT NULL DEFAULT 7
                COMMENT 'Periode (jours) entre deux rappels — defaut 7, attendu dans [1 ; 365]',
    -- RG3 etendu (opt-in) : l'utilisateur choisit, a chaque soin enregistre, si le
    -- prochain rappel doit etre programme. Desactive (0) : aucun nouveau rappel et
    -- le rappel deja planifie est annule (statut « ignore »), l'historique est conserve.
    rappel_actif  TINYINT(1) NOT NULL DEFAULT 1
                COMMENT 'Opt-in du rappel automatique — 1 = programmer a chaque soin, 0 = desactive',
    added_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_collections_user_id   (user_id),
    KEY idx_collections_orchid_id (orchid_id),
    UNIQUE KEY uq_collections_user_legacy (user_id, legacy_id),
    CONSTRAINT fk_collections_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    CONSTRAINT fk_collections_orchid FOREIGN KEY (orchid_id) REFERENCES orchids(id) ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Collection personnelle. Table de jointure enrichie entre users et orchids. Remplace collections.json.';

-- ---------------------------------------------------------------------------
-- 4b. TABLES DE LOOKUP (3NF sélective — voir docs/architecture/00-arbitrage-denormalisation.md)
-- ---------------------------------------------------------------------------
-- types_soin : vocabulaire FERMÉ (5 valeurs, posé par la migration
--              20261018_lookup_types_soin_categories_conseil.sql).
-- categories_conseil : vocabulaire OUVERT (upsert au fil des créations admin).
CREATE TABLE IF NOT EXISTS types_soin (
    id    TINYINT     NOT NULL AUTO_INCREMENT,
    label VARCHAR(50) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_types_soin_label (label)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Vocabulaire fermé des types de soin (arrosage/engrais/rempotage/substrat/ravageurs).';

CREATE TABLE IF NOT EXISTS categories_conseil (
    id    SMALLINT    NOT NULL AUTO_INCREMENT,
    label VARCHAR(50) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_conseil_label (label)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Vocabulaire des catégories de conseils (labels éditoriaux + comportements botaniques).';

-- ---------------------------------------------------------------------------
-- 4. TABLE : soins
-- ---------------------------------------------------------------------------
-- Correspond a /assets/js/data/soins.json (vide au seed)
-- Extrait du tableau careHistory[] embarque dans chaque item collections.json
-- Champs inferes depuis addCareEntry() dans collection-service.js :
--   id (care-xxx), collectionId, date, type, notes
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS soins (
    id            CHAR(36)     NOT NULL DEFAULT (UUID()),
    legacy_id     VARCHAR(100) UNIQUE COMMENT 'Ancien id JS : care-xxxxx',
    collection_id CHAR(36)     NOT NULL,
    soin_date     DATE         NOT NULL DEFAULT (CURRENT_DATE),
    -- Référence au vocabulaire fermé des types de soin (arrosage, engrais,
    -- rempotage, substrat, ravageurs). 3NF sélective : voir
    -- docs/architecture/00-arbitrage-denormalisation.md.
    id_type_soin  TINYINT      NOT NULL,
    notes         TEXT,
    -- Champs booleen du formulaire care-modal (macollection.html)
    engrais       TINYINT(1)   NOT NULL DEFAULT 0,
    substrat      TINYINT(1)   NOT NULL DEFAULT 0,
    ravageurs     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_soins_collection_id (collection_id),
    KEY idx_soins_date          (soin_date DESC),
    KEY idx_soins_type          (id_type_soin),
    CONSTRAINT fk_soins_collection FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE,
    CONSTRAINT fk_soins_type       FOREIGN KEY (id_type_soin)  REFERENCES types_soin(id) ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Historique des soins. Extrait du careHistory[] embarque dans collections.json. Correspond a soins.json et addCareEntry() dans collection-service.js.';

-- ---------------------------------------------------------------------------
-- 5. TABLE : conseils
-- ---------------------------------------------------------------------------
-- Correspond a /assets/js/data/conseils.json (434 lignes, 2 types)
-- Champs inferes : id (TEXT slug), type, name, img, category, content, careCards
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS conseils (
    -- id string conserve (ex: "conseils-base", "fiche-acacalis_cyanea")
    id          VARCHAR(200) NOT NULL,
    -- 'category' (conseils generaux) | 'species' (fiche botanique)
    type        ENUM('category', 'species') NOT NULL,
    name        VARCHAR(200) NOT NULL,
    img         TEXT,
    -- Référence à categories_conseil (vocabulaire ouvert). Peut rester null pour
    -- une fiche espèces sans catégorisation éditoriale.
    id_categorie SMALLINT    NULL COMMENT 'FK categories_conseil — null = non catégorisé',
    content     MEDIUMTEXT,
    -- careCards en JSON natif MySQL 8 : {"temperature":"20°C","arrosage":"regulier",...}
    care_cards  JSON         COMMENT 'Carte de soins flexible. Equivalent du JSONB Supabase.',
    -- Lien optionnel vers l orchidee (pour les fiches species uniquement)
    orchid_id   VARCHAR(200) NULL COMMENT 'Espece concernee (fiches species uniquement)',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_conseils_type   (type),
    KEY idx_conseils_orchid (orchid_id),
    KEY idx_conseils_categorie (id_categorie),
    -- Cle etrangere explicite au niveau table (MySQL/InnoDB IGNORE le REFERENCES inline colonne)
    CONSTRAINT fk_conseils_orchid    FOREIGN KEY (orchid_id)    REFERENCES orchids(id)          ON DELETE SET NULL,
    CONSTRAINT fk_conseils_categorie FOREIGN KEY (id_categorie) REFERENCES categories_conseil(id) ON DELETE SET NULL,
    -- FULLTEXT remplace searchConseils() dans conseil-service.js
    -- Depuis la normalisation categories_conseil, l'index ne porte plus que
    -- sur (name, content) — la recherche par label de catégorie passe par un LIKE.
    FULLTEXT KEY ft_conseils_search (name, content)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Fiches conseils generaux et par espece. Correspond a conseils.json. FULLTEXT remplace searchConseils().';

-- ---------------------------------------------------------------------------
-- 6. TABLE : notifications
-- ---------------------------------------------------------------------------
-- Correspond a /assets/js/data/notifications.json
-- Champs inferes : id, userId, collectionId, date, type, message, isRead
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS notifications (
    id            CHAR(36)     NOT NULL DEFAULT (UUID()),
    legacy_id     INT          COMMENT 'Ancien id numerique du JSON seed (1,2,3...)',
    user_id       CHAR(36)     NOT NULL,
    collection_id CHAR(36)     NULL COMMENT 'Null si notification globale',
    type          VARCHAR(50)  NOT NULL DEFAULT 'rappel_soin',
    message       TEXT         NOT NULL,
    is_read       TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Equivalent de isRead dans notifications.json',
    -- Cycle de vie etendu (RG3) : un rappel peut etre planifie (date future),
    -- actif (chu aujourd'hui), traite ou ignore par le proprietaire. Les
    -- notifications non-rappel (validation / rejet / info / nouvelle_proposition)
    -- restent 'actif' par defaut : elles n'ont pas de notion d'echeance.
    statut        ENUM('planifie','actif','traite','ignore') NOT NULL DEFAULT 'actif'
                  COMMENT 'Cycle de vie du rappel (voir docs/architecture/01-mcd-mld-aligne.md §notifications)',
    -- Phase C0 : le rappel porte desormais le type du prochain soin attendu
    -- (vocabulaire ferme types_soin). NULL = rappel historique ou global.
    id_type_soin  TINYINT      NULL
                  COMMENT 'FK types_soin — type du prochain soin attendu (NULL = rappel historique ou global)',
    notif_date    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notifications_user_id (user_id),
    KEY idx_notifications_unread  (user_id, is_read),
    KEY idx_notifications_statut  (user_id, statut, notif_date),
    CONSTRAINT fk_notifications_user       FOREIGN KEY (user_id)       REFERENCES users(id)       ON DELETE CASCADE,
    CONSTRAINT fk_notifications_collection FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE SET NULL,
    CONSTRAINT fk_notifications_type_soin  FOREIGN KEY (id_type_soin)  REFERENCES types_soin(id)  ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Notifications (rappels de soins, alertes). Correspond a notifications.json. is_read = isRead du JSON.';

-- ===========================================================================
-- VUE : v_collection_complete
-- ===========================================================================
-- Remplace le pattern JS dans features/collection.js :
--   const orchid = orchidsDatabase.find(o => o.id === item.orchidId);
-- ===========================================================================

CREATE OR REPLACE VIEW v_collection_complete AS
SELECT
    c.id              AS collection_id,
    c.legacy_id,
    c.user_id,
    u.nom             AS user_nom,
    u.prenom          AS user_prenom,
    u.email           AS user_email,
    o.id              AS orchid_id,
    o.name            AS orchid_name,
    o.vernacular,
    o.behavior,
    o.origin,
    o.img,
    o.short_desc,
    o.long_desc,
    o.genre,
    o.family,
    c.photo,
    c.location,
    c.notes,
    c.temp,
    c.hygro,
    c.light,
    c.ventilation,
    c.added_at,
    c.updated_at
FROM collections c
JOIN users   u ON u.id = c.user_id
JOIN orchids o ON o.id = c.orchid_id;

-- ===========================================================================
-- DONNEES INITIALES (SEEDS)
-- ===========================================================================
-- Le schema ne contient QUE la structure (DDL). Les donnees de demonstration
-- sont deliees dans data/seeds/, a appliquer dans l ordre :
--   01_seed_users.sql        (comptes demo, hashs bcrypt password_hash())
--   02_seed_orchids.sql      (21 especes)
--   03_seed_conseils.sql     (categories + fiches espece)
--   04_seed_collections.sql  (collection de demonstration)
--   05_seed_soins.sql        (historique de soins)
--   06_seed_notifications.sql(rappels)
-- Mots de passe demo (test local uniquement) : jeremy.ferrierpro@gmail.com / demoadmin
-- ===========================================================================

SET FOREIGN_KEY_CHECKS = 1;
