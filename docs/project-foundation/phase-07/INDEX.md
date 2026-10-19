# PHASE 07 — ARBITRAGE DES DÉCISIONS DE PRODUCTION & PRÉPARATION CONTRÔLÉE DE LA PUBLICATION

**Projet :** MES ORCHIDÉES
**Rôle :** Architecte logiciel / responsable de livraison / conseiller technique
**Verdict :** `BLOCKED` (décision D1 ouverte + contrainte de coût nouvelle ; D6a sans réponse)
**Date :** 2026-10-17
**Autorité décisionnelle :** Propriétaire du projet (aucune décision imputée ici)

---

## 1. Objet

Transformer les six décisions ouvertes de la Phase 06 en un plan d'arbitrage traçable.
Cette phase **n'exécute rien** : elle documente les options, les contraintes vérifiées,
les recommandations et ce qui reste à confirmer **auprès des fournisseurs**, puis attend
les arbitrages écrits.

## 2. État de départ (VERIFIED)

| Élément | Valeur |
|---|---|
| HEAD | `ca40ef5` (docs phase-06) — commits de référence `64c6cf0`, `6fe6872` présents |
| Distance | `main…origin/main [ahead 6]`, `HEAD..origin/main` = 0 → push = fast-forward |
| Arbre | propre au démarrage |
| Dossier Phase 06 | 6 fichiers, complets ; audit d'attributs §2 du doc `01` |

## 3. Contenu du dossier

| Doc | Objet |
|---|---|
| [01-decision-matrix.md](./01-decision-matrix.md) | Matrice D1–D6 (options, reco, risques, vérifications, statuts) + contrôle de complétude du dossier 06 + faits officiels nouveaux |
| [02-publication-risks.md](./02-publication-risks.md) | Risques de publication, contenu résiduel à assumer, question dépôt public/privé, séparation D6a/D6b |
| [03-next-steps.md](./03-next-steps.md) | Découpage proposé : étape P (push), Phase 08 (ajustements/tests/docs), Phase 09 (déploiement) — avec critères d'entrée/sortie |

Amont référencé, non dupliqué : `../phase-06/` (6 docs), `../phase-05/04-production-readiness.md`,
`../phase-05/05-deployment-runbook.md`, `../phase-05/06-open-decisions.md`.

## 4. Synthèse en une page

- **Décisions reçues (2)** : **D2 = Render pour l'app + base externe** · **D4 = Option B,
  suite HTTP complète** (à l'encontre de la recommandation A — enregistrée telle quelle).
- **Décision bloquante non résolue** : **D1**. Le propriétaire déclare n'avoir aucun
  fournisseur et vouloir éviter un abonnement → **toutes les options instruites sont
  payantes**, et la seule piste « MySQL-compatible gratuite » examinée (TiDB Serverless) est
  justement **écartée par l'exigence FULLTEXT**. Une étape d'instruction préalable est donc
  ajoutée (`03-next-steps.md` §3bis).
- **Non répondu / non soumis** : D6a (push — la question a été posée sans réponse), D2b, D5, D6b.
- **Faits officiels nouveaux vérifiés** (doc `01` §3) : port Render par défaut `PORT=10000`
  avec auto-détection habituelle d'un autre binding ; **terminaison TLS + redirection
  HTTP→HTTPS par la plateforme** ; health check TCP par défaut, HTTP optionnel ; **MySQL 8
  possible sur Render en service Docker privé + disque**, mais snapshots de disque
  officiellement **déconseillés pour restaurer une BDD** (`mysqldump` requis) ; **instances
  gratuites Render réservées à Static Site / Web Service / Postgres / Key Value**, avec
  spin-down 15 min, fichier éphémère, 750 h/mois et mention explicite « do not use for
  production » ; Postgres gratuit Render **expire à 30 jours**.
- **Risque dominant inchangé** : 6 commits et ≈ 11 700 lignes ajoutées n'existent que sur un
  seul disque (`02-publication-risks.md` §2). **La publication Git est la seule action de
  sauvegarde disponible à coût 0 €** et ne dépend pas de D1.

## 5. Décisions : reçues, manquantes, restantes

**Reçues** — D2 « Render (app) + base externe » · D4 « Option B — suite HTTP complète ».

**Manquantes, à arbitrer explicitement (rien ne sera présumé)** :

1. **D1 — bloquant.** Aucune des options instruites n'est gratuite. Levez-vous la contrainte
   de budget (≈ 5 $/mois : Railway, ou Render + disque avec `mysqldump` auto-géré) ?
   Sinon : une ressource déjà payée est-elle disponible (serveur d'école, VPS, hébergement
   pro) ? Sinon : faut-il instruire une piste mutualisée PHP+MySQL à coût nul (avec les
   contrôles listés en `03` §3bis) ? Ou acter « **pas de production** » ?
2. **D6a — posée, sans réponse.** Autorisez-vous le push des 6 commits (fast-forward, diff
   audité propre, **coût 0 €**, aucune dépendance à D1) ? Visibilité : **privée** (reco) ou
   publique ? Le dépôt est-il déjà privé ou public à votre connaissance ?
3. **D2b** — Health check : livabilité simple (`GET /` / TCP + tests de fumée) ou micro-route
   `/healthcheck` avec ping BDD à créer ?
4. **D5** — Schémas Neon/Supabase : conserver (reco), déplacer en archive, ou supprimer ?
5. **D6b** — Déploiement : **non autorisé par défaut** ; un ordre écrit distinct sera requis,
   et ne pourra intervenir qu'après fermeture de D1.

## 6. Verdict

`BLOCKED` — la mission de la phase (rendre chaque décision de production closable) ne peut pas
aboutir : **une décision bloquante reste ouverte (D1)** et se heurte à une contrainte nouvelle
(enveloppe 0 €) qu'aucune option instruite ne satisfait. La question de publication (D6a)
n'a pas reçu de réponse, donc **aucun push n'a été effectué**.

Les **livrables documentaires sont, eux, complets** : audit du dossier 06, matrice à statuts
réels, risques de publication, découpage conditionnel de la suite, faits fournisseurs sourcés.
Aucune action irréversible, aucun fichier de l'application modifié.

**ARRÊT À LA FIN DE LA PHASE 07.** Aucun push. Aucun déploiement. Aucune création de
service externe. Aucune modification de code, de `Dockerfile` ou de schéma. En attente
des arbitrages §5.

