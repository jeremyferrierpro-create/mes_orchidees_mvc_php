# 04 — Comparaison des architectures et recommandation

Trois options confrontées. Les coûts sont en **effort/risque** (le budget financier
cible est 0 €/mois). Les faits proviennent des docs 01-03.

## Option A — MySQL/MariaDB local + MySQL en hébergement

Le projet reste **mono-moteur MySQL/MariaDB** partout (dev, tests, prod/démo).

- **Modifs code** : **aucune** pour l'existant ; uniquement la config de
  connexion via `.env` (et l'alignement `DB_PASS`/`DB_PASSWORD` recommandé).
- **Migrations** : inchangées (déjà MySQL).
- **Maintenance / risque régression** : **minimal** (un seul dialecte).
- **Tests HTTP** : directement réutilisables (déjà `mysql:` + `mes_orchidees_test`).
- **Recherche** : FULLTEXT/MATCH natifs conservés.
- **Complexité config** : faible (hôte mutualisé MySQL standard).
- **Coût mensuel** : **0 €** (GoogieHost ou équivalent MySQL).
- **Démo jury** : simple — le local XAMPP et l'hébergement ont **le même moteur**.

## Option B — MySQL/MariaDB local + PostgreSQL/Neon en ligne

Local en MySQL, en ligne sur **Neon/Supabase (PostgreSQL gratuit)**.

- **Modifs code** : **lourdes**. Il faut donner à l'app un **chemin PostgreSQL**
  qui n'existe pas : `DB_DSN` pgsql, installation `pdo_pgsql` chez l'hébergeur,
  et surtout réécrire les 3 dépendances incompatibles (doc 02 §2 n°1-3).
- **Migrations** : **à réécrire** intégralement pour PG, en repartant de schémas
  `neon`/`supabase` **obsolètes** (ils n'ont pas les rappels/soins récents) →
  re-création + remise à niveau.
- **Maintenance / risque** : **élevé** — deux dialectes à faire coexister (local
  MariaDB ≠ prod PG), toute nouvelle requête doit être compatible PG alors que le
  dev/test est en MySQL : source de bugs silencieusement non testés.
- **Tests HTTP** : **cassés** (pensés MariaDB) → suite PG à créer.
- **Recherche** : comportement FULLTEXT ≠ `tsvector` → résultats/ scoring différents.
- **Complexité config** : élevée (drivers, DSN, schémas distincts).
- **Coût mensuel** : 0 € possible (Neon/Supabase free) **mais** coût de dev fort.
- **Démo jury** : **fragile** — risque de comportement différent local ↔ en ligne.

> **Piège méthodologique écarté** : la mission interdit de conclure à une
> compatibilité PG au vu de PDO ou d'un schéma importable. Ici, la preuve est
> l'**inverse** : le code active des requêtes MySQL-only et `pdo_pgsql` est absent.

## Option C — Compatibilité applicative explicite MySQL + PostgreSQL

L'app supporte **officiellement les deux** moteurs, choisis par configuration.

Une simple sélection de pilote **ne suffit PAS** (la preuve : doc 02). Le strict
minimum pour du vrai multi-moteur serait :

1. **Sélection explicite par configuration** (une clé `DB_DRIVER ∈ {mysql, pgsql}`),
   **validée au démarrage**, comportement **fermé** (exception) si valeur inconnue.
   → La mission proscrit toute **détection automatique par essais de connexion
   ambigus** : on ne la propose pas.
2. **Adaptateurs / dialectes SQL** : une couche qui produit `MATCH…AGAINST` vs
   `ts_rank(to_tsvector…)` et `ON DUPLICATE KEY` vs `ON CONFLICT…RETURNING`.
   Aujourd'hui le SQL est **en dur dans les repositories** → refactor profond.
3. **Migrations doubles** : maintenir `schema_mysql` **et** un `schema_pg` à jour
   (les fichiers PG actuels ne le sont pas).
4. **Suite de tests par moteur** : CI exécutant la suite HTTP contre MariaDB **et**
   PostgreSQL pour prouver la parité.

- **Coût/risque** : le plus élevé des trois ; sur-ingénierie pour un projet DWWM.
- **Bénéfice pour le jury** : quasi nul (le jury évalue le site, pas le support multi-BDD).

## Tableau de synthèse

| Critère | A (MySQL/MySQL) | B (MySQL→PG en ligne) | C (multi-moteur officiel) |
|---------|:---:|:---:|:---:|
| Modifs de code applicatif | **aucune** | lourdes | très lourdes |
| Migrations à réécrire | 0 | toutes | doubles, à créer + maintenir |
| Tests HTTP réutilisables | **oui** | non (suite PG à créer) | non (2 suites) |
| Recherche identique local/prod | **oui** | non | oui si adapté (complexe) |
| Complexité de config | faible | élevée | très élevée |
| Risque de régression | **minimal** | élevé | élevé |
| Coût €/mois | **0 €** | 0 € (coût dev caché) | 0 € (coût dev max) |
| Facilité démo jury | **maximale** | moyenne | faible |

## Recommandation

> **Option A — mono-moteur MySQL/MariaDB**, dev + tests + hébergement sur le même
> moteur.

Justification (lisible par un débutant) :
- C'est **déjà** ce que fait le projet (VERIFIED) ; on ne casse rien.
- **0 €** et **0 réécriture** : le chemin le plus court vers une démo en ligne fiable.
- Local et production partagent le **même SQL** → pas de surprise de comportement
  (recherche, rappels) devant le jury.
- PostgreSQL/Neon n'apporte **aucun bénéfice** pour ce rendu ; il n'est justifié
  que si l'on veut *démontrer* une compétence multi-BDD, ce qui relève d'un
  **autre** sujet (et de l'option C, à réserver à un exercice dédié).

**Si** un jour le multi-moteur devient un objectif pédagogique assumé, retenir
l'option **C** **avec sélection explicite par configuration validée au démarrage**
(et jamais par détection auto de connexion), suite de tests par moteur obligatoire.
