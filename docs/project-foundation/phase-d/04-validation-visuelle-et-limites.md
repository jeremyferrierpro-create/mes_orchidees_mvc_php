# 04 · Validation visuelle (navigateur réel) et limites

**Avertissement méthodologique :** une compilation CSS n'est **pas** une
validation visuelle. Ce document ne sépare pas ce qui a été *mesuré* de ce qui a
été *déduit* : chaque ligne indique comment elle a été obtenue, et ce qui n'a pas
pu être vérifié est explicitement marqué.

Un navigateur de test était **disponible** (subagent navigateur piloté en
interne) : la vérification a donc été faite, et elle a **trouvé six défauts
réels** que la suite HTTP de 320 assertions ne voyait pas.

---

## 1. Protocole reproductible

```powershell
# 1) Serveur de test sur la BASE DE TEST (jamais la base de développement)
$env:TEST_HTTP_DB_USER='root'; $env:TEST_HTTP_DB_PASS=''
php -S 127.0.0.1:8099 -t public tests/http/server-router.php

# 2) Preuve d'isolation AVANT toute manipulation
#    http://127.0.0.1:8099/__http-test-probe  →  attends « mes_orchidees_test »

# 3) Données de session, créées PAR L'APPLICATION (aucune écriture en base de dev)
#    /register → compte de test ; /encyclopedie → « Ajouter à ma collection » ;
#    /collection/{id} → enregistrer un soin (rappel_actif coché) → le rappel est
#    programmé par le mécanisme C0 ; /macollection → l'entrée de la modale.

# 4) Vérifications : ouverture depuis le tableau de bord et depuis la fiche
#    plante, actions, focus, scroll, console et réseau (DevTools).
```

Le routeur de test `tests/http/server-router.php` est celui de la suite HTTP :
il injecte les variables `DB_*` avant `config/env.php` (le `variables_order` du
poste est `GPCS`), prouve l'isolation par sa route `/__http-test-probe`, sert les
fichiers statiques de `public/` et délègue tout le reste au vrai
`public/index.php`. **Rien de l'application n'est simulé.**

Session de préparation utilisée pour les runs : compte
`http+beta.ui@example.invalid` sur `mes_orchidees_test`, un spécimen en
collection, quatre notifications couvrant les quatre sections (jour J, en retard
de 3 jours, planifié à +6 jours, traité à J-1). Le script de préparation était
**jetable et s'est autodétruit** ; le protocole ci-dessus repart de zéro sans lui.

---

## 2. Six défauts trouvés **en navigateur**, et leur correction (tous remesurés depuis)

| # | Défaut mesuré | Preuve de mesure | Cause racine | Correction |
|---|---|---|---|---|
| 1 | **Fausse bannière d'échec après un succès réel** : le soin était enregistré en base, la modale affichait une erreur rouge. | Console réseau : la `fetch` suit la 302, cible `https://…` → `net::ERR_CONNECTION_CLOSED`. Blocage fonctionnel. | `succes = res.ok && res.redirected` + CSP `upgrade-insecure-requests` qui réécrit la cible de la redirection ; une 302 ne distingue de toute façon pas succès / déjà clôturé / refus RG1. | **Négociation JSON côté serveur** (`reply()` sur les quatre routes C0) ; le client ne suit plus aucune redirection et teste `status === 'ok'`. Le parcours formulaire HTML (flash + 302) reste inchangé. |
| 2 | **Aucune confirmation visible** après une action (l'utilisateur ne savait pas si c'était enregistré). | Observation directe, fenêtre ouverte. | Le retour visuel était porté par un flash… affiché sur `/rappels`, page jamais atteinte. | Message d'état **dans** la fenêtre : `.rappels-modal__statut--ok\|--info\|--ko` dans la zone `aria-live="polite"`, texte `textContent` uniquement. |
| 3 | **L'arrière-plan continuait de défiler** fenêtre ouverte. | `window.scrollY` mesuré à **500** alors que la modale était ouverte. | `core/modal.js` pose `overflow: hidden` sur `<body>` seul ; or le scroller réel est `<html>`. | Règle scopée `html:has(.rappels-modal.active) { overflow: hidden }` — aucune autre modale touchée, aucun fichier cœur modifié. |
| 4 | **Les toasts C1 passaient au-dessus du voile** et masquaient le bord droit de la fenêtre. | Comparaison des couches rendues. | `$z-toast: 1000` > voile `.modal-overlay: 500`. | Tant que la modale est ouverte : `.toast-container { z-index: 480 }` (sous le voile) + le retour d'action est rendu **dans** la fenêtre (§2 #2), donc aucun message ne devient invisible. |
| 5 | **Les lignes d'historique n'affichaient pas la note du rappel** (une action clôturée devenait illisible). | Inspection du rendu. | `_liste.php` n'imprimait pas `message` dans la ligne d'historique. | `<span class="rappel-histo__libelle"><?= e($r->message) ?></span>` + repli en seconde ligne (`flex-wrap`, `order: 2`) pour ne pas écraser les badges. Mesuré depuis : la ligne clos affiche « Brumiser les feuilles — en retard ». |
| 6 | **À la fermeture, la page saillait** : la position de lecture était perdue. | Mesure avant/après sur le code non corrigé : le focus rendu faisait défiler jusqu'au déclencheur. | `lastFocusedElement.focus()` sans option — à l'encontre du commentaire d'intention du fichier (« il n'est pas renvoyé en haut de page »). | `focus({ preventScroll: true })` avec repli `try/catch` dans `core/modal.js` (bénéfice à toutes les modales). Remesuré : **scrollY 280 → 280** à la fermeture par Échap, focus revenu sur « Gérer mes rappels ». |

Deux autres relevés n'étaient pas des défauts mais des seuils :

- **Cibles tactiques** mesurées à **33 px** (et 19-23 px sur les mini-boutons)
  → portées à `min-height: 40px` dans la fenêtre (`WCAG 2.5.5` vise 44 px ;
  le projet applique déjà 40 px ailleurs, par exemple `.toast-rappel__read` en C1).
- **Repli d'erreur de chargement** : le corps passait par un état vide qui se
  lisait comme « aucun rappel » — remplacé par `.rappels-modal__erreur` avec lien
  de repli vers la page complète (et assertion HTTP `Aucun soin consigné par un
  simple chargement`).

---

## 3. Run de validation final (code corrigé, session propre)

Préalable nécessaire : la première tentative est repartie sur un **cookie de
session périmé** (session bound à un utilisateur supprimé par `http_cleanup()` du
suite, lancée entre-temps) — 0 plante affichée alors que la base contenait bien
les données. Le protocole impose donc désormais : **Déconnexion → reconnexion →
contrôle visuel « 1 plante, 4 sections »** avant toute mesure.

| ID | Scénario mesuré | Verdict | Détail mesuré |
|---|---|---|---|
| **A** | Ouverture depuis le **tableau de bord**, sans navigation | **CONFORME** | URL inchangée (`/macollection`), squelette rempli, quatre sections rendues, focus initial sur `.modal-close`, `Tab` reste contenu, `Échap` ferme et rend le focus au déclencheur. |
| **A2** | Ouverture depuis la **fiche plante** | **CONFORME** | Idem sur `/collection/{id}` ; le lien « Tous mes rappels → » du pied de fenêtre reste un vrai `href` (échappatoire clavier). |
| **B** | « Effectuer le soin » sur un rappel **en retard** | **CONFORME** | `POST /rappel/<id>/fait` → **200** JSON `{status:ok,type:success,resultat:soin_consigne,prochain_rappel:true,statut:traite}` (193 octets), **un seul** `GET /rappels/modal` ensuite, **aucun** `GET /rappels`, **aucune** bannière rouge ; message vert « Soin consigné. Prochain rappel programmé. » ; **un seul** soin écrit en base ; carte déplacée vers l'historique. |
| **C** | Seconde frappe sur une carte déjà clôturée | **NON TESTABLE structurellement** | Un rappel clos n'expose plus de bouton « effectuer le soin » dans la fenêtre ; le cas est néanmoins prouvé côté serveur (assertion JSON `deja_cloture` · `info` · soins 0 → 0). |
| **D** | « Ignorer » | **CONFORME** | Message « Rappel ignoré. », cartes ouvertes **2 → 1**, aucune navigation. |
| **E** | « Supprimer » | **CONFORME** | `confirm()` natif respecté, message « Rappel supprimé. », historique **3 → 2**, aucune navigation. |
| **F** | Retour du focus **sans saut de page** | **CONFORME sur le comportement** | `scrollY` **280 → 280** après `Échap`, focus revenu sur « Gérer mes rappels ». La valeur cible de 600 n'a pas pu être atteinte sur cette page (le déclencheur est alors hors viewport) : c'est la variation de position qui est mesurée, pas une valeur absolue. |
| **G** | Console et ressources | **CONFORME** | **0 message** en console sur toute la session ; toutes les requêtes applicatives en **200** ; `rappels-modal.js`, `modal.js`, `focus.js`, les deux feuilles CSS et les images servis depuis `'self'`. |
| **H** | RG1 visuel : fiche d'une plante tierce | **CONFORME** | `GET /collection/{id}` d'un autre compte → **404** (page d'erreur), aucune modale servie. |
| **I** | Mobile | **PARTIELLEMENT VÉRIFIÉ** | Voir limites §4.2. |
| **J** | Session expirée **dans la fenêtre** | **NON VÉRIFIÉ en navigateur** | Voir limites §4.4. |

### 3.1 Preuves visuelles : ce qui est conservé et ce qui ne l'est pas

La preuve de la validation navigateur est **numérique et consignée dans ce
document** (§2 et §3 : `scrollY 500`, puis `280 → 280`, cartes `2 → 1`,
historique `3 → 2`, réponse JSON `soin_consigne` 193 octets, compteurs de sections,
et table de verdicts §5). Ce sont ces mesures qui engagent la phase, pas des
images.

Les captures produites pendant les sessions l'ont été dans **l'espace de travail
éphémère de l'outil navigateur**, hors du dépôt : elles **n'ont pas été
enregistrées dans `tests/`** et **n'apparaissent pas** dans `git status` (vérifié
: 0 fichier `.png` de capture sous `tests/` et dans tout le dépôt). Par
conséquent, **aucune décision d'ignor ou de purge de PNG n'est requise pour la
phase D** — il n'y a rien à commettre ni à nettoyer. La reproductibilité passe
par le protocole §1, qui recrée la session de zéro et retient les mêmes mesures.

---

## 4. Limites assumées

### 4.1 Piègeage de focus : évasion possible par `<body>` (décision propriétaire)

`core/focus.js` ne peut faire reboucler le focus **qu'entre deux éléments
focalisables**. Si `document.activeElement` redevient `BODY` (rechargement du
fragment pendant une tabulation, mise à jour d'un bouton détruit par le
rafraîchissement), le `Tab` suivant repart dans l'arrière-plan. Constaté en
navigation clavier. **Non corrigé** : la correction impose de toucher la logique
de piège du cœur, partagée avec les autres écrans du site. Reproduction : ouvrir
la modale, atteindre le bord du contenu, `Tab`. Mitigation en place : après
chaque rafraîchissement le focus est reposé sur `#rappels-modal-title`
(`tabindex="-1"`), ce qui réduit la fenêtre de bascule à un cas précis.

### 4.2 Mobile 390 × 844 : pas de capture native

Le redimensionnement natif du viewport n'était pas disponible dans la session
(`window.resizeTo` ignoré, état `NATIVE_BROWSER_VIEWPORT_UNAVAILABLE`). La
validation a donc été faite **par procuration** : iframe même-origine de 386 px
largeur, mesurant `scrollWidth` vs `clientWidth` → **aucun débordement
horizontal** ; la boîte reste `width: min(92%, 700px)` avec `max-height: 88vh` et
`overflow-y: auto`, la grille des cartes est `auto-fit` donc monocolonne sous
~700 px. **Ce que cela ne prouve pas** : le rendu réel sur un appareil tactile
(tailles de police physiques, safe areas, clavier virtuel, `:hover` sur tactile).
Statut : **PASS WITH LIMITATIONS**, à revalider manuellement sur téléphone.

### 4.3 Service Worker : 404 intermittents et precache stale

Pendant les runs d'audit, des `404` de ressource déclenchés par le Service
Worker sont apparus en console (ils ont disparu sur la session finale, **0
message**), et le precache `v7` de `public/sw.js` conserve une **taille obsolète
de `style.css`** depuis les recompilations des phases précédentes. Cet écart de
manifeste de precache n'est **pas** un défaut de la phase D, mais il doit être
tranché avant publication (les phases C1 et 04-07 le signalent déjà).

### 4.4 « Session expirée » dans la fenêtre : partiellement prouvé

Prouvé côté serveur par la suite HTTP : anonyme → **403** sans fuite de contenu,
et `modal()` renvoie un paragraphe d'erreur minimal si `uid === ''` ; jeton
périmé → **400** en HTML comme en JSON, **jamais 200**, état intact. Le front
affiche bien les messages distincts (400 / 403 / réseau). **Non mesuré** dans un
navigateur avec une session volontairement expirée de bout en bout (le bac à sable
et le harnais n'offraient pas de voie propre pour faire expirer la session côté
serveur pendant une session live sans toucher la base de développement). Protocole
manuel reproductible : ouvrir la modale, supprimer le cookie de session dans un
second onglet, déclencher « Effectuer le soin » → attendre « Session fermée :
reconnectez-vous pour gérer vos rappels. », **aucune** écriture en base, la carte
restant ouverte.

### 4.5 Ce qui reste structurellement non testable ici

- Pas de tests automatisés clavier/souris (aucune dépendance de type Playwright
  autorisée) : les items A/B/D/E/F/G proviennent de sessions navigateur pilotées,
  avec valeurs lues dans la page et dans la base — pas d'un runner rejouable en CI.
- Lecteur d'écran réel : **non vérifié** (aucun NVDA/JAWS/VoiceOver dans
  l'environnement). L'implémentation suit les attributs requis
  (`role="dialog"`, `aria-modal`, `aria-labelledby`, `aria-hidden` basculé par
  `core/modal.js`, `aria-live="polite"`, libellés `sr-only`, `×` avec
  `aria-label`), mais « attributs présents » n'est pas « annonce correcte ».
- Le cas C (double frappe) est prouvé au serveur, pas à la fenêtre (la carte
  clôturée ne présente plus l'action).
- Les captures d'écran ne sont pas des preuves de conformité RGAA complète : la
  phase D ne revendique **aucune** certification d'accessibilité, seulement la
  mise en œuvre des critères utiles à la fenêtre et leur contrôle mesuré.

---

## 5. Verdict visuel et a11y

| Exigence de la phase D | Statut |
|---|---|
| D1 entrées sans navigation accidentelle, `APP_BASE_PATH` respecté | **VERIFIED** (HTTP + navigateur) |
| D2 contenu (4 sections, compteurs, cartes, plante, type de soin, échéance, fréquence, états vides) | **VERIFIED** (HTTP + navigateur) |
| D3 actions réelles préservées, 4 sémantiques distinctes, C0 obligatoire pour « effectuer le soin », aucune fausse réussite avant confirmation serveur | **VERIFIED** — la correction du défaut n° 1 est exactement ce point |
| D4 stratégie de chargement (rendu serveur, pas d'API improvisée, no-JS utilisable) | **VERIFIED** |
| D5 ouverture clavier, fermeture Échap, focus initial, retour du focus, annonce des rafraîchissements | **VERIFIED**, **sauf** évasion par `<body>` (§4.1) et lecteur d'écran réel (§4.5) |
| D5 piègeage de focus | **PASS WITH LIMITATIONS** (§4.1) |
| D5 verrou du scroll, `prefers-reduced-motion` | **VERIFIED** (défaut n° 3 corrigé et remesuré ; règle globale `_reset.scss` héritée) |
| `inert` de l'arrière-plan | **NON MIS EN ŒUVRE** — le projet n'utilise `inert` nulle part (0 occurrence) ; convention `aria-modal` + piègeage conservée |
| Design verre, contraste, badges, responsive | **PASS WITH LIMITATIONS** (navigateur desktop OK ; mobile §4.2 ; cibles 40 px §2) |
| Console et ressources en navigateur réel | **VERIFIED** (0 message, tout en 200 sur la session finale) |

**Bilan de la phase D : PASS WITH LIMITATIONS.**
