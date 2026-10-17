# 02 — Exigences base MySQL/MariaDB et comparaison de fournisseurs (Phase 06)

## 1. Exigences techniques réelles (déduites du code/schéma, VERIFIED)

| Exigence | Source dans le dépôt | Conséquence fournisseur |
|---|---|---|
| Moteur MySQL 8 **ou** MariaDB ≥ 10.4 | `config/db.php` (`mysql:` DSN) ; testée MariaDB 10.4.32 | PostgreSQL/Neon/Supabase **non compatibles** |
| **FULLTEXT InnoDB** + `MATCH … AGAINST (… IN NATURAL LANGUAGE MODE)` | `data/schema_mysql.sql` (`ft_orchids_search`) ; `OrchidRepository::search()` | Élimine les offres qui ignorent FULLTEXT |
| Fonction `UUID()` + `CHAR(36)` PK par défaut | `schema_mysql.sql` (users/… DEFAULT (UUID())) | MySQL 8 / MariaDB ≥ 10.2 |
| InnoDB, FK `ON DELETE CASCADE/SET NULL`, utf8mb4_unicode_ci | `schema_mysql.sql` | Moteurs transactionnels complets |
| Vraies préparations PDO (`EMULATE_PREPARES=false`) | `config/db.php` | Compatibilité protocole MySQL standard |
| Connectivité TCP 3306 depuis l'hébergeur d'app | conteneur distant | Liste blanche/sortie réseau à ouvrir |
| Sauvegardes/restauration | aucune dans le dépôt | À fournir par le prestataire |
| Volume | 7 tables, 21 orchidées seedées, données utilisateurs privées | Petit plan suffisant |

> Note de repli : `OrchidRepository::search()` prévoit un fallback `LIKE`, mais le
> FULLTEXT reste une exigence nominale (performance + sémantique validée en 04B/05).

## 2. Critères de sélection objectifs

C1 compatibilité FULLTEXT/`UUID()`/FK · C2 accès réseau depuis la plateforme d'app ·
C3 backups gérés + restauration testable · C4 TLS optionnel · C5 prix et limites ·
C6 localisation des données (RGPD — projet français) · C7 rotation des identifiants.

## 3. Comparaison (3 options ; faits sourcés, distingo hypothèses)

### 3.1 PlanetScale (Vitess / MySQL 8 managé)

| Critère | Éléments |
|---|---|
| Compatibilité | MySQL 8 via Vitess ; FULLTEXT : la documentation PlanetScale le couvre (« we could also use a MySQL FULLTEXT index », cours MySQL for Developers). **Point de vigilance (NOT VERIFIED)** : des outils de migration (ex. Prisma) ont eu des soucis de création d'index FULLTEXT (StackOverflow 2023) — ici l'import se fait par `schema_mysql.sql` direct, donc présumé non impacté (INFERRED), à reconfirmer par `SHOW INDEX` après import sur la base réelle. |
| Gratuité | **Non — le plan gratuit est supprimé** (« All databases require a paid subscription starting with our Base plan », planetscale.com/docs/planetscale-plans) — fait sourcé. |
| Réseau | Connexion MySQL standard + liste blanche d'IP; intégrations Vercel/Render documentées. |
| Backups | Sauvegardes continues / restauration par rebuild de branche (documenté). |
| Coûts | À partir du plan payant « Base » (montant exact à vérifier sur la page pricing — NON VÉRIFIE ici). |
| Risques/limites | Vitess introduit des restrictions de schéma (sharding non nécessaire ici = sans objet) ; coût minimal pour un projet étudiant. |

### 3.2 Railway — déploiement MySQL 8 / MariaDB « first-class »

| Critère | Éléments |
|---|---|
| Compatibilité | **Vraie instance MySQL 8 / MariaDB communautaire** (templates officiels `railway.com/deploy/mysql-8-or-any-version`, `…/mariadb-…`) → FULLTEXT/`UUID()`/FK natifs (VERIFIED par la nature communautaire du moteur). |
| Gratuité | Essai 30 j avec crédits 5 $, puis plan payant à l'usage (≈ 5 $/mois service + stockage, source : railway.com/pricing + page MariaDB « Typical cost: ~$5/month »). |
| Réseau | Base privée au réseau du projet, exposition externe possible ; si l'app Render y accède : ouverture réseau à configurer (à confirmer au provisionnement). |
| Backups | Snapshots volumes proposés — **volume exact de rétention à vérifier** (NON VÉRIFIÉ). |
| Bonus | Peut héberger **aussi** l'app (plateforme unique) → simplifie D2. |
| Risques | Projet perso hébergé sur plateforme à crédits ; facturation à l'usage à surveiller. |

### 3.3 TiDB Serverless (PingCAP) — **ÉCARTÉ**

| Critère | Éléments |
|---|---|
| Compatibilité | Protocole MySQL 8-compatible **MAIS** : la doc officielle « MySQL Compatibility » indique que les **index FULLTEXT ne sont pas pris en charge et sont « parsed and ignored »** (docs.pingcap.com/tidb/stable/mysql-compatibility) — fait sourcé, décisif. |
| Conséquence | `ft_orchids_search` serait silencieusement absent ; `MATCH … AGAINST` échouerait ou retomberait sur le fallback LIKE avec une autre sémantique. |
| Verdict | **Incompatible avec l'exigence C1 → écarté** pour MES ORCHIDÉES (souvent recommandé comme « alternative Render » dans les guides généraux, mais inadapté ici). |

### 3.4 Mentions non instruites en détail

Offres **UE** (résidence des données) : Aiven for MySQL/MariaDB, OVHcloud/Scaleway
bases managées, VPS auto-hébergé. Non comparées faute de temps cette phase —
pertinentes pour le critère C6 (RGPD). À instruire si le humain privilégie la
localisation UE sur la simplicité.

## 4. Recommandation technique (non définitive — décision humaine)

- **Recommandation principale : Railway MySQL 8 ou MariaDB** (C1 natif vérifié par la
  nature du moteur, coût plancher le plus bas, backups, et option d'unifier app+base).
- Alternative si le propriétaire veut un service « database-only » réputé : PlanetScale
  (plan payant minimal).
- **À éviter : TiDB** (FULLTEXT ignoré — preuve documentaire).
- **Incompatibles : Neon/Supabase/PostgreSQL Render** (moteur différent du code actuel).

## 5. Informations manquantes (BLOCKED / NON VÉRIFIÉ)

- Prix exacts actuels (pages pricing non chiffrées ici) et quotas de connexions.
- Latence mesurée hébergeur-app ↔ base (dépend du provisionnement).
- TLS imposé/optionnel par compte chez chaque fournisseur.
- Politique de rétention des backups Railway.
