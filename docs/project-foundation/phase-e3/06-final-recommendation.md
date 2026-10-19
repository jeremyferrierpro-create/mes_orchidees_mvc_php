# 06 — Recommandation finale, classement des options et questions à trancher

## 1. Classement des options (exigence : 0 €/mois, base persistante, pas de perte,
## pas de réécriture, code MySQL-only)

| Option | Classement | Justification (faits dépôt + sources) |
|--------|-----------|----------------------------------------|
| **D — Hébergeur PHP/MySQL gratuit (GoogieHost/équivalent)** | **RECOMMENDED** | App + base + **uploads persistants** sur un seul disque, **0 €**, **aucune réécriture** (MySQL ↔ MySQL). `Dockerfile`/`.htaccess` déjà prêts pour Apache/mod_rewrite. Limites (version MySQL, docroot `/public`, inactivité) = **à vérifier dans le compte** (NOT VERIFIED). |
| **A — Render free (app) + MySQL externe gratuit (TiDB Starter)** | **POSSIBLE WITH CONDITIONS** | App Render prête (Docker pensé pour Render) et TiDB free **DOCUMENTED**. Mais : **TLS PDO à configurer** (B2), **uploads éphémères** sur Render free (B3), **spin-down ~1 min**, et **compatibilité FULLTEXT/`ON DUPLICATE KEY`/`DEFAULT(UUID())` sur TiDB NOT VERIFIED**. |
| **C — Render + MySQL conteneur + disque persistant** | **BLOCKED** | « Free web services **cannot** » avoir un disque persistant (Render docs). Fiable = **payant** (web service + disque $0,25/Go/mo). Hors budget. |
| **B — Render + PostgreSQL/Neon** | **NOT RECOMMENDED** | Exige une **migration complète du code** (FULLTEXT→tsvector, `ON DUPLICATE KEY`→`ON CONFLICT`, ENUM, UUID, DDL) interdite ici ; **pdo_pgsql absent** ; schémas PG du dépôt **obsolètes**. Render free PG **expire 30 j** sans backup. Neon free persiste mais ne lève pas le coût de réécriture. |

## 2. Meilleur scénario Render à 0 €/mois (réponse à la question posée)

> **Scénario A** est le seul qui tienne **uniquement sur Render (gratuit)** pour une
> app **sans réécriture**. Mais il est **fragile** pour un jury : il **ne peut pas**
> conserver les photos uploadées (filesystem éphémère, pas de disque gratuit) et
> impose de **coder le TLS** et de **vérifier la compatibilité SQL** du MySQL
> externe. En pratique, pour la démo, il faudrait **renoncer à montrer l'upload**
> en ligne.

**Meilleure réponse globale au besoin réel** (persistance + 0 € + zéro réécriture) :
> **Scénario D** (hébergement PHP/MySQL gratuit), avec le **plan B local XAMPP** en
> garantie. Render, lui, brille surtout en **payant** (disque, sans spin-down) ou
> après **migration PostgreSQL** — deux chemins hors périmètre/budget de ce rendu.

## 3. Coûts incontournables éventuels

| Poste | 0 € possible ? | Condition / coût incompressible |
|-------|:--:|--------------------------------|
| App Render free | oui | limites (spin-down, 750 h, ephemeral) |
| App Render fiable sans spin-down | **non** | ≥ ~$7/mo (payant) |
| Base MySQL Render | **non** | Render ne fournit **pas** de MySQL |
| Base externe MySQL gratuite (TiDB) | oui (quota) | au-delà → payant ; TLS requis |
| Disque persistant (uploads) sur Render | **non** | $0,25/Go/mo (payant) |
| Hébergeur PHP/MySQL gratuit (D) | **oui** | risque suspension/inactivité (0 € mais fiabilité limitée) |

➜ **Aucun coût n'est incompressible si l'on accepte les limites du free.** Le seul
**vrai** obstacle technique gratuit sur Render est la **persistance des uploads**.

## 4. Compatibilité démontrée / non, par scénario

- **A** : app compatible (VERIFIED) ; base externe **POSSIBLE mais non démontrée**
  (FULLTEXT/TLS sur TiDB = NOT VERIFIED) ; uploads **non persistants** (VERIFIED).
- **B** : **incompatible sans migration** (VERIFIED — requêtes MySQL-only, doc E2).
- **C** : **impossible en free** (DOCUMENTED — disque payant).
- **D** : compatible en principe (MySQL↔MySQL, VERIFIED) ; points exacts du compte
  **NOT VERIFIED** (version MySQL, docroot, extensions, suspension).

## 5. Actions nécessaires sur la configuration PDO (à autoriser explicitement)

1. **B1** — Corriger `DB_PASS` vs `DB_PASSWORD` (`config/db.php` L51) pour que le
   mot de passe soit réellement lu.
2. **B2** — Ajouter la config **TLS** (`PDO::MYSQL_ATTR_SSL_CA`/`ssl-mode`) dès
   qu'une base distante l'exige (scénario A). 
3. **B5** — Implémenter un **endpoint de santé** qui teste la base (`SELECT 1`,
   HTTP 503 si KO) — le 200 actuel ne prouve pas la BDD. 
4. **B4** — Prévoir l'**import du schéma hors conteneur** (migrations exclues de
   l'image par `.dockerignore`). 
5. (Optionnel) **B6** — Adapter l'écoute au **`$PORT`** si Render impose un port
   autre que 80. 

> Aucune de ces corrections n'est faite dans E3 : ce sont des **bloqueurs** à
> traiter **après** validation de l'architecture.

## 6. Plan de démonstration et de secours

Voir `05-jury-demonstration-plan.md` : **Principal D** (ou A si Render impératif,
sans upload), **données fictives** via seeds publics **sans email perso**, **reset**
par re-import, **export `_pre-demo.sql`** + copie uploads, **checklist pré-oral**,
et **plan B local XAMPP** garanti (hors-ligne, aucune dépendance externe).

## 7. Questions à trancher par le porteur (décision attendue)

1. **Choix de l'architecture** : valider **D** (recommandé) ou exiger **Render**
   (alors **A**, en acceptant uploads non persistants + config TLS) ?
2. L'animation de la fonctionnalité **upload de photo** est-elle **indispensable**
   devant le jury ? (si oui → D obligatoire ou stockage objet payant/à créer)
3. Une **URL publique** est-elle exigée, ou une démo **locale** suffit-elle ? 
4. Accepte-t-on de corriger les **bloqueurs B1/B2/B5** (mini-fix config) **avant**
   de déployer ? 
5. Faut-il **archiver** les `schema_neon/supabase.sql` pour éviter toute ambiguïté
   de « compatibilité PostgreSQL » ? 

## 8. Recommandation en une phrase

> Pour présenter le **vrai** site, avec ses **vraies** données persistantes et ses
> uploads, à **0 €** et **sans réécrire le code**, pars sur un **hébergement
> PHP/MySQL gratuit (D)** et garde ton **XAMPP local en plan B**. Render gratuit
> n'est pas le bon outil pour ce besoin précis (pas de MySQL, uploads éphémères,
> base free qui expire, spin-down) : il ne devient pertinent qu'en **payant** ou
> après une **migration PostgreSQL** assumée — deux sujets hors de ce rendu.

---

**STOP.** Audit et recommandation rendus. Je ne lance **ni** migration, **ni**
déploiement, **ni** correction de code/config, **ni** harmonisation visuelle.
J'attends ta **décision explicite** sur les questions §7 avant toute étape suivante.
