# Phase 03 — Rapports d'incréments 4 & 5 (Notifications, Administration, Modération)

Projet : **MES ORCHIDÉES** (fil rouge DWWM) — migration PHP MVC (routeur Mithridatem) + MySQL/MariaDB.
Auteur : Jérémy Ferrier. Date : 2026-10-17. Moteur local : **MariaDB 10.4.32**, PHP 8.3.33.

Ce document clôture la Phase 03 : il scelle les incréments 2 & 3 en Git, documente
l'**incrément 4 (Notifications & rappels, RG3/RG5)** et l'**incrément 5 (Administration &
Modération)** et **démontre qu'aucune route de l'application ne répond plus en 501** et que
le cycle de vie complet est opérationnel.

---

## 1. Commit des incréments 2 & 3

```
feat(phase03): increments conseils et collection/soins avec isolation RG1
```

- **Commit** : `89717f0` (15 fichiers, 2 237 insertions / 14 suppressions). Message intégralement
  ASCII — aucune corruption d'encodage PowerShell.
- **Périmètre** : entités `Conseil`/`Collection`/`Soin`, leurs repositories, les contrôleurs
  `ConseilController`/`CollectionController`/`SoinController`, les vues `templates/pages/conseils/`
  et `templates/pages/collection/`, et le rapport d'incréments 2 & 3.
- Arborescence Git après opération :
  - `0a9872a` — refactor(mvc): extraction du projet a la racine, schema MySQL et socle encylopedie
  - `89717f0` — feat(phase03): increments conseils et collection/soins avec isolation RG1 ← HEAD

> Les incréments 4 & 5 sont **laissés non commités** (aucun commit n'a été demandé pour cette
> partie) pour relecture. Voir §6.

---

## 2. Incrément 4 — Notifications & rappels de soins (RG3 / RG5)

### 2.1 Fichiers créés

| Rôle | Fichier |
|---|---|
| Entité | `src/Entity/Notification.php` |
| Repository | `src/Repository/NotificationRepository.php` |
| Contrôleur | `src/Controller/NotificationController.php` |
| Vues | `templates/pages/notifications/index.php`, `templates/pages/notifications/show.php` |
| Hook RG3 | `src/Controller/SoinController.php` (méthode privée `genererRappelSoin()`) |

### 2.2 Modèle & isolation

Table `notifications` : `id CHAR(36)`, `legacy_id`, `user_id CHAR(36) NOT NULL`,
`collection_id CHAR(36) NULL`, `type VARCHAR(50)` (`rappel_soin`/`validation`/`rejet`/`info`),
`message TEXT`, `is_read TINYINT(1)`, `notif_date DATETIME`.

**Même discipline que RG1** : `NotificationRepository` filtre **toute** lecture, tout marquage
« lu » et toute suppression par `WHERE ... user_id = :uid`. Le destinataire est lu **uniquement
en session**, jamais depuis la requête. Jointure `LEFT JOIN collections → orchids` pour afficher
le nom de la plante concernée. L'`id` est généré en **PHP (UUID v4)** à la création (le
`DEFAULT (UUID())` de MySQL n'est pas récupérable pour une colonne `CHAR`).

### 2.3 Routes (contrat)

| Méthode | Route | Action | Grant |
|---|---|---|---|
| GET | `/notifications` | `index` | authentifié |
| GET | `/notification/{id}` | `show` (+ passe en « lu ») | authentifié |
| POST | `/notification/{id}/read` | `markRead` | authentifié |
| POST | `/notification/delete/{id}` | `delete` | authentifié |

### 2.4 RG3 — rappel automatique J+7

Chaque enregistrement de soin (`POST /soin/new`) déclenche, **côté serveur**, la création d'une
notification de rappel destinée au propriétaire :

```
type        = rappel_soin
collection_id = plante concernée
notif_date  = now()
message     = "Rappel automatique : prochain soin (<type>) prévu le <date+7j> pour <plante>."
```

La constante `SoinController::RAPPORT_JOURS_RAPPEL = 7` pilote le décalage. Le nom de la plante
est résolu via `CollectionRepository::findByIdForUser()` (RG1 : uniquement la plante du
propriétaire).

### 2.5 RG5 — notification de décision (voir incrément 5)

Les décisions de modération (validation / refus) envoient une notification globale
(`collection_id = NULL`) à l'auteur de la proposition via `ModerationController::notifierAuteur()`.

---

## 3. Incrément 5 — Administration & Modération

### 3.1 Fichiers

| Rôle | Fichier |
|---|---|
| Contrôleur admin | `src/Controller/AdminController.php` (réécrit) |
| Repository stats | `src/Repository/StatsRepository.php` |
| Contrôleur utilisateurs | `src/Controller/UserController.php` |
| Contrôleur modération | `src/Controller/ModerationController.php` |
| Vues | `templates/pages/admin/{dashboard,users,user_form}.php`, `templates/pages/moderation/index.php` |
| Extensions | `OrchidRepository` (`findUnpublished`, `countUnpublished`, `setPublished`), `UserRepository` (`findAll`, `update`, `delete`, `emailExists`) |
| Navigation | `templates/components/sidebar.php` (liens Notifications / Modération) |

### 3.2 `AdminController` — `/administration` (grant : admin)

Tableau de bord agrégé (lecture seule via `StatsRepository`) : utilisateurs, orchidées publiées /
en attente, conseils, plantes en collection, soins, notifications (totales / non lues), répartition
par rôle, et actions rapides (gestion utilisateurs, modération, nouvelle orchidée, nouveau conseil)
+ aperçu de la file d'attente de modération.

### 3.3 `UserController` — gestion des utilisateurs (grant : admin)

Routes : `GET /users` (liste), `GET /user/new` (création), `GET /user/{id}` (fiche/édition),
`POST /update/user` (**création** si `id` vide, **mise à jour** sinon — la contrat ne déclare pas
de POST dédié à la création), `POST /user/delete/{id}`.

Sécurité : le mot de passe n'est jamais exposé ; à la création il est haché via
`password_hash(..., PASSWORD_DEFAULT)` ; contrôle d'unicité email (`emailExists`) ; **un admin ne
peut ni se rétrograder lui-même ni se supprimer** (garantie de non-verrouillage du compte courant).

### 3.4 `ModerationController` — `/moderation` (grant : admin + modérateur)

| Méthode | Route | Effet |
|---|---|---|
| GET | `/moderation` | Liste des propositions `is_published = 0` |
| POST | `/moderation/{id}/approve` | `is_published = 1` **+ notification « validation » à l'auteur (RG5)** |
| POST | `/moderation/{id}/reject` | suppression de la proposition **+ notification « rejet » à l'auteur (RG5)** |

L'auteur est lu depuis `orchids.proposed_by` ; la notification est silencieusement ignorée si
`proposed_by` est NULL (fiches de démonstration seedées).

---

## 4. Validation — aucune route en 501 & cycle de vie opérationnel

### 4.1 Analyse statique

- `php -l` sur **60 fichiers** (`src/`, `templates/`, `config/`) : **0 erreur**.

### 4.2 Balayage HTTP des routes (serveur `php -S 127.0.0.1:8000 -t public server.php`)

39 sondes GET réparties sur trois états de session (anonyme / utilisateur Jean-Marc / administrateur).
**Résultat : compteur de 501 = 0.**

| État | Exemples de routes | Code observé |
|---|---|---|
| Anonyme — public | `/`, `/login`, `/register`, `/encyclopedie`, `/orchids`, `/orchids/search`, `/orchid/{id}`, `/conseils`, `/conseil/{id}`, `/confidentialite`, `/mentions`, `/accessibilite` | **200** |
| Anonyme — protégé | `/macollection`, `/collections`, `/soins`, `/notifications`, `/me`, `/administration`, `/users`, `/user/new`, `/moderation`, `/conseil/new`, `/orchid/new` | **403** (grant, plus 501) |
| Connecté (user) | `/macollection`, `/collections`, `/soins`, `/notifications`, `/me`, `/collection/{id}`, `/notification/{id}` | **200** |
| Connecté (admin) | `/administration`, `/users`, `/user/new`, `/moderation`, `/orchid/new`, `/update/orchid/{id}`, `/update/conseil/{id}` | **200** |

Avant l'incrément 4/5, `/notifications`, `/administration` (placeholder mis à part), `/users`,
`/user/new`, `/moderation` et toutes les actions POST associées tombaient en **501** (classe ou
méthode absente). Elles répondent désormais **200 / 403 / 404 / 302** selon le grant.

### 4.3 Cycle de vie complet (preuves en direct, MariaDB)

| Scénario | Action | Vérification |
|---|---|---|
| RG3 | `POST /soin/new` (Jean-Marc) → 200 | +1 soin ; notification `rappel_soin` créée, message « … prévu le 24/10/2026 … » (J+7) |
| Proposition | `POST /orchid/propose` ×2 (Jean-Marc) → 200 | fiches `plante_test_mod_a/b` créées `is_published = 0`, listées dans `/moderation` |
| Approbation (RG5) | `POST /moderation/plante_test_mod_a/approve` → 200 | `is_published = 1` ; `GET /orchid/plante_test_mod_a` → **200** ; notif `validation` à l'auteur |
| Refus (RG5) | `POST /moderation/plante_test_mod_b/reject` → 200 | orchidée supprimée ; `GET /orchid/plante_test_mod_b` → **404** ; notif `rejet` à l'auteur |
| Notification lue | `POST /notification/{id}/read` → 200 | `is_read = 1` |
| Notification supprimée | `POST /notification/delete/{id}` → 200 | ligne retirée |
| Création utilisateur | `POST /update/user` (id vide, admin) → 200 | compte `zz_test_user@…` présent (hash bcrypt) |
| Suppression utilisateur | `POST /user/delete/{id}` (admin) → 200 | compte absent de `/users` |

Compteurs MySQL pendant la campagne : notifications 6 → **9** (RG3 + validation + rejet),
soins 4 → 5, +1 utilisateur de test. Après nettoyage et suppression des données de test, la base
est **revenue à la baseline seed** : `users 3 / orchids 21 / conseils 16 / collections 6 / soins 4 /
notifications 6`.

---

## 5. Comment rejouer la Phase 03

```powershell
# Base (MariaDB 10.4.32) — schéma puis seeds dans l'ordre
mysql -u root --default-character-set=utf8mb4 -e "source data/schema_mysql.sql"
# seeds 01..06 dans data/seeds/
# Démarrage
C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe -S 127.0.0.1:8000 -t public server.php
```

Comptes de démonstration :

| Rôle | Email | Mot de passe |
|---|---|---|
| Administrateur | `jeremy.ferrierpro@gmail.com` | `demoadmin` |
| Modérateur | (rôle `moderateur` via `/users` → édition) | — |
| Utilisateur | `admin@mesorchidees.fr` (Jean-Marc) | `demouser` |
| Utilisatrice | `jessica.amateur@gmail.com` | `demouser` |

---

## 6. État Git & suite

- HEAD = `89717f0` (incréments 2 & 3 scellés).
- **Incréments 4 & 5 : code en place, validé, non commité** (en attente d'une autorisation de
  commit). Fichiers concernés :
  - Modifiés : `AdminController.php`, `SoinController.php`, `OrchidRepository.php`,
    `UserRepository.php`, `sidebar.php`.
  - Créés : `ModerationController.php`, `NotificationController.php`, `UserController.php`,
    `Entity/Notification.php`, `Repository/NotificationRepository.php`, `Repository/StatsRepository.php`,
    `templates/pages/{admin,moderation,notifications}/`.
- Suite envisageable (phases ultérieures) : export PDF des rappels, pagination des listes admin,
  réinitialisation de mot de passe, et déploiement Render.
