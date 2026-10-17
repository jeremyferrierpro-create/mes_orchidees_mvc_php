# 06 — Décisions humaines requises (Phase 05)

La mise en production effective dépend de décisions qui **relèvent du propriétaire du
projet**. Cette phase les documente sans les trancher (choisir à la place du humain est
hors périmètre).

## D1 — Fournisseur de base MySQL/MariaDB (bloquant)

Render ne fournit pas de MySQL managé. Il faut retenir un hébergeur MySQL/MariaDB
externe satisfaisant les critères de `04-production-readiness.md` §3 (FULLTEXT InnoDB,
`UUID()`, réseau ouvert à Render, TLS, sauvegardes).

- **Statut :** `BLOCKED` (décision humaine + création de compte non autorisées ici).
- **Options candidates (non exhaustif, à valider par l'humain) :** base managée tierce,
  VPS auto-hébergé, ou alternative. Le choix appartient au propriétaire.
- **Impact :** sans base jointe, l'app renvoie une erreur 500 générique (pas de fuite).

## D2 — Port applicatif et health check sur la plateforme (bloquant)

Le `Dockerfile` écoute sur **80** (Apache). Beaucoup de plateformes (dont Render pour
les services Docker) **surchargent `PORT`** et routent vers le port déclaré.

- **Statut :** `BLOCKED` — dépend de la plateforme réellement retenue.
- **Action humaine :** confirmer le port et la variable attendue ; si `PORT` est imposé,
  adapter la config Apache du conteneur.
- **Preuve actuelle :** en Docker local, `:80` sert `GET / → 200` (VERIFIED 04B/05) ; ce
  n'est **pas** une preuve de compatibilité plateforme.

## D3 — HTTPS et `COOKIE_SECURE` (à l'activation)

- `COOKIE_SECURE=true` n'a de sens que derrière HTTPS. À activer au déploiement.
- **Statut :** `NOT VERIFIED` en local (tests HTTP simples), à valider en prod.

## D4 — Sortie de la dette de tests HTTP/intégration (qualité, non bloquant)

La suite actuelle couvre l'unitaire + RG1 notifications. Les parcours HTTP complets
(connexion réelle, grants 403/404, 400 CSRF) restent reproduits manuellement.

- **Statut :** `NOT VERIFIED` par test automatisé (couverture future optionnelle).
- **Décision humaine :** investir ou non dans un harnais HTTP (coût de maintenance).

## D5 — Archivage des schémas legacy (hygiène)

`data/schema_neon.sql` et `data/schema_supabase.sql` sont inutilisés par le runtime.

- **Recommandation :** documenter comme archives ; éventuelle suppression **ultérieure**
  sur décision explicite (non supprimés dans cette phase).
- **Statut :** `INFERRED` (non utilisés) — aucune référence runtime constatée.

## D6 — Autorisation de push / déploiement (gouvernance)

- 4 commits locaux ne sont **pas** poussés ; un commit Phase 05 éventuel restera local.
- **Statut :** `BLOCKED` — attend une autorisation humaine explicite pour push et déploiement.

---

## Synthèse

| Décision | Verdict phase | Blocage prod |
|---|---|---|
| D1 fournisseur BDD | — | oui |
| D2 port/health check | — | oui |
| D3 HTTPS/cookie | NOT VERIFIED | à l'activation |
| D4 tests HTTP | NOT VERIFIED | non |
| D5 schémas legacy | INFERRED | non |
| D6 push/déploiement | BLOCKED | oui (gouvernance) |

**Tant que D1, D2 et D6 ne sont pas tranchés par l'humain, la mise en production reste
`BLOCKED`.** La qualification, les correctifs de sécurité, les tests et la procédure sont,
eux, prêts (voir INDEX.md, verdict `PASSED WITH LIMITATIONS`).
