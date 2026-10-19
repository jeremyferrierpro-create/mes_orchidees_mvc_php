# Lot 2 — Toast persistant du jour J (frontend)

## Fichiers

| Fichier | Rôle | Justification |
|---|---|---|
| `public/assets/js/features/rappels-toast.js` | **Créé** — module dédié | `showToast()` de `core/notifications.js` ne sait pas porter d'actions (vérifié L55-116) ; un module séparé évite de toucher au composant partagé par tous les toasts existants. |
| `public/assets/js/app.js` | Import + appel global dans `DOMContentLoaded` | Notification **transverse** (toutes pages), pas liée à une page du routeur B ; garde DOM côté module. |
| `templates/components/footer.php` | Amorce `#rappels-toast-bootstrap` (`data-endpoint`) rendue **seulement si `is_logged_in()`** | Unicité des URLs avec `url()` (préfixe d'installation), zéro requête pour un anonyme, zéro jeton exposé hors session. |
| `public/assets/scss/pages/_mvc-rappels.scss` | Bloc `.toast-rappel` (+ variante `--retard`) | Prolonge le design system existant (tokens `$clr-gold/$clr-error/$font-titles/$spacing-*/$transition-fast`, cible tactile 40 px, `focus-visible` or) — pas de seconde identité. |

## Comportement

1. Une seule requête `GET /api/rappels/dus` (en-tête `Accept: application/json`)
   par chargement de page (garde `dejaLance`) ; pas de refresh périodique.
2. Réponse `!ok` (403 session perdue, 500, réseau coupé) : **silence total** —
   aucun rappel n'est perdu, aucun état n'est changé.
3. Toast empilé dans le `#toast-container` global (aria-live="polite"), max 3 ;
   chaque toast porte : plante, type de soin, échéance (« En retard — … » /
   « À faire aujourd'hui — … »), message, lien **Consulter** (`consultUrl`),
   bouton **Marquer comme lu**, et un `×` **distinct** (fermeture DOM seule).
4. « Marquer comme lu » : `POST {readUrlBase}/{id}/read` (urlencoded
   `csrf_token=` + `Accept: application/json`), bouton désactivé pendant l'appel
   (anti double-frappe). Le toast n'est retiré **qu'après 200 `{status:"ok"}`** ;
   sinon `window.AppToast` (`showToast` de notifications.js) affiche l'erreur et
   le rappel reste non lu — comportement exigé par le cas de test « échec réseau ».
5. Déjà lu / traité entre-temps (autre onglet) : le prochain rechargement ne
   rejoue pas le toast (le flux ne renvoie que les non lus).

## CSP et sécurité

- Aucun script inline, aucun gestionnaire inline : module ES importé par `app.js`
  (`script-src 'self'` satisfait).
- Tout le contenu texte passe par `textContent` (XSS), les URLs par `href`/`fetch`
  depuis des valeurs **servies par le serveur** (`url()`), le jeton CSRF arrive par
  la réponse JSON authentifiée.
- Isolation : la seule autorité d'identité côté serveur est la session ; un
  `user_id` envoyé par le navigateur n'est jamais lu.

## Accessibilité

- `aria-live="polite"` du conteneur existant : le rappel est annoncé sans
  intercepter l'agent (urgence informationnelle ; `role="alert"`/assertive réservé
  aux toasts d'erreur existants — choix documenté dans le module).
- `aria-label` par toast (« Rappel de soin à effectuer ») ; `×` libellé
  « Fermer la notification (ne pas marquer comme lue) » — distinction audible.
- Actions nativement focusables (lien/bouton), `:focus-visible` or 3 px, cible
  ≥ 40 px ; `prefers-reduced-motion` déjà traité globalement (`_reset.scss`).
- Limite assumée : après retrait du toast par « Marquer comme lu », le focus
  retombe sur le corps de page (pas de conteneur focusable) — mineur, le message
  de confirmation est bien annoncé ; à affiner en phase D avec la modale.

## Protocole manuel navigateur (seul moyen de valider le rendu visuel)

1. Base de dev : plante en opt-in avec rappel à date d'hier (ou avancer la date
   système / insérer une notif `actif` due pour son compte dev).
2. Logué → ouvrir n'importe quelle page : le toast glass apparaît en haut à
   droite et **reste** (duration 0).
3. `×` : le toast disparaît ; recharger → il revient (non lu, preuve DOM/DB).
4. « Consulter mes rappels » : arrive sur `/rappels`, rappel toujours non lu.
5. « Marquer comme lu » : toast se retire + toast de confirmation ; recharger →
   ne revient plus ; `is_read=1` en base ; le rappel reste `actif` (≠ traité).
6. Anonyme : aucun toast et **aucune requête** `/api/rappels/dus` (Network).
7. simulateur réseau coupé (DevTools offline) avant « Marquer comme lu » :
   toast d'erreur, rappel toujours non lu en base.
