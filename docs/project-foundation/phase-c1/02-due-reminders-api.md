# Lot 2 — Flux « rappels dus » et marquage comme lu (contrats serveur)

## Audit préalable (constaté dans le code, pas supposé)

| Question | Réponse vérifiée |
|---|---|
| Un endpoint « rappels dus » existait-il ? | **Non** — seules routes notification : `GET /notifications`, `GET /notification/{id}`, `POST /notification/{id}/read`, `POST /notification/delete/{id}` (`config/routes.php` §4.3). |
| `activateDueReminders()` réutilisable ? | **Oui** — `NotificationRepository::activateDueReminders()` : UPDATE lot `planifie`→`actif` WHERE `user_id` + `notif_date <= NOW()` ; **ne crée aucune ligne**. Déjà utilisé par `/rappels` (C0) et le dashboard. |
| GET modifiant un état ? | Existait : `GET /notification/{id}` (show) marque lu en guise d'affichage (comportement legacy documenté, hors périmètre C1). **Le flux toast n'utilise jamais cette route** ; seule la modification d'état du C1 est un POST. |
| Champs réels de `Notification::toArray()` | `id, type, typeLabel, message, isRead, statut, idTypeSoin, typeSoinLabel, collectionId, orchidName, notifDate, dateFr` (12 champs) — vérifié dans `src/Entity/Notification.php` L193-209. |
| Non authentifié sur une route GRANT_AUTH ? | `Router::dispatch` lève `UnauthorizedException` → front-controller : **403** HTML générique (`public/index.php` L145-147). |
| CSRF invalides ? | `CsrfException` → **400** générique, jamais 500 (`public/index.php` L157-160). |

## `GET /api/rappels/dus` — nouveau (`RappelController::dus()`)

- Route : `config/routes.php` §4.3bis, grant `GRANT_AUTH`.
- Propriétaire lu **uniquement en session** (`current_user()`), jamais dans la requête (RG1).
- Séquence : `activateDueReminders($uid)` (mécanisme existant, idempotent) →
  `findRappelsForUser($uid, ['actif'])` → filtres entité C0 :
  `isUnread() && (isLate() || isDueToday())`. Aucune nouvelle écriture, aucun doublon.
- Éligibilité exclut donc : futur (`planifie` non échu), lu, `traite`, `ignore`.

### Contrat JSON stable (documenté dans le docblock)

```json
{
  "status": "ok",
  "count": 1,
  "consultUrl": "/rappels",
  "readUrlBase": "/notification",
  "csrf": "<64 hex>",
  "rappels": [ { "<Notification::toArray()>…", "late": true } ]
}
```

- Seule extension par item : `late` (booléen dérivé serveur des helpers entité — le
  client ne re-code pas la comparaison de dates).
- `consultUrl`/`readUrlBase` générés par `url()` : le préfixe d'installation
  (`APP_BASE_PATH`) est respecté sans rien coder en dur côté client.
- `csrf` : le jeton de session, transmis **uniquement ici** (réponse authentifiée),
  jamais dans le HTML public.

## `POST /notification/{id}/read` — réutilisé, négociation JSON ajoutée

`NotificationController::markRead()` : `assertCsrf($_POST)` puis `markReadForUser()`
(WHERE `id` AND `user_id` — RG1). Si le requérant préfère JSON
(`Accept: application/json` ou `X-Requested-With: XMLHttpRequest`) :

- réussi → `200 {"status":"ok","id":…}`
- notification hors du propriétaire/absente → `404 {"status":"not_found","id":…}`
  (aucune divulgation : même réponse pour « inexistante » et « à quelqu'un d'autre »)
- CSRF absent/invalide → **400** par le front-controller (chemin inchangé).

Sans ces en-têtes, le parcours formulaire HTML (flash + 302) est **strictement
inchangé** — les tests D existants du marquage HTML restent au vert.

## Distinctions sémantiques préservées (exigence produit)

| Action | Effet réel |
|---|---|
| Fermer le toast (×) | Retrait DOM seul ; le rappel reste non lu et réapparaîtra. |
| Marquer comme lu | POST `read` → `is_read=1` uniquement ; statut du rappel inchangé. |
| Consulter | Lien GET `/rappels` ; aucun effet de bord. |
| Ignorer le rappel / Effectuer le soin | Uniquement les cartes `/rappels` (C0) — absents du toast. |

Affichage **≠** lecture : rien ne marque lu automatiquement ; seul le clic
explicité « Marquer comme lu » le fait, après 200 ok côté client.
