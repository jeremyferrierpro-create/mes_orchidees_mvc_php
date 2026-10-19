# 02 — Réconciliation : quelle version sur GitHub, laquelle sur Render ?

## 1. Ce que contient réellement le dépôt GitHub (état live vérifié)

Moyen : `git ls-remote origin` (lecture seule, sans écriture locale) +
consultation de la page publique du dépôt + `git show <ref>:<fichier>` sur les
refs déjà présentes.

| Ref distant | SHA | Date | Nature |
|-------------|-----|------|--------|
| `refs/heads/main` (= HEAD du dépôt, branche par défaut) | `c031e12` | 2026-09-02 17:35 | Legacy à plat, « Update : Correction fetch sur neon » |
| `refs/heads/dev` | `3dcc3c1` | 2026-08-27 17:19 | Legacy à plat, « mise a jour dokerfiles », histoire **sans ancêtre commun** avec main |

Niveau : `VERIFIED` (ls-remote confronté aux refs locales, identiques).

Faits établis sur le contenu publié (`VERIFIED` par `git show`) :

- **Page publique GitHub** : description « mon projet fil rouge version php »,
  README affiché = version « **JavaScript vanilla + localStorage** »,
  « Pas besoin de XAMPP », Supabase présenté comme l'avenir. Le README
  **contredit le code de la même branche** (qui contient déjà `api/*.php` et
  une connexion Neon) : il date de la génération précédente du projet.
- `origin/main:README.md` == `origin/dev:README.md` == README local : aucun des
  trois n'a été mis à jour depuis.
- `origin/main:Dockerfile` et `origin/dev:Dockerfile` (identiques) :
  `php:8.2-apache`, `docker-php-ext-install pdo pdo_mysql pdo_pgsql pgsql`,
  `EXPOSE 80`, pas de docroot `public/` (app à plat), pas de composer.
- `origin/dev:.env.example` définit **`NEON_DSN`** et **`JWT_SECRET`** :
  l'legacy était conçu pour Neon (PostgreSQL) + JWT, sans sessions PHP.
- `origin/main:api/config/db.php` : lit `NEON_DSN`, construit un DSN `pgsql:`,
  ajoute `sslmode=require` et `options=endpoint=…` pour le SNI Neon
  (un identifiant d'endpoint Neon figure en exemple dans un commentaire du
  code public — référence partielle, sans credential ; valeur secrète non
  reproduite ici).
- Historique de `dev` : `ea17b30 « Ajout configuration Docker pour Render »`
  → `4aebfb9…5dfb395` (troubleshooting .htaccess/erreur 500) → `3dcc3c1`
  « mise a jour dokerfiles ». C'est la trace documentaire des tentatives de
  déploiement Render du legacy.

## 2. Ce qui est réellement déployé sur Render

**Aucun accès au tableau de bord Render n'est disponible dans cet environnement.**
En conséquence :

| Question | Réponse | Niveau |
|----------|---------|--------|
| Le service Render existe-t-il encore ? | Inconnu — aucune URL `*.onrender.com` n'est commitée nulle part (recherche `onrender.com` sur tout le workspace : 2 hits, deux **commentaires génériques** de doc/exemple, aucune URL réelle) | `NOT VERIFIED` |
| Dépôt connecté à Render | Probablement `mes_orchidees_php` (c'est le dépôt lié au travail Docker/Render documenté en `dev`) | `INFERRED` |
| Branche déployée | Candidat n°1 : `main` (`c031e12`, le plus récent, 2026-09-02). Candidat n°2 : `dev` (`3dcc3c1`, branche où le Docker Render a été bricolé). **Tranché impossible sans dashboard** | `BLOCKED` |
| Commit déployé | Aucun des commits locaux MVC (`ca40ef5` et les 6 d'avance **n'ont jamais été poussés**) ; si le service tourne, il sert une version ≤ 2026-09-02 | `INFERRED` (forte) |
| Date du dernier déploiement | Inconnue ; borne supérieure = dernier push de la branche déployée (2026-09-02 pour main) | `NOT VERIFIED` |
| Type de service | Web Service **Docker** (le Dockerfile publié est la seule config de build présente ; Render ne construit pas sans) | `DOCUMENTED` (historique dev) / `NOT VERIFIED` (dashboard) |
| Port / démarrage | `EXPOSE 80`, `CMD apache2-foreground` dans le Dockerfile publié | `VERIFIED` (fichier) |
| Base associée | Neon PostgreSQL via `NEON_DSN` injecté en variable d'environnement Render | `VERIFIED` (code legacy) pour la conception ; `BLOCKED` pour l'état réel de la base |
| Version de schéma attendue par le code déployé | Schéma PostgreSQL legacy (`data/schema_neon.sql`, v1.0 « Phase 3 ») **sans** les tables colonnes/rappels/soins MySQL récentes | `VERIFIED` (fichiers) |

Le SHA `ca40ef5` cité dans les rapports E2/E3 comme « SHA local » est confirmé :
c'est bien le HEAD local actuel, et il n'existe **que** localement
(`ahead 6` sur `origin/main`, absent de toute ref distante — ls-remote).
`VERIFIED`

## 3. Écart de versions — synthèse

```
GitHub main (c031e12, 02/09) ── legacy pages PHP à plat + api/ + Neon + README "localStorage"
        │  (6 commits MVC JAMAIS poussés)
Local main (ca40ef5, 17/10) ── MVC MySQL, Router Mithridatem, tests, Docker php:8.2 docroot public
        │
Arbre de travail (119 entrées, 18/10→) ── + rappels/soins/uploads, 5 migrations, tests http/js, docs C1→E3
        │
Render (inconnu) ── sert au mieux c031e12 (legacy Neon) ; peut être suspendu/expiré (free tier)
```

**Aucune ligne de ce diagramme ne relie directement Render au MVC local : entre
le code présenté au jury potentiel (local) et ce que GitHub/Render montrent,
l'écart est de deux générations d'architecture.**

## 4. Ce qu'il faudrait pour passer de `INFERRED/BLOCKED` à `VERIFIED`

Preuves manquantes (décisions humaines à prendre en phase suivante, hors E4) :

1. Ouvrir le dashboard Render → capture de la branche + commit + date de
   dernier build + variables (noms seulement) → rendrait §2 `VERIFIED`.
2. Ouvrir le dashboard Neon → statut de la base, dernière activité, rétention
   free (les docs Render/Neon free 2026 évoquent une expiration ~30 j sans
   activité — `DOCUMENTED` via phase E3, non revérifié ici).
3. Conserver l'URL publique si elle existe (actuellement absente du dépôt →
   inexploitable en l'état pour la soutenance sans ressaisie manuelle).
