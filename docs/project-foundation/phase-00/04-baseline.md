# 04 — Baseline technique et état de santé (§8)

---

## 1. Git — dépôt racine et sous-dépôts (§3.1 / §8.1)

- **Racine `mes_orchidees/` : PAS un dépôt Git** (`git rev-parse` → fatal). 25 sous-dépôts
  Git indépendants détectés. Les exerçant dans le périmètre projet :

| Dépôt | Branche | HEAD | Working tree | Remote |
|---|---|---|---|---|
| `docs/mes_orchidees_php` | `main` | `c031e12` | **MODIFIÉ (non propre)** | github/jeremyferrierpro-create/mes_orchidees_php |
| `docs/support_formateur/router-librairie` | `main` | `e872796` | propre | github/evaluationWeb/router-librairie |

### 1.1 Modifications PRÉEXISTANTES (base de travail) — Ne sont PAS mon travail

`git status --porcelain` sur `mes_orchidees_php` (9 entrées), relevé **avant** toute action :

```
 M .htaccess
 M assets/css/style.css
 M assets/css/style.css.map
 M assets/css/style.min.css
 M assets/css/style.min.css.map
 M assets/js/services/orchid-service.js
 M index.php
?? .docs/mcd_mld.lo1
?? .docs/mcd_mld.loo
```

Diffstat : `.htaccess` (+6/-…), `assets/css/style.css` (+3123…), `orchid-service.js` (+70…),
`index.php` (+5…). **Aucune de ces modifications n'a été lue en détail ni touchée** par la
Phase 00 (lecture seule). Elles constituent la baseline de départ.

> Avertissement Git : LF→CRLF sur `style.css` et `orchid-service.js` (bruit de line endings).

## 2. Environnement (§8.2) — `VERIFIED`

| Outil | Version | Note |
|---|---|---|
| OS | Windows 25H2 | Shell PowerShell (sep `;`) |
| PHP (CLI local) | **8.3.33** (laragon) | Router exige `^8.1` → OK |
| Composer | 2.10.3 | Présent, non utilisé dans le projet |
| Docker image cible | `php:8.2-apache` | **Écart 8.2 (prod) vs 8.3 (local)** |
| Extensions PHP (Dockerfile) | pdo, pdo_mysql, pdo_pgsql, pgsql | Cohérent Neon |

Base de données : **Neon PostgreSQL distant** (DSN réel dans `.env`). Non contacté (§3.2).

## 3. Fichier `.env` (§7) — noms de variables uniquement, JAMAIS les valeurs

Variables détectées (présence) : `NEON_DSN`, `APP_ENV`, `APP_DEBUG`, `COOKIE_DOMAIN`,
`COOKIE_SECURE`, `COOKIE_SAMESITE`, `JWT_SECRET`. Un `.env` réel **existe** (non commité,
protégé par `.htaccess`). → Secret de production potentinel présent dans la base de travail.

## 4. Tests de référence (§8.3)

- **Aucun test PHP** dans `mes_orchidees_php` (ni `phpunit.xml`, ni `tests/`).
- **Aucune commande de test exécutable** pour le projet → rien lancé (conforme §8.3 :
  ne pas simuler un succès ; documenter le prérequis manquant).
- La librairie router possède ses tests (`vendor` non installée → exécution nécessiterait
  `composer install`, **non fait** en Phase 00).
- Prérequis manquant pour toute exécution : dépendances Composer absentes, base Neon
  distante non reachable de façon sûre/autorisée.

## 5. Anomalies / dette préexistantes constatées (VERIFIED, non corrigées)

Code lu en Phase 00 ; **aucune correction** (interdite §3.2). À traiter en phase dédiée :

1. **`api/config/db.php` l.30-31** : deux `throw` consécutifs → le 2ᵉ est du **code mort
   inatteignable** (message dupliqué). Gravité faible.
2. **`api/middleware/auth.php` l.40-42** : `base64UrlDecode()` fait `return` ligne 40, puis
   lignes 41-42 **inaccessibles** ; la version retournée **ne restaure pas le padding `=`**
   (logique correcte mais morte). Risque de décodage JWT fragile. Gravité moyenne.
3. **`HomeController.php`** (exemple MVC) ne **s'étend pas** d'`AbstractController` et redéclare
   `render()` en privé → incohérence héritage dans le support de cours (référence, pas le projet).
4. **Architecture hybride** : pages `.php` peuplées via JSON client alors que l'API PDO existe →
   double source de données (`.json` vs Neon), non branchée.

## 6. Classification des constats (§8.4)

| Constat | Classe |
|---|---|
| État Git, HEAD, diffs préexistants | VERIFIED |
| Versions PHP/Composer/Dockerfile | VERIFIED |
| Absence tests PHP | VERIFIED |
| Bugs code mort (db.php, auth.php) | VERIFIED |
| Modèle de données détaillé (schémas non lus à fond) | NOT-VERIFIED |
| Contenu des cours PDF | BLOCKED |
| État « localStorage » du README vs code | REPORTED (README) / contredit par VERIFIED |

## 7. Limites

- Aucun commit/stash/reset effectué ; working tree laissé **intact**.
- Aucune valeur de secret affichée ; aucune base contactée ; aucun service lancé.
