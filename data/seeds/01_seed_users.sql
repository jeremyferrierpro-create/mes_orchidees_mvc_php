-- ===========================================================================
-- SEED 01 — TABLE users
-- ===========================================================================
-- Ordre de migration : 1er (aucune FK entrante)
-- Source : /assets/js/data/users.json
-- BDD : MySQL 8 / MariaDB
-- Schema : schema_mysql.sql
--
-- SECURITE (decision D4 — phase E17-C) : comptes ENTIEREMENT FICTIFS.
-- Adresses en domaine reserve example.com (RFC 2606, jamais deliverable).
-- Les hashs ci-dessous sont des hashs bcrypt generes avec
-- password_hash($mdp, PASSWORD_DEFAULT) a partir de mots de passe de
-- demonstration fictifs ; leurs versions en clair ne sont publiees nulle
-- part (ni dans le depot, ni dans un rapport). Ils servent uniquement aux
-- tests locaux et a la demonstration de soutenance.
--
-- Avant une demonstration, definir un mot de passe connu :
--   1. php -r "echo password_hash('votre_mdp_demo', PASSWORD_DEFAULT);"
--   2. UPDATE users SET password_hash='<hash>' WHERE email='<email du compte>';
-- La colonne id a DEFAULT uuid() : on ne l'insert pas.
-- ===========================================================================

INSERT INTO users (nom, prenom, email, password_hash, role, created_at, updated_at)
VALUES
    -- Compte de demonstration « administrateur » (fictif)
    ('Exemple', 'Admin', 'admin@example.com',
        '$2y$10$QW3ffetio8pqXXuQlw2pXOPAq/QRlqnRCGDfGk3/TJvpqNCAbVhTO', 'admin',
        '2026-01-15 00:00:00', '2026-04-01 00:00:00'),
    -- Compte de demonstration « utilisateur standard » (fictif)
    ('Exemple', 'Utilisateur', 'utilisateur@example.com',
        '$2y$10$bMoHmGCIENpiZ7ckoI7HuOD7BFZsHopjEq15Fq1G5NBXis.cGayki', 'user',
        '2026-01-15 00:00:00', '2026-04-01 00:00:00'),
    -- Compte de demonstration « moderateur » (fictif — role prévu au
    -- schema : ENUM('user','admin','moderateur') de schema_mysql.sql)
    ('Exemple', 'Moderateur', 'moderateur@example.com',
        '$2y$10$wx.LgzanwKPBj6Ti6IhLq.eafr8xSIJ8LZrgxfJbTMSA1vYGXuqA.', 'moderateur',
        '2026-02-03 00:00:00', '2026-05-10 00:00:00')
ON DUPLICATE KEY UPDATE
    nom           = VALUES(nom),
    prenom        = VALUES(prenom),
    password_hash = VALUES(password_hash),
    role          = VALUES(role);

-- Verification post-seed (MySQL/MariaDB) : doit afficher >= 3.
SELECT CONCAT('SEED 01 OK : ', COUNT(*), ' utilisateur(s) en base') AS seed_check
  FROM users;
