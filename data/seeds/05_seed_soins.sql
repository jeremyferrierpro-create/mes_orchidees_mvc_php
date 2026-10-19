-- ===========================================================================
-- SEED 05 — TABLE soins
-- ===========================================================================
-- Ordre de migration : 5eme (collections doit exister)
-- Source : /assets/js/data/soins.json (vide au seed initial)
-- BDD : MySQL 8 / MariaDB
-- Schema : schema_mysql.sql
--
-- En production, la table soins est VIDE au deploiement.
-- Elle est alimentee par l application PHP via addCareEntry().
-- Ce seed insere des donnees de demonstration basees sur les
-- messages des notifications.json pour les tests.
--
-- Normalisation 3NF selective (migration 20261018_lookup_types_soin_
-- categories_conseil) : le type de soin est desormais une FK vers la
-- reference `types_soin`. La valeur est resolue par label via une
-- sous-requete — le seed reste idempotent et lisible.
--
-- Idempotence : legacy_id est UNIQUE → ON DUPLICATE KEY UPDATE a la
-- re-execution (pas de doublon).
-- ===========================================================================

INSERT INTO soins (legacy_id, collection_id, soin_date, id_type_soin, notes, engrais, substrat, ravageurs)
VALUES
    -- Soin Jean-Marc : arrosage col-001 (acacalis_cyanea)
    ('care-demo-001',
     (SELECT id FROM collections WHERE legacy_id = 'col-001' LIMIT 1),
     '2026-08-14',
     (SELECT id FROM types_soin WHERE label = 'arrosage'),
     'Arrosage complet trempage 15 minutes eau douce', FALSE, FALSE, FALSE),

    -- Soin Jean-Marc : engrais col-001
    ('care-demo-002',
     (SELECT id FROM collections WHERE legacy_id = 'col-001' LIMIT 1),
     '2026-07-28',
     (SELECT id FROM types_soin WHERE label = 'engrais'),
     'Engrais orchidee N20-P20-K20 dilue au 1/4', TRUE, FALSE, FALSE),

    -- Soin Jessica : arrosage col-004 (angraecum_sesquipedale)
    ('care-demo-003',
     (SELECT id FROM collections WHERE legacy_id = 'col-004' LIMIT 1),
     '2026-08-15',
     (SELECT id FROM types_soin WHERE label = 'arrosage'),
     'Arrosage enregistre - prochain rappel fixe au 22/08/2026', FALSE, FALSE, FALSE),

    -- Soin Jessica : verification ravageurs col-005
    ('care-demo-004',
     (SELECT id FROM collections WHERE legacy_id = 'col-005' LIMIT 1),
     '2026-08-10',
     (SELECT id FROM types_soin WHERE label = 'ravageurs'),
     'Controle cochenilles - traitement preventif huile de neem', FALSE, FALSE, TRUE)

ON DUPLICATE KEY UPDATE
    soin_date    = VALUES(soin_date),
    id_type_soin = VALUES(id_type_soin),
    notes        = VALUES(notes);

-- Verification post-seed (MySQL/MariaDB) : doit afficher >= 4.
SELECT CONCAT('SEED 05 OK : ', COUNT(*), ' soin(s) enregistre(s)') AS seed_check
  FROM soins;
