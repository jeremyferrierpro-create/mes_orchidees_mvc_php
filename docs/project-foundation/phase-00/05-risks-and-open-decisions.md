# 05 — Risques et décisions ouvertes (§10, §9.3)

---

## 1. Registre des risques (§10)

| # | Risque | Gravité | Probabilité | Preuve | Mesure recommandée |
|---|---|---|---|---|---|
| R1 | **Écrasement du travail préexistant** : 7 fichiers modifiés non commités sur la base de travail | **Élevée** | Haute | baseline §1.1 | Sauvegarder/commiter (autorisation) **avant** toute refonte |
| R2 | **Secret de prod dans `.env`** (NEON_DSN réel, JWT_SECRET) présent en local | **Élevée** | Moyenne | baseline §3 | Ne jamais commiter ; rotation si exposé ; variables via plateforme Render |
| R3 | **Base Neon distante** contactée par inadvertance lors de tests | Élevée | Moyenne | `api/config/db.php` | Isoler : DB de test locale / transactions rollback ; jamais de migration auto |
| R4 | **Auth JWT maison fragile** (padding base64Url + code mort) | Moyenne | Haute | baseline §5.2 | Refondre via patterns standards en phase sécurité |
| R5 | **Double source de données** (JSON client vs API PDO/Neon) → incohérence | Moyenne | Haute | inventory §3 | Choisir une source unique (API) en phase 02 |
| R6 | **Absence totale de tests** → régressions non détectées | Élevée | Haute | baseline §4 | Mettre en place PHPUnit + tests de routes dès MVC |
| R7 | **Docroot racine** (pages `.php` exposées) vs sécurité/front-controller | Moyenne | Haute | inventory §4 | Passer docroot en `public/` (aligné MVC + Render) |
| R8 | **Divergence PHP local 8.3 vs Docker 8.2 vs Render** | Moyenne | Moyenne | baseline §2 | Figée une version cible unique |
| R9 | **Contradiction de stack** (`stack.md` Next.js/TS vs mandate PHP) | Moyenne | Certaine | doc-formation §4 | Corriger `stack.md` (décision humaine) |
| R10 | **Cours PDF inaccessibles** → conventions MVC non tracées à 100 % | Moyenne | Certaine | doc-formation §3 | Fournir équivalent texte ou statuer sur l'exemple MVC comme autorité |
| R11 | **3 schémas SQL concurrents** (mysql/neon/supabase) | Moyenne | Haute | inventory §8 | Désigner le schéma faisant autorité |
| R12 | **Perte de traçabilité Git** (README vs remote vs branches) | Faible | Moyenne | doc-formation §5 | Harmoniser nom de dépôt + stratégie de branches |
| R13 | **Déploiement Render non préparé** (build, docroot, env) | Élevée | Moyenne | aucune config Render trouvée | Plan de livraison dédié, avec retour arrière |

## 2. Décisions ouvertes (§9.3) — à trancher par un humain

### D1 — Intégration de `Mithridatem Router` (question explicite de l'utilisateur)
**Question :** faut-il adopter la librairie router du formateur dans le projet ?

**Avis (recommandation, ≠ exigence validée) :** **OUI, adapté**, sous conditions.

Preuves techniques :
- PSR-4 (`Mithridatem\Routing\`), `require php ^8.1` → compatible PHP 8.3 local.
- Routage fluide + **segments dynamiques `{id}`** et wildcards → couvre exactement la
  nomenclature cible (`/user/new`, `/delete/entity/{id}`, `/entity/{id}`, `/users`).
- `Route::controller()` + résolution par conteneur → colle aux contrôleurs MVC.
- `GrantCheckerInterface` (`ArrayGrantChecker`) → **contrôle d'accès** branchable (rôles
  user/admin du projet).
- `RequestContextInterface` isole les superglobales → testabilité (§8.3, futur R6).
- `setBasePath()` → déploiement derrière un préfixe (utile Render).
- Licence MIT, repo propre (`main`, HEAD `e872796`), testé (phpunit).

**Options / conséquences :**
- *(A) Composer require (packagist)* — simple mais suppose publication ; **le paquet n'est
  pas nécessairement publié** → risque.
- *(B) Dépendance de chemin / VCS* sur `support_formateur/router-librairie` — reproductible
  en local ; lie le projet au dépôt voisin.
- *(C) Vendoring (copie `src/` + autoload)* — autonome pour Render, mais perd les MAJ.

**Conséquences :** nécessite `composer.json` (absent aujourd'hui) + passage du docroot en
`public/` (front-controller) → impact déploiement (R7/R13). **Recommandation :** option (B)
en local puis (A)/(C) à l'arbitrer selon la disponibilité du paquet sur le registre.
**Autorité :** utilisateur. **Moment :** phase 02 (architecture).

### D2 — Source de données de référence
JSON client vs API Neon : laquelle fait autorité en cible ? Impact R5, migrations.

### D3 — Base de données cible
Neon vs Supabase vs MySQL (3 schémas) → R11. Impact seeds/migrations/RLS.

### D4 — Périmètre de formation faisant autorité
Comment couvrir §5 malgré PDF illisibles (R10) : fournir texte, ou valider l'exemple
`structure_mvc` comme référence normative. **Sinon condition d'arrêt §15.**

### D5 — Préservation du working tree (R1)
Commit/stash/branche de sauvegarde des modifications préexistantes **avant** refactor.
→ Nécessite autorisation (§3.2 : aucun commit sans demande explicite).

### D6 — Correction de `stack.md` (R9)
Mettre à jour la stack déclarée vers PHP/MVC/Router, ou assumer la divergence.

### D7 — Version PHP cible unique (R8)
8.2 vs 8.3 : figer pour local/Docker/Render.

## 3. Arbitrages humains indispensables avant phase 01
D4 (cours), D5 (sauvegarde) et D1 (router) sont **bloquants**. Les autres peuvent être
décidés en phase 02. Sans D4 et D5 résolus, la poursuite contreviendrait à §15 et
menacerait le travail existant (§3).
