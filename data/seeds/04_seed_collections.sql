-- ===========================================================================
-- SEED 04 — TABLE collections
-- ===========================================================================
-- Ordre de migration : 4eme (users + orchids doivent exister)
-- Source : /assets/js/data/collections.json (vide en seed initial)
-- BDD : MySQL 8 / MariaDB
-- Schema : schema_mysql.sql
--
-- En production, la table collections est VIDE au deploiement.
-- Elle sera alimentee par les utilisateurs via l application PHP.
-- Ce seed insere des donnees de demonstration pour les tests de recette.
--
-- IMPORTANT : les collections.json du localStorage utilisaient des
-- collectionId string (ex: "col-001"). En SQL ce devient un UUID dans
-- la colonne id (DEFAULT (UUID())), avec legacy_id qui conserve l ancien
-- identifiant pour la migration sans perte de donnees.
--
-- Idempotence : la cle UNIQUE (user_id, legacy_id) déclenche ON DUPLICATE
-- KEY UPDATE a chaque re-execution (pas de doublon).
-- ===========================================================================

-- Exemples de demonstration (correspondent aux notifications.json)
-- Les sous-requetes resolvent les UUID depuis l email / legacy_id
-- (independant de l ordre d insertion des users)

INSERT INTO collections (legacy_id, user_id, orchid_id, location, notes, added_at)
VALUES
    -- Utilisateur (fictif) : col-001 → ACACALIS CYANEA
    ('col-001',
     (SELECT id FROM users WHERE email = 'utilisateur@example.com' LIMIT 1),
     'acacalis_cyanea',
     'Veranda Sud', 'Tres belle floraison en mars',
     '2026-03-01 00:00:00'),

    -- Utilisateur (fictif) : col-002 → BLETILLA OCHRACEA
    ('col-002',
     (SELECT id FROM users WHERE email = 'utilisateur@example.com' LIMIT 1),
     'bletilla_ochracea',
     'Jardin pleine terre', 'Hiberne en hiver',
     '2026-04-15 00:00:00'),

    -- Utilisateur (fictif) : col-003 → VANILLA PLANIFOLIA
    ('col-003',
     (SELECT id FROM users WHERE email = 'utilisateur@example.com' LIMIT 1),
     'vanilla_planifolia',
     'Serre chauffee', 'Pollinisation manuelle en cours',
     '2026-05-10 00:00:00'),

    -- Admin (fictif) : col-004 → ANGRAECUM SESQUIPEDALE
    ('col-004',
     (SELECT id FROM users WHERE email = 'admin@example.com' LIMIT 1),
     'angraecum_sesquipedale',
     'Fenetre Est salon', 'Premier arrosage soin enregistre',
     '2026-06-01 00:00:00'),

    -- Admin (fictif) : col-005 → AERANGIS FASTUOSA
    ('col-005',
     (SELECT id FROM users WHERE email = 'admin@example.com' LIMIT 1),
     'aerangis_fastuosa',
     'Terrarium humide', NULL,
     '2026-07-20 00:00:00'),

    -- Admin (fictif) : col-006 → ANGULOA VIRGINALIS
    ('col-006',
     (SELECT id FROM users WHERE email = 'admin@example.com' LIMIT 1),
     'anguloa_virginalis',
     'Serre froide', 'Repos hivernal programme',
     '2026-08-01 00:00:00')

ON DUPLICATE KEY UPDATE
    location = VALUES(location),
    notes    = VALUES(notes),
    added_at = VALUES(added_at);

-- Verification post-seed (MySQL/MariaDB) : doit afficher >= 6.
SELECT CONCAT('SEED 04 OK : ', COUNT(*), ' entree(s) de collection en base') AS seed_check
  FROM collections;
