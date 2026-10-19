# 01 · Audit préalable et architecture retenue

Audit effectué **avant toute écriture**, sur l'état réel du dépôt (branch
`main`, HEAD `ca40ef5`, 105 entrées en `git status` — l'arbre de travail porte
les phases 00 à C1 et n'a jamais été commité). Environnement mesuré :
PHP **8.3.33** (`php -v`), MariaDB **10.4.32** (`SELECT VERSION()` via le
harnais), base de développement `mes_orchidees`, base de test
`mes_orchidees_test` (9 tables).

---

## 1. Briques existantes, contrat réellement vérifié (lecture de code, pas de rapport)

### 1.1 `public/assets/js/core/modal.js` (158 lignes, lu intégralement)

| Élément du contrat | Comportement mesuré dans le code |
|---|---|
| API | `export function open(modalElement, triggerElement = null)` / `export function close(modalElement)` + `window.ModalManager = { open, close }` (L157). |
| Ouverture | refuse un élément nul (L61) ; ferme la modale précédemment active (L67) ; sauvegarde `lastFocusedElement = triggerElement \|\| document.activeElement` (L76) et `previousBodyOverflow` (L77). |
| État DOM | ajoute la classe `active` et bascule `aria-hidden="false"` (L79-85). |
| Scroll | `document.body.style.overflow = 'hidden'` (L89), restauré à la fermeture (L130). **Uniquement `<body>`.** |
| Clavier | un seul `keydown` document (remove puis add, L94-95) : `Escape` → `close()` (L41), `Tab` → `trapFocus(activeModal, event)` (L48). |
| Focus initial | après 50 ms, `modalElement.querySelector('.modal-close, .close-modal')` puis `focus()` ; repli sur `modalElement.focus()` (L101-109). |
| Fermeture | `close()` ne réagit que si `modalElement === activeModal` (L120) — pas de fermeture parasite. |
| Retour du focus | `lastFocusedElement.focus()` (L143 **avant** phase D) — sans option `preventScroll`. |

Conclusion : **le contrat convient à la phase D**, aucune réécriture nécessaire ;
deux de ses comportements ont dû être complétés (voir §3).

### 1.2 `public/assets/js/core/focus.js`

`trapFocus(modal, event)` : liste officielle des éléments focalisables,
filtrée sur `offsetWidth > 0 || offsetHeight > 0 || el === document.activeElement`
(L32-41), rebouclage premier/dernier avec `preventDefault()`. Si la modale ne
contient **aucun** élément focalisable visible, la fonction sort sans rien faire
(L44) — point sensible documenté en limites (§3.4).

### 1.3 `public/assets/js/core/router.js` + `app.js`

`getCurrentPage()` renvoie des clés stables ; `app.js` tient un registre
`featureInitializers` et **cloisonne chaque initialiseur dans un `try/catch`**
pour qu'un module qui échoue ne casse ni le reste de l'app ni l'enregistrement
du Service Worker. À l'audit, les clés MVC étaient toutes à `() => {}` avec ce
commentaire : « *en attendant les modules des prochaines phases* ». La phase D
est exactement cette phase-là.

### 1.4 `features/rappels-toast.js` (Phase C1)

Toast non bloquant alimenté par `GET /api/rappels/dus` (JSON), amorcé par le
`#rappels-toast-bootstrap` du footer, **transverse à toutes les pages**.
Contient déjà le marquage « lu » (`POST /notification/{id}/read`). Ce module
déclenche les toasts qui, en navigateur, passaient au-dessus du voile de la
future modale (problème n° 3 du §3).

### 1.5 Côté serveur

| Pièce | Constat à l'audit |
|---|---|
| `config/routes.php` | `GET /rappels` (GRANT_AUTH) + `POST /rappel/{id}/fait`, `/ignorer`, `/report/{jours}`, `/rappel/delete/{id}` + `GET /api/rappels/dus`. **Aucun endpoint de fragment HTML.** |
| `RappelController` | `index()` faisait la collecte **et** le classement (activation `planifie`→`actif`, ventilation en retard / aujourd'hui / à venir, historique) puis rendait `pages/rappels/index`. Les quatre actions étaient typées `: void` avec `addFlash()` + `redirectTo()` uniquement. |
| `AbstractController` | `render()` (avec layout), `renderPartial()` (sans layout — **présent mais aucun consommateur**), `json()`. `prefersJson()` existait **en privé dans `NotificationController`** (C1). |
| `templates/pages/rappels/index.php` + `_liste.php` | Le partial `_liste.php` rendait déjà les quatre sections, les cartes glass (`rappel-card`), badges, compteurs et formulaires POST CSRF. Réutilisable tel quel. |
| `templates/components/` | Aucun composant de modale ; la convention de markup était celle du site statique (`section.modal-overlay[role=dialog] > .modal-container > .modal-close`, styles dans `components/_modals.scss`, voile en `z-index: 500`). |
| `dashboard.php` / `show.php` | Une entrée texte vers `/rappels` (« Tous mes rappels »), pas de modale, pas de déclencheur. |
| `templates/layout.php` | CSP `script-src 'self'` + `upgrade-insecure-requests` ; les modules JS ne peuvent être chargés que par `app.js` (aucun script inline autorisé). |

### 1.6 Tests existants à l'audit

- `tests/http/run.php` : **290 PASS · 0 FAIL · 1 NOT RUN** (291 lignes, groupes
  PRE/A/B/C/C1/D/E/F/G/INT), base de test isolée, gardes d'isolation,
  `http_cleanup` en fin de run.
- `tests/js/router.test.mjs` : **39 PASS · 0 FAIL**, dont une vérification
  statique du registre `app.js` (B2) exigeant un `() => {}` sur les quatre clés
  MVC.
- Aucune automatisation navigateur.

---

## 2. Diagnostic factuel (état **avant** phase D)

1. **Pas de modale du tout** côté collection : la gestion des rappels
   supposait une navigation hors page (`/rappels`), ce que la mission refuse.
2. **Pas d'endpoint de fragment** : seule la page complète existait ; le seul
   endpoint « machine » était `/api/rappels/dus`, JSON, limité aux rappels dus
   non lus — insuffisant pour afficher les quatre sections et l'historique, et
   le transformer en API improvisée aurait violé la règle « ne pas transformer
   `/rappels` en API ».
3. **Logique de classement piégée dans `index()`** : sans extraction, la modale
   aurait dû dupliquer la ventilation des sections côté JS (interdit) ou
   recopier du PHP (duplication).
4. **Aucun moyen vérifiable pour le client de savoir si une action a réussi** :
   les quatre réponses étaient des 302 + flash. En `fetch`, suivre une 302 ne
   distingue pas « succès », « rappel déjà clôturé » et « RG1 refusé ». **Ce
   point est devenu un défaut réel, mesuré en navigateur** (§3.1).
5. **Deux lacunes du cœur modal.js, mesurées depuis** : verrou de scroll posé
   sur `<body>` seulement (le viewport réel est `<html>`), et retour du focus
   qui fait défiler la page (`focus()` sans `preventScroll`, à l'encontre du
   commentaire d'intention du fichier).
6. **Conflit d'empilement prévisible** : `$z-toast: 1000` (variables SCSS) au
   dessus du voile `.modal-overlay: 500` → les toasts C1 allaient masquer le
   bord droit de la fenêtre. Confirmé en navigateur.
7. **Contrainte structurelle du rendu** : depuis `templates/pages/collection/`,
   le chemin d'inclusion `__DIR__ . '/../components/…'` ne pointe **pas** sur
   `templates/components` (il remonte d'un cran seulement). La convention du
   dépôt est `dirname(__DIR__)` ou `dirname(__DIR__, 2)` selon la profondeur
   (précédent : `templates/pages/encyclopedie.php`). Ce piège a produit le
   défaut n° 1 de la phase (§3.2).

---

## 3. Architecture retenue (et pourquoi)

**Décision : fragment HTML rendu par le serveur, chargé à l'ouverture, actions
en POST négocié JSON.** Chaque alternative a été écartée sur preuve.

| Question | Choix | Motif / alternative écartée |
|---|---|---|
| D4 stratégie de chargement | **Endpoint PHP de fragment** `GET /rappels/modal`, rendu par `renderPartial('pages/rappels/modal')` qui inclut le **même** `_liste.php`. | Alternative écartée : réutiliser `/api/rappels/dus` et reconstruire les cartes en JS ⇒ duplication de la logique métier et de l'échappement côté client, explicitement interdit. Alternative écartée : rendre le contenu dans chaque page ⇒ 4 sections mortes sur `/macollection`, état périmé après action. |
| Sécurité du fragment | `GRANT_AUTH` (le front-controller renvoie la 403 générique) + défense en profondeur `uid === ''` → 403 + message minimal, **aucune donnée**. | Le visiteur anonyme ne reçoit jamais de contenu ; le jeton CSRF ne sort que dans une réponse authentifiée. |
| Structure | Extraction de `listsRappels()` (partagée par `index()` et `modal()`), les vues restent la seule source de rendu. | Aucune logique dupliquée : un seul endroit calcule aujourd'hui / en retard / à venir / historique. |
| Actions | **Mêmes routes C0**, mêmes `assertCsrf()` + filtres RG1 ; terminal `reply()` qui **négocie** : `Accept: application/json` (ou `X-Requested-With`) → JSON `{status,type,message,resultat}` ; sinon flash + 302 **strictement comme avant**. | Alternative écartée : créer `/api/rappel/{id}/…` ⇒ nouvelles routes, double surface, et la mission dit ne pas improviser d'API. Alternative écartée : suivre les 302 en fetch ⇒ fausse information (défaut mesuré §3.1). |
| A11y | Réutilisation **totale** de `core/modal.js` + convention de markup existante ; aucune réécriture du piègeage. | Alternative écartée : un second gestionnaire de modale « maison » ⇒ deux sources de vérité clavier/focus. |
| JS | Un module externe `features/rappels-modal.js`, branché dans le registre `app.js` sur `collection-dashboard` et `collection-detail`, no-op si le squelette est absent. | Aucun script inline (CSP), aucune dépendance nouvelle, aucune double-initialisation (garde `dataset.liee`). |
| Sans JS | Le déclencheur est un `<a href="/rappels">` réel ; la page autonome garde sections, cartes et formulaires. | La modale est un enrichissement, jamais un prérequis. |

### 3.1 Défaut réel né de ce diagnostic (navigateur, run n° 1)

Le premier jet du module testait `res.ok && res.redirected` pour décider du
succès. Mesure navigateur : la 302 est suivie par `fetch`, sa cible `http://…`
est réécrite en `https://` par `upgrade-insecure-requests` →
`net::ERR_CONNECTION_CLOSED` → **bannière rouge affichée alors que le soin
avait bien été enregistré**. D'où la négociation JSON (une logique métier, deux
rendus) plutôt qu'une chasse de redirection, et le message d'état **dans** la
fenêtre.

### 3.2 Ce que l'audit n'a pas suffi à voir (et qui a été tranché par mesure)

Le squelette inclus avec `__DIR__ . '/../components/rappels_modal.php'` depuis
`templates/pages/collection/` ne provoque **aucune erreur fatale** : `include`
d'un fichier manquant émet un warning et **0 octet**. La page restait valide,
le commentaire voisin s'affichait, la modale était vide. Diagnostic rendu par
sonde (existence du chemin résolu), corrigé par `dirname(__DIR__, 2)`, puis
vérifié par l'exécution (les deux lignes d'entrée de la suite HTTP sont passées
de 0 octet à PASS). Leçon : ici, un contrôle d'existence de fichier vaut plus
qu'une relecture du code.

### 3.3 Fichiers partagés touchés (volontairement, et signalé)

- `src/Controller/AbstractController.php` : promotion de `prefersJson()` en
  `protected` (copie privée de `NotificationController` supprimée) — C1 conserve
  un comportement identique.
- `public/assets/js/core/modal.js` : retour du focus en
  `focus({ preventScroll: true })` avec repli `try/catch`. Bénéfice pour **toutes**
  les modales du site ; le contrat de restitution est inchangé.
- `public/assets/scss/pages/_mvc-rappels.scss` : verrou de scroll et
  empilement **scopés** à `html:has(.rappels-modal.active)` — aucune autre
  modale n'est affectée, aucun fichier `core/...` SCSS n'est modifié.

### 3.4 Limite structurelle identifiée à l'audit, non corrigée

`trapFocus()` ne peut piéger le focus que **entre deux éléments
focalisables**. Si `document.activeElement` redevient `BODY` (rechargement du
fragment pendant une tabulation, par exemple), le `Tab` suivant repart dans
l'arrière-plan. Corriger cela imposerait de modifier la logique de piège du
cœur, utilisée par d'autres écrans : **décision laissée au propriétaire**,
comportement mesuré et documenté (voir `04-`).
