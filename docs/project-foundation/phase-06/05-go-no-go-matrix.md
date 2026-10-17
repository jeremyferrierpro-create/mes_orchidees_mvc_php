# 05 — Matrice GO/NO-GO et décisions attendues (Phase 06)

> Règle appliquée : **aucune ligne n'est GO tant que sa décision bloquante est ouverte**.
> Propriétaire de chaque décision : utilisateur humain (le conseiller technique ne
> choisit pas le fournisseur/platforme). Détail des constats : docs 01–04 du présent dossier.

## 1. Matrice

| # | Décision / contrôle | Preuve attendue | Statut actuel | Décideur | Conséquence si ouvert | Prochaine action autorisée |
|---|---|---|---|---|---|---|
| 1 | **Fournisseur MySQL/MariaDB (D1)** | Choix écrit du humain + provisionnement + import réussi de `schema_mysql.sql` + `SHOW INDEX` confirmant `ft_orchids_search` | **BLOCKED** — comparaison instruite et sourcée (02) ; TiDB écarté (preuve doc) ; reco = Railway, non décidée | Humain | Déploiement impossible ; aucune donnée réelle ne peut être créée | Le humain tranche D1 ; ensuite suivi du runbook §2–3 |
| 2 | **Plateforme d'hébergement (D2 partie A)** | Choix plateforme + déclaration du port accepté + variable d'env injectées | **BLOCKED** — conteneur ne gère que 80, aucun `$PORT` (VERIFIED 03 §1) ; Render D *peut* convenir si port déclarable (INFERRED) | Humain | Idem ; risque d'incompatibilité port si plateforme à `$PORT` imposé | Le humain tranche ; si port dynamique imposé → autoriser la micro-modification Dockerfile `${PORT:-80}` (hors périmètre 06) |
| 3 | **Health check (D2 partie B)** | Sonde choisie : `GET /` (livabilité, **VERIFIED 200 sans BDD**) ou micro-route `/healthcheck` avec ping BDD (à créer) | **NOT VERIFIED** en conditions réelles ; readiness **inexistante** (03 §2) | Humain | Redémarrages auto possibles mais pannes BDD non détectées par la plateforme | Accepter liveness-simple + tests de fumée runbook §7, OU commander la micro-route |
| 4 | **HTTPS + cookies (D3)** | `curl -I https://…` → 200 ; cookie session avec `Secure/HttpOnly/SameSite=Lax` en réel ; `COOKIE_SECURE=true` réglé | **BLOCKED** (post-provisionnement) — drapeau serveur déjà compatible (VERIFIED lecture 03 §5) ; TLS/redirect **NOT CONFIGURÉ** | Humain (activation) | Ouverture publique sans HTTPS exposerait les sessions | Exécuter la checklist 03 §6 **après** provisionnement, **avant** public |
| 5 | **Publication des 5 commits locaux** | Audit de diff propre + autorisation explicite de push | Audit **PROPRES (VERIFIED, 01)** ; autorisation **NON DONNÉE** | Humain | Rien n'est sauvegardé hors du disque local (risque de perte) ; README encore obsolète (01 §5) | Le humain autorise (ou non) `git push origin main` ; corriger README avant/après à sa discrétion |
| 6 | **Tests HTTP automatisés (D4)** | Choix Option A (harnais + minimal) ou B (suite HTTP réelle) — 04 §3 | **BLOCKED** (décision) ; gap HTTP réel documenté ; suite actuelle 27/0/0 limitée aux unités/intégration | Humain | Régressions de routage/en-têtes non détectées automatiquement | Trancher A/B ; reco technique : A maintenant |
| 7 | **Sauvegardes / restauration** | Politique du fournisseur retenue + **restauration testée** sur base neuve | **BLOCKED** — dépend de D1 ; aucune procédure dans le dépôt (02 §1) | Humain + fournisseur | Perte de données irréversible possible en prod | Inscrire le test de restauration dans la recette post-provisionnement |
| 8 | **Schémas historiques Neon/Supabase (D5)** | Décision : conserver en archive / déplacer `archive/legacy/data/` / supprimer | **REPORTED → STATU QUO** ; aucune référence runtime (VERIFIED 04 §4) ; rien supprimé | Humain | Hygiène only — aucun risque fonctionnel | Si un dépôt public épuré est voulu → autoriser un commit de déplacement séparé |
| 9 | **Autorisation explicite de push (gouvernance)** | Ordre écrit « push autorisé » | **NON DONNÉE** — aucun push effectué (phases 04B→06) | Humain | Toute action de publication est interdite au conseiller | Attendre l'ordre ; vérifier `git status` propre avant |
| 10 | **Autorisation explicite de déploiement (gouvernance)** | Ordre écrit + runbook suivi + tests 03 §6 concluants | **NON DONNÉE** — aucun service créé, aucun déploiement tenté | Humain | Aucun GO global possible | Déployer seulement après lignes 1–4 et 7 au vert |

## 2. Synthèse

- **Lignes auditables en local : closes et propres** (1-preuve partielle, 5-audit, 8, et les
  constats 2/3/4 de sécurité).
- **Lignes bloquées par décision humaine : 1, 2, 3, 4, 6, 7, 9, 10.**
- **Aucune ligne GO.** Le projet est **prêt techniquement au niveau local** ; le passage
  en production dépend exclusivement des autoritations et des choix ci-dessus.

**Verdict de la phase : `READY FOR HUMAN DECISION`.**
