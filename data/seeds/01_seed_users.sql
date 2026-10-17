-- ===========================================================================
-- SEED 01 — TABLE users
-- ===========================================================================
-- Ordre de migration : 1er (aucune FK entrante)
-- Source : /assets/js/data/users.json
-- BDD : MySQL 8 / MariaDB
-- Schema : schema_mysql.sql
--
-- SECURITE : les mots de passe en clair du JSON sont REMPLACES par des hashs
-- generes avec password_hash($mdp, PASSWORD_DEFAULT) (bcrypt), coherence avec
-- la decision Phase 02. Les hashs ci-dessous sont des hashs de DEMONSTRATION
-- valables pour les tests locaux uniquement.
--
-- Correspondances (tests locaux, ne pas commiter en prod) :
--   jeremy.ferrierpro@gmail.com  => "demoadmin"  (role admin)
--   admin@mesorchidees.fr        => "demouser"   (role user)
--   jessica.amateur@gmail.com    => "demouser"   (role user)
--
-- Pour regenerer : php -r "echo password_hash('demoadmin', PASSWORD_DEFAULT);"
-- La colonne id a DEFAULT uuid() : on ne l'insert pas.
-- ===========================================================================

INSERT INTO users (nom, prenom, email, password_hash, role, created_at, updated_at)
VALUES
    -- Jean-Marc Dupont — utilisateur simple
    ('Dupont', 'Jean-Marc', 'admin@mesorchidees.fr',
        '$2y$10$9znH4ryBgExcnzQgGsCzJOs3ZhQHbibGsnYDhGQ5BiBGCyyd8ID.O', 'user',
        '2026-01-15 00:00:00', '2026-04-01 00:00:00'),
    -- Jeremy Ferrier — administrateur
    ('FERRIER', 'Jeremy', 'jeremy.ferrierpro@gmail.com',
        '$2y$10$Ck.ltdPOB/sPUDjU7ZRxB.q.h7GCxuH20mg/JLWUQpspxFrthZ/Mu', 'admin',
        '2026-01-15 00:00:00', '2026-04-01 00:00:00'),
    -- Jessica Martin — utilisatrice simple
    ('Martin', 'Jessica', 'jessica.amateur@gmail.com',
        '$2y$10$9znH4ryBgExcnzQgGsCzJOs3ZhQHbibGsnYDhGQ5BiBGCyyd8ID.O', 'user',
        '2026-02-03 00:00:00', '2026-05-10 00:00:00')
ON DUPLICATE KEY UPDATE
    nom           = VALUES(nom),
    prenom        = VALUES(prenom),
    password_hash = VALUES(password_hash),
    role          = VALUES(role);

-- Verification post-seed (MySQL/MariaDB) : doit afficher >= 3.
SELECT CONCAT('SEED 01 OK : ', COUNT(*), ' utilisateur(s) en base') AS seed_check
  FROM users;
