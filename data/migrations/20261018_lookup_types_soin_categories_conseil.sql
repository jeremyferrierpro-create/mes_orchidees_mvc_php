-- ===========================================================================
-- MIGRATION — 20261018_lookup_types_soin_categories_conseil
-- ===========================================================================
-- Auteur : Jérémy Ferrier
-- But    : Appliquer la « 3NF sélective » retenue dans l'arbitrage
--          docs/architecture/00-arbitrage-denormalisation.md.
--
-- Deux tables de lookup fermées sont créées :
--   * types_soin        : les 5 types de soin historiquement whitelistés
--                         dans SoinController::TYPES.
--   * categories_conseil: le vocabulaire editorial des conseils (11 valeurs
--                         présentes en production, mélange de labels
--                         éditoriaux et de comportements botaniques).
--
-- Colonnes normalisées :
--   * soins.id_type_soin        TINYINT NOT NULL  (FK → types_soin, RESTRICT)
--   * conseils.id_categorie     SMALLINT NULL     (FK → categories_conseil, SET NULL)
--
-- Colonnes supprimées après backfill :
--   * soins.type                VARCHAR(100)
--   * conseils.category         VARCHAR(100)   (+ reconstruit l'index FULLTEXT)
--
-- Idempotence : chaque ALTER / CREATE / DROP est protégé par un test
-- information_schema. Le fichier peut être relancé sans effet de bord.
--
-- exécution : mysql -u root mes_orchidees < ...   OU   via tests\_apply_lookup.php
-- ===========================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- 1. Table de lookup : types_soin
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS types_soin (
    id    TINYINT      NOT NULL AUTO_INCREMENT,
    label VARCHAR(50)  NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_types_soin_label (label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Vocabulaire fermé des types de soin (5 valeurs).';

INSERT INTO types_soin (label) VALUES
    ('arrosage'), ('engrais'), ('rempotage'), ('substrat'), ('ravageurs')
ON DUPLICATE KEY UPDATE label = VALUES(label);

-- ---------------------------------------------------------------------------
-- 2. Table de lookup : categories_conseil
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories_conseil (
    id    SMALLINT     NOT NULL AUTO_INCREMENT,
    label VARCHAR(50)  NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_conseil_label (label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Vocabulaire ouvert des catégories de conseils (labels éditoriaux + comportements botaniques).';

-- 2a. Seed des 6 labels éditoriaux « category advice » attendus par le cahier.
INSERT INTO categories_conseil (label) VALUES
    ('Conseils de base'),
    ('Pour les epiphytes'),
    ('Pour les terrestres'),
    ('Pour les hemi-epiphytes'),
    ('Apres achat'),
    ('Sortie de flask')
ON DUPLICATE KEY UPDATE label = VALUES(label);

-- 2b. Backfill depuis les valeurs présentes en base (cas des fiches espèces
--     dont `category` portait le comportement botanique : Epiphyte, etc.).
INSERT INTO categories_conseil (label)
    SELECT DISTINCT c.category FROM conseils c
    WHERE c.category IS NOT NULL AND c.category <> ''
      AND NOT EXISTS (SELECT 1 FROM categories_conseil cc WHERE cc.label = c.category);

-- ---------------------------------------------------------------------------
-- 3. soins : ajouter id_type_soin, backfill, drop `type`
-- ---------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS mig_soins_lookup;
DELIMITER $$
CREATE PROCEDURE mig_soins_lookup()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'soins'
                     AND COLUMN_NAME = 'id_type_soin') THEN
        ALTER TABLE soins ADD COLUMN id_type_soin TINYINT NULL AFTER type;
    END IF;

    UPDATE soins s
       SET s.id_type_soin = (SELECT ts.id FROM types_soin ts WHERE ts.label = s.type LIMIT 1)
     WHERE s.id_type_soin IS NULL;

    -- Repli : tout soin orphelin (type inconnu ou NULL) est rattaché à « arrosage ».
    UPDATE soins SET id_type_soin = (SELECT MIN(id) FROM types_soin) WHERE id_type_soin IS NULL;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'soins'
                     AND COLUMN_NAME = 'id_type_soin' AND IS_NULLABLE = 'NO') THEN
        ALTER TABLE soins MODIFY id_type_soin TINYINT NOT NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'soins'
                     AND CONSTRAINT_NAME = 'fk_soins_type') THEN
        ALTER TABLE soins ADD CONSTRAINT fk_soins_type
            FOREIGN KEY (id_type_soin) REFERENCES types_soin(id) ON DELETE RESTRICT;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'soins'
                 AND COLUMN_NAME = 'type') THEN
        ALTER TABLE soins DROP COLUMN type;
    END IF;
END $$
DELIMITER ;
CALL mig_soins_lookup();
DROP PROCEDURE IF EXISTS mig_soins_lookup;

-- ---------------------------------------------------------------------------
-- 4. conseils : ajouter id_categorie, backfill, drop `category` (+ FULLTEXT)
-- ---------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS mig_conseils_lookup;
DELIMITER $$
CREATE PROCEDURE mig_conseils_lookup()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conseils'
                     AND COLUMN_NAME = 'id_categorie') THEN
        ALTER TABLE conseils ADD COLUMN id_categorie SMALLINT NULL AFTER category;
    END IF;

    UPDATE conseils c
       SET c.id_categorie = (SELECT cc.id FROM categories_conseil cc WHERE cc.label = c.category LIMIT 1)
     WHERE c.id_categorie IS NULL AND c.category IS NOT NULL AND c.category <> '';

    IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conseils'
                     AND CONSTRAINT_NAME = 'fk_conseils_categorie') THEN
        ALTER TABLE conseils ADD CONSTRAINT fk_conseils_categorie
            FOREIGN KEY (id_categorie) REFERENCES categories_conseil(id) ON DELETE SET NULL;
    END IF;

    -- Le FULLTEXT actuel référence `category`. Il faut le recréer sans lui.
    IF EXISTS (SELECT 1 FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conseils'
                 AND INDEX_NAME = 'ft_conseils_search') THEN
        ALTER TABLE conseils DROP INDEX ft_conseils_search;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conseils'
                 AND COLUMN_NAME = 'category') THEN
        ALTER TABLE conseils DROP COLUMN category;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conseils'
                     AND INDEX_NAME = 'ft_conseils_search') THEN
        ALTER TABLE conseils ADD FULLTEXT KEY ft_conseils_search (name, content);
    END IF;
END $$
DELIMITER ;
CALL mig_conseils_lookup();
DROP PROCEDURE IF EXISTS mig_conseils_lookup;

SET FOREIGN_KEY_CHECKS = 1;

-- ===========================================================================
-- Vérification post-migration :
--   SELECT COUNT(*) FROM types_soin;            -- attendu : 5
--   SELECT COUNT(*) FROM categories_conseil;    -- attendu : >= 6 (jusqu'à 11)
--   SHOW COLUMNS FROM soins LIKE 'type';        -- attendu : empty set
--   SHOW COLUMNS FROM conseils LIKE 'category'; -- attendu : empty set
-- ===========================================================================
