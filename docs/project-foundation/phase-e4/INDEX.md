# PHASE E4 — Réconciliation forensique GitHub / MVC local / Render-Neon

**Audit en lecture seule.** Aucune correction, aucun commit, aucune migration,
aucun déploiement. Date de l'audit : 2026-10-19.

## Objet

Établir une photographie vérifiable de trois états et recommander une stratégie
de soutenance DWWM :

- **A** — dépôt GitHub `jeremyferrierpro-create/mes_orchidees_php` et son historique ;
- **B** — répertoire local `C:/Users/jerem/Desktop/mes_orchidees` (refonte MVC + travaux non commités) ;
- **C** — déploiement Render/Neon, limité aux éléments accessibles sans toucher au service.

## Méthode et niveaux de preuve

Chaque affirmation est classée :

| Niveau | Signification |
|--------|---------------|
| `VERIFIED` | Preuve directe observée (commande exécutée, fichier lu) |
| `DOCUMENTED` | Affirmé par un document du dépôt, sans re-vérification d'exécution |
| `INFERRED` | Déduction argumentée à partir de preuves |
| `NOT VERIFIED` | Preuve insuffisante |
| `BLOCKED` | Vérification impossible sous les contraintes E4 |

## Commandes de diagnostic exécutées (toutes sans effet de bord)

```
git rev-parse --show-toplevel
git branch -a -vv
git remote -v
git log --oneline -15                (HEAD, origin/main, origin/dev)
git status --porcelain=v1 -uall
git diff --cached --stat             (index vs HEAD)
git diff --stat                      (arbre de travail vs index)
git ls-remote origin                 (lecture seule du distant, sans fetch)
git rev-list --left-right --count origin/main...main
git rev-list --left-right --count origin/dev...main
git merge-base main origin/dev       → vide : histoires sans ancêtre commun
git merge-base main origin/main      → c031e12
git ls-tree --name-only <ref>        (racine des arbres main/dev/HEAD)
git ls-tree -r --name-only <ref> | grep render|docker|yml
git log --max-parents=0 HEAD         (racine f520c7b)
git rev-list --count HEAD            (13 commits)
git show origin/main:Dockerfile / :api/config/db.php / :README.md
git show origin/dev:Dockerfile / :.env.example
git stash list                       (vide)
git diff --name-only origin/main HEAD | measure   (361 fichiers)
Select-String … (clés .env*, motifs SQL MySQL, schémas)
Test-Path docs/project-foundation/phase-e4   (absent avant rédaction)
```

Aucune écriture hors des sept fichiers du présent dossier.

## Livrables

| Fichier | Contenu |
|---------|---------|
| `01-local-and-remote-state.md` | Photographie Git locale et distante, inventaire des modifications |
| `02-github-render-version-reconciliation.md` | Identification de la version publiée/deployée, niveaux de certitude |
| `03-mvc-and-database-compatibility.md` | Écarts d'architecture, compatibilité réelle MySQL vs PostgreSQL/Neon |
| `04-deployment-scenarios.md` | Scénarios A/B/C/D évalués |
| `05-jury-demo-and-offline-fallback.md` | Plan de soutenance : mode principal + mode secours |
| `06-final-recommendation.md` | Recommandation finale argumentée |

## Limites de l'audit

- **Pas d'accès au tableau de bord Render** ni au dashboard Neon : l'état C est
  reconstitué uniquement à partir du code et des documents du dépôt. Le commit
  réellement déployé, l'URL publique et l'état de la base distante sont
  `NOT VERIFIED` / `BLOCKED`.
- Aucun navigateur, aucun test d'exécution lancé dans cette phase : les états
  « site en ligne » n'ont pas été interrogés (une requête HTTP vers le site
  aurait été possible si l'URL avait été connue du dépôt — elle ne l'est pas).
- Les phases antérieures (C0→E3) sont reprises comme `DOCUMENTED`, pas comme preuves d'exécution.

## Constats structurants (détail dans les documents 01–03)

1. `VERIFIED` — Le local (`main` @ `ca40ef5` + 119 entrées de travail) est **en avance
   de 6 commits** sur GitHub et **0 derrière** ; rien de ce MVC n'est publié.
2. `VERIFIED` — La branche GitHub `dev` est d'**histoire totalement distincte** de
   `main` (aucun ancêtre commun) : le dépôt remote contient **deux lignes
   incompatibles** du projet.
3. `VERIFIED` — Le code local est **MySQL-only** (FULLTEXT/MATCH…AGAINST,
   `ON DUPLICATE KEY`, `DEFAULT(UUID())`, `ENGINE=InnoDB`, DSN `mysql:` en dur,
   extension `pdo_mysql` seule dans le Dockerfile).
4. `BLOCKED` — La version Render effective (branche, commit, date, variable) ne
   peut être établie sans accès dashboard ; le candidat le plus probable est
   `origin/main` @ `c031e12` (2026-09-02, « Correction fetch sur neon »).
5. `VERIFIED` — Le README publié décrit la **version localStorage vanilla JS**,
   état du projet antérieur à PHP ; il est triplement obsolète (README local == README publié).
