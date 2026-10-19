-- ===========================================================================
-- MIGRATION — Ajout libre à la collection + photo + workflow de modération
-- ===========================================================================
-- Auteur  : Jérémy Ferrier
-- Projet  : Mes Orchidées — Fil Rouge
-- Date    : 2026-10-18
-- Base    : MySQL 8 / MariaDB (mes_orchidees)
--
-- But :
--   1) `orchids.moderation_status` : suivi explicite du flux
--      proposition -> validation/refus. Le refus ne SUPPRIME plus la plante :
--      elle reste acquise au propriétaire de la collection (FK RESTRICT respectée).
--   2) `collections.photo` : photo personnelle uploadée par l'utilisateur lors de
--      l'ajout d'une orchidée à SA collection (distincte de l'image de l'espèce).
--
-- Idempotente : chaque ALTER est protégé par un test sur information_schema,
-- MySQL 8 ne connaissant pas « ADD COLUMN IF NOT EXISTS ».
-- À exécuter UNE seule fois sur une base déjà en place. Les bases neuves créées
-- via data/schema_mysql.sql contiennent déjà ces colonnes.
-- ===========================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1. orchids.moderation_status
-- ---------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS mig_orchids_moderation_status;
DELIMITER $$
CREATE PROCEDURE mig_orchids_moderation_status()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name   = 'orchids'
           AND column_name  = 'moderation_status'
    ) THEN
        ALTER TABLE orchids
            ADD COLUMN moderation_status ENUM('pending','approved','rejected')
                NOT NULL DEFAULT 'approved'
                COMMENT 'Etat du workflow de moderation'
                AFTER is_published;
    END IF;
END$$
DELIMITER ;
CALL mig_orchids_moderation_status();
DROP PROCEDURE IF EXISTS mig_orchids_moderation_status;

-- Repli de l'existant : une fiche non publiée (is_published = 0) issue d'une
-- proposition (proposed_by non NULL) est « pending » ; sinon « approved ».
UPDATE orchids
   SET moderation_status = 'pending'
 WHERE is_published = 0
   AND proposed_by IS NOT NULL
   AND moderation_status = 'approved';

-- Index de la file de modération.
DROP PROCEDURE IF EXISTS mig_orchids_moderation_index;
DELIMITER $$
CREATE PROCEDURE mig_orchids_moderation_index()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name   = 'orchids'
           AND index_name   = 'idx_orchids_moderation'
    ) THEN
        CREATE INDEX idx_orchids_moderation ON orchids (moderation_status);
    END IF;
END$$
DELIMITER ;
CALL mig_orchids_moderation_index();
DROP PROCEDURE IF EXISTS mig_orchids_moderation_index;

-- ---------------------------------------------------------------------------
-- 2. collections.photo
-- ---------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS mig_collections_photo;
DELIMITER $$
CREATE PROCEDURE mig_collections_photo()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name   = 'collections'
           AND column_name  = 'photo'
    ) THEN
        ALTER TABLE collections
            ADD COLUMN photo VARCHAR(255) NULL
                COMMENT 'Chemin relatif de la photo uploadee (perso)'
                AFTER ventilation;
    END IF;
END$$
DELIMITER ;
CALL mig_collections_photo();
DROP PROCEDURE IF EXISTS mig_collections_photo;

-- ---------------------------------------------------------------------------
-- 3. Vue d'ensemble : exposer la photo personnelle
-- ---------------------------------------------------------------------------
-- (Recréée à l'identique du schéma, avec c.photo en plus.)
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
-- Contrôles post-migration
-- ===========================================================================
SELECT CONCAT('moderation_status : ',
       COUNT(*)) AS pending_en_attente
  FROM orchids WHERE moderation_status = 'pending';
