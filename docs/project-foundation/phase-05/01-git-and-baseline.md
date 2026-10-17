# 01 — État Git et baseline (Phase 05)

Tous les releves ont ete obtenus **pendant la Phase 05** sur le depot reel.

## 1. Releves initiaux (VERIFIED)

| Element | Valeur |
|---|---|
| Racine | `C:/Users/jerem/Desktop/mes_orchidees` |
| Branche active | `main` |
| HEAD | `64c6cf0` |
| Arbre de travail | **propre** (`git status --short` vide) |
| Index | vide (rien de stage) |
| Fichiers non suivis | aucun (hors `.env` ignore) |
| `origin/main` | `main...origin/main [ahead 4]` → **non pousse** |

## 2. Commits locaux non pousses (VERIFIED)

Ordre du plus recent au plus ancien (`git log origin/main..HEAD`) :

```
64c6cf0 chore(phase04): harden security and prepare deployment
f00981b feat(phase03): increments notifications (RG3/RG5) et administration/moderation
89717f0 feat(phase03): increments conseils et collection/soins avec isolation RG1
0a9872a refactor(mvc): extraction du projet a la racine, schema MySQL et socle encylopedie
```

- `git merge-base --is-ancestor 64c6cf0 HEAD` → **exit 0** (HEAD est son propre ancetre).
- `git merge-base --is-ancestor f00981b HEAD` → **exit 0** (reference fonctionnelle presente).
- Le commit `64c6cf0` porte 9 fichiers (+576/-16) : `.dockerignore`,
  `.env.production.example`, `.gitignore`, `Dockerfile`, rapport phase-04,
  `public/index.php`, `AbstractController.php`, `AuthController.php`,
  `CsrfException.php`. Aucun secret.

## 3. Cohérence avec le rapport Phase 04B

Le rapport `docs/project-foundation/phase-04/01-stabilization-and-deployment-prep.md`
annoncait HEAD `64c6cf0`, arbre propre, avance de 4 : **CONSTATE CONFORME** (VERIFIED).
Aucun ecart entre l'etat annonce et l'etat reel.

## 4. Protections preservees (VERIFIED)

- `.env` reel : statut `!!` (`git status --ignored`) → **ignore, jamais ajoute, contenu jamais affiche**.
- Aucune commande destructive (`reset --hard`, `clean`, `checkout .`) n'a ete utilisee.
- Historique des increments 4 & 5 (`f00981b`) **inchanger**.

## 5. Baseline technique

| Dimension | Etat | Statut |
|---|---|---|
| PHP CLI local | 8.3.33 | VERIFIED |
| MariaDB local | 10.4.32 | VERIFIED |
| Docker | 29.8.2 | VERIFIED |
| Composer | 2.10.3 | VERIFIED |
| `vendor/autoload.php` | present (PSR-4 App\→src\, Mithridatem\Routing\→lib\) | VERIFIED |
| Suite de tests | ajoutee en Phase 05 (`tests/`) | VERIFIED |

## 6. Decisions/limites

- La branche **n'est pas poussée** (interdit). L'avance de 4 commits reste locale.
- Les modifications de la Phase 05 (tests + docs) seront eventuellement committees
  localement a la fin de la phase, sur selection explicite (voir §commit du rapport final).
