# 05 — Plan de démonstration au jury DWWM + solution de secours

But : permettre au jury d'accéder au **vrai** site, avec ses **vraies**
fonctionnalités et des **données de démonstration**, à 0 €, sans exposer de
données personnelles, et avec un filet si l'hébergement ou Internet tombe.

## 1. Scénario retenu (aligné sur la reco Option A, doc 04)

**Principal** : hébergement MySQL gratuit (GoogieHost ou équivalent) + base MySQL
importée depuis `data/schema_mysql.sql` puis les `data/seeds/*.sql`.
**Secours** : démonstration **locale XAMPP** (déjà fonctionnelle : `httpd` :80 +
`mysqld` :3306 observés, doc 01).

## 2. Création des comptes de démonstration

- Les comptes de démo viennent des **seeds**, pas de l'usage réel :
  `data/seeds/01_seed_users.sql` fournit des comptes dédiés avec **emails de
  démonstration** et rôles (`user`, `admin`) ; mots de passe **hachés bcrypt**
  (L9-19). Les identifiants de démo (`demoadmin`, `demouser`) sont destinés aux
  tests locaux.
- ⚠️ **Avant publication en ligne** : **ne pas** téléverser le seed qui contient
  l'adresse personnelle `jeremy.ferrierpro@gmail.com` avec le mot de passe de
  démo. Créer **un seed de démo public** distinct (ex. `demo@mesorchidees.invalid`)
  et retirer tout compte personnel. **Recommandation** — aucune donnée perso ne
  doit être exposée. **VERIFIED** (lecture du seed).
- L'inscription publique (`authentification` / `AuthController`) permet aussi au
  jury de **créer son propre compte** en direct → démo vivante, sans pré-création.

## 3. Données de démonstration et séparation

- Peupler avec les seeds officiels : orchidées (`02`), conseils (`03`),
  collections (`04`), soins (`05`), notifications/rappels (`06`). Ils contiennent
  des données **fictives** destinées au rendu.
- **Séparation perso/démo** : la base de démo en ligne est **recréée** (import
  schéma + seeds), jamais un dump de la base de développement personnelle. Le
  compte de démo n'est pas le compte réel du porteur.

## 4. Réinitialisation des données (reset)

Procédure simple et reproductible, sans script custom :
1. `DROP`/recréer la base de démo (ou `TRUNCATE` des tables) via phpMyAdmin ;
2. ré-importer `schema_mysql.sql` ;
3. appliquer les migrations `data/migrations/2026-10-18_*.sql` dans l'ordre ;
4. ré-importer les `data/seeds/*.sql`.

> Prévoir un **« snapshot » exporté** (`.sql` via phpMyAdmin) = state propre de
> référence, pour restaurer en < 2 min si le jury a modifié des données.

## 5. Sauvegardes et restauration

- **Avant** la démo : export complet (`mysqldump`/phpMyAdmin) de la base de démo
 peuplée → fichier `_backup_demo.sql` conservé localement.
- Restauration = import de cet export. **1-Click Backup/Restore** est par ailleurs
  annoncé sur l'offre GoogieHost (**DOCUMENTED**), mais ne pas s'y reposer à 100 % :
  garder l'export local.

## 6. Risques propres à l'hébergement gratuit

| Risque | Probabilité | Mitigation |
|--------|:---:|------------|
| **Suspension pour inactivité** | moyenne | Activer le site quelques jours avant ; tester la veille. |
| **Rate-limit / surcharge « unmetered »** | moyenne | Base de démo légère, peu d'images ; éviter les pics. |
| **Version MySQL du compte < 8.0.13** | à vérifier | Import du schéma testé **avant** le jour J ; sinon adapter DDL (doc 03 §4.1). |
| **Coupure Internet le jour J** | faible-moyenne | Bascule sur le **plan local** (§7). |
| **Perte de données** | faible | Snapshot + export local (§5). |

## 7. Plan de secours : démonstration locale hors-ligne

Le poste de développement **est déjà un serveur de démo complet** :
- XAMPP actif (`httpd` :80, `mysqld` :3306) ; base `mes_orchidees` peuplée ;
- ou via le harnais : `php -S 127.0.0.1:8099 -t public tests/http/server-router.php`
  (isolation base de test) — utile pour montrer sans toucher la base de dev.
- **Fonctionne sans Internet** : aucune dépendance externe (vendor = autochargeur,
  doc 01 §4) → le site local ne dépend d'aucun CDN pour tourner.
- Préparer un **tunnel court** (ex. ngrok/Cloudflare Tunnel) uniquement si l'on
  veut montrer l'URL « en ligne » depuis un autre poste, **en secours** du mutualisé.

## 8. Checklist « prêt pour le jury »

- [ ] Site en ligne gratuit testé **la veille** (URL accessible, HTTPS OK, login démo OK).
- [ ] Seed de démo **sans email personnel** publié ; mot de passe fort.
- [ ] Export `_backup_demo.sql` + export local conservés.
- [ ] XAMPP local démarré et vérifié = **plan B immédiat**.
- [ ] Scénario de démo chronométré : accueil → encyclopédie (**recherche FULLTEXT**)
      → compte/collecte → ajouter une plante → enregistrer un **soin** (opt-in rappel)
      → **modale « Mes rappels »** → **toast du jour J** → historique.
- [ ] Note écrite de l'architecture (MySQL/MariaDB, choix assumé) pour répondre au jury.

> **Aucune action de déploiement n'est faite ici** (interdite par la mission) : ce
> document décrit la procédure, il ne l'exécute pas. **BLOCKED** pour la mise en
> ligne effective (nécessite décision + compte du porteur).
