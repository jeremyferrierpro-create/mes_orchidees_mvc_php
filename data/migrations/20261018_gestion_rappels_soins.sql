-- ===========================================================================
-- Migration — Gestion des rappels de soins
-- ===========================================================================
-- Auteur   : Jeremy Ferrier
-- Date     : 2026-10-18
-- Objet    : (1) fréquence personnalisable par plante ; (2) cycle de vie d'un
--            rappel (planifié → actif → traité/ignoré) ; (3) backfill de la
--            date d'échéance pour les rappels déjà enregistrés.
--
-- 3 colonnes touchées, aucun DROP, aucune casse applicative.
-- Idempotent : chaque ALTER est protégé par un garde information_schema.
-- ===========================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1. collections.rappel_frequence_jours
-- ---------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS mig_rappels_collections();
DELIMITER $$
CREATE PROCEDURE mig_rappels_collections()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'collections'
          AND COLUMN_NAME  = 'rappel_frequence_jours'
    ) THEN
        ALTER TABLE collections
            ADD COLUMN rappel_frequence_jours SMALLINT NOT NULL DEFAULT 7
                COMMENT 'Fréquence personnalisable du rappel (jours). RG3 : 7 par défaut.';
    END IF;
END$$
DELIMITER ;
CALL mig_rappels_collections();
DROP PROCEDURE IF EXISTS mig_rappels_collections;

-- ---------------------------------------------------------------------------
-- 2. notifications.statut (cycle de vie des rappels)
-- ---------------------------------------------------------------------------
-- Les notifications déjà présentes en base (validation, rejet, info, admin)
-- reçoivent « actif » par défaut. Les rappels de soin existants seront
-- requalifiés en « planifie » par le backfill §3.
DROP PROCEDURE IF EXISTS mig_rappels_notifications();
DELIMITER $$
CREATE PROCEDURE mig_rappels_notifications()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'notifications'
          AND COLUMN_NAME  = 'statut'
    ) THEN
        ALTER TABLE notifications
            ADD COLUMN statut ENUM('planifie','actif','traite','ignore')
                NOT NULL DEFAULT 'actif'
                COMMENT 'Cycle de vie : planifie (rappel futur), actif (dû maintenant), traite / ignore (consommé).'
                AFTER is_read;
        CREATE INDEX idx_notifications_statut
            ON notifications (user_id, statut, notif_date);
    END IF;
END$$
DELIMITER ;
CALL mig_rappels_notifications();
DROP PROCEDURE IF EXISTS mig_rappels_notifications;

-- ---------------------------------------------------------------------------
-- 3. Backfill des rappels de soin préexistants
-- ---------------------------------------------------------------------------
-- Pour chaque notification `rappel_soin` liée à une collection, on repositionne
-- notif_date sur « dernier soin + fréquence de la plante » et on la marque
-- « planifie » (elle n'est pas encore due). Les autres types (validation,
-- nouvelle_proposition, info, rejet) restent à « actif ».
UPDATE notifications n
   JOIN collections c ON c.id = n.collection_id
   SET n.notif_date = COALESCE(
        (SELECT DATE_ADD(MAX(s.soin_date), INTERVAL c.rappel_frequence_jours DAY)
           FROM soins s WHERE s.collection_id = c.id),
        DATE_ADD(n.created_at, INTERVAL c.rappel_frequence_jours DAY)
   ),
   n.statut = 'planifie'
 WHERE n.type = 'rappel_soin';

-- ---------------------------------------------------------------------------
-- 4. Seed de la fréquence par défaut sur les entrées préexistantes
-- ---------------------------------------------------------------------------
-- Le DEFAULT 7 couvre déjà ; cette requête sécurise un éventuel NULL hérité
-- d'un ALTER précédent non NOT NULL.
UPDATE collections SET rappel_frequence_jours = 7
 WHERE rappel_frequence_jours IS NULL OR rappel_frequence_jours = 0;
