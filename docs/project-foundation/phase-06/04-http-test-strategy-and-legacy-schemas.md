# 04 — Stratégie de tests HTTP et sort des schémas hérités (Phase 06)

## 1. Couverture actuelle (référence, non dupliquée)

Suite existante : `php tests/run.php` (harnais sans dépendance, Phase 05 — `REPORTED`,
27/0/0 exécuté). Couvre déjà, **au niveau unité/intégration** :

| Domaine | Nature | Base de données ? |
|---|---|---|
| Échappement XSS `e()` (8 tests) | unité | non |
| CSRF génération/vérification/exception (8) | unité | non |
| Helpers `url()`/`asset()` (3) | unité | non |
| Sessions et rôles (6) | unité | non |
| RG1 isolation notifications (2) | intégration | **oui** (SKIP si absent) |

## 2. Ce que la suite ne teste PAS (gap)

- **Boucle HTTP complète** : routage réel du front-controller, codes 404/403/400/500,
  en-têtes CSP/cookies émis, sessions via HTTP, parcours multi-pages (login → collection → logout).
- Ces comportements sont **VERIFIED manuellement** en 04B/05 (relevant + curl local),
  donc prouvés une fois mais **non répétables automatiquement**.

## 3. Deux options proportionnées (choix = propriétaire)

### Option A — Maintenir le harnais actuel + ajout minimal « HTTP-like » (recommandé si périmètre étudiant)

- Étaler : 3 à 6 tests d'intégration appelant les contrôleurs resolveurs/mock request
  pour vérifier codes et en-têtes (sans serveur HTTP réel), + réutiliser `TestableController`.
- Coût : faible (quelques heures) ; maintenance : faible (même harnais, mêmes sorties).
- Prérequis : aucun nouveau paquet ; base de test `mes_orchidees_test` pour les cas avec BDD.
- Limite : ne valide pas Apache/PHP built-in ni les en-têtes réels transportés.

### Option B — Suite HTTP automatisée (serveur réel + requêtes réelles)

- Lancer `php -S` (ou le conteneur Docker) dans le script de test, taper les routes
  avec `file_get_contents`/cURL PHP natif (toujours **sans dépendance Composer**),
  asserting status codes, cookies `Set-Cookie`, CSRF bout-en-bout, redirections.
- Coût : moyen (setup cycle de vie serveur, ports, temporisation ; ~1 journée) ;
  maintenance : moyenne (sensibilité aux changements de routes/env).
- Bénéfice : couvre le gap §2 ; proximité avec les tests de fumée du runbook §7.
- Prérequis : décision sur l'exécution en CI future (aucune CI configurée à ce jour —
  **NOT VERIFIED/ABSENT**), sinon exécution locale uniquement.

**Aucune des deux options n'est engagée par cette phase** (protocole : aucune
modification de code, aucune nouvelle dépendance). Recommandation technique :
**Option A maintenant, Option B si une CI ou un deuxième développeur arrive.**

## 4. Schémas hérités Neon/Supabase — constat (VERIFIED, Phase 06)

| Fichier | Lignes | Références runtime ? |
|---|---|---|
| `data/schema_neon.sql` | 403 | **Aucune** (grep : uniquement docs phases 00–05 + commentaires de soi) |
| `data/schema_supabase.sql` | 390 | **Aucune** (idem) |
| `archive/legacy/api/migration/schema.sql` | 86 | Legacy archivé, hors runtime |
| `data/schema_mysql.sql` | 288 | **Actif** — seul schéma importé/testé |

Le code runtime n'évoque PostgreSQL que dans des **commentaires d'aide à la
traduction** (`OrchidRepository`, `ConseilRepository`, en-tête de `schema_mysql.sql`).

## 5. Recommandation (non appliquée — suppression = décision humaine)

- **Statu quo documenté** : les fichiers sont déjà traités comme archives ; le dépôt
  est encore **non publié**, donc le coût de nettoyage est nul aujourd'hui et le coût
  de conservation est faible (~800 lignes SQL sans effet).
- **Si le humain veut publier un dépôt épuré** : les déplacer vers
  `archive/legacy/data/` (comme le reste du legacy, motif R100 déjà vu dans le diff)
  plutôt que supprimer — préserve l'historique pédagogique du fil rouge.
- **Suppression pure** : autorisable mais sans bénéfice mesurable ; à faire dans un
  commit séparé explicitement autorisé.
- **Ne rien supprimer dans cette phase** (protocole §7).
