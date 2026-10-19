# 03 — Prix, limites et sources officielles

Chaque information déterminante est étiquetée et sourcée. Dates de consultation :
**2026-10-18**. Séparation explicite : **coût app** vs **coût base** vs **stockage**
vs **réseau**.

## Render — Documentations officielles

**Source : https://render.com/docs/free (consultée 2026-10-18) — DOCUMENTED**

| Fait confirmé | Détail / limite |
|---------------|-----------------|
| Types de services gratuits | Web services, **Postgres**, Key Value, static sites. **Pas de MySQL.** |
| Free web service | compute `free` = **0.1 CPU / 512 MB RAM** (L244) |
| **Spin-down** | après **15 min** sans trafic ; redémarrage **~1 min** avec page de chargement (L57-61) |
| **Filesystem éphémère** | uploads/SQLite locaux **perdus** à chaque redeploy/restart/spin-down (L65-67) |
| **Persistant disk** | réservé aux services **PAYANTS** : « Free web services **cannot** » (L71, L133) |
| Autres limites free | pas de SSH, pas de scaling, pas d'edge caching, pas de one-off jobs (L131-136) |
| **750 h free/mois** | dépassement = **suspension** des free web services jusqu'au mois suivant (L79-83) |
| **Service-initiated traffic** | Render **peut suspendre** un free service qui initie beaucoup de trafic (ex. **accès à une base externe**) (L103-113) |
| **Free Postgres** | expire **30 jours** après création ; grace 14 j puis **suppression** ; 1 GB ; **pas de backup** ; 1/workspace (L143-168) |

**Source : https://render.com/pricing (consultée 2026-10-18) — DOCUMENTED**
- **Persistent disks : $0,25 / GB / mois** (facturé à la seconde). → coût stockage.
- Les bases managées Render (Postgres) au-delà du free sont **payantes**.

**Source (secondary) : https://render.com/articles/platforms-with-a-real-free-tier-for-developers-in-2026** — confirme 15-min spin-down. Changements 2026 rapportés par des tiers (bandeau 5 GB, DB 30 j) : cohérents avec la doc officielle → retenus **DOCUMENTED** côté officiel pour le spin-down et l'expiration 30 j.

**Coûts Render récapitulés**
- App free : **0 €** (limites ci-dessus). App fiable sans spin-down/disque : **payant** (≥ ~$7/mo).
- Base MySQL Render : **inexistante** (il faut un tiers, gratuit ou payant).
- Disque persistant : **payant** ($0,25/GB/mo).

---

## MySQL/MariaDB externes gratuits

**TiDB Cloud Starter (MySQL-compatible) — Source : https://docs.pingcap.com/tidbcloud/select-cluster-tier/ et https://www.pingcap.com/pricing/ (consultées 2026-10-18) — DOCUMENTED**
- Free : **5 GiB de stockage ligne** + 5 GiB columnar + **50 M Request Units/mois**,
  jusqu'à 5 instances éligibles, **sans carte bancaire**.
- **Limites** : quota RU mensuel ; compatibilité MySQL **mais pas identité** —
  `FULLTEXT`/`MATCH…AGAINST`, `DEFAULT (UUID())`, `ON DUPLICATE KEY` **à valider
  par import réel** → **NOT VERIFIED**.
- **TLS** exigé en usage normal → nécessite config PDO SSL (absente, doc 01). **BLOCKED (code)**.

**PlanetScale — Source : https://planetscale.com/docs/plans/hobby-plan-deprecation-faq (2026-10-18) — DOCUMENTED**
- **Free « Hobby » tier supprimé** (bascule payante exigée depuis avril 2024). → **non retenu** comme 0 €.

**Aiven free / autres** — plusieurs annuaires (cloudinsight 2025-12) listent « Aiven free MySQL ». **Non confirmé ici par la doc officielle Aiven consultée** → **NOT VERIFIED** (à vérifier sur https://aiven.io si retenu).

---

## PostgreSQL / Neon (scénario B, pour mémoire)

**Source : https://neon.com/pricing et https://neon.com/docs/introduction/plans (consultées 2026-10-18) — DOCUMENTED**
- Free plan **0 €/mois** : environ **0.5 GB stockage** + **100 CU-heures**/mois,
  **scale-to-zero** (le compute se suspend ; **les données persistent**).
- **Mais** : c'est du **PostgreSQL**. Le passage du code MySQL→PG est requis
  (doc 02 §B) → coût de **développement**, pas d'hébergement.

---

## Hébergement PHP/MySQL gratuit (scénario D)

**GoogieHost — Source : https://googiehost.com/freephphosting & /freehosting (consultées 2026-10-18) — DOCUMENTED (partiel)**
- PHP **7.4 → 8.3** (Multi-PHP), **MySQL**, **phpMyAdmin**, **free SSL**, panneau
  **DirectAdmin**, NVMe SSD, bandeau « unmetered bandwidth ».
- Free plan rapporté : **~1000 MB (1 GB)**, **1 site**, sous-domaine gratuit, sans CB
  (sources secondaires concordantes — digitalprahlad, hostphobia). 
- **À vérifier dans le compte (NOT VERIFIED)** : **version MySQL exacte** (≥ 8.0.13
  requis pour `DEFAULT (UUID())` + FULLTEXT), extensions PHP, possibilité de
  pointer le docroot sur `/public`, politique de **suspension pour inactivité**,
  nombre de bases.

**Coût scénario D : 0 €** (app + base + uploads sur le même disque persistant).

---

## Ce qui reste à vérifier dans un compte réel (récapitulatif)

| Élément | Scénario | Statut |
|---------|----------|--------|
| Version MySQL exacte (≥8.0.13 ?) | A (TiDB) / D (GoogieHost) | **NOT VERIFIED** |
| Support FULLTEXT + `ON DUPLICATE KEY` sur TiDB Starter | A | **NOT VERIFIED** |
| TLS obligatoire + CA à fournir | A | **NOT VERIFIED** (et code sans SSL) |
| Accès au docroot `/public` sur mutualisé | D | **NOT VERIFIED** |
| Seuil réel de suspension « service-initiated traffic » | A | **NOT VERIFIED** |

> Conformément à la consigne, **aucun fournisseur gratuit n'est affirmé sans doc
> officielle** : TiDB (PingCAP) et PlanetScale (dépréciation) sont **DOCUMENTED** ;
> Aiven et les chiffres secondaires GoogieHost sont marqués **NOT VERIFIED** tant
> que la source officielle n'a pas été consultée dans le compte.
