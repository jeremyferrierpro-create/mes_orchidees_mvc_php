# 02 — Risques de publication et périmètre des autorisations (Phase 07)

## 1. Ce qui sera publié (D6a)

| Élément | Valeur | Source |
|---|---|---|
| Commits non poussés | **6** (`0a9872a`, `89717f0`, `f00981b`, `64c6cf0`, `6fe6872`, `ca40ef5`) | `git rev-list --count origin/main..HEAD` = 6 (VERIFIED 07) |
| Écart de contenu | 355 fichiers, +11 329 / −17 258 (hors commit 06 : +369 sur 6 fichiers docs) | audit 06/01 §3 |
| Secrets | **Aucun** dans le diff ; `.env` ignoré via `.gitignore:6` | VERIFIED 06/01 §4 |
| Cible | `https://github.com/jeremyferrierpro-create/mes_orchidees_php.git` | VERIFIED 06/01 §1 |

## 2. Risque de ne pas pousser

- **Unique copie sur un seul disque** : tout le travail des phases 02→06 (≈ 11 700 lignes
  ajoutées) n'existe qu'en local. Panne de disque/effacement = perte totale hors
  sauvegardes locales. **C'est le risque le plus élevé du projet à ce jour.**
- **Aucune divergence possible tant que rien n'est poussé** — `HEAD..origin/main` = **0**
  commit : `origin/main` est sur `c031e12` (« Update : Correction fetch sur neon »),
  directement en ligne droite sous HEAD → un push serait un **fast-forward** pur, sans
  risque d'écrasement et sans travail distant à fusionner (VERIFIED 07).
- **Pas de CI, pas de sauvegarde externe** : la publication n'est pas une sauvegarde
  réversible à elle seule, mais en est la première étape.

## 3. Contenu sensible résiduel (à assumer, pas à corriger d'office)

| Élément | État | Commentaire |
|---|---|---|
| Comptes de démonstration dans les seeds | présents, **volontaires** | identités fictives du projet étudiant, mots de passe hachés (`PASSWORD_DEFAULT`) ; un dépôt **public** les rend lisibles à tous → à accepter explicitement |
| Hash de mot de passe faible de démo | à vérifier au cas par cas | si un compte de démo a un mot de passe simple documenté dans le README/seed, un lecteur pourrait le deviner — **sans conséquence réelle** (base publique = données de démo), mais à énoncer |
| `README.md` obsolète | **VERIFIED 06/01 §5** | décrit encore « JS vanilla + localStorage, PHP+Supabase prévu » → publication en l'état = documentation trompeuse pour un recruteur/formateur |
| Arborescence de cours (`docs/MODULES/`, ISO, vidéos, conv. de stage) | **hors dépôt** (non suivi) | ne sera pas publiée — aucun risque de diffusion de documents tiers |
| `archive/legacy/` (146 fichiers renommés) | publié | ancien code API JS/PHP-Supabase ; pédagogique, sans secret |
| Historique Git complet | publié | les 6 commits + l'historique préexistant ; aucun secret retrouvé dans le diff ; **une recherche dans l'historique profond (`git log -p` complet) n'a pas été menée → NOT VERIFIED** (action possible en Phase 08 si dépôt public) |

## 4. Question non résolue : dépôt public ou privé

**Aucun élément du dépôt ne tranche cette question ; elle n'a jamais été posée
formellement.** Elle conditionne D5 (nettoyage des schémas legacy) et le niveau de
vigilance sur §3. Éléments objectifs :

- **Public** : vitrine professionnelle utile (CV, formateur), mais expose seeds de démo,
  code legacy, et un README actuellement trompeur.
- **Privé** : publication sûre et sauvegarde immédiate, partage ponctuel possible.
- **Intermédiaire** : privé maintenant, mise en public après correction README +
  éventuel nettoyage — **recommandation technique : commencer privé** (le push atteint
  l'objectif de sauvegarde sans exposer quoi que ce soit ; passer public est ensuite
  une décision séparée et réversible).

La bascule public/privé se fait dans les réglages du dépôt : **elle n'est effectuée
automatiquement par personne sur le projet** (règle — ne pas rendre le dépôt public).

## 5. Séparation stricte des autorisations

| Autorisation | Couvre | Ne couvre PAS |
|---|---|---|
| **D6a — push** | `git push origin main` des 6 commits | création de service, mise en public du dépôt, déploiement |
| **D6b — déploiement** | provisionnement + déploiement selon runbook | — ne peut être accordée par une validation documentaire des dossiers 06/07 |
| Validation d'un dossier (06/07) | reconnaissance que l'analyse est recevable | **rien d'exécutoire** |

Une approbation de D6a **sans** D1/D2 laisse le projet sauvegardé mais non déployé —
état cohérent et recommandé. À l'inverse, aucune séquence ne permet de déduire D6b.

## 6. Vérifications non faites dans cette phase

- Recherche exhaustive de secrets dans **tout l'historique** (pas seulement le diff 06→HEAD).
- Contrôle de la visibilité actuelle du dépôt distant (aucune commande réseau, aucun compte utilisé).
- Validation des prix/quotas fournisseurs (extraits officiels seulement — §3 du doc 01).

## 7. Statut de l'autorisation D6a à la fin de la Phase 07

La question du push a été **posée explicitement** au propriétaire (options : push privé /
push public / README d'abord / pas de push). **Aucune réponse n'est parvenue** pour cette
question lors de l'arbitrage du 2026-10-17.

Conséquence appliquée : **aucun `git push` n'a été exécuté** ; HEAD local reste `ca40ef5`
(+ docs phase-07 non commités), `origin/main` reste `c031e12`. Le statut documenté est `OPEN`,
et en aucun cas une absence de réponse n'est interprétée comme un accord implicite (règle 11).

Rappel utile pour l'arbitrage : cette action est **indépendante de D1** et **sans coût**
(le dépôt GitHub cible existe déjà) — contrairement au déploiement, elle n'attend ni
fournisseur de base ni provisionnement.
