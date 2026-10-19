# 01 — Connexions réelles et séparation des environnements

Périmètre : **quelle base utilise réellement l'application**, comment le code
construit la connexion, comment la base de test est isolée, et quel serveur est
**réellement actif** sur la machine (sans rien arrêter).

## 1. Chaîne de connexion : fichiers exécutés

| Étape | Fichier | Rôle |
|-------|---------|------|
| 1 | `config/env.php` (L26-60) | Parse le `.env` à la RACINE, peuple `$_ENV`/`$_SERVER`/`putenv()`. **Ne surcharge pas** une clé déjà présente (L48). |
| 2 | `config/db.php` (L31-86) | Fabrique PDO `getDb()` (singleton). Lit `DB_DSN` **ou** `DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASS`. |
| 3 | Front-controller | `public/index.php` utilise `getDb()` via les repositories. |

**Pilote réellement utilisé** : `config/db.php` L65 assemble le DSN avec le
préfixe codé en dur **`mysql:`** :
`sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', …)`. Le seul moyen
d'utiliser un autre moteur est de fournir un `DB_DSN` complet explicite (L49,
L53) — aucun code du dépôt ne fournit de DSN `pgsql:` actif.
**VERIFIED** (lecture du fichier).

Options PDO (L69-73) : `ERRMODE_EXCEPTION`, `FETCH_ASSOC`,
`ATTR_EMULATE_PREPARES => false` (vraies requêtes préparées). Standard et
portable, mais sans plus : cela **ne rend pas** le SQL portable.

## 2. Observation directe par connexion de diagnostic (lecture seule)

Commande exécutée via un script jetable (`_tmp_e2_diag.php`, **supprimé après**,
aucune écriture) passant par le vrai `getDb()` :

```
=== DEV (app config getDb) ===
database=mes_orchidees
version=10.4.32-MariaDB
tables=9
sql_mode=NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION
version_comment=mariadb.org binary distribution

=== TEST (mes_orchidees_test) ===
database=mes_orchidees_test
version=10.4.32-MariaDB
tables=9

=== POSTGRES driver disponible ? ===
pdo_drivers=mysql,sqlite
pgsql_ext=no
```

**Constats VERIFIED** :
- L'app se connecte bien à **MariaDB 10.4.32**, base `mes_orchidees`, 9 tables.
- La base de test `mes_orchidees_test` existe, même moteur, 9 tables.
- Les pilotes PDO disponibles sont **`mysql` et `sqlite` uniquement**.
  **`pdo_pgsql` n'est PAS chargé** → dans cet environnement l'application est
  **physiquement incapable** de parler à PostgreSQL, même avec un DSN `pgsql:`.
  **VERIFIED** (`PDO::getAvailableDrivers()`).

## 3. Séparation dev / test / distant

### Développement
- Source de config : `.env` racine (valeurs **non divulguées**). Clés présentes
  (rôle documenté, valeurs masquées) : `APP_ENV=development`, `APP_DEBUG=true`,
  `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_NAME=mes_orchidees`,
  `DB_USER=root`, `DB_PASSWORD=***MASKED***`, `DB_CHARSET=utf8mb4`,
  `APP_SECRET=***MASKED***`. **VERIFIED** (listing à valeurs masquées).

- ⚠️ **Écart de nommage constaté** : le `.env` définit `DB_PASSWORD`, mais
  `config/db.php` L51 lit **`DB_PASS`**. Donc `DB_PASS` reste vide côté app ; la
  connexion dev aboutit seulement parce que `root` a un mot de passe **vide**
  (config XAMPP par défaut). Risque : sur tout MySQL avec mot de passe, la
  connexion échouerait silencieusement. **VERIFIED** (comparaison des clés).
  → Recommandation (doc 06), **sans correction ici** : aligner `DB_PASS`/`DB_PASSWORD`.

### Tests HTTP
- Harnais : `tests/http/server-router.php` (L53-82). Il **injecte** la config de
  test dans `$_ENV`/`$_SERVER`/`putenv()` **avant** que `config/env.php` ne
  charge le vrai `.env`. Comme `env.php` L48 refuse de surcharger une clé déjà
  définie, les valeurs de test priment → isolation **déterministe**.
- Base de test : `mes_orchidees_test` par défaut (L55), suruser par
  `TEST_HTTP_DB_*`. DSN test construit là aussi en `mysql:` (L64-69).
- **Preuve d'isolation** : route réservée `/__http-test-probe` (L91-115) renvoie
  `SELECT DATABASE()`, le nombre de tables et `SELECT VERSION()` — le runner
  s'arrête si la base annoncée n'est pas la base de test. Aucun secret renvoyé.
  **VERIFIED** (lecture du harnais).

### Base distante
- Aucune base distante n'est configurée localement (`.env` pointe `127.0.0.1`).
- Le gabarit `.env.production.example` documente une cible **Render + MySQL
  externe** (PlanetScale, JAWSDB, ClearDB, Railway, VPS) et précise : « Render ne
  fournit PAS de MySQL managé (PostgreSQL seulement) ». **VERIFIED** (lecture).
  Cet environnement n'est pas actif ici → **NOT VERIFIED** (aucune connexion distante).

### Risque de connexion accidentelle à dev/prod
- Le mécanisme d'injection-before-`env.php` protège l'isolation des tests.
  **INFERRED** (justifié par `server-router.php` L9-15 + `env.php` L48).
- En production, si `DB_DSN`/`DB_*` sont absents, `getDb()` lève une exception
  (`db.php` L59-63) plutôt que de deviner une base → comportement fermé. **VERIFIED**.

## 4. Environnement serveur réellement actif (sans rien arrêter)

`Get-Process` + `Get-NetTCPConnection -State Listen` :

```
httpd   (PID 2576, 4880)  → Apache XAMPP, écoute sur :80 et :443
mysqld  (PID 3788)        → MariaDB, écoute sur :3306
aucun processus "php"     → pas de "php -S" actif au moment de l'audit
port :5432                → AUCUNE écoute (PostgreSQL non installé/non démarré)
```

**Constats VERIFIED** :
- Le site est servi par **Apache (XAMPP)**, pas par le serveur interne PHP.
  Le `public/.htaccess` (front-controller `index.php`) suppose Apache/LiteSpeed →
  cohérent avec l'observation. **VERIFIED**.
- « Il y a un dossier XAMPP » **ne prouvait pas** que le serveur actif est
  XAMPP : la vérification par ports/processus confirme bien `httpd` + `mysqld`.
  La consigne est respectée : **aucun processus n'a été arrêté**. **VERIFIED**.
- `php -v` CLI = **PHP 8.3.33** ; `composer.json` exige `php >=8.1` et n'a
  **aucune dépendance externe** (autoload PSR-4 only, routeur vendagé dans
  `lib/`) → le `vendor/` n'est que l'autochargeur. **VERIFIED** (lecture).

## 5. Synthèse

| Question | Réponse | Preuve |
|----------|---------|--------|
| Moteur réel | MariaDB 10.4.32 | connexion de diagnostic (VERIFIED) |
| Base dev | `mes_orchidees` (9 tables) | `SELECT DATABASE()` |
| Base test | `mes_orchidees_test` (9 tables) | harnais + diagnostic |
| Pilote PDO | `mysql` (codé en dur) ; `pgsql` **absent** | `db.php` L65, `getAvailableDrivers()` |
| Serveur actif | Apache XAMPP :80/:443 + mysqld :3306 | ports/processus |
| PostgreSQL | non installé, non démarré, non piloté | :5432 fermé, pgsql_ext=no |
| Base distante | aucune active | `.env`→127.0.0.1 |
