# 03 — Découpage proposé de la suite (Phase 07 — **proposition, non exécutée**)

> Ce document **ne lance aucune phase**. Il rend chaque étape suivante conditionnelle à une
> autorisation écrite explicite, et définit ses critères d'entrée/sortie vérifiables.

## 1. Vue d'ensemble

```
Phase 07 (ce dossier)
   │  décisions D1–D6 reçues
   ├─► Étape P  : publication Git          (autorisation D6a, séparée)
   ├─► Phase 08 : ajustements de config, tests, documentation (périmètre local)
   └─► Phase 09 : provisionnement + déploiement (autorisation D6b, séparée)
```

P et 08 sont indépendants ; 09 dépend de D1/D2/D3 **et** d'un 08 au vert. P ne débloque
rien d'autre que la sauvegarde du dépôt distant.

## 2. Étape P — publication Git (ne pas exécuter sans D6a)

| | |
|---|---|
| Entrée | D6a écrite ; `git status` propre ; `HEAD..origin/main` = 0 (fast-forward — VERIFIED 07) ; choix public/privé **confirmé** (02 §4) |
| Action | un seul `git push origin main` (6 commits) |
| Sortie | `origin/main` = HEAD local ; historique local intact ; **aucune** mise en public automatique |
| Facultatif avant P | correction du README (obsolète — 06/01 §5) : décision du propriétaire, ~15 min |
| Interdit | `push --force`, `reset --hard`, réécriture d'historique |

## 3. Phase 08 — ajustements, tests et documentation (périmètre local)

Contenu conditionné aux arbitrages :

| Bloc | Déclencheur | Nature | Charge estimée |
|---|---|---|---|
| 8.1 Correction README (état réel MVC/PHP/MySQL, captures, commandes) | souhait du propriétaire | doc | 0,5 j |
| 8.2 Tests HTTP **Option A** (3–6 cas légers) | D4 = A — **non retenu** (le propriétaire a choisi B) | — | — |
| 8.3 Suite HTTP **Option B** (serveur réel via `php -S`, assertions statut/Set-Cookie/CSRF bout-en-bout) | **D4 = B — VALIDÉ par le propriétaire (07 §4)** | code de test uniquement (`tests/`), aucune dépendance | 1 j |
| 8.4 Adaptation Docker `${PORT:-80}` + `EXPOSE` dynamique | **seulement si** la plateforme retenue l'impose (01 §3, non confirmé à ce jour) | 2 lignes Dockerfile | 0,25 j |
| 8.5 Micro-route `/healthcheck` (ping BDD) + grant public | si D2b = b | code + route | 0,25 j |
| 8.6 Déplacement des schémas legacy vers `archive/legacy/data/` | D5 = b | `git mv`, aucun contenu changé | 0,1 j |
| 8.7 Scan de secrets sur **tout l'historique** | recommandé si dépôt public | lecture seule | 0,25 j |
| 8.8 Exécution complète `composer test` + build/run Docker + rapport | toujours | vérification | 0,25 j |

Sortie de phase : suite au vert, aucun secret, Docker reconstruit et testé, Git propre,
rapport phase-08 avec statuts VERIFIED/NOT VERIFIED.

## 3bis — Étape préalable créée par la contrainte 0 € (D1 reste `BLOCKED`)

Le propriétaire a déclaré ne pas disposer de fournisseur et vouloir éviter un abonnement.
Les pistes instruites en 06/07 étant toutes payantes, une **étape d'instruction** doit
précéder toute Phase 09. Elle est **proposée, pas engagée** :

| Question à trancher d'abord | Options |
|---|---|
| Un petit budget (≈ 5 $/mois) est-il acceptable ? | oui → D1 se ferme sur Railway ou Render-Disk ; non → ligne suivante |
| Une ressource déjà payée peut-elle être utilisée ? | serveur de l'école / VPS existant / hébergement du projet pro — à nommer par le propriétaire |
| Cost-zero technique ? | mutualisé PHP+MySQL à coût nul ou très bas (contrôles à vérifier : PHP ≥ 8.1, docroot `public/`, MySQL distant, FULLTEXT, backups) — **non instruit, aucune offre affirmée** |
| Aucune de ces pistes | statut **local uniquement** : le projet reste non déployé, et la seule action utile devient la publication Git (D6a) |

Sortie attendue de cette étape : **une ligne D1 passée à `DECIDED`**, ou la décision
écrite « pas de production » (auquel cas D2/D3/D6b se ferment comme « sans objet »).

## 4. Phase 09 — déploiement (ne pas exécuter sans D6b)

Ordre imposé par le runbook (`../phase-05/05-deployment-runbook.md`) :

1. Vérification **directe chez le fournisseur** des points restants (prix, quotas, TLS,
   rétention des backups) — **aucune de ces valeurs n'est affirmée par ce dossier**.
2. Création des services (base, puis app) avec variables d'environnement injectées par la
   console — jamais par fichier `.env` commité.
3. Import de `data/schema_mysql.sql` + contrôle **`SHOW INDEX` confirmant `ft_orchids_search`**
   (critère de clôture D1) + `MATCH … AGAINST` testé sur les 21 orchidées de seed.
4. `COOKIE_SECURE=true`, `APP_DEBUG=false` ; test de la checklist `06/03 §6` (HTTPS,
   cookies, CSRF 400, isolation, 500 sans fuite, logs sans secret).
5. **Test de restauration** : `mysqldump` → base neuve → vérification (critère de clôture
   sauvegardes). Si la base est sur Render-Disk, la doc officielle écarte le snapshot de
   disque pour restaurer une BDD → le `mysqldump` planifié est **obligatoire**, pas optionnel.
6. Ouverture publique seulement après 1–5 concluants ; rollback = image/tag précédent +
   restauration du dump.

Arrêt immédiat si : fuite de secret dans les logs, `FULLTEXT` absent, cookie sans `Secure`
en HTTPS, ou échec de restauration.

## 5. Ce que la Phase 07 **n'a pas** fait (volontairement)

Aucun push, aucun déploiement, aucune création de compte/service/base/resource payante,
aucune modification de code applicatif ou de `Dockerfile`, aucun déplacement/suppression de
schéma, aucune lecture du `.env` réel, aucun changement de visibilité de dépôt,
aucune décision imputée au propriétaire.
