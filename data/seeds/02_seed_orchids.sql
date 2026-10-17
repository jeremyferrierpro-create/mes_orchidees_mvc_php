-- ===========================================================================
-- SEED 02 — TABLE orchids
-- ===========================================================================
-- Ordre de migration : 2eme (users doit exister avant)
-- Source : /assets/js/data/orchids.json (21 especes)
-- BDD : MySQL 8 / MariaDB
-- Schema : schema_mysql.sql
--
-- Note : le champ "order" du JSON devient "botanical_order" en SQL
-- car ORDER est un mot reserve SQL.
-- La recherche plein-texte repose sur l index FULLTEXT ft_orchids_search.
-- ===========================================================================

INSERT INTO orchids (id, name, vernacular, botanical_order, species, genre, family, subfamily, tribu, subtribu, behavior, discovered, origin, img, short_desc, long_desc, is_published)
VALUES
('acacalis_cyanea', 'ACACALIS CYANEA', 'AGANISIA BLEUE', 'Asparagales', 'Cyanea', 'Acacalis', 'Orchidaceae', 'Epidendroideae', 'Cymbidieae', 'Zygopetalinae', 'Epiphyte', 'John Lindley (1839)', 'Amerique du Sud (Bassin de l Amazone)', './assets/images/orchids/acacalis_cyanea.png', 'Rare orchidee epiphyte tropicale reputee pour ses teintes bleu-violace uniques.', 'Acacalis cyanea demande une forte humidite ambiante (80%+), des temperatures chaudes et une lumiere tamisee de sous-bois.', TRUE),

('acineta_barkerii', 'ACINETA BARKERII', 'ACINETA DE BARKER', 'Asparagales', 'Barkerii', 'Acineta', 'Orchidaceae', 'Epidendroideae', 'Cymbidieae', 'Stanhopeinae', 'Epiphyte', 'John Lindley (1843)', 'Mexique, Amerique Centrale', './assets/images/orchids/acineta_barkerii.png', 'Orchidee spectaculaire produisant de lourdes grappes pendantes de fleurs jaunes cireuses.', 'Acineta barkerii se cultive imperativement en panier suspendu car ses hampes florales traversent le substrat vers le bas.', TRUE),

('ada_aurantiaca', 'ADA AURANTIACA', 'ADA ORANGEE', 'Asparagales', 'Aurantiaca', 'Ada', 'Orchidaceae', 'Epidendroideae', 'Cymbidieae', 'Oncidiinae', 'Epiphyte', 'John Lindley (1854)', 'Andes (Colombie, Venezuela)', './assets/images/orchids/ada_aurantiaca.png', 'Orchidee d altitude aux fleurs etoilees d un orange vif et etincelant.', 'Ada aurantiaca pousse naturellement dans les forets de nuages fraiches et humides des Andes.', TRUE),

('aerangis_articulata', 'AERANGIS ARTICULATA', 'AERANGIS ARTICULE', 'Asparagales', 'Articulata', 'Aerangis', 'Orchidaceae', 'Epidendroideae', 'Vandeae', 'Aerangidinae', 'Epiphyte', 'Achille Richard (1841)', 'Madagascar, Comores', './assets/images/orchids/aerangis_articulata.png', 'Superbe orchidee monopodiale aux cascades de fleurs blanc de cire a long eperon.', 'Aerangis articulata developpe de magnifiques grappes pendantes tres parfumees la nuit.', TRUE),

('aerangis_fastuosa', 'AERANGIS FASTUOSA', 'AERANGIS FASTUEUX', 'Asparagales', 'Fastuosa', 'Aerangis', 'Orchidaceae', 'Epidendroideae', 'Vandeae', 'Aerangidinae', 'Epiphyte', 'Heinrich Gustav Reichenbach (1881)', 'Madagascar', './assets/images/orchids/aerangis_fastuosa.png', 'Miniature compacte produisant des fleurs geantes blanches extremement odorantes.', 'Aerangis fastuosa est une petite plante spectaculaire dont la fleur est souvent aussi grosse que le feuillage.', TRUE),

('aerangis_kirkii', 'AERANGIS KIRKII', 'AERANGIS DE KIRK', 'Asparagales', 'Kirkii', 'Aerangis', 'Orchidaceae', 'Epidendroideae', 'Vandeae', 'Aerangidinae', 'Epiphyte', 'Heinrich Gustav Reichenbach (1865)', 'Afrique de l Est (Kenya, Tanzanie)', './assets/images/orchids/aerangis_kirkii.png', 'Miniature africaine aux feuilles pincees et aux fleurs blanches etoilees raffinees.', 'Aerangis kirkii presente un feuillage tres caracteristique aux extremites bilobees.', TRUE),

('aerides_houlletiana', 'AERIDES HOULLETIANA', 'AERIDES DE HOULLET', 'Asparagales', 'Houlletiana', 'Aerides', 'Orchidaceae', 'Epidendroideae', 'Vandeae', 'Aeridinae', 'Epiphyte', 'Henri Victor Regnault (1872)', 'Asie du Sud-Est (Thailande, Viet Nam)', './assets/images/orchids/aerides_houlletiana.png', 'Orchidee monopodiale robuste aux grappes de fleurs odorantes piquetees de magenta.', 'Aerides houlletiana produit d epaisses racines aeriennes et de denses grappes de fleurs.', TRUE),

('aerides_odorata', 'AERIDES ODORATA', 'AERIDES ODORANT', 'Asparagales', 'Odorata', 'Aerides', 'Orchidaceae', 'Epidendroideae', 'Vandeae', 'Aeridinae', 'Epiphyte', 'Lour. (1790)', 'Asie tropicale et subtropicale', './assets/images/orchids/aerides_odorata.png', 'Grande orchidee tres populaire au parfum d agrumes puissant et envoûtant.', 'Aerides odorata est une plante vigoureuse produisant de nombreuses fleurs cireuses blanches et roses.', TRUE),

('angraecum_didierii', 'ANGRAECUM DIDIERII', 'ANGRAECUM DE DIDIER', 'Asparagales', 'Didierii', 'Angraecum', 'Orchidaceae', 'Epidendroideae', 'Vandeae', 'Angraecinae', 'Epiphyte', 'Henri Jumelle & Henri Perrier (1915)', 'Madagascar', './assets/images/orchids/angraecum_didierii.png', 'Miniature malgache florifere aux fleurs etoilees blanches et parfumees la nuit.', 'Angraecum didierii est une orchidee compacte tres appreciee pour ses fleurs disproportionnees.', TRUE),

('angraecum_eburneum', 'ANGRAECUM EBURNEUM', 'ANGRAECUM COULEUR D IVOIRE', 'Asparagales', 'Eburneum', 'Angraecum', 'Orchidaceae', 'Epidendroideae', 'Vandeae', 'Angraecinae', 'Epiphyte', 'Louis-Marie Aubert du Petit-Thouars (1822)', 'Madagascar, Mascareignes', './assets/images/orchids/angraecum_eburneum.png', 'Orchidee majestueuse et imposante aux fleurs inversees blanc ivoire et vertes.', 'Angraecum eburneum devient une grande plante aux feuilles coriaces en eventail.', TRUE),

('angraecum_sesquipedale', 'ANGRAECUM SESQUIPEDALE', 'ETOILE DE MADAGASCAR', 'Asparagales', 'Sesquipedale', 'Angraecum', 'Orchidaceae', 'Epidendroideae', 'Vandeae', 'Angraecinae', 'Epiphyte', 'Louis-Marie Aubert du Petit-Thouars (1822)', 'Madagascar', './assets/images/orchids/angraecum_sesquipedale.png', 'Celebre orchidee de Darwin possedant un eperon nectarifere spectaculaire de 30 cm.', 'Angraecum sesquipedale produit de grandes fleurs nocturnes etoilees blanches.', TRUE),

('anguloa_clowesii', 'ANGULOA CLOWESII', 'ORCHIDEE TULIPE JAUNE', 'Asparagales', 'Clowesii', 'Anguloa', 'Orchidaceae', 'Epidendroideae', 'Cymbidieae', 'Stanhopeinae', 'Terrestre / Lithophyte', 'John Lindley (1844)', 'Andes (Colombie, Venezuela)', './assets/images/orchids/anguloa_clowesii.png', 'Spectaculaire orchidee aux grandes fleurs globuleuses jaunes au parfum de eucalyptus.', 'Anguloa clowesii forme d imposants pseudobulbes et de grandes feuilles plissees.', TRUE),

('anguloa_virginalis', 'ANGULOA VIRGINALIS', 'ANGULOA VIRGINAL', 'Asparagales', 'Virginalis', 'Anguloa', 'Orchidaceae', 'Epidendroideae', 'Cymbidieae', 'Stanhopeinae', 'Terrestre / Lithophyte', 'John Lindley (1851)', 'Andes (Perou, Colombie, Equateur)', './assets/images/orchids/anguloa_virginalis.png', 'Fleur tulipe d un blanc virginal delicatement piquetee de rose a parfum de cannelle.', 'Anguloa virginalis pousse dans les forets montagneuses humides.', TRUE),

('ansellia_africana', 'ANSELLIA AFRICANA', 'ORCHIDEE LEOPARD', 'Asparagales', 'Africana', 'Ansellia', 'Orchidaceae', 'Epidendroideae', 'Cymbidieae', 'Cyrtopodiinae', 'Epiphyte', 'John Lindley (1844)', 'Afrique subsaharienne', './assets/images/orchids/ansellia_africana.png', 'Grande orchidee africaine aux fleurs jaunes abondamment tachetees de chocolat.', 'Ansellia africana developpe de longues cannes et un systeme racinaire en nid.', TRUE),

('ascocentrum_spp', 'ASCOCENTRUM SPP', 'ASCOCENTRUM', 'Asparagales', 'Spp', 'Ascocentrum', 'Orchidaceae', 'Epidendroideae', 'Vandeae', 'Aeridinae', 'Epiphyte', 'Carl Ludwig Blume (1825)', 'Asie du Sud-Est', './assets/images/orchids/ascocentrum_spp.png', 'Orchidee miniature aux denses epis floraux dresses aux couleurs orange ou rouge vifs.', 'Le genre Ascocentrum regroupe des petites plantes compactes aux floraisons eclatantes.', TRUE),

('aspasia_lunata', 'ASPASIA LUNATA', 'ASPASIA EN CROISSANT', 'Asparagales', 'Lunata', 'Aspasia', 'Orchidaceae', 'Epidendroideae', 'Cymbidieae', 'Oncidiinae', 'Epiphyte', 'John Lindley (1836)', 'Bresil', './assets/images/orchids/aspasia_lunata.png', 'Orchidee compacte aux fleurs etoilees vertes tachetees de brun avec labelle violet.', 'Aspasia lunata est originaire des forets tropicales humides du littoral bresilien.', TRUE),

('aspasia_principissa', 'ASPASIA PRINCIPISSA', 'ASPASIA PRINCESSE', 'Asparagales', 'Principissa', 'Aspasia', 'Orchidaceae', 'Epidendroideae', 'Cymbidieae', 'Oncidiinae', 'Epiphyte', 'Heinrich Gustav Reichenbach (1852)', 'Amerique Centrale (Panama, Costa Rica)', './assets/images/orchids/aspasia_principissa.png', 'Elegante espece aux grandes fleurs rayees de vert et de brun au labelle blanc/rose.', 'Aspasia principissa pousse dans les forets tropicales de basse altitude.', TRUE),

('barkeria_spectabilis', 'BARKERIA SPECTABILIS', 'BARKERIA REMARQUABLE', 'Asparagales', 'Spectabilis', 'Barkeria', 'Orchidaceae', 'Epidendroideae', 'Epidendreae', 'Laeliinae', 'Epiphyte', 'John Lindley (1842)', 'Amerique Centrale (Guatemala, Mexique)', './assets/images/orchids/barkeria_spectabilis.png', 'Orchidee aux magnifiques grappes de fleurs rose/lilas delicatement veinees.', 'Barkeria spectabilis pousse accrochee aux branches dans les forets de montagne ouvertes.', TRUE),

('bifrenaria_inodora', 'BIFRENARIA INODORA', 'BIFRENARIA SANS ODEUR', 'Asparagales', 'Inodora', 'Bifrenaria', 'Orchidaceae', 'Epidendroideae', 'Cymbidieae', 'Bifrenariinae', 'Epiphyte / Lithophyte', 'John Lindley (1843)', 'Bresil (Foret Atlantique)', './assets/images/orchids/bifrenaria_inodora.png', 'Orchidee robuste aux fleurs cireuses vert-jaunatre dotees d un labelle violet intense.', 'Bifrenaria inodora developpe de tetragones pseudobulbes tres durs.', TRUE),

('bletilla_ochracea', 'BLETILLA OCHRACEA', 'BLETILLA OCRE', 'Asparagales', 'Ochracea', 'Bletilla', 'Orchidaceae', 'Epidendroideae', 'Arethuseae', 'Coelogyninae', 'Terrestre', 'Miquel (1873)', 'Chine', './assets/images/orchids/bletilla_ochracea.png', 'Orchidee terrestre de jardin rustique aux delicates fleurs jaune ocre et labelle marbré.', 'Bletilla ochracea est une espece terrestre tres elegante et moyennement rustique (-10C).', TRUE),

('vanilla_planifolia', 'VANILLA PLANIFOLIA', 'VANILLE', 'Asparagales', 'Planifolia', 'Vanilla', 'Orchidaceae', 'Vanilloideae', 'Vanilleae', 'Vanillinae', 'Hemiepiphyte / Grimpante', 'J. W. Klotzsch (1841)', 'Mexique, Amerique Centrale', './assets/images/orchids/vanilla_planifolia.jpg', 'Orchidee grimpante tropicale celebre pour ses fruits dont est extraite la vanille.', 'Vanilla planifolia est une orchidee grimpante tropicale qui developpe de longues tiges charnues.', TRUE)

ON DUPLICATE KEY UPDATE
    name         = VALUES(name),
    vernacular   = VALUES(vernacular),
    is_published = VALUES(is_published);

-- Verification post-seed (MySQL/MariaDB) : la commande ci-dessous doit afficher 21.
SELECT CONCAT('SEED 02 OK : ', COUNT(*), ' orchidees en base') AS seed_check
  FROM orchids;
