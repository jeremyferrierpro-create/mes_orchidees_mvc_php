# 03 — Comparaison des architectures et compatibilité MySQL ↔ PostgreSQL/Neon

## 1. Écarts structurels version publiée vs MVC local

| Domaine | GitHub `origin/main` (legacy, déployable Render) | Local `main` + WT (MVC, jamais publié) | Preuve |
|---------|--------------------------------------------------|------------------------------------------|--------|
| Organisation | Pages PHP à plat à la racine + `api/` REST minimal + `includes/` | `public/` docroot, `src/` MVC (Controller/Entity/Repository/Service/Security), `templates/`, `lib/Mithridatem/Router` à attributs | `git ls-tree` × 2 — VERIFIED |
| Routage | Inclusion de fichiers + `.htaccess` racine | Front-controller `public/index.php`, router maison à attributs (`Route`, `RouteCollection`), `config/routes.php` | fichiers lus — VERIFIED |
| Accès aux données | `api/config/db.php` → `NEON_DSN` pgsql + SNI Neon | `config/db.php` → DSN `mysql:` en dur, 10 repositories PDO injectables (`__construct(?PDO)`) | VERIFIED |
| AuthN/AuthZ | JWT (`JWT_SECRET` en exemple dev), localStorage côté legacy le plus ancien | Sessions PHP natives, hachage mot de passe, `src/Security/SessionGrantChecker.php`, admin/rôles, isolation RG1 | VERIFIED + DOCUMENTED (phases) |
| CSRF | Non traité dans le legacy visible | Middleware/tokens CSRF (`CsrfException`, vérifié par tests HTTP) | VERIFIED (code tests + src) |
| Collection/soins | Modales JS + appels `api/` | Dashboard server-side, formulaire ajout, autocomplete espèces, soins avec lookup `type_soin`, isolation stricte par propriétaire | DOCUMENTED (phases 03/C1/D) + code |
| Rappels/notifications | Absents du schéma legacy | `RappelController/RappelService`, opt-in non destructif, notifications en base, calcul à la volée (pas de cron) | VERIFIED (fichiers non suivis) |
| Uploads | Inexistants (côté client seulement) | `src/Service/Upload.php` → `move_uploaded_file()` vers `public/assets/images/uploads/` (filesystem local) | VERIFIED |
| Schéma | `schema_neon.sql` / `schema_supabase.sql` (PostgreSQL, v1.0 « Phase 3 », **obsolètes — sans rappels/soins récents**) | `schema_mysql.sql` + 1 migration versionnée + **5 migrations 20261018 non commitées** + seeds MySQL | VERIFIED |
| Tests | Aucun | `tests/http/*` (suite B sans dépendance), `tests/js/*.test.mjs`, validations PHP | VERIFIED (fichiers) ; exécution = DOCUMENTED (phases 05/C1/D) |
| Build/deploy | Dockerfile `pdo_mysql + pdo_pgsql`, app à plat | Dockerfile `pdo_mysql` seul, docroot `public/`, composer dump-autoload, `.dockerignore` plus strict | VERIFIED |

## 2. Compatibilité PostgreSQL/Neon — évaluation concrète (pas « PDO suffit »)

Points de non-compatibilité **vérifiés dans le code** (comptage `Select-String`
sur `src/Repository`, `src/Service`, `data/schema_mysql.sql`, `data/migrations` :
**35 occurrences** de motifs MySQL-spécifiques) :

| # | Constat MySQL-only | Emplacement (exemples) | Équivalent PG requis | Coût de portage |
|---|--------------------|------------------------|----------------------|-----------------|
| 1 | `MATCH (cols) AGAINST (:q IN BOOLEAN MODE)` recherche plein texte | `src/Repository/OrchidRepository.php` L158/173/176 (+ Conseil) | `to_tsvector/tsquery` + index GIN | Élevé — sémantique de ranking différente, à re-tester |
| 2 | `FULLTEXT KEY ft_orchids_search (name, vernacular, short_desc, origin)` | `data/schema_mysql.sql` L110-111 | `CREATE INDEX … USING GIN (to_tsvector(...))` + colonnes générées | Élevé (DDL + triggers/dates virtuals) |
| 3 | `ON DUPLICATE KEY UPDATE` | repositories (upserts collections/rappels) | `INSERT … ON CONFLICT … DO UPDATE` (clé unique explicite nécessaire) | Moyen |
| 4 | `DEFAULT (UUID())` (MySQL 8) et/ou `AUTO_INCREMENT` | `schema_mysql.sql`, migrations | `gen_random_uuid()` / sequences `IDENTITY` | Moyen (types d'PK à harmoniser INT vs UUID) |
| 5 | `ENGINE=InnoDB`, `utf8mb4`, `CHARSET`, comments SQL | `schema_mysql.sql` L55/111 | Dialecte PG complet du schéma + des 6 migrations + seeds | Élevé (réécriture intégrale) |
| 6 | DSN `mysql:` assemblé en dur dans `config/db.php` (L65) | config | Pilote + fabrique `pgsql:` ; **`pdo_pgsql` retiré du Dockerfile** (décision D3) | Faible (code) mais contredit D3 |
| 7 | Types MySQL dans les tables (TINYINT, ENUM-like via lookup, DATETIME) | schema/migrations | smallint/enum→check ou domain, timestamptz | Moyen |

Contexte documentaire : les rapports E2 (doc 02) et E3 (doc 06) étaient déjà
parvenus aux mêmes conclusions (`DOCUMENTED`, revérifiées point par point ici
dans le code → `VERIFIED` pour les motifs 1-6). Les schémas
`schema_neon.sql`/`schema_supabase.sql` **ne couvrent pas** le périmètre
fonctionnel actuel (pas de rappels, types de soins, categories conseils,
opt-in) — importables tels quels ils produiraient une base **incohérente avec
le code MVC**. VERIFIED (diff de contenu des fichiers).

**Verdict de compatibilité** : le MVC local n'est **pas** exécutable sur
Neon/PostgreSQL sans une réécriture profonde (schéma + DDL + recherche +
upserts + seeds + tests). La seule présence de PDO ne garantit rien : les
35 marqueurs dialectaux l'interdisent. Niveau : `VERIFIED`.

## 3. Sens inverse : le legacy (version publiée) tourne-t-il sur MySQL ?

Non plus : `api/config/db.php` publié produit un DSN `pgsql:` avec
`sslmode=require;options=endpoint=…` (SNI Neon). Une base MySQL ne conviendrait
pas au code déployé. `VERIFIED` (git show). Les deux mondes sont **incompatibles au
niveau du dialecte SQL** ; toute stratégie « tout sur une base » impose un portage d'un
côté ou de l'autre.

## 4. Autres risques transverses relevés (sans correction, périmètre E4)

- **TLS PDO absent** côté MVC pour les bases distantes (`config/db.php` L69-73 :
  uniquement ERRMODE/FETCH_ASSOC/EMULATE_PREPARES). VERIFIED. (Ironie : le
  legacy le gérait pour Neon ; le MVC l'a perdu en passant à MySQL local.)
- **`DB_PASS` vs `DB_PASSWORD`** : bloquant de config déjà documenté (E3),
  reconfirmé §01-7. VERIFIED.
- **Migrations non embarquées** : `.dockerignore` exclut `data/migrations/` →
  le schéma d'une base distante doit être créé hors conteneur. VERIFIED.
- **Uploads sur filesystem éphémère** : `Upload.php` écrit dans le conteneur →
  perte au spin-down/redeploy ; pas de stockage objet. VERIFIED (code) +
  DOCUMENTED (doc Render free, E3).
- **Santé applicative** : aucun endpoint health testant la base ; PDO paresseux
  (page affichée ≠ base connectée). DOCUMENTED (E3 §4/§8), cohérent avec le code relu.

## 5. Conséquence pour la soutenance

Le jury DWWM évaluera le **MVC local** (organisation, routes, repositories,
vues, tests). La version GitHub/Render (legacy à plat + Neon) n'est **pas
présentable** comme le travail MVC : elle contredit frontalement l'architecture
demandée. Toute stratégie de soutenance doit donc reposer sur le code local,
la question étant uniquement **où et comment l'exécuter de façon stable**
(voir doc 04).
