# Lot C1 — Rapport de validation (2026-10-18)

Statuts : `VERIFIED` (prouvé par exécution/outil réel) · `PASS` · `PASS WITH
LIMITATIONS` · `BLOCKED` · `NOT RUN` · `NOT VERIFIED`.

## Résultats réellement exécutés

| Vérification | Outil / commande | Résultat | Statut |
|---|---|---|---|
| Lint PHP (10 fichiers touchés : Abstract/Orchid/Home/Rappel/Notification controllers, routes, footer, run.php) | `php -l` | aucun | **PASS** |
| Suite HTTP complète (avant Lot 2, après Lot 1) | `php tests/http/run.php` (serveur réel) | **272 PASS / 0 FAIL** (282 appels) | **PASS** — base de référence C0 intacte |
| Suite HTTP complète (Lot 2 final) | idem | **290 PASS / 0 FAIL / 1 NOT RUN** (299 appels serveur réels) | **PASS** |
| Groupe C1 (18 assertions + 1 NOT RUN) | intégrée à la suite | toutes verts : flux dû 200/ok, échu activé `planifie→actif`, 13 champs contractuels, csrf 64 hex, futur/lu/traité exclus, anonyme 403 sans fuite, RG1 lecture et écriture (404 état intact), CSRF absent/invalide 400, lu→inéligible, ×2 chargements = 0 doublon (6=6), `/rappels` 200 | **VERIFIED** |
| Tests routeur JS | `node tests/js/router.test.mjs` | **39 PASS / 0 FAIL** | **PASS** |
| Compilation SCSS (expanded + compressed) | `sass` (binaire du projet) | exit 0 ×2 ; `line-clamp: 4` dans les deux feuilles ; `.toast-rappel` présent ; sélecteurs glass C0 intacts | **VERIFIED** |
| `duration = 0` = persistant | lecture `core/notifications.js` L113 (`if (duration > 0) setTimeout`) | contrat confirmé | **VERIFIED** |
| Isolation base de test | preuve par sonde serveur de la suite (`INT`) | base test ≠ dev, 0 résidu, dev inchangée | **VERIFIED** |
| Échec réseau client → rappel non lu conservé | comportement `fetch` du module JS | impossible en HTTP serveur réel ; code relu (retrait seulement après 200 ok) + protocole manuel §03 | **NOT RUN** (affiché tel quel dans le tableau de suite) |
| Re-lecture IDE des 3 diagnostics après corrections | language server IDE | server en re-indexation suite à l'ajout de `.vscode/settings.json` ; CLI Intelephense non installée | **NOT VERIFIED** — une syntaxe valide ne prouve pas la disparition du diagnostic ; les causes sont quant à elles établies par le code (voir 01) |
| Rendu visuel du toast dans un navigateur | — | non ouvert pendant la phase | **NOT RUN** — protocole manuel en 7 étapes documenté dans 03-toast-persistent.md |

## Sécurité et accessibilité (vérifications)

- **RG1** : owner-only en lecture (`/api/rappels/dus`) et en écriture
  (`markReadForUser` WHERE user_id) — prouvé par tests C1 (isolement + 404 état intact).
- **CSRF** : absent → 400, invalide → 400 + `is_read` inchangé (testé).
- **Auth** : anonyme → 403, aucune donnée dans le corps (testé) ; le module JS
  ne reçoit d'amorce DOM que si `is_logged_in()`.
- **GET sans effet de bord** : `/api/rappels/dus` n'écrit que via le mécanisme
  d'activation idempotent existant (comptage avant/après ×2 = identique, testé) ;
  le changement d'état « lu » est exclusivement POST.
- **A11y** : conteneur `aria-live="polite"` (urgence réelle = informationnelle),
  libellés distincts fermeture/lecture, focus-visible or, cibles ≥ 40 px ;
  `prefers-reduced-motion` global existant réutilisé. **PASS WITH LIMITATIONS** :
  repos du focus après retrait du toast (documenté, affinement visé phase D).

## Fichiers modifiés (phase C1) et justification

| Fichier | Lot | Pourquoi |
|---|---|---|
| `.vscode/settings.json` | 1 | Exclusion `docs/**` de l'index Intelephense (cause P1006) |
| `src/Controller/AbstractController.php` | 1 | `redirectTo(): never` — contrat réel (cause P1075) |
| `public/assets/scss/components/_cards.scss` | 1 | `line-clamp` standard (cause SCSS) |
| `config/routes.php` | 2 | Route `GET /api/rappels/dus` (GRANT_AUTH) |
| `src/Controller/RappelController.php` | 2 | `dus()` : réutilise `activateDueReminders` + filtres entité C0 |
| `src/Controller/NotificationController.php` | 2 | `markRead()` : négociation JSON, parcours HTML inchangé |
| `templates/components/footer.php` | 2 | Amorce `#rappels-toast-bootstrap` (session uniquement) |
| `public/assets/js/features/rappels-toast.js` | 2 | **créé** — module toast persistant |
| `public/assets/js/app.js` | 2 | Branchement transverse du module |
| `public/assets/scss/pages/_mvc-rappels.scss` | 2 | `.toast-rappel` glass + variante retard |
| `public/assets/css/style.css(.map)` / `style.min.css(.map)` | 1+2 | Recompile Sass |
| `tests/http/run.php` | 2 | Groupe C1 (19 lignes de test) sans remplacer les précédents |
| `docs/project-foundation/phase-c1/*` | 2 | Le présent dossier |

## Limites restantes

1. Re-lecture IDE des diagnostics en attente (language server en re-indexation).
2. Rendu navigateur du toast non vérifié automatiquement (protocole §03).
3. `GET /notification/{id}` (legacy) marque lu en GET : hors périmètre C1, non
   utilisé par le toast ; à harmoniser si le propriétaire le décide.
4. Notification uniquement **pendant la consultation** : pas de Web Push, pas de
   cron (hors périmètre assumé).
5. Aucun commit : `git status` = 88 entrées modifiées/non suivies (travail A/B/C0/C1
   en cours), HEAD toujours `ca40ef5`, branche `main`.
