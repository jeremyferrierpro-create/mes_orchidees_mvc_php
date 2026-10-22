-- ===========================================================================
-- SEED 06 — TABLE notifications
-- ===========================================================================
-- Ordre de migration : 6eme (collections doit exister pour les FK)
-- Source : /assets/js/data/notifications.json (6 entrees)
-- BDD : MySQL 8 / MariaDB
-- Schema : schema_mysql.sql
--
-- Traductions :
--   userId      → user_id CHAR(36) (resolu depuis email via sous-requete)
--   collectionId string "col-001" → collection_id CHAR(36) via legacy_id
--   date "dd/MM/yyyy" → notif_date DATETIME
--   isRead boolean → is_read TINYINT(1)
--   type "rappel_soin" → type VARCHAR(50) DEFAULT 'rappel_soin'
--
-- Idempotence : notifications n a pas de cle unique naturelle hors PK.
-- On supprime d abord les 6 lignes de demonstration (legacy_id 1..6)
-- puis on reinsere → re-execution sans doublon.
-- ===========================================================================

DELETE FROM notifications WHERE legacy_id IN (1, 2, 3, 4, 5, 6);

INSERT INTO notifications (legacy_id, user_id, collection_id, type, message, is_read, notif_date)
VALUES
    -- Notification 1 : Utilisateur / col-001 / non lue
    (1,
     (SELECT id FROM users WHERE email = 'utilisateur@example.com' LIMIT 1),
     (SELECT id FROM collections WHERE legacy_id = 'col-001' LIMIT 1),
     'rappel_soin',
     'Utilisateur : prochain arrosage prevu le 21/08/2026 pour ACACALIS CYANEA.',
     FALSE,
     '2026-08-14 00:00:00'),

    -- Notification 2 : Utilisateur / col-002 / non lue
    (2,
     (SELECT id FROM users WHERE email = 'utilisateur@example.com' LIMIT 1),
     (SELECT id FROM collections WHERE legacy_id = 'col-002' LIMIT 1),
     'rappel_soin',
     'Utilisateur : arrosage a prevoir pour BLETILLA OCHRACEA.',
     FALSE,
     '2026-08-17 00:00:00'),

    -- Notification 3 : Utilisateur / col-003 / non lue
    (3,
     (SELECT id FROM users WHERE email = 'utilisateur@example.com' LIMIT 1),
     (SELECT id FROM collections WHERE legacy_id = 'col-003' LIMIT 1),
     'rappel_soin',
     'Utilisateur : apport d eau et d engrais a prevoir pour VANILLA PLANIFOLIA.',
     FALSE,
     '2026-08-19 00:00:00'),

    -- Notification 4 : Admin / col-004 / LUE
    (4,
     (SELECT id FROM users WHERE email = 'admin@example.com' LIMIT 1),
     (SELECT id FROM collections WHERE legacy_id = 'col-004' LIMIT 1),
     'rappel_soin',
     'Admin : arrosage enregistre pour ANGRAECUM SESQUIPEDALE. Le prochain rappel est fixe au 22/08/2026.',
     TRUE,
     '2026-08-15 00:00:00'),

    -- Notification 5 : Admin / col-005 / non lue
    (5,
     (SELECT id FROM users WHERE email = 'admin@example.com' LIMIT 1),
     (SELECT id FROM collections WHERE legacy_id = 'col-005' LIMIT 1),
     'rappel_soin',
     'Admin : prochain arrosage prevu le 23/08/2026 pour AERANGIS FASTUOSA.',
     FALSE,
     '2026-08-16 00:00:00'),

    -- Notification 6 : Admin / col-006 / non lue
    (6,
     (SELECT id FROM users WHERE email = 'admin@example.com' LIMIT 1),
     (SELECT id FROM collections WHERE legacy_id = 'col-006' LIMIT 1),
     'rappel_soin',
     'Admin : arrosage a prevoir pour ANGULOA VIRGINALIS.',
     FALSE,
     '2026-08-16 00:00:00');

-- Verification post-seed (MySQL/MariaDB) : doit afficher 6 notifications dont 1 lue.
SELECT CONCAT('SEED 06 OK : ', COUNT(*), ' notification(s) dont ',
       SUM(is_read = 0), ' non lue(s)') AS seed_check
  FROM notifications;
