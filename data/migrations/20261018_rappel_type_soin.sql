-- ===========================================================================
-- Migration — Type de soin porté par le rappel (phase C0, arbitrage propriétaire)
-- ===========================================================================
-- Auteur   : Jeremy Ferrier
-- Date     : 2026-10-18
-- Objet    : Le rappel de soin (notifications, type « rappel_soin ») ne stockait
--            le type de soin que dans le texte libre du message. Cette migration
--            ajoute une référence structurée au vocabulaire fermé `types_soin`.
--
-- 1 colonne nullable ajoutée, aucun DROP, aucune casse applicative :
--   - les rappels historiques restent valides (id_type_soin = NULL → pas de badge) ;
--   - les nouveaux rappels héritent du type du soin enregistré qui les programme ;
--   - la FK en RESTRICT protège le vocabulaire fermé (jamais supprimé).
-- Idempotent : l'ALTER est protégé par un garde information_schema.
-- ===========================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- notifications.id_type_soin
-- ---------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS mig_rappel_type_notifications();
DELIMITER $$
CREATE PROCEDURE mig_rappel_type_notifications()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'notifications'
          AND COLUMN_NAME  = 'id_type_soin'
    ) THEN
        ALTER TABLE notifications
            ADD COLUMN id_type_soin TINYINT NULL
                COMMENT 'FK types_soin — type du prochain soin attendu (NULL = rappel historique ou global)'
                AFTER statut,
            ADD CONSTRAINT fk_notifications_type_soin
                FOREIGN KEY (id_type_soin) REFERENCES types_soin(id)
                ON DELETE RESTRICT;
    END IF;
END$$
DELIMITER ;
CALL mig_rappel_type_notifications();
DROP PROCEDURE IF EXISTS mig_rappel_type_notifications;
