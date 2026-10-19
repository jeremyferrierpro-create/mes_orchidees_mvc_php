# 02 · Validation fonctionnelle intégrée (Lot 1)

Méthode : **preuves triples** sur chaque parcours — (1) code réellement lu,
(2) assertions de la suite HTTP (`php tests/http/run.php`), (3) parcours effectifs
dans un **vrai navigateur** contre le serveur de test sur la base de test
(`php -S 127.0.0.1:8099 -t public tests/http/server-router.php`, isolation prouvée
par `/__http-test-probe` → `mes_orchidees_test`).

## 0. Transparence sur le jeu de données de session

- **v1 (1ᵉʳ run navigateur)** : ma sonde jetable de préparation contenait deux
  artefacts — le rappel « jour J » était daté de **demain** (`now +8 hours`
  calculé après 16 h) et **trois rappels ouverts empilés sur la même plante**
  (état que l'application ne produit jamais grâce à sa déduplication). Les
  scénarios dépendant de ces états ont été logiquement marqués PARTIEL par le
  run ; **aucun n'a été requalifié en succès sur cette base**.
- **v2 (run définitif)** : sonde corrigée (3 plantes, 1 rappel ouvert max par
  plante, échéance jour J posée explicitement à `date du jour 08:00`), session
  entièrement re-exécutée. Verdicts ci-dessous = **v2**, sauf mention.
- La sonde est **jetable** : elle s'est auto-supprimé après `--wipe`
  (résidus vérifiés : notifications 0, collections 0, orchids 0) et la suite
  HTTP relancée après le run confirme « Aucune donnée de test avant l'exécution :
  0 » et 0 résidu après.

---

## A. Enregistrement d'un soin

| Exigence | Preuve code | Preuve suite HTTP | Preuve navigateur (v2) | Statut |
|---|---|---|---|---|
| Choix explicite programmer/ou non | `SoinController::create()` L95-107 (`rappel_actif` absent→1, « 0 »→désactivé) | G : « Opt-in activé → rappel programmé » · « Opt-in désactivé → rappel_actif = 0 » (PASS) | Fiche P1 : case « Programmer le prochain rappel (tous les 7 jours) » ** cochée ** (reflète bien `rappel_actif`, lu ≠ désactivé) | **PASS** |
| Choix persisté | `setRappelActifForUser()` | G : « collections.rappel_actif = 1 persisté » (PASS) | Après soin décoché, la fiche P1 affiche le bandeau `rappel-bandeau--suspendu` et le rappel reste éteint au rechargement | **PASS** |
| Soin réellement consigné | `$this->soins->create($soin)` | G (soins créés/reliés) | 2 soins visibles dans l'historique de la fiche avec mes notes exactes (« Soin E2E activé » / « Soin E2E désactivé ») | **PASS** |
| Désactivation conforme à la décision métier documentée (annulation **non destructive**) | `RappelService::annuler()` L71-77 : `planifie/actif` → `ignore`, JAMAIS de DELETE | G : « Rappel annulé mais CONSERVÉ (statut ignore, non supprimé) » (PASS) | /rappels après S6 : la ligne de P1 apparaît en **Historique avec badge IGNORÉ** et son message complet (« prochain soin (arrosage) prévu le 25/10… ») — ligne conservée, pas supprimée | **PASS** |
| Aucun doublon de rappel | `generer()` L112-120 : reprise du dernier `planifie/actif` au lieu d'empiler | G : « Pas d'empilement : toujours 1 seul rappel pour cette plante » (PASS) · C1 : « Chargements répétés : aucun rappel créé, aucun doublon » (PASS) | S5 : soin enregistré sur P1 (échéance du jour) → le rappel est **replanifié en place** (« À venir » 25/10), total ouvert = 1 par plante (3 plantes → 3 cartes) | **PASS** |
| Type du soin conservé vers le rappel | `generer()` L117/L129 (`idTypeSoin` porté) | G/D (JSON `resultat`, badges) | S2 : rappel P2 affiche le libellé **Engrais** hérité ; S4 après « effectuer le soin », le nouveau rappel porte **Engrais** (type du soin effectué) | **PASS** |
| Historique à type inconnu traité proprement | `?id_type_soin` NULL autorisé + rendu avec repli | D : « Type de soin inconnu : repli sans erreur serveur » (PASS) | Non redémontré en navigateur (les lignes d'historique de la session avaient toutes un type) | **PASS** (preuve serveur ; navigateur NON VÉRIFIÉ sur ce sous-cas) |

## B. Cycle de vie des rappels

| Exigence | Preuve | Statut |
|---|---|---|
| États aujourd'hui/en retard/à venir/historique rendus | S2 (v2) : sections 1/1/1/2 cartes, intro « 1 à faire aujourd'hui · 1 en retard · 1 à venir » | **PASS** |
| Compteurs, dates, statuts, types, fréquences affichés | S2 : « En retard de 3 j », « Tous les 7 j », « Prévu le 24/10/2026 », badges TRAITÉ/IGNORÉ + notes d'historique | **PASS** |
| Effectuer le soin | Suite D/MDL + S4 : `POST /rappel/{id}/fait` 200 JSON `soin_consigne`, carte → historique, **replanification +7 j** affichée, un seul POST + un seul GET du fragment | **PASS** |
| Ignorer | Suite MDL (HTML + JSON) + session D (run du 18/10, v1 — mécanique conforme) | **PASS** |
| Reporter | Suite G (+7/+14, bornes [1;365] refusées) + S2-run1 : carte 24/10 → 31/10, flash « Rappel reporté de 7 jour(s). » | **PASS** |
| Supprimer | Suite D (« Notification réellement supprimée de la base ») + session D phase D (confirm() respecté, historique 3→2) | **PASS** |
| Consulter la plante | Run 1 #7 : navigation fiche plante OK | **PASS** |
| Une action ne touche que les données de l'utilisateur (RG1) | Suite MDL/D : traversées HTML 302-sans-effet et JSON `ko/introuvable`, 403 anonyme, 404 fiche tierce | **PASS** |

## C. Notification persistante du jour J

| Exigence | Preuve | Statut |
|---|---|---|
| Éligibilité : échu **et** non lu seulement | S1 (v2) : `GET /api/rappels/dus` 200 `count:2` = P1 (jour J) + P2 (retard) ; **aucun** toast pour le planifié (+6 j) ni le traité | **PASS** |
| Persiste jusqu'à action explicite | S1 : toast présent au rechargement tant que non lu | **PASS** |
| Fermer ≠ lu ≠ ignorer ≠ effectuer | S1 : croix × = **0 requête**, toast **réapparaît** après reload (état serveur intact) ; « Marquer comme lu » = POST `read` 200 **puis** retrait ; ignorer/effectuer sont des actions distinctes de /rappels et de la modale (sémantiques C0/C1 prouvées suite MDL/C1) | **PASS** |
| Échecs réseau/invalides sans faux succès | Code `rappels-toast.js` L156-171 (retrait seulement après `status:ok`, message d'échec sinon) ; suite C1 (marquage lu refusé sans bascule d'état) | **PASS** (serveur + code) ; le **run navigateur de l'échec réseau** reste NOT RUN (même limite que la ligne C1 NOT RUN de la suite) |
| Comportement sans JavaScript documenté | Le toast est une amélioration ; la page /rappels (repli) rend sections/cartes/actions sans JS (suite MDL « Repli sans JS » PASS) | **PASS** |
| Cohérence toast ↔ actions faites ailleurs | **Défaut reproduit (non corrigé à ce stade)** : après « Effectuer le soin » réussi dans la modale (S4), le toast du même rappel **reste affiché** jusqu'au prochain chargement (où `/api/rappels/dus` ne le renvoie plus). Non bloquant, cosmétique ; correctif minimal candidat : retirer le toast correspondant quand la modale confirme la clôture de ce rappel id | **FAIL (constaté, pas encore corrigé — voir `05-` à venir)** |

## D. Fenêtre modale « Gérer mes rappels »

| Exigence | Preuve (v2, S3/S4) | Statut |
|---|---|---|
| Ouverture depuis tableau de bord ET fiche plante | Suite MDL (entrées 200) + S3 fiche plante : URL inchangée, `GET /rappels/modal` 200 ; dashboard prouvé run phase D + suite | **PASS** |
| Données de l'utilisateur connecté seulement | Suite MDL RG1 (fragment userB sans données userA, 403 anonyme) | **PASS** |
| Actions + actualisation depuis l'état serveur | S4 : 1 POST → 200 JSON → 1 GET fragment → sections recalculées (« 1 à faire aujourd'hui · 2 à venir »), message vert dans la fenêtre, **aucune navigation** | **PASS** |
| Fermeture bouton + Échap, focus initial et retour | S3 : focus initial sur `.modal-close` (libellé accessible complet), Échap ferme, focus **revenu sur le lien déclencheur**, scrollY 400→398→398 sans saut | **PASS** |
| Verrouillage du défilement | S3 : `overflow hidden` effectif (molette ne défile plus) | **PASS** |
| États chargement/vide/erreur | Suite MDL (état vide soigné, repli erreur) + runs D ; l'« état de chargement » transitoire n'a pas été photographié (trop rapide) — couvert par le code (`aria-busy`) | **PASS** (chargement : code + suite, pas d'observation visuelle directe) |
| Repli /rappels sans JS | Suite MDL PASS ; le lien « Tous mes rappels → » reste un vrai `href` (S3 : « Ouvrir la page complète… » présent dans le fragment) | **PASS** |
| Annonce des retours d'action | Le `<p class="rappels-modal__statut">` est **inséré dans** `.rappels-modal__body` qui porte `aria-live="polite"` (vérifié dans le gabarit, L37) — l'affirmation « aucune zone d'annonce » du rapport navigateur n'est **pas confirmée par le code** ; l'annonce réelle par un lecteur d'écran reste à valider au Lot 3 | **NOT VERIFIED** (awaiting real screen reader) |

## E. Non-régression globale à l'issue du Lot 1

| Contrôle | Valeur |
|---|---|
| Suite HTTP (run final après sessions navigateur) | **320 PASS · 0 FAIL · 0 BLOCKED · 2 NOT RUN · 0 NOT VERIFIED** — identique à l'entrée de phase ; les 2 NOT RUN sont ceux documentés en phase D (aucun nouveau) |
| `node tests/js/router.test.mjs` / `rappels-modal.test.mjs` | **39 PASS · 0 FAIL** / **23 PASS · 0 FAIL** |
| `php -l` (79 fichiers) | 0 erreur |
| Console navigateur sur les 2 sessions | 0 message d'erreur |
| Réseau | Aucun 4xx/5xx non attendu sur les parcours |
| Découverte hors périmètre immédiate | Les pages chargent encore 6 JSON du prototype statique (`assets/js/data/*.json`, 200) — **code mort préexistant**, sans effet sur les rappels ; à traiter comme dette (décision propriétaire), PAS modifié dans ce Lot |

**Verdict Lot 1 : le périmètre fonctionnel A/B/C/D est validé dans l'application
réelle. Un défaut mineur reproduit (persistance du toast après action dans la
modale) est documenté, non corrigé à ce stade ; une mesure d'annonce lecteur
d'écran reste ouverte au Lot 3.**
