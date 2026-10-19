-- ===========================================================================
-- Migration — Opt-in du rappel automatique de soin
-- ===========================================================================
-- Auteur   : Jeremy Ferrier
-- Date     : 2026-10-18
-- Objet    : Ajout d'un interrupteur « rappel_actif » par plante dans la
--            collection. RG3 étendu : l'utilisateur choisit explicitement, à
--            l'enregistrement d'un soin, si le prochain rappel doit être
--            programmé. Défaut = 1 (comportement existant préservé).
--
-- 1 colonne ajoutée, aucun DROP, aucune casse applicative.
-- Idempotent : l'ALTER est protégé par un garde information_schema.
-- ===========================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- collections.rappel_actif
-- ---------------------------------------------------------------------------
-- 1 = un rappel est programmé à chaque soin enregistré (comportement RG3).
-- 0 = rappels désactivés pour cette plante : aucun nouveau rappel, et le
--     rappel éventuellement déjà planifié est annulé (statut « ignore ») côté
--     applicatif — la ligne est conservée (aucune destruction de l'historique).
DROP PROCEDURE IF EXISTS mig_optin_collections();
DELIMITER $$
CREATE PROCEDURE mig_optin_collections()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'collections'
          AND COLUMN_NAME  = 'rappel_actif'
    ) THEN
        ALTER TABLE collections
            ADD COLUMN rappel_actif TINYINT(1) NOT NULL DEFAULT 1
                COMMENT 'Opt-in du rappel automatique (1 = programmer à chaque soin, 0 = désactivé)'
                AFTER rappel_frequence_jours;
    END IF;
END$$
DELIMITER ;
CALL mig_optin_collections();
DROP PROCEDURE IF EXISTS mig_optin_collections;

-- ---------------------------------------------------------------------------
-- Sécurité : les valeurs NULL héritées d'un ALTER non NOT NULL sont repliées
-- sur l'état activé (1) pour préserver le comportement historique.
-- ---------------------------------------------------------------------------
UPDATE collections SET rappel_actif = 1 WHERE rappel_actif IS NULL;
