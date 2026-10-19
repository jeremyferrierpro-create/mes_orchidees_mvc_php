# 03 · Tests et résultats

Règle appliquée : **tests de référence d'abord, diagnostic ensuite, implémentation
enfin, et jamais un test modifié pour masquer une régression.** Chaque chiffre
ci-dessous vient d'une exécution réelle, pas d'une intention.

---

## 1. Référence avant tout changement

| Suite | Résultat de référence (état fin C1) |
|---|---|
| `php tests/http/run.php` | **290 PASS · 0 FAIL · 1 NOT RUN** (291 lignes) |
| `node tests/js/router.test.mjs` | **39 PASS · 0 FAIL** |
| `php -l` sur l'arbre | 0 erreur |

Cette référence a été conservée **intacte** tout au long de la phase : les
groupes A/B/C/C1/D/E/F/G/INT n'ont été ni touchés ni réécrits.

---

## 2. Après phase D — résultats définitifs

### 2.1 Suite HTTP (`php tests/http/run.php`)

```
=== Bilan ===
PASS 320 · FAIL 0 · BLOCKED 0 · NOT RUN 2 · NOT VERIFIED 0
```

| Vérification structurelle | Mesure |
|---|---|
| Lignes d'assertion | 322 (291 à la référence + **31** du bloc MDL) |
| Requêtes HTTP réellement émises (ligne `INT`) | **333** |
| Isolation | `GET /__http-test-probe` → `mes_orchidees_test` · 9 tables (preuve serveur, pas hypothèse) |
| Propreté avant run / après run | `Aucune donnée de test avant l'exécution` PASS (0) · `Aucun résidu de donnée de test après l'exécution` PASS (0) |
| Base de développement | `strictement inchangée` PASS + `aucun marqueur de test en base de dev` PASS |
| Bloc MDL | **30 PASS · 1 NOT RUN · 0 FAIL · 0 BLOCKED** |
| Les 2 NOT RUN | (a) C1 « Échec réseau ne marque pas le rappel lu » (impossible en HTTP serveur) ; (b) MDL « Ouverture, piègeage de focus, Échap, retour du focus, erreur réseau » — **la suite HTTP ne peut pas l'automatiser** ; le contrôle a été fait dans un vrai navigateur (voir `04-`), la ligne reste NOT RUN pour l'automatisation plutôt que d'être transformée en faux PASS. |

### 2.2 Ce que le bloc MDL prouve (extrait réel des libellés)

| Catégorie | Lignes (toutes PASS sauf indication) |
|---|---|
| **Endpoint** | `GET /rappels/modal authentifié : 200 fragment sans layout` · `Chargements répétés : aucune notification créée (GET sans effet de bord)` (9 → 9) · `Aucun soin consigné par un simple chargement du fragment` (soins 1 → 1) |
| **Contenu D2** | `Section « À faire aujourd'hui » : rappel du jour présent` · `Section « En retard » : rappel dépassé + compteur de jours` · `Section « À venir » : planifié non encore dû, resté planifié` · `Historique : rappels clos listés (lignes cohérentes avec la base + badge « Traité »)` (2 lignes = 2 en base) |
| **RG1** | `RG1 : le fragment de userB ne contient aucun rappel de userA` · `RG1 : userA ne voit que son propre rappel dû` · `Accès anonyme au fragment refusé (403, contenu non fuite)` · `RG1 : la fiche d'une plante tierce n'est pas accessible (404, modale hors service)` |
| **CSRF** | `Fragment : actions POST C0 présentes et jeton CSRF 64 hex embarqué` · `CSRF absent refusé sur la carte d'action (400)` · `CSRF invalide refusé, rappel toujours ouvert` · `JSON sans jeton CSRF : 400 (jamais 200), rappel encore ouvert` |
| **Actions D3, parcours HTML (C0 inchangé)** | `POST /rappel/{id}/fait depuis le flux modale : 302 → /rappels` · `Rappel clos côté serveur (statut traite persisté)` · `Rechargement du fragment : carte ouverte en moins, ligne d'historique en plus (état persisté)` (7 → 6 cartes, historique = base) · `Cohérence persistée : le fragment rechargé reflète la base (pas d'état optimiste)` · `RG1 : clôture croisée refusée (302 sans effet, état intact)` |
| **Actions D3, négociation JSON (MJ-1…MJ-6)** | `JSON : ignorer un rappel (200 ok · statut ignore persisté)` · `JSON : rappel déjà clôturé → info, aucune seconde écriture` (soins 0 → 0) · `JSON + RG1 : rappel tier → status ko (introuvable), état de B intact` · `JSON : report de 7 jours (200 reporte · échéance repoussée en base)` (mesuré `2026-10-23 16:34:54 → 2026-10-30 16:34:54`) · `Réponse JSON d'action : Content-Type json et aucune fuite interne` (`application/json; charset=utf-8`) |
| **Entrées D1** | `Tableau de bord : entrée « Gérer mes rappels » + squelette + endpoint` · `Fiche plante : entrée « Gérer mes rappels » + squelette présentés` |
| **CSP / no-inline (ajoutés en fin de phase)** | `CSP : aucun gestionnaire en ligne (on*=) dans le squelette ni le fragment` (0 attribut) · `Fragment : aucun <script> embarque (chargement par le module externe)` (0 balise) |
| **Repli sans JS (D4)** | `Repli sans JS : /rappels rend toujours sections, cartes et actions (régression C0)` |
| **Navigateur** | `Ouverture, piègeage de focus, Échap, retour du focus, erreur réseau` → **NOT RUN** (voir `04-`) |

### 2.3 Tests de sécurité : ce qui a été vérifié, ligne par ligne

- **Aucune action métier déclenchée par un simple GET** : deux assertions
  comptables (notifications 9 → 9 sur deux chargements du fragment ; soins 1 → 1
  après chargement) — le `GET /rappels/modal` ne mute rien.
- **Anonyme** : 403 + **aucun** marqueur de donnée dans le corps.
- **Traversée de compte (RG1)** : fragment, clôture croisée (HTML 302 sans effet
  **et** JSON `ko/introuvable`), fiche tierce 404.
- **CSRF** : absent et invalide refusés, en HTML (400) comme en JSON (400, jamais
  200), état de la notification intact dans les deux cas.
- **Échappement** : le fragment est produit par les vues `e()` ; les lignes
  `E` (échappement d'une note stockée) de la suite restent PASS ; aucune
  concatenation HTML côté client (assertion `03` du test JS, voir §3.2).
- **Fuite interne** : `body_leaks_internals()` sur la réponse JSON (aucun chemin
  absolu, aucun message PDO).

---

## 3. Défauts trouvés **par** les tests, et résolution (transparence)

### 3.1 Squelette de modale rendu à 0 octet (BLOCKED, puis PASS)

Symptôme : `GET /macollection` contenait bien le commentaire de la modale, mais
pas `id="rappels-modal"`. Mesure (sonde d'existence du chemin résolu) :
`exists=false`, `dir=…\templates\pages\collection`, sortie 0 octet.

Cause : `include __DIR__ . '/../components/rappels_modal.php'` depuis
`templates/pages/collection/` résout `templates/pages/components` (inexistant).
Un `include` de fichier manquant n'est **pas** fatal : warning + 0 octet.

Correction : `dirname(__DIR__, 2) . '/components/rappels_modal.php'` (convention
déjà utilisée ailleurs dans le dépôt), dans les deux gabarits. Sonde et fichiers
temporaires supprimés après coup. **Vérification : les deux lignes d'entrée
passent à PASS à l'exécution** — pas par hypothèse.

### 3.2 « Fiche plante » BLOCKED → test HTTP réel

Le test réutilisait l'espèce du seed, déjà consommée par les blocs C/D : la
règle anti-doublon (`CollectionRepository::existsForUser()` → flash « Cette
espèce est déjà dans votre collection. » + `redirectTo('/macollection')`)
renvoyait une 302 sans `/collection/{uuid}`. Rather than fake the state, le test
crée désormais **sa propre espèce publiée** (`httptest-mdl-orch-<runId>`,
`is_published=1`, `moderation_status='approved'`), passe par la vraie route
`POST /collection/new`, puis vérifie la fiche. La fixture est explicitement
supprimée (`DELETE collections`, `DELETE orchids`, notifications) — le contrôle
« aucun résidu après l'exécution » reste PASS.

### 3.3 Régression JS détectée, **annoncée**, et traitée

Après branchement de `initRappelsModal` dans `app.js`, `router.test.mjs` est
tombé à **37 PASS · 2 FAIL** :

```
FAIL | « collection-dashboard » mappé à un no-op () => {}
FAIL | « collection-detail »   mappé à un no-op () => {}
```

Le test B2 exigeait (état C0) que **les quatre clés MVC** soient un `() => {}`
littéral, commentaire d'origine : « *en attendant les modules des prochaines
phases* ». La phase D livre précisément un de ces modules.

Plutôt que de supprimer l'assertion, elle a été **remplacée par une garantie
équivalente et plus stricte** (`tests/js/router.test.mjs`) :

- liste blanche **par clé** : `collection-dashboard` et `collection-detail`
  peuvent valoir `() => {}` ou `initRappelsModal` ; `rappels` et
  `admin-dashboard` doivent **rester** `() => {}` ;
- interdiction explicite de tout module legacy (`initCollection`,
  `initAdministration`, `initSearch`, `initAddButton`, `initConseils`,
  `initAuthForm`, `initBackgroundAnimation`) sur une clé MVC ;
- le libellé affiché montre la valeur réelle trouvée dans `app.js`.

Résultat : **39 PASS · 0 FAIL**. L'intention du test (aucun module dangereux du
site statique ne tourne sur les vues MVC) est désormais *mieux* couverte qu'auparavant.
**Aucune assertion n'a été retirée pour faire taire un échec.**

### 3.4 Deux échecs qui venaient de mes propres tests (pas de l'application)

- MJ-6 écrivait mal : lecture de `$r->headers['Content-Type'][0]` (mauvaise
  structure ; l'idiome du harnais est `$r->header('content-type')`) **et**
  réutilisation de la réponse HTML 400 de MJ-5. Réécrit avec sa propre requête
  JSON → PASS.
- La ligne `INT · Aucune donnée de test avant l'exécution` est passée en **FAIL
  (7 lignes)** pendant que les fixtures de ma session navigateur étaient encore
  en base : artifact de mon environnement, pas une régression. Après
  auto-nettoyage de ces fixtures, la ligne est revenue à PASS (0). Le run de
  validation final (320 PASS · 0 FAIL) a été obtenu base de test vierge.

---

## 4. Tests JavaScript

| Suite | Résultat |
|---|---|
| `node tests/js/router.test.mjs` (existant, B2 évolué §3.3) | **39 PASS · 0 FAIL** |
| `node tests/js/rappels-modal.test.mjs` (**nouveau**, 23 assertions) | **23 PASS · 0 FAIL** |

Le nouveau test est **exécutable** (il importe le vrai module de production en
Node, avec un bouchon de DOM minimal, sans dépendance ajoutée) et **ne prétend
pas** remplacer la preuve navigateur — son en-tête le dit noir sur blanc.

| Groupe | Ce qui est réellement exercé / vérifié |
|---|---|
| **R1 · contrat d'initialisation** | le module s'importe et exporte `initRappelsModal` ; **no-op sans exception** quand `#rappels-modal` est absent et **0 écouteur posé** ; no-op quand le corps n'a pas de `data-endpoint` ; garde anti-double-lier (0 écouteur `document`, 0 écouteur `submit`) ; cas nominal = exactement **1** écouteur `click` délégué, **1** écouteur `submit`, **1** branchement du bouton fermer, `dataset.liee` posé |
| **R2 · invariants lisibles dans le source** | **2** `fetch` seulement (fragment + action) ; écriture exclusivement `method: 'POST'` (aucun `method: 'GET'`) ; aucune action de soin (`/fait`, `/ignorer`, `/report`, `/delete`) appelée en GET ; négociation `'Accept': 'application/json'` présente ; succès conditionné à `status === 'ok'` ; **aucune** mention de `redirected` (la cause exacte du faux message mesuré en navigateur) ; messages d'action posés via `textContent` ; `innerHTML` alimenté **uniquement** par `await res.text()` (aucun gabarit `${…}`, pas d'`insertAdjacentHTML`) ; pas d'`eval` / `new Function` / `document.write` ; aucune URL de fragment durcie (`'\/rappels\/modal'` absent, endpoint lu de `data-endpoint`) ; état d'erreur avec repli vers la page autonome ; `app.js` importe le module **une seule fois** ; `layout.php` ne charge pas le module séparément (pas de double initialisation possible) |

---

## 5. Contrôles techniques

| Contrôle | Commande | Résultat |
|---|---|---|
| Syntaxe PHP | `php -l` sur `src`, `templates`, `config`, `public`, `tests` (récursif) | **79 fichiers, 0 erreur** |
| Syntaxe JS (modules ES) | analyse réelle sans exécution (`vm.SourceTextModule`, un seul processus — le bac à sable refuse les lancements répétés de `node.exe`) | **27 fichiers, 0 erreur** |
| Compilation SCSS | les deux commandes réelles du projet (§6) | feuilles **régénérées et byte-identiques** aux feuilles en place → source et compilés synchronisés (SHA1 `style.css` `CAE98AB513…`, `style.min.css` `60F5C48B48…`) |
| Sélecteurs phase D présents dans le CSS servi | recherche dans `style.css` et `style.min.css` | `rappels-modal__statut` 4 / 1 · `rappels-modal__statut--info` 1 / 1 · `rappel-histo__libelle` 1 / 1 · `html:has(.rappels-modal.active)` 2 / 1 |
| `prefers-reduced-motion` | recherche dans la feuille compilée | règle globale `_reset.scss` présente (1 occurrence) → couvre la modale |
| Migration SQL | — | **aucune** |

---

## 6. Commandes exactes exécutées

```powershell
# Référence et validation de la suite HTTP (base de test dédiée)
$env:TEST_HTTP_DB_USER='root'; $env:TEST_HTTP_DB_PASS=''
php tests/http/run.php

# Tests JS
node tests/js/router.test.mjs
node tests/js/rappels-modal.test.mjs

# Syntaxe
Get-ChildItem -Recurse -Include *.php -Path src,templates,config,public,tests | ForEach-Object { php -l $_.FullName }
node --experimental-vm-modules <sonde de parsing ES>   # jetable, supprimée après usage

# Compilation (commande réelle du projet, deux passes)
npx sass public/assets/scss/style.scss public/assets/css/style.css --style=expanded --embed-sources --quiet
npx sass public/assets/scss/style.scss public/assets/css/style.min.css --style=compressed --quiet

# Serveur de test pour la vérification navigateur (base de test, jamais la base de dev)
php -S 127.0.0.1:8099 -t public tests/http/server-router.php
```

Notes d'exécution honnêtes : `npx sass` écrit ses avertissements
`@import DEPRECATION` sur stderr, ce que PowerShell traduit parfois en
`ExitCode: 1` alors que la feuille est produite correctement — le contrôle retenu
est donc le **contenu et le hash** du fichier produit, pas le code de retour.
Les scripts temporaires de préparation de session navigateur et les sondes de
diagnostic ont été supprimés après usage.

---

## 7. Ce que ces tests **ne prouvent pas**

La suite HTTP prouve le serveur (rendu, autorisation, CSRF, RG1, absence d'effet
de bord, persistance). Elle ne prouve **aucun** comportement de la modale dans un
navigateur : ouverture réelle, focus, Échap, rendu visuel, console. Ces points
ont été vérifiés à part, dans un vrai navigateur, avec mesures et captures — c'est
l'objet du document `04-`, y compris ce qui reste non prouvé.
