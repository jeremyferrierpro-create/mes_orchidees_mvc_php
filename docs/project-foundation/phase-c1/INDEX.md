# Phase C1 — Diagnostic fixes & notifications persistantes des rappels

Phase suivante de la foundation (après A/B/C0 : opt-in rappel, câblage routeur,
refonte glassmorphism des rappels). Deux lots :

| Document | Contenu |
|---|---|
| [01-diagnostic-fixes.md](01-diagnostic-fixes.md) | Lot 1 — causes exactes et corrections des trois diagnostics Intelephense/SCSS |
| [02-due-reminders-api.md](02-due-reminders-api.md) | Lot 2 — contrat du flux `GET /api/rappels/dus` et marquage « lu » JSON |
| [03-toast-persistent.md](03-toast-persistent.md) | Lot 2 — module front `rappels-toast.js`, accessibilité, sécurité, protocole manuel |
| [04-rapport-validation.md](04-rapport-validation.md) | Résultats horodatés (VERIFIED/PASS/NOT RUN), limites, `git status` |

## périmètre

- IN : 3 diagnostics ; toast persistant jour J (rappels dus non lus) ; tests ; docs.
- OUT (volontaire) : phase D (modale de gestion des rappels), Web Push, tâches
  planifiées — la notification n'existe que **pendant la consultation** du site.

## décisions prises

1. **P1006 = faux positif d'indexage** (copies de cours `docs/` partageant la FQCN
   `App\Controller\AbstractController`) → correction par exclusion IDE, zéro ligne
   applicative touchée.
2. **P1075 = contrat `redirectTo()` mensonger** (`: void` alors que la méthode
   `exit`) → `: never` (PHP 8.1+, projet en 8.3.33).
3. **Endpoint dédié** `GET /api/rappels/dus` (aucun équivalent existant) ;
   **réutilisation** de `POST /notification/{id}/read` avec négociation JSON par
   en-tête (le parcours formulaire HTML est inchangé).
4. **CSRF véhiculé uniquement par la réponse JSON authentifiée** — rien d'exposé
   dans le HTML aux visiteurs anonymes ; le footer ne fournit que l'URL du flux.
5. Le toast **consulte `/rappels`** (GET pur) et ne cible pas `/notification/{id}`
   dont la consultation HTML marque lu en GET (hors charte pour ce flux).
