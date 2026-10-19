# Phase D — Modale « Gérer mes rappels de soins »

**Date :** 2026-10-18 · **Bilan : PASS WITH LIMITATIONS** · **Aucun commit, aucun push.**

## Objet de la phase

Permettre à un utilisateur connecté de **consulter et gérer l'intégralité de ses
rappels de soins depuis les pages de sa collection, sans quitter la page**, dans
une fenêtre modale accessible (clavier + lecteur d'écran) et responsive, en
réutilisant le rendu serveur, les routes et les protections (CSRF, RG1) déjà en
place — et en gardant `/rappels` entièrement utilisable sans JavaScript.

## Contenu du dossier

| Fichier | Contenu |
|---|---|
| [`01-audit-et-architecture.md`](01-audit-et-architecture.md) | État réel du dépôt à l'audit, contrat vérifié des briques existantes, diagnostic factuel, architecture retenue et alternatives écartées. |
| [`02-implementation.md`](02-implementation.md) | Ce qui a été livré, fichier par fichier, avec le contrat de chaque pièce (entrées, fragment, négociation JSON, module JS, styles). |
| [`03-tests-et-resultats.md`](03-tests-et-resultats.md) | Tests de référence, suite HTTP, tests JS, sécurité, régressions rencontrées et leur résolution, commandes exactes. |
| [`04-validation-visuelle-et-limites.md`](04-validation-visuelle-et-limites.md) | Vérification en navigateur réel (protocole, mesures, défauts trouvés et corrigés) et limites assumées. |

## Chiffres retenus (mesurés, pas déclarés)

| Indicateur | Référence (fin C1) | Après phase D |
|---|---|---|
| Suite HTTP | 290 PASS · 0 FAIL · 1 NOT RUN (291 lignes) | **320 PASS · 0 FAIL · 0 BLOCKED · 2 NOT RUN · 0 NOT VERIFIED** (322 lignes) |
| Bloc MDL (modale) | — | 31 lignes (30 PASS · 1 NOT RUN) |
| Requêtes HTTP réellement émises | — | 333 |
| Tests JS routeur + registre | 39 PASS · 0 FAIL | 39 PASS · 0 FAIL |
| Nouveau test JS modale | — | 23 PASS · 0 FAIL |
| `php -l` | — | 79 fichiers, 0 erreur |
| Parsing ES modules | — | 27 fichiers, 0 erreur |
| Compilation SCSS | — | 2 feuilles régénérées, **octets identiques** (source ↔ compilés synchronisés) |
| Migration SQL | — | **aucune** |

## Ce que la phase D a livré

- Une **entrée** « Gérer mes rappels » sur `/macollection` et `/collection/{id}`,
  qui reste un lien `href="/rappels"` fonctionnel sans JavaScript.
- Un **endpoint de fragment HTML** `GET /rappels/modal` (authentifié, RG1,
  rendu sans layout, mêmes vues que `/rappels`, aucun `<!-- API improvisée -->`).
- Un **gabarit de modale** `components/rappels_modal.php` réutilisant la
  convention `.modal-overlay > .modal-container > .modal-close` et
  `core/modal.js` (Échap, piège de focus, focus initial, retour du focus).
- Un **module externe** `features/rappels-modal.js` (branché dans `app.js`,
  CSP `script-src 'self'` respecté, aucune logique métier dupliquée).
- Une **négociation JSON** des quatre actions de cycle de vie (`/fait`,
  `/ignorer`, `/report/{jours}`, `/delete/{id}`) : même logique, même CSRF, même
  RG1, réponse vérifiable côté client ; le parcours formulaire HTML (flash + 302)
  est **inchangé**.
- Six défauts **trouvés en navigateur réel et corrigés** (fausse bannière
  d'erreur, absence de confirmation, scroll de l'arrière-plan, toasts au-dessus
  du voile, note d'historique absente, saut de page à la fermeture).

## Limites principales (détail dans `04-`)

1. Le piègeage de focus de `core/modal.js` peut « s'échapper » si
   `document.activeElement` redevient `BODY` (limite du cœur, non modifié) —
   décision laissée au propriétaire.
2. Le mobile 390 × 844 n'a pas pu être photographié (viewport natif
   indisponible) : validé par proxys iframe même-origine + mesures de débordement.
3. Le Service Worker a produit des 404 intermittents en phase d'audit et son
   precache v7 conserve une taille stale de `style.css`.
4. Cibles tactiques portées à 40 px (mesure navigateur), sous les 44 px de
   WCAG 2.5.5 AAA — le projet vise AA.
5. Aucun contrôle automatisé de frappe clavier réelle : les vérifications
   Échap/Tab viennent de sessions navigateur manuelles outillées, pas d'un
   runner.

## Reproduire

```powershell
# Suite HTTP complète (base de test dédiée, isolation vérifiée par le harnais)
$env:TEST_HTTP_DB_USER='root'; $env:TEST_HTTP_DB_PASS=''
php tests/http/run.php

# Tests JS
node tests/js/router.test.mjs
node tests/js/rappels-modal.test.mjs

# Compilation (commande réelle du projet)
npx sass public/assets/scss/style.scss public/assets/css/style.css --style=expanded --embed-sources --quiet
npx sass public/assets/scss/style.scss public/assets/css/style.min.css --style=compressed --quiet

# Serveur de test pour la vérification navigateur (base de test, jamais la base de dev)
$env:TEST_HTTP_DB_USER='root'; $env:TEST_HTTP_DB_PASS=''
php -S 127.0.0.1:8099 -t public tests/http/server-router.php
```

**STOP :** phase D terminée, rien n'est commité ni poussé.
