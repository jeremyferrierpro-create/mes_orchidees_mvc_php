# 02 — Comparaison des scénarios d'architecture de déploiement

Contraintes rappelées : **0 €/mois**, base **persistante**, **pas de perte de
données**, **pas de réécriture** (l'audit ne migre pas le code), app **MySQL-only**
(VERIFIED doc 01 + Phase E2).

---

## Scénario A — Render (app) + MySQL/MariaDB externe

**Topologie** : l'app PHP tourne sur un **Render Web Service free** (via le
`Dockerfile` existant) ; la base est un **service MySQL distant gratuit**.

- **App sur Render free** : le Docker est explicitement conçu pour Render
  (`Dockerfile` L2, `.env.production.example`). Faisable. **VERIFIED**.
- **Base distante MySQL gratuite** : Render **ne fournit pas** de MySQL (que du
  Postgres — DOCUMENTED). Il faut un tiers. Candidat **confirmé en doc officielle**
  : **TiDB Cloud Starter** (MySQL-compatible, free, 5 GiB/ligne, sans CB) — voir
  `03-`. Les autres (PlanetScale) **ont retiré** leur free tier (2024). **DOCUMENTED**.
- **TLS** : les MySQL managés exigent généralement le TLS. Le code **ne configure
  aucune option SSL PDO** (doc 01 §4). → **Bloqueur** : il faudrait ajouter
  `PDO::MYSQL_ATTR_SSL_*` (hors périmètre de l'audit). **VERIFIED (absence)**.
- **Compatibilité SQL** : TiDB est MySQL-compatible mais **pas identique** :
  `FULLTEXT`/`MATCH…AGAINST`, `DEFAULT (UUID())`, `ON DUPLICATE KEY` doivent être
  **validés par import réel** → **NOT VERIFIED** (impossible sans compte).
- **Accès distant / latence** : TCP public + região potentiellement éloignée →
  latence ajoutée. Render peut **suspendre** un free service qui « initie un
  volume anormalement élevé de trafic » (accès BDD externe = ce cas) — faible pour
  une démo jury, mais à connaître. **DOCUMENTED**.
- **Uploads** : **toujours éphémères** sur Render free (pas de disque persistant
  gratuit) → les photos envoyées **disparaissent** au spin-down/redéploiement.
  **DOCUMENTED + VERIFIED** (doc 01 §6).
- **Coût réel** : 0 € **tant que** les quotas free ne sont pas dépassés ; sinon
  passage payant Render (base externe TiDB payante au-delà du free).

> **Classement : POSSIBLE WITH CONDITIONS.** Conditions : (1) config TLS PDO,
> (2) validation import schéma sur le MySQL distant choisi, (3) accepter ou
> résoudre la perte des uploads, (4) accepter le spin-down ~1 min.

---

## Scénario B — Render + PostgreSQL/Neon

**Topologie** : app Render + base **PostgreSQL** (Render Postgres ou Neon).

- **Render Postgres free expire au bout de 30 jours** (puis suppression après
  grace 14 j), **sans backup** → **inadapté** à une démo persistante. **DOCUMENTED**.
- **Neon free** : persiste (scale-to-zero, ~0.5 GB/100 CU-h — DOCUMENTED `03-`),
  mais c'est du **PostgreSQL**.
- **Le code actuel est incompatible PostgreSQL sans réécriture** (Phase E2) :
  `MATCH…AGAINST`+FULLTEXT (×2 repositories), `ON DUPLICATE KEY … LAST_INSERT_ID`,
  `ENUM`, `DEFAULT (UUID())`, DDL `ENGINE/CHARSET`. Plus **`pdo_pgsql` absent**
  localement (Dockerfile ne l'installe pas). Les `schema_neon/supabase.sql` sont
  **obsolètes** et ne prouvent rien.
  → **Une migration complète (code + schéma + tests)** est requise, ce que la
  phase **interdit** d'implémenter et qui contredit le « pas de réécriture ».
- **Coût** : 0 € possible (Neon free) mais **coût de dev** élevé + risque régression.

> **Classement : NOT RECOMMENDED** pour ce rendu (exige une migration de code
> lourde pour un bénéfice nul devant le jury).

---

## Scénario C — Render + MySQL/MariaDB conteneur + disque persistant

**Topologie** : faire tourner MySQL **dans** un service Render avec un **disque
persistant** monté (persistance des données ET des uploads).

- **Techniquement** Render autorise des conteneurs + **persistent disks**, **mais
  les disques persistants sont réservées aux services PAYANTS** : « _Paid_ services
  can preserve local filesystem changes by attaching a persistent disk, but **Free
  web services cannot** ». **DOCUMENTED** (Render /docs/free).
- Le free tier n'offre **ni** disque persistant, **ni** SSH, **ni** scaling, **ni**
  one-off jobs → impossible d'exécuter MySQL stateful gratuitement de façon fiable.
- **Coût** : payant (web service payant ≥ ~$7-10/mo + disque $0.25/Go/mo) → hors
  budget 0 €.

> **Classement : BLOCKED** (au budget 0 €). Ne sera pas implémenté ici.

---

## Scénario D — Autre hébergement PHP/MySQL gratuit (candidat : GoogieHost)

**Topologie** : un **seul** hébergeur mutualisé gratuit sert **l'app + la base
MySQL**, sur **disque persistant** (comportement classique d'un mutualisé).

- **Avantage décisif** : le filesystem d'un mutualisé est **persistant** → les
  **uploads survivent** (contrairement à Render free). La base MySQL est une
  vraie instance du fournisseur. **INFERRED** (nature des mutualisés) + à confirmer
  sur le compte.
- **PHP** : GoogieHost annonce PHP **7.4 → 8.3** (couvre `>=8.1`). **DOCUMENTED**
  (Phase E2). Extensions `pdo_mysql`/`mbstring`/`fileinfo` : **à vérifier** dans le
  panneau (presque toujours présentes sur un host MySQL). **NOT VERIFIED**.
- **Moteur MySQL** : version exacte du compte **à vérifier** ; `DEFAULT (UUID())`
  et `FULLTEXT` exigent **MySQL ≥ 8.0.13** (ou MariaDB récent). Si le compte est
  en 5.7, adapter le DDL. **NOT VERIFIED**.
- **Routage MVC / docroot** : `.htaccess`/LiteSpeed OK, **mais** le webroot est
  `/public` alors que le docroot mutualisé est `public_html` → procédure de
  restructuration FTP (docroot vers `/public`, ou déplacement — Phase E2 §3).
- **HTTPS/secrets** : free SSL fourni ; `.env` posé **hors** docroot (le code lit
  `.env` racine, `env.php` L48). **VERIFIED (mécanisme)**.
- **Limites/policy** : ~1 GB, 1 site, **risque de suspension pour inactivité** →
  tester la veille. **DOCUMENTED** (Phase E2).

> **Classement : RECOMMENDED (hors Render)** comme solution globale 0 € avec
> persistance des uploads et d'une vraie base MySQL sans réécriture.

---

## Tableau de départage

| Critère | A Render+MySQL ext. | B Render+PG/Neon | C Render MySQL+disque | D Mutualisé PHP/MySQL |
|---|:--:|:--:|:--:|:--:|
| 0 €/mois tenable | oui (quotas) | oui (Neon) | **non** | oui |
| Persistance **uploads** | **non** (éphémère) | **non** | oui (si payant) | **oui** |
| Persistance **base** | oui (externe) | Render: 30 j / Neon: oui | oui (si payant) | oui |
| Réécriture de code | **aucune** | **lourde** | aucune | **aucune** |
| TLS à configurer | **oui** | oui | non (local) | non (local) |
| Spin-down/cold start | **oui (~1 min)** | oui (app) | non | non |
| Adapté au jury | fragile | non | hors budget | **le plus direct** |

## Conclusion de l'étape B

Pour les **besoins réels du jury** (URL en ligne, base persistante, uploads qui
restent, 0 €, **sans** réécrire le code), le **scénario D** est le plus cohérent.
Render n'est pertinent **gratuitement** que pour l'app **sans** persistance locale
(scénario A) — ce qui casse la fonctionnalité d'upload. **Si** l'exigence « Render »
est impérative, il faudra soit **payer** (C/stockage objet), soit **migrer vers
PostgreSQL** (B), soit **accepter** que les uploads soient temporaires (A).
