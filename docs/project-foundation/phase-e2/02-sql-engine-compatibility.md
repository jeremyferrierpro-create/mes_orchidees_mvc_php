# 02 — Dépendances SQL propres au moteur (MySQL vs PostgreSQL)

Objectif : établir, **preuve à l'appui**, ce qui lie le projet à MySQL/MariaDB
et ce qui empêcherait/complexifierait PostgreSQL. Règle méthodologique rappelée
par la mission : **ne jamais déclarer une compatibilité PostgreSQL à la seule
présence de PDO ou d'un schéma importable.**

## 1. Le code applicatif est MySQL-exclusif par conception

Les en-têtes des repositories le documentent explicitement :
- `src/Repository/OrchidRepository.php` L6-7 : « … PostgreSQL/Neon : `public.`,
  `to_tsquery`, `ILIKE`). Ici, tout est réécrit pour **MySQL 8** … ».
- `src/Repository/ConseilRepository.php` L6-7 : idem.
- `config/db.php` L5-7 : « Cible MySQL actée en Phase 01/02 (décision D3), en
  **remplacement** de l'ancienne connexion PostgreSQL/Neon. »

**VERIFIED** : la migration PG→MySQL a été faite dans le code ; il ne reste pas
de chemin PostgreSQL dans les repositories.

## 2. Tableau des dépendances au moteur

| # | Fonctionnalité | Fichier / requête | Moteur requis | Équivalent PostgreSQL | Impact | Preuve |
|---|----------------|-------------------|---------------|-----------------------|--------|--------|
| 1 | Recherche plein-texte `MATCH(...) AGAINST(... IN NATURAL LANGUAGE MODE)` | `OrchidRepository::search()` L173,176 ; `ConseilRepository::search()` L97,100 | MySQL/MariaDB | `to_tsvector/ts_rank` + index **GIN** (déjà écrit dans les anciens schémas) | **ÉLEVÉ** (logique métier de recherche) | VERIFIED |
| 2 | Index `FULLTEXT` | `schema_mysql.sql` L110 `FULLTEXT KEY ft_orchids_search` ; migrations reconstruisant FULLTEXT (`20261018_lookup_…sql` L21) | MySQL/MariaDB | `CREATE INDEX … USING GIN (tsvector)` | **ÉLEVÉ** | VERIFIED |
| 3 | Upsert `INSERT … ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)` | `CategorieConseilRepository.php` L81 | MySQL/MariaDB | `INSERT … ON CONFLICT (label) DO UPDATE … RETURNING id` | **ÉLEVÉ** | VERIFIED |
| 4 | Types `ENUM(...)` | `schema_mysql.sql` L49 (`role`), L96 (`moderation_status`) ; migration `20261018_gestion_rappels_soins.sql` L55 (`statut`) | MySQL | `CREATE TYPE … AS ENUM` ou `CHECK` | **MOYEN** | VERIFIED |
| 5 | Défaut UUID `id CHAR(36) NOT NULL DEFAULT (UUID())` | `schema_mysql.sql` L41 | MySQL **8.0.13+** (default d'expression) | `DEFAULT gen_random_uuid()` (uuid) | **MOYEN** (syntaxe DDL non portable) | VERIFIED |
| 6 | `AUTO_INCREMENT` (TINYINT/SMALLINT) | migration `20261018_lookup_…sql` L36,51 | MySQL | `SERIAL` / `GENERATED … IDENTITY` | **FAIBLE-MOYEN** | VERIFIED |
| 7 | DDL MySQL-specific : `ENGINE=InnoDB`, `DEFAULT CHARSET=utf8mb4`, `COLLATE=utf8mb4_unicode_ci`, `SET NAMES utf8mb4` | `schema_mysql.sql` L23-28,55-57 ; toutes migrations | MySQL/MariaDB | sans `ENGINE/CHARSET` (encodage = `client_encoding`) | **FAIBLE** mais bloque l'import DDL tel quel | VERIFIED |
| 8 | Génération d'UUID côté **PHP** pour les INSERT | `CollectionRepository::uuid()` L126 ; `NotificationRepository::uuid()` L310 | portable | portable | **NUL** (déjà moteur-agnostique) | VERIFIED |
| 9 | `NOW()`, comparaisons de dates, `LIMIT n` | `NotificationRepository.php` L213,231 ; nombreux repositories | portable (les 2) | portable | **NUL** | VERIFIED |
| 10 | Clés étrangères `ON DELETE CASCADE`, transactions | `schema_mysql.sql` (FK), repositories | portable | portable | **NUL** | INFERRED (syntaxe standard) |

> Cas n°8 est important : même si le DDL utilise `DEFAULT (UUID())`, le code ne
> **s'appuie pas** dessus à l'insertion (il génère l'UUID en PHP) — commentaire
> `NotificationRepository.php` L12 : « le `DEFAULT (UUID())` de MySQL n'est pas
> récupérable pour une colonne CHAR ». Donc la dépendance n°5 est surtout un
> problème **d'import du schéma**, pas de fonctionnement applicatif.

## 3. Compatibilité PostgreSQL réelle : verdict

- **Au niveau code** : NON. Requêtes n°1, n°2, n°3 sont incompatibles telles
  quelles ; il faudrait un **dialecte SQL** différent pour au moins 3 fichiers.
- **Au niveau environnement** : NON plus. `pdo_pgsql` **non installé**
  (`getAvailableDrivers()` = `mysql,sqlite`) et aucun serveur PG (port :5432
  fermé). **VERIFIED** (doc 01).
- **Schémas PG existants** : `data/schema_neon.sql` et `data/schema_supabase.sql`
  sont bien du PostgreSQL (`gen_random_uuid()`, `TSVECTOR`,
  `to_tsvector('french', …)`, RLS/`POLICY`). Mais ils sont **OBSOLÈTES** : ils
  **ne contiennent pas** les évolutions des rappels/soins ajoutées par les
  migrations `2026-10-18` (pas de `statut` ENUM des notifications, pas de
  `types_soin`, `categories_conseil`, `id_type_soin`, `rappel_actif`). **VERIFIED**
  par grep ciblé. Ils datent d'avant la Phase B/C et ne sont **référencés par
  aucun code actif**.

**Conclusion (VERIFIED)** : il n'existe **aucune compatibilité PostgreSQL
fonctionnelle** aujourd'hui. Les fichiers `neon`/`supabase` sont des artefacts
historiques d'une architecture abandonnée, et non une double-source maintenue.

## 4. Ce qu'exigerait une vraie bascule PostgreSQL

Pour que l'app tourne sur PostgreSQL, il faudrait (chiffré en effort, pas fait ici) :
1. Installer/activer `pdo_pgsql` et viser un serveur PG.
2. Réécrire les 3 requêtes dépendantes (recherche fulltext × 2, upsert × 1).
3. Adopter **un** schéma PG à jour et écrire les migrations équivalentes
   (`ENUM`→type/CHECK, `FULLTEXT`→`tsvector`+GIN, `AUTO_INCREMENT`→`IDENTITY`,
   `DEFAULT (UUID())`→`gen_random_uuid()`), en repartant des anciens `neon`/
   `supabase` **désynchronisés** = travail de re-création.
4. Retirer/adaptater le DDL MySQL (`ENGINE`, `CHARSET`, `SET NAMES`).
5. **Recréer une suite de tests** spécifique PG (la suite HTTP actuelle suppose
   MariaDB ; groupe C1 notamment teste le comportement des rappels en SQL).

Estimation **INFERRED** : plusieurs jours de réécriture + reprise des tests,
avec risque de régression sur la recherche et les rappels. **Non justifié** pour
un rendu DWWM (voir doc 04).
