# 01 · État initial et inspection (Étape 0)

Inspecté **dans le dépôt réel**, le 2026-10-18, avant toute modification de la
phase E. Aucune commande destructive exécutée ; aucun `git add`, commit ou push.

---

## 1. État Git

| Élément | Valeur mesurée |
|---|---|
| Branche | `main` |
| HEAD | `ca40ef5` — « docs(phase06): infrastructure decision dossier, git publication audit and go/no-go matrix » |
| Index (staged) | **vide** — rien n'a jamais été ajouté à l'index |
| Entrées `git status --porcelain` | **82** = 48 modifiées/supprimées suivies + 34 non suivies |
| Préservation des phases précédentes | Intégrale : les travaux non commités des phases 00→D sont tous présents et **intacts** (aucune restauration, aucun écrasement) |

Fichiers modifiés suivis remarquables : `config/routes.php`, les contrôleurs/
entités/dépôts MVC, `templates/layout.php`, `app.js`, `core/modal.js`,
`core/router.js`, les feuilles compilées `public/assets/css/style*.css(+map)`,
`public/sw.js`, seeds et `data/schema_mysql.sql`.
**Suppressions suivies non commitées** : `public/assets/scss/style.css` et
`.map` (fichiers compilés mal placés, retirés aux phases précédentes) — la
phase E ne restaure ni ne valide rien à ce stade ; à justifier avant tout commit.

Non suivis notables : `src/Controller/RappelController.php`,
`src/Service/RappelService.php`, `templates/components/rappels_modal.php`,
`templates/pages/rappels/`, `public/assets/js/features/rappels-modal.js`,
`rappels-toast.js`, `public/assets/scss/pages/_mvc-rappels.scss` (+ autres
partial SCSS), `tests/http/`, `tests/js/`, `data/migrations/20261018_*.sql`
(5), `docs/project-foundation/phase-07|c1|d`, `docs/architecture/`, `.vscode/`.

## 2. Périmètre rappels/soins/notifs/modales (cartographie réelle)

| Domaine | Fichiers vérifiés présents |
|---|---|
| Serveur | `src/Controller/{Soin,Rappel,Notification,Collection}Controller.php`, `src/Service/RappelService.php`, `src/Entity/{Notification,Soin,TypeSoin}.php`, `src/Repository/*`, `config/routes.php` (routes `POST /soin/new`, `GET /rappels`, `GET /rappels/modal`, `POST /rappel/{id}/{fait|ignorer}`, `POST /rappel/{id}/report/{jours}`, `POST /rappel/delete/{id}`, `GET /api/rappels/dus`, `POST /notification/{id}/read`) |
| Vues | `templates/pages/rappels/{index,_liste,modal}.php`, `templates/components/rappels_modal.php`, `templates/pages/collection/{dashboard,show}.php` |
| Client | `public/assets/js/features/{rappels-modal,rappels-toast}.js`, `core/{modal,focus,notifications}.js`, registre `app.js` |
| Styles | `public/assets/scss/pages/_mvc-rappels.scss`, `components/{_modals,_toast,_cards,_badges}.scss` → feuilles servies `public/assets/css/style{,.min}.css` |

## 3. Rapports de phases — incohérences relevées

1. `docs/project-foundation/` contient **phase-00 → phase-07, phase-c1,
   phase-d**. Il n'existe **aucun dossier de rapport pour les phases A, B et C0**
   (grep confirmant : 0 occurrence de « phase A/B/C0 » comme rapport dans ces
   documents). Leur périmètre est malgré tout **matérialisé** dans le code
   (migrations `20261018_*.sql`, commentaires « phase C0 », `docs/architecture/`)
   et couvert par les groupes de la suite HTTP (G, D, C1, MDL…). La phase E
   travaille donc sur le **code réel** comme source de vérité pour ces phases.
2. La limite « captures navigateur non conservées dans le dépôt » est actée
   depuis la correction de `phase-d/04` (les preuves sont numériques).
3. `public/sw.js` : cache `mes-orchidees-v7`. Stratégie **network-first**
   (`fetch()` d'abord, cache seulement en fallback d'échec réseau) → **aucun
   risque de servir une feuille ancienne pendant que le serveur est joignable**.
   Le precache ne liste **pas** `rappels-modal.js`/`rappels-toast.js`
   (cohérent avec la politique « aucune page privée en cache », mais le mode
   hors-ligne des pages de collection ne pourra pas initialiser la modale —
   limite documentée, préexistante). La taille consignée de `style.css` dans le
   precache est l'obsolescence déjà signalée aux phases précédentes ; sans effet
   en ligne du fait du network-first.
4. Fichiers de test JS réellement présents : `tests/js/router.test.mjs` et
   `tests/js/rappels-modal.test.mjs`. **Aucun fichier de test JS dédié aux
   notifications** : leur couverture réelle est le groupe **C1** de la suite
   HTTP (19 lignes) + les contrôles navigateur. La mission mentionnait « les
   tests JavaScript de notifications déjà présents » — **ils n'existent pas** ;
   ce n'est pas transformé en succès (voir `04-`).

## 4. Sources SCSS ↔ CSS compilés — vérification par recompile temporaire

Méthode : compilation des sources vers des fichiers **temporaires**
(`_tmp_e_sync*.{css,map}`, supprimés ensuite), puis comparaison de contenu avec
les feuilles **servies** (aucun écrasement des fichiers en place).

| Feuille servie | Résultat |
|---|---|
| `public/assets/css/style.css` (expanded + embed-sources) | **Contenu identique** à la recompilation, à la ligne `sourceMappingURL` près (cette ligne incorporate le NOM DU FICHIER DE SORTIE — artifact de nom, pas une divergence) |
| `public/assets/css/style.min.css` (compressed) | **Contenu identique**, même seule nuance de nom de map |

Commande officielle utilisée (telle que documentée aux phases précédentes) :
`npx sass public/assets/scss/style.scss <sortie> --style=expanded --embed-sources --quiet`
puis `--style=compressed`. ⚠️ Enseignement méthodologique retenu : la
comparaison par **hash brut** des fichiers est trompeuse ici (le nom de sortie
est embarqué dans le map URL) ; la comparaison de **contenu hors ligne de map**
est la bonne mesure.

## 5. Exécution de référence (état d'entrée de la phase E)

| Contrôle | Commande | Résultat |
|---|---|---|
| Suite HTTP | `php tests/http/run.php` (base de test) | **320 PASS · 0 FAIL · 0 BLOCKED · 2 NOT RUN · 0 NOT VERIFIED** · 333 requêtes · isolation `mes_orchidees_test` (9 tables) · 0 résidu avant/après · base de dev inchangée |
| Test JS routeur | `node tests/js/router.test.mjs` | **39 PASS · 0 FAIL** |
| Test JS modale | `node tests/js/rappels-modal.test.mjs` | **23 PASS · 0 FAIL** |
| Syntaxe PHP | `php -l` récursif `src,templates,config,public,tests` | **79 fichiers · 0 erreur** |

**Conclusion Étape 0 : état sain et cohérent avec la fin de phase D ;
aucune régression latente détectée ; les incohérences documentées sont des
écarts de RAPPORTS (phases A/B/C0 sans dossier propre) et des limites connues
(SW, tests JS notifications inexistants), pas des pannes.**
