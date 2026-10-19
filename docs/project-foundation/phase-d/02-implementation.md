# 02 · Implémentation

Tous les fichiers ci-dessous sont **relus dans le dépôt avant écriture** ; les
paths sont donnés relativement à la racine. Aucune dépendance nouvelle, aucune
migration SQL, aucun script inline, aucune route d'écriture en GET.

---

## 1. Serviteur : endpoint de fragment et logique partagée

### 1.1 `config/routes.php` (modifié)

```php
// Phase D : fragment HTML de la modale « Gérer mes rappels » (mêmes listes que
// /rappels, rendu sans layout via renderPartial). GRANT_AUTH : un visiteur
// anonyme reçoit la 403 générique du front-controller, jamais un contenu tiers.
$router->mapController('GET', '/rappels/modal', \App\Controller\RappelController::class, 'modal', GRANT_AUTH);
```

- **Une seule route ajoutée**, en GET, de lecture pure.
- Les quatre routes d'écriture C0 (`/rappel/{id}/fait`, `/ignorer`,
  `/report/{jours}`, `/rappel/delete/{id}`) sont **inchangées** : mêmes
  méthodes, mêmes grants, mêmes contrôleurs.

### 1.2 `src/Controller/RappelController.php` (modifié)

| Changement | Détail |
|---|---|
| Extraction `listsRappels(string $uid): array` (L70-92) | Déplace **tel quel** le code d'`index()` : `activateDueReminders()` (mécanisme C0 idempotent), ventilation `actif`+`isLate()` → en retard, `actif`+`isDueToday()` → aujourd'hui, sinon à venir ; historique par requête distincte (`traite`, `ignore`). Retourne `compact('aujourdhui','enRetard','aVenir','historique')`. |
| `index()` (L102-119) | Ne fait plus que `listsRappels()` + `render('pages/rappels/index', …)`. **Comportement et sortie inchangés** pour `/rappels`. |
| `modal()` (L143-162) | Nouveau : `uid === ''` → `http_response_code(403)` + paragraphe d'erreur minimal (aucune donnée) ; sinon **les mêmes** listes → `renderPartial('pages/rappels/modal', …)` avec `user` et `csrf`. |
| Terminal `reply(string $message, string $type, array $extra = []): ?string` (région L383) | Nouveau : si `prefersJson()` → `json({status, type, message, …extra})` (`status` = `ko` si `type === 'error'`, sinon `ok`) ; sinon `addFlash()` + `redirectTo(returnUrl())` — **exactement le comportement C0**. |
| `fait()` / `ignorer()` / `report()` / `delete()` | Signature `: void` → `: string|null`, corps métier **inchangé** (CSRF, RG1, consignation du soin, replanification conditionnelle à `collections.rappel_actif`), chaque terminal passe par `reply()`. |

Vocabulaire `resultat` renvoyé en JSON (aucun code d'erreur technique exposé) :
`introuvable` · `deja_cloture` · `soin_consigne` (+ `prochain_rappel: bool`,
`statut`) · `traite` · `ignore` · `reporte` (+ `nouvelleDate`) ·
`report_invalide` · `supprime`.

### 1.3 `src/Controller/AbstractController.php` (modifié, fichier partagé)

`prefersJson()` promu `protected` (L129) :

```php
protected function prefersJson(): bool
{
    $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
    return str_contains($accept, 'application/json')
        || (string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
}
```

`NotificationController` (C1) conserve une négociation **identique** : sa copie
privée a été supprimée. Le groupe C1 de la suite HTTP (19 lignes) reste à
0 FAIL, y compris la ligne « Marquage „lu“ JSON par le propriétaire ».

### 1.4 Vues

| Fichier | Rôle |
|---|---|
| `templates/components/rappels_modal.php` (**nouveau**, 40 lignes) | Squelette serveur de la fenêtre : `section.modal-overlay.rappels-modal#rappels-modal[role=dialog][aria-modal=true][aria-hidden=true][aria-labelledby=rappels-modal-title]` > `.modal-container` > `button.modal-close[data-rappels-modal-close][aria-label="Fermer la fenêtre (aucune donnée modifiée)"]`, `h2#rappels-modal-title[tabindex=-1]`, `div.rappels-modal__body[data-endpoint=<?= e(url('/rappels/modal')) ?>][aria-live=polite]`. Corps **vide** au rendu : c'est le chargement à l'ouverture qui le remplit. |
| `templates/pages/rappels/modal.php` (**nouveau**, 44 lignes) | Corps du fragment : paragraphe `.rappels-modal__bilan` calculé depuis les listes réelles (« Vous avez 2 à faire aujourd'hui · 1 en retard … », ou « Aucun rappel ouvert pour le moment. »), puis `include _liste.php` (le **même** partial que la page autonome), puis lien de repli vers `/rappels`. |
| `templates/pages/rappels/_liste.php` (modifié) | **Une seule** addition phase D : la ligne d'historique affiche désormais la note du rappel clos — `<span class="rappel-histo__libelle"><?= e($r->message) ?></span>` (défaut relevé en navigateur réel, voir `04-` §3). Tout le reste (cartes, badges, compteurs, formulaires, `sr-only` sur les libellés d'action) est celui de C0. |

Les cinq actions d'une carte restent des `<form method="post">` avec
`<input type="hidden" name="csrf_token">` et un libellé explicite étendu par un
`<span class="sr-only"> : nom de la plante</span>` — « Effectuer le soin »,
« +7 j », « +14 j », « Ignorer », « Supprimer ce rappel ». Aucune carte ne
devient une icône muette.

### 1.5 Entrées (D1)

| Fichier | Markup livré |
|---|---|
| `templates/pages/collection/dashboard.php` | Bloc `.collection-rappels-entry` sous le fil de présentation (L33-39) : `<a class="btn-secondary" href="<?= e(url('/rappels')) ?>" data-rappels-modal-trigger aria-controls="rappels-modal" aria-haspopup="dialog">Gérer mes rappels</a>`, puis `include dirname(__DIR__, 2) . '/components/rappels_modal.php';` (L166). |
| `templates/pages/collection/show.php` | Deux entrées selon l'état des rappels de la plante (bandeau actif L67-68 / suspendu L80-81), même markup, plus le même `include` (L208). |

`url()` est utilisé partout : le préfixe d'installation `APP_BASE_PATH` est
respecté, aucun chemin n'est durci dans le JS (vérifié par test, voir `03-` §4).
Sans JavaScript, le déclencheur est un lien réel vers `/rappels`.

---

## 2. Client : `public/assets/js/features/rappels-modal.js` (**nouveau**, 238 lignes)

Module externe, importé une seule fois par `app.js` (L37) et branché sur
`collection-dashboard` + `collection-detail` (L61-62) — les clés MVC des deux
pages de collection. Aucun `<script>` ajouté à `layout.php`.

| Responsabilité | Implémentation |
|---|---|
| No-op propre | Sortie immédiate si `#rappels-modal` absent, si le corps n'a pas de `data-endpoint`, ou si `dataset.liee === '1'` (garde anti-double-lier, L105-120). |
| Ouverture (délégation `click` sur `document`, L125-141) | Cible `[data-rappels-modal-trigger]` ; **respecte le clic modifié** (`button !== 0` ou Ctrl/Meta/Shift/Alt → pas d'interception, l'onglet demandé part vraiment) ; `preventDefault()` ; `ouvrirModale(modale, declencheur)` ; `chargerContenu(corps)`. |
| Chargement (L43-78) | `fetch(endpoint, { headers: {'X-Requested-With': 'XMLHttpRequest'}, credentials: 'same-origin' })`, `aria-busy` posé/retiré, `corps.innerHTML = await res.text()` (HTML **déjà échappé** par les vues `e()`, jamais de concaténation de donnée client). Échec → état d'erreur explicite `.rappels-modal__erreur` avec lien de repli vers la page complète, **jamais** un corps vide qui simulerait « aucun rappel ». |
| Fermeture | Bouton `×` → `fermerModale(modale)`. Échap, piègeage, focus initial et retour du focus : **délégués à `core/modal.js`**, pas réécrits. Pas de fermeture au clic sur le voile (non exigée, évite une perte d'état involontaire). |
| Actions (délégation `submit` sur la modale, L159-236) | `preventDefault()` + `stopPropagation()` (pour que le gestionnaire global `js-confirm-delete` de `navigation.js` ne confirme pas deux fois) ; `window.confirm()` conservé pour les suppressions ; verrou `actionEnCours` contre la double frappe ; bouton désactivé pendant la requête. |
| Négociation | `POST` + `Accept: application/json` + `X-Requested-With`. **Aucune redirection suivie.** `succes = donnees.status === 'ok'` ; `type` → genre d'affichage (`success` → vert `ok`, `info` → doré `info`, sinon rouge `ko`). |
| Messages | 400 → « Jeton de protection expiré : l'action n'a pas été envoyée… » ; 403 → « Session fermée : reconnectez-vous… » ; autre code → « Le serveur a refusé l'action (HTTP n)… » ; exception réseau → « Réseau indisponible : le serveur n'a confirmé aucune écriture… ». **Rien n'est présenté comme réussi avant confirmation du serveur.** |
| Rafraîchissement | `await chargerContenu(corps)` **dans tous les cas** : la fenêtre ne montre que l'état persisté, jamais un état optimiste ; puis `signaler()` du message serveur ; puis repose du focus sur `#rappels-modal-title` (l'élément cliqué a été détruit par le rechargement). |
| Annonce | `signaler()` écrit un `<p class="rappels-modal__statut rappels-modal__statut--ok|info|ko" data-genre>` avec `textContent` uniquement, dans le corps qui porte `aria-live="polite"` → lu par les lecteurs d'écran, sans couleur comme seule information. |

---

## 3. Cœur partagé : `public/assets/js/core/modal.js` (2 lignes de comportement)

Seule modification du cœur, justifiée par une mesure en navigateur réel
(position de lecture perdue à la fermeture, voir `04-` §3) :

```js
try {
    lastFocusedElement.focus({ preventScroll: true });
} catch (erreur) {
    lastFocusedElement.focus();   // navigateur sans support de l'option
}
```

Elle **implémente l'intention déjà écrite dans le fichier** (« l'utilisateur
retrouve exactement sa position, il n'est pas renvoyé en haut de page ») que
`focus()` simple ne tenait pas. Aucune autre modale du site n'est affectée
autrement que positivement.

---

## 4. Design : `public/assets/scss/pages/_mvc-rappels.scss` (bloc `.rappels-modal`, L459-633)

Aucun second système de design : voile, boîte verre, croix `×`, dimensions
fluides et défilement interne viennent de `components/_modals.scss`
(`width: min(92%, 700px)`, `max-height: 88vh`, `overflow-y: auto`,
`@include glass-effect`, voile `z-index: 500` + `backdrop-filter: blur(6px)`).
Le bloc phase D n'ajuste que l'habillage **du contenu de rappels dans la
fenêtre** :

| Sélecteur | Apport |
|---|---|
| `.rappels-modal .modal-container` | `padding-top: $spacing-lg` pour que le titre ne passe pas sous la croix absolue. |
| `&__titre` | Cinzel 22 px or, `margin-right: 48px`, `:focus-visible` (3 px or) : le focus y revient après chaque rafraîchissement. |
| `&__bilan` | 14 px, `opacity: .9` — le compteur d'ouverture, lisible sans surcharger les cartes. |
| `&__body` | `.rappel-section` reserrées, `.section-title` 17 px (c'est le titre de la modale qui hiérarchise), `.rappel-histo` en `flex-wrap: wrap` + `.rappel-histo__libelle { flex: 1 1 100%; order: 2 }` (la note passe en seconde ligne plutôt que d'écraser les badges), `.btn-mini { min-height: 40px }` (cibles tactiques). |
| `&__statut` (+ `--ok`, `--info`, `--ko`) | Message d'action dans la fenêtre : bordure 1 px + bordure gauche 4 px, fond translucide 12-14 %, texte plein contraste. `--ok` `$clr-success`, `--info` `$clr-gold`, `--ko` `$clr-error` — **la couleur n'est jamais la seule information** (bordure pleine + texte). |
| `&__erreur` | Même discipline pour l'état de chargement en échec, lien de repli souligné + `:focus-visible`. |
| `&__lien` | Lien discret vers la page complète, aligné à droite. |
| `.collection-rappels-entry` | Emplacement de l'entrée du tableau de bord. |
| `html:has(.rappels-modal.active)` | **Deux corrections mesurées** : `overflow: hidden` sur `<html>` (le verrou `<body>` de `core/modal.js` ne bloquait pas le viewport réel) et `.toast-container { z-index: 480 }` tant que la modale est ouverte (les toasts C1 en `$z-toast: 1000` passaient au-dessus du voile et masquaient le bord droit de la fenêtre). |

`prefers-reduced-motion` : la règle globale de `base/_reset.scss` (L114, « je
coupe toutes mes transitions/animations ») s'applique aussi à cette modale ; la
présence de `@media (prefers-reduced-motion: reduce)` a été vérifiée dans la
feuille compilée. Aucune règle de mouvement n'a été ajoutée.

**Inert du fond :** le projet ne manipule `inert` nulle part (vérifié : 0
occurrence en JS et en SCSS). La fenêtre suit donc la convention existante —
`aria-modal="true"` + `aria-hidden` + piègeage de focus — et n'introduit pas un
second mécanisme. C'est une limite assumée, pas une régression (detail `04-`).

---

## 5. Ce qui n'a **pas** été fait (et pourquoi)

- **Pas de migration SQL** : `notifications.statut`, `collections.rappel_actif`,
  `collections.rappel_frequence_jours`, `types_soin` existaient déjà (C0) ; le
  fragment et le squelette n'introduisent aucune colonne.
- **Pas de nouvelle route d'écriture ni d'API** : la modale consomme les routes
  C0 ; la négociation se fait par en-tête sur ces mêmes routes.
- **Pas de reconstruction de carte en JavaScript** : le client ne formate ni
  date, ni jours de retard, ni nom de plante ; il affiche du HTML serveur.
- **Pas de fermeture au clic sur le voile** : non exigée par la mission, et elle
  aurait laissé échapper la fenêtre avec un formulaire de confirmation en vol.
- **Pas de `inert`, pas de réécriture du piègeage de focus** : touches au cœur
  `modal.js`/`focus.js` utilisées par d'autres écrans → décision propriétaire
  (limites `04-`).
- **Pas de commit, pas de push, aucun `git add`** (aucun même partiel).
