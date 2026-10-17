# 05 — Procédure de livraison (Runbook) — DOCUMENTAIRE

> ⚠️ Cette procédure est **documentaire**. Aucune ressource réelle n'a été créée et
> aucune mise en production n'a été effectuée pendant la Phase 05. Elle décrit les
> étapes à exécuter **une fois l'autorisation humaine obtenue**.

## 0. Éléments à vérifier AVANT tout déploiement

- Décision prise sur le **fournisseur MySQL/MariaDB externe** (voir `06-open-decisions.md`).
- Le schéma `data/schema_mysql.sql` est importé et les seeds chargés (21 orchidées).
- Les variables d'environnement de production sont prêtes (voir §4).
- Le branch à déployer est identifié (ex. `64c6cf0` + commit Phase 05).

## 1. Prérequis techniques et comptes

- Compte sur la plateforme d'hébergement (ex. Render) **et** sur le fournisseur BDD.
- Dépôt Git à jour (le push reste **une décision explicite** séparée).
- `Dockerfile` validé localement (`docker build` + `docker run` → 200 : VERIFIED 04B/05).

## 2. Choix et validation du fournisseur MySQL/MariaDB

- Appliquer les critères de `04-production-readiness.md` §3 (FULLTEXT, `UUID()`,
  réseau, TLS, sauvegardes, coûts, rotation).
- **Validation :** connecter un client à la base cible et vérifier
  `SHOW INDEX FROM orchids WHERE Index_type='FULLTEXT';` et `SELECT COUNT(*) FROM orchids;`.

## 3. Création et initialisation de la base

```
# base vierge (à adapter à l'outil du fournisseur)
mysql --defaults-extra-file=<creds> <data/schema_mysql.sql>
# puis seeds de référence
for f in data/seeds/*.sql; do mysql --defaults-extra-file=<creds> mes_orchidees < "$f"; done
```
- Vérifier le comptage attendu (orchidées = 21) et la présence des comptes seedés.
- **Ne jamais** exécuter ceci sur une base existante sans sauvegarde préalable.

## 4. Configuration des variables d'environnement (sans exposer de secrets)

À renseigner dans le tableau de bord de la plateforme (jamais committées) :

| Variable | Production |
|---|---|
| `APP_DEBUG` | `false` |
| `DB_DSN` **ou** `DB_HOST`/`DB_PORT`/`DB_NAME`/`DB_USER`/`DB_PASS` | selon fournisseur (nom : **`DB_PASS`**) |
| `COOKIE_SECURE` | `true` (HTTPS) |
| `COOKIE_SAMESITE` | `Lax` |
| `COOKIE_DOMAIN` | vide (domaine par défaut de la plateforme) |
| `APP_BASE_PATH` | vide (racine) |

## 5. Port et health check — à CONFIRMER sur la plateforme

- Le `Dockerfile` expose le port **80** (Apache). **Vérifier l'exigence réelle de la
  plateforme** : certaines surchargent `PORT` et attendent que l'app écoute dessus.
  → Si la plateforme impose une variable `PORT`, **adapter la config Apache** ou
  choisir l'option « Docker porte son propre port » ; ne **pas** présumer 80.
- Health check : cible une route **publique** saine (ex. `GET /` → 200). Confirmer le
  chemin et l'intervalle selon la plateforme.

## 6. Construction et vérification de l'image

```
docker build -t mes-orchidees:<tag> .
docker run --rm -p 8080:80 mes-orchidees:<tag>   # attente 200 sur /
docker exec <cid> php -m   # mbstring + pdo_mysql présents
```

## 7. Tests de fumée après démarrage (déploiement)

- `GET /` → 200 (accueil rendu).
- `GET /login` → 200 ; connexion d'un compte seedé → redirection + accès `/macollection`.
- `GET /orchids/search?q=phalaenopsis` → 200 (FULLTEXT OK).
- `POST /logout` sans jeton CSRF → **400** ; avec jeton valide → 302 + session détruite.
- Protégées en anonyme (`/administration`) → **403**.

## 8. Validation de la connexion à la base

- Vérifier l'absence d'erreur 500 « Erreur interne » liée à PDO ; contrôler les logs
  applicatifs. Un échec de connexion doit produire l'erreur générique (pas de fuite).

## 9. Parcours métier essentiels (recette)

- Encyclopédie : liste + fiche détail. Conseil : liste + recherche.
- Collection : ajout/édition/suppression d'une plante et de ses soins (RG1).
- Notifications : marquage lu, suppression (destinataire = session).
- Administration : tableau de bord, gestion users ; Modération : approbation/rejet.

## 10. Sauvegarde et restauration

- Snapshot/`mysqldump` de la base initialisée **avant** exposition publique.
- Tester la restauration sur une base secondaire (procédure documentée par le fournisseur).

## 11. Procédure de retour arrière (rollback)

- Application : redéploiement de l'image du dernier tag sain (ou rollback natif plateforme).
- Base : si une migration a été appliquée, restaurer le snapshot pré-migration ; sinon
  désactiver la fonctionnalité concernée par repli sur le tag applicatif précédent.
- Git : le rollback reste **local/lecture** ; aucun reset destructif sur un dépôt partagé.

## 12. Critères d'arrêt en cas d'échec

Arrêter la livraison et rétablir le tag précédent si :
- `GET /` ≠ 200 après démarrage, ou erreur PDO de connexion ;
- un test de fumée sécurité échoue (CSRF non 400, route protégée non 403, fuite de trace) ;
- le FULLTEXT ne renvoie aucun résultat attendu ;
- la base n'a pas pu être sauvegardée avant l'exposition.

---

**Statut :** procédure prête, **NON exécutée**. Exécution soumise à autorisation humaine
explicite et aux décisions d'infrastructure de `06-open-decisions.md`.
