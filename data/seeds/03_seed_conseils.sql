-- ===========================================================================
-- SEED 03 — TABLE conseils (categories uniquement — fiches generees ensuite)
-- ===========================================================================
-- Ordre de migration : 3eme (orchids doit exister pour les FK species)
-- Source : /assets/js/data/conseils.json (premiere partie : categories)
-- BDD : MySQL 8 / MariaDB
-- Schema : schema_mysql.sql
--
-- Depuis la normalisation 3NF selective (migration
-- 20261018_lookup_types_soin_categories_conseil) :
--   * categories_conseil porte le vocabulaire des categories de conseils.
--   * conseils.id_categorie reference cette lookup (NULL autorise).
--   * La resolution par label est faite via une sous-requete, le seed reste
--     idempotent et lisible.
--
-- Deux types dans conseils.json :
--   "category" => conseils generaux (id: "conseils-base", "conseils-epiphytes"...)
--   "species"  => fiche par espece (id: "fiche-acacalis_cyanea"...)
--               Le pattern "fiche-" + orchid_id est formalise en FK orchid_id.
--
-- Note : care_cards stocke en JSON natif MySQL (structure flexible preservee depuis le JSON)
-- ===========================================================================

-- -------------------------------------------
-- 3A. CATEGORIES GENERALES
-- -------------------------------------------

INSERT INTO conseils (id, type, name, img, id_categorie, content, care_cards, orchid_id)
VALUES
('conseils-base', 'category', 'Conseils de base',
 './assets/images/site/base.jpg',
 (SELECT id FROM categories_conseil WHERE label = 'Conseils de base' LIMIT 1),
 'Le secret absolu : L orchidee est une plante dite aerienne dont les racines respirent. Le pire ennemi de votre nouvelle acquisition reste l asphyxie racinaire due a un exces d eau. Lumiere : Preferez une lumiere vive tamisee, a moins d un metre d une fenetre, sans jamais de soleil direct brulant. Arrosage : Ne versez jamais un petit peu chaque jour. Attendez que le substrat soit presque sec, puis trempez le pot 10 a 15 minutes. Hygrometrie : Une ambiance humide (autour de 60 %) est favorable. Un bac de graviers humidifies aide beaucoup. Environnement : Bannissez le terreau de jardin ordinaire. Utilisez un melange d ecorces, de tourbe et de perlite bien drainant.',
 '{"temperature": "20 C", "arrosage": "regulier", "hygrometrie": "65 %", "rempotage": "2 ans", "engrais": "1 arrosage sur 2", "substrats": "selon type"}',
 NULL),

('conseils-epiphytes', 'category', 'Pour les epiphytes',
 './assets/images/site/epiphyte.jpg',
 (SELECT id FROM categories_conseil WHERE label = 'Pour les epiphytes' LIMIT 1),
 'Le secret absolu : Dans la nature, les racines des orchidees epiphytes respirent autant qu elles boivent. Lumiere : Installez votre protegee tout pres d une fenetre (a moins d un metre). Arrosage : N arrosez jamais un petit peu chaque jour. Attendez que le substrat soit presque sec. L indicateur magique : Regardez les racines a travers le pot. Sont-elles grises ? L orchidee a soif. Sont-elles vertes ? Elle a assez d eau.',
 '{"temperature": "25 C", "arrosage": "regulier", "hygrometrie": "65 %", "rempotage": "2 ans", "engrais": "1 arrosage sur 2", "substrats": "Plaque de liege"}',
 NULL),

('conseils-terrestres', 'category', 'Pour les terrestres',
 './assets/images/site/terrestre.jpg',
 (SELECT id FROM categories_conseil WHERE label = 'Pour les terrestres' LIMIT 1),
 'Le secret absolu : Dans la nature, les racines des orchidees terrestres aiment la fraicheur de la terre, mais elles detestent etre noyees. Lumiere : Installez votre protegee dans une piece bien eclairee. Arrosage : N arrosez jamais par petites gouttes quotidiennes. Attendez que le coeur du pot commence a s alleger.',
 '{"temperature": "-1 / +30 C", "arrosage": "regulier", "hygrometrie": "50 %", "rempotage": "2 ans", "engrais": "1 arrosage sur 2", "substrats": "organique"}',
 NULL),

('conseils-hemi-epiphytes', 'category', 'Pour les hemi-epiphytes',
 './assets/images/site/hemiepyphite.jpg',
 (SELECT id FROM categories_conseil WHERE label = 'Pour les hemi-epiphytes' LIMIT 1),
 'Le secret absolu : Dans la nature, ces orchidees possedent un double systeme : des racines qui aiment l humus et d autres qui grimpent dans l air pour respirer. Le substrat doit etre tres aere pour reproduire ce compromis parfait entre terre et ecorce.',
 '{"temperature": "25 C", "arrosage": "regulier", "hygrometrie": "70 %", "rempotage": "2 ans", "engrais": "1 arrosage sur 2", "substrats": "fibreux"}',
 NULL),

('conseils-apres-achat', 'category', 'Apres achat',
 './assets/images/site/orchidee_apres_achat.jpg',
 (SELECT id FROM categories_conseil WHERE label = 'Apres achat' LIMIT 1),
 'Le transport protege : Les orchidees detestent les courants d air et les chocs thermiques. L emplacement ideal : Installez-la immediatement dans une piece lumineuse. La quarantaine de securite : Par prudence, gardez la nouvelle venue isolee des autres plantes pendant deux semaines. Pas de rempotage immediat : Ne rempotez jamais une orchidee en pleine floraison.',
 '{"temperature": "20 C", "arrosage": "regulier", "hygrometrie": "65 %", "rempotage": "2 ans", "engrais": "1 arrosage sur 2", "substrats": "selon type"}',
 NULL),

('conseils-flask', 'category', 'Sortie de flask',
 './assets/images/site/sortie_flask.webp',
 (SELECT id FROM categories_conseil WHERE label = 'Sortie de flask' LIMIT 1),
 'CONSEIL SORTIE DE FLASK : LE SAUVETAGE DES PLANTULES. L avis de l expert : Sortir des plantules de leur flacon sterile est une operation delicate. Le Sevrage : Sortez delicatement les plantules du flacon. Lavez-les imperativement a l eau tiede pour eliminer toute trace de gelose. L Installation : Ne les rempotez pas individuellement. Installez-les ensemble en communaute.',
 '{"temperature": "25 C", "arrosage": "regulier", "hygrometrie": "90 %", "rempotage": "1 an", "engrais": "pas d engrais", "substrats": "ultra fin"}',
 NULL)

ON DUPLICATE KEY UPDATE
    content      = VALUES(content),
    care_cards   = VALUES(care_cards),
    id_categorie = VALUES(id_categorie);

-- -------------------------------------------
-- 3B. FICHES PAR ESPECE (type = 'species')
-- -------------------------------------------
-- Pattern : id = 'fiche-' || orchid_id
-- orchid_id FK doit exister dans la table orchids (seed 02)

INSERT INTO conseils (id, type, name, img, id_categorie, content, care_cards, orchid_id)
VALUES
('fiche-acacalis_cyanea', 'species', 'ACACALIS CYANEA', './assets/images/orchids/acacalis_cyanea.png',
 (SELECT id FROM categories_conseil WHERE label = 'Epiphyte' LIMIT 1),
 'ACACALIS CYANEA est une orchidee epiphyte originaire de Amerique du Sud. Acacalis cyanea demande une forte humidite ambiante (80%+), des temperatures chaudes et une lumiere tamisee de sous-bois.',
 '{"temperature": "20-25 C", "arrosage": "regulier", "hygrometrie": "65 %", "rempotage": "2 ans", "engrais": "1 arrosage sur 2", "substrats": "Plaque de liege"}',
 'acacalis_cyanea'),

('fiche-acineta_barkerii', 'species', 'ACINETA BARKERII', './assets/images/orchids/acineta_barkerii.png',
 (SELECT id FROM categories_conseil WHERE label = 'Epiphyte' LIMIT 1),
 'ACINETA BARKERII est une orchidee epiphyte originaire de Mexique, Amerique Centrale.',
 '{"temperature": "20-25 C", "arrosage": "regulier", "hygrometrie": "65 %", "rempotage": "2 ans", "engrais": "1 arrosage sur 2", "substrats": "Plaque de liege"}',
 'acineta_barkerii'),

('fiche-ada_aurantiaca', 'species', 'ADA AURANTIACA', './assets/images/orchids/ada_aurantiaca.png',
 (SELECT id FROM categories_conseil WHERE label = 'Epiphyte' LIMIT 1),
 'ADA AURANTIACA est une orchidee epiphyte originaire des Andes.',
 '{"temperature": "15-20 C", "arrosage": "regulier", "hygrometrie": "70 %", "rempotage": "2 ans", "engrais": "1 arrosage sur 2", "substrats": "Ecorces fines"}',
 'ada_aurantiaca'),

('fiche-aerangis_articulata', 'species', 'AERANGIS ARTICULATA', './assets/images/orchids/aerangis_articulata.png',
 (SELECT id FROM categories_conseil WHERE label = 'Epiphyte' LIMIT 1),
 'AERANGIS ARTICULATA est une superbe orchidee monopodiale de Madagascar et des Comores.',
 '{"temperature": "20-28 C", "arrosage": "frequent", "hygrometrie": "75 %", "rempotage": "2 ans", "engrais": "1 arrosage sur 2", "substrats": "Liege ou panier"}',
 'aerangis_articulata'),

('fiche-aerangis_fastuosa', 'species', 'AERANGIS FASTUOSA', './assets/images/orchids/aerangis_fastuosa.png',
 (SELECT id FROM categories_conseil WHERE label = 'Epiphyte' LIMIT 1),
 'AERANGIS FASTUOSA est une miniature compacte de Madagascar produisant des fleurs geantes.',
 '{"temperature": "18-26 C", "arrosage": "frequent", "hygrometrie": "70 %", "rempotage": "3 ans", "engrais": "1 arrosage sur 2", "substrats": "Sphaigne + liege"}',
 'aerangis_fastuosa'),

('fiche-aerangis_kirkii', 'species', 'AERANGIS KIRKII', './assets/images/orchids/aerangis_kirkii.png',
 (SELECT id FROM categories_conseil WHERE label = 'Epiphyte' LIMIT 1),
 'AERANGIS KIRKII est une miniature africaine de Kenya et Tanzanie.',
 '{"temperature": "20-28 C", "arrosage": "frequent", "hygrometrie": "75 %", "rempotage": "2 ans", "engrais": "dilue", "substrats": "Liege"}',
 'aerangis_kirkii'),

('fiche-angraecum_sesquipedale', 'species', 'ANGRAECUM SESQUIPEDALE', './assets/images/orchids/angraecum_sesquipedale.png',
 (SELECT id FROM categories_conseil WHERE label = 'Epiphyte' LIMIT 1),
 'ANGRAECUM SESQUIPEDALE est la celebre orchidee de Darwin de Madagascar.',
 '{"temperature": "18-28 C", "arrosage": "regulier", "hygrometrie": "70 %", "rempotage": "3 ans", "engrais": "mensuel", "substrats": "Ecorces grossieres"}',
 'angraecum_sesquipedale'),

('fiche-vanilla_planifolia', 'species', 'VANILLA PLANIFOLIA', './assets/images/orchids/vanilla_planifolia.jpg',
 (SELECT id FROM categories_conseil WHERE label = 'Hemiepiphyte / Grimpante' LIMIT 1),
 'VANILLA PLANIFOLIA est l orchidee grimpante tropicale dont sont issues les gousses de vanille.',
 '{"temperature": "22-30 C", "arrosage": "abondant", "hygrometrie": "80 %", "rempotage": "2 ans", "engrais": "mensuel", "substrats": "Humus + ecorces"}',
 'vanilla_planifolia'),

('fiche-anguloa_clowesii', 'species', 'ANGULOA CLOWESII', './assets/images/orchids/anguloa_clowesii.png',
 (SELECT id FROM categories_conseil WHERE label = 'Terrestre / Lithophyte' LIMIT 1),
 'ANGULOA CLOWESII est la spectaculaire orchidee tulipe jaune des Andes.',
 '{"temperature": "10-22 C", "arrosage": "abondant en croissance", "hygrometrie": "65 %", "rempotage": "2 ans", "engrais": "mars a septembre", "substrats": "Compost + perlite"}',
 'anguloa_clowesii'),

('fiche-bletilla_ochracea', 'species', 'BLETILLA OCHRACEA', './assets/images/orchids/bletilla_ochracea.png',
 (SELECT id FROM categories_conseil WHERE label = 'Terrestre' LIMIT 1),
 'BLETILLA OCHRACEA est une orchidee terrestre rustique de plein jardin, originaire de Chine.',
 '{"temperature": "-10 / +30 C", "arrosage": "regulier", "hygrometrie": "50 %", "rempotage": "3 ans", "engrais": "printemps", "substrats": "Terreau drainant"}',
 'bletilla_ochracea')

ON DUPLICATE KEY UPDATE
    content      = VALUES(content),
    care_cards   = VALUES(care_cards),
    orchid_id    = VALUES(orchid_id),
    id_categorie = VALUES(id_categorie);

-- Verification post-seed (MySQL/MariaDB)
SELECT CONCAT('SEED 03 OK : ',
       SUM(type = 'category'), ' categories + ',
       SUM(type = 'species'),  ' fiches espece en base') AS seed_check
  FROM conseils;
