# Structure du diaporama — 3ᵉ oral DWWM « Mes Orchidées »

> **Durée totale** : 35 min — 30 min FR + 5 min EN
> **Règle d'or** : montrer des preuves réelles du dépôt, pas de théorie.
> **Consignes coordinatrice respectées** :
> - Pas de zoning dans le support principal
> - Pas de persona, pas de SWOT
> - Chaque outil d'accessibilité sur une slide distincte
> - Extraits de code directement dans les slides (pas de bascule VS Code)
> - Transitions de langue annoncées explicitement

---

## Séquence 0 — Ouverture (0 min → 0:30)

| Élément | Détail |
|---|---|
| **Slide** | Titre + nom + logo + badge DWWM DEV_FAD_2026 |
| **Contenu** | « Mes Orchidées — 3ᵉ oral de suivi », ton nom, URL GitHub |
| **Preuve** | Capture de la page d'accueil du site déployé (ou local) |

---

## Séquence EN — Pitch en anglais (0:30 → 5:30 ⏱ 5 min)

> **Transition annoncée** : *« I will now present the first part of my pitch in English. »*

| Slide | Contenu | Objectif | Preuve / Fichier |
|---|---|---|---|
| **EN-1** | Project overview in English | Présenter le besoin en anglais | `README.md` §Pourquoi ce projet |
| **EN-2** | Tech stack summary (PHP 8 / MySQL 8 / MVC / PDO) | Montrer la stack réelle | `composer.json:12`, `config/db.php:69-73` |
| **EN-3** | Key features live (3 captures écran) | Démontrer le MVP fonctionnel | Captures accueil, collection, fiche orchidée |
| **EN-4** | What I learned — English sentence | Bilan compétences EN | — |

> **Note** : cette partie chronométrée à 5 min max doit être répétée plusieurs fois.

---

## Transition de langue

> *« Cette partie en anglais est terminée. Je reprends maintenant en français pour la suite de la présentation. »*

---

## Séquence FR — Présentation complète (5:30 → 35:00 ⏱ 30 min)

---

### 1. Besoin, objectifs et contraintes (2 min)

| Élément | Détail |
|---|---|
| **Critère évalué** | Phase 1 — expression du besoin et des contraintes |
| **Preuves** | `docs/project-foundation/phase-01/01-functional-framing.md` · `docs/USER_STORIES.md` |
| **Contenu slides** | 1 slide besoin + 1 slide contraintes techniques/fonctionnelles/accessibilité |
| **Ce qu'il faut dire** | Le problème (info éparpillée, sites non sécurisés), les objectifs (centraliser, collectionner, accompagner), les contraintes (accessibilité RGAA, responsive, PHP ≥ 8.1, MySQL) |
| **⚠️ À éviter** | Pas de persona, pas de SWOT |

---

### 2. Maquettage et résultat visuel (2 min)

| Élément | Détail |
|---|---|
| **Critère évalué** | Phase 1 — maquettage, charte graphique |
| **Preuves** | `docs/site_html/` (maquette statique HTML/CSS) + captures desktop/mobile du rendu réel |
| **Contenu slides** | 1 slide desktop + 1 slide mobile du résultat final |
| **À faire avant l'oral** | Capturer `docs/site_html/index.html` et 2-3 pages clés dans un navigateur, responsive 375px + 1280px |
| **⚠️ Zoning retiré** | Ne pas inclure le zoning dans le support principal |

---

### 3. Diagramme UML cas d'utilisation (2 min)

| Élément | Détail |
|---|---|
| **Critère évalué** | Phase 1 — diagramme de cas d'utilisation |
| **Preuve** | `docs/oral-prep/02-uml-cas-utilisation.puml` → exporter en PNG |
| **Contenu slides** | 1 slide = le diagramme PNG en pleine largeur |
| **Points à expliquer** | 3 acteurs (Visiteur, Utilisateur, Admin), généralisation U→V, paquets Consulter/Auth/Collection/Admin, une relation `<<include>>` (soin→rappel) et une `<<extend>>` (désactiver rappel) |
| **Corrections appliquées** | Pas de flèches sur associations · extend sans croisement · U hérite de V |

---

### 4. Arborescence et structure MVC (3 min)

| Élément | Détail |
|---|---|
| **Critère évalué** | Phase 2 — arborescence · Phase 3 — MVC explicable |
| **Preuve** | §2 de l'audit + `README.md` §Arborescence |
| **Contenu slides** | 1 slide arbre annoté + 1 slide parcours requête (schéma fléché) |
| **Parcours à montrer** | Requête → `.htaccess` → `public/index.php` (session+CSRF+router) → `routes.php` (match+grant) → Contrôleur → Service → Repository (PDO préparé) → Entity → Vue (échappée `e()`) → réponse HTML/JSON |
| **Nuance honnête** | Pas de DI container — `new Repository()` en dur dans les contrôleurs. Expliquer pourquoi (phase d'apprentissage). |

---

### 5. Front-end : HTML/CSS responsive + fonctionnalité JavaScript (4 min)

| Élément | Détail |
|---|---|
| **Critère évalué** | Phase 2 — HTML/CSS responsive + JS |
| **Preuve HTML/CSS** | `public/assets/scss/layout/_grid.scss`, media queries dans les vues |
| **Fonctionnalité JS recommandée** | `public/assets/js/features/rappels-modal.js` |
| **Test JS associé** | `tests/js/rappels-modal.test.mjs` — PASS |

**Extraits à intégrer dans les slides (3 max) :**

```
// Slide 5a — Extrait HTML vue show.php (≤ 8 lignes)
<article>
  <nav aria-label="Navigation espèce">...</nav>
  <button data-modal="rappels" aria-haspopup="dialog"
          aria-expanded="false">Gérer mes rappels</button>
</article>
```

```css
/* Slide 5b — Extrait CSS responsive (≤ 6 lignes) */
@media (max-width: 768px) {
  .collection-grid { grid-template-columns: 1fr; }
}
```

```js
// Slide 5c — Extrait JS rappels-modal.js (≤ 10 lignes)
// Ouverture + fetch fragment authentifié
async function openRappelsModal(trigger) {
  const url = trigger.dataset.href ?? '/rappels';
  const res = await fetch(url, { headers: { 'X-Requested-With': 'fetch' } });
  const html = await res.text();
  injectFragment(html);
  trapFocus(modal); // → core/focus.js
}
```

| Points à expliquer | Détail |
|---|---|
| Enhancement progressif | Sans JS, le lien `/rappels` reste navigable |
| Focus trap | `core/focus.js` — Tab cyclique + Échap + retour focus |
| CSRF | `FormData` inclut le jeton caché dans le POST |
| aria-live | Le conteneur de la modale a `role="dialog" aria-modal="true"` |

---

### 6. Accessibilité — 5 slides (5 × 1 = 5 min)

> **Chaque outil sur une slide distincte** (consigne formelle).
> **Impératif** : effectuer le test AVANT de créer la slide. Ne pas inventer un score.

| Slide | Outil | Ce que doit montrer la capture | Action requise avant l'oral |
|---|---|---|---|
| **6a** | **Lighthouse** (Chrome DevTools) | Score Accessibilité ≥ 90 + 2-3 recommandations | Lancer sur le site local, capturer |
| **6b** | **RGAA Checker** | Résultat critères 6, 7, 11 (structure/forms) | Utiliser l'extension ou le site rgaa numerique.gouv.fr |
| **6c** | **Validateur W3C HTML** | Page sans erreur (0 erreurs / warnings acceptés) | Coller l'URL ou le code HTML dans validator.w3.org |
| **6d** | **Validateur W3C CSS** | CSS valide (les warnings SCSS compilés sont OK) | Valider le CSS compilé de `public/assets/css/` |
| **6e** | **Extension Blindly** | Simulation trouble visuel / dyschromatopsie | Installer l'extension, tester 3 pages, capturer |

**Preuves code à montrer en complément (orales)** :
- `core/focus.js` — piège de focus et retour du focus
- `core/modal.js` — rôle dialog, aria-modal
- `templates/pages/orchids/show.php` — `<article>`, `<nav aria-label>`, `alt` dynamique
- `templates/pages/accessibilite.php` — déclaration d'accessibilité

**Statut actuel** : aucun rapport d'outil n'est commité dans le dépôt → **À EXÉCUTER avant l'oral** (priorité Critique dans l'audit).

---

### 7. Base de données — MCD/MLD + SQL (4 min)

| Élément | Détail |
|---|---|
| **Critère évalué** | Phase 3 — MCD/MLD, dictionnaire, SQL de 2-3 tables, relations |
| **Preuves** | `docs/architecture/01-mcd-mld-aligne.md` · `docs/oral-prep/01-dictionnaire-donnees.md` · `data/schema_mysql.sql:40-302` |
| **Tables à présenter** | `users` → `collections` → `soins` (cœur métier, porte la RG1 et les 3 types de FK) |

**Slide 7a** — MCD simplifié (3 tables + attributs clés + cardinalités)
**Slide 7b** — Extrait SQL `users` + `collections` (CREATE TABLE avec FK, UNIQUE, ENUM)
**Slide 7c** — Extrait SQL `soins` + relation `types_soin` (FK RESTRICT)
**Slide 7d** — Dictionnaire : 5-6 colonnes expliquées (password_hash, role ENUM, rappel_actif…)

**Points à montrer à l'oral** :
- PK UUID(`CHAR(36) DEFAULT (UUID())`) pour users/collections/soins
- `uq_users_email` UNIQUE = un compte par email
- `ENUM('user','admin','moderateur')` = contrôle d'accès intégré en BDD
- FK CASCADE vs RESTRICT vs SET NULL — expliquer un cas concret
- FULLTEXT `ft_orchids_search` = recherche sans Elasticsearch

---

### 8. Backend PHP POO — Démonstration « Enregistrer un soin » (4 min)

> **Fonctionnalité recommandée** (par l'audit §12) : SoinController::create() → RappelService::generer() → SoinRepository::create()

| Slide | Contenu | Fichier source |
|---|---|---|
| **8a** | Besoin utilisateur : « Je viens d'arroser ma Phalaenopsis, je le consigne » | Capture du formulaire (macollection) |
| **8b** | Parcours : route → contrôleur → service → repository → PDO | Extraits code (5-8 lignes chacun) |
| **8c** | Règles métier : opt-in `rappel_actif`, fréquence bornée [1;365], déduplication | `src/Service/RappelService.php:87` |
| **8d** | SQL généré : `INSERT INTO soins (...) VALUES (:collection_id, :id_type_soin, ...)` | `src/Repository/SoinRepository.php:119` |

**Extrait clé — SoinController::create() (simplifié)** :
```php
// src/Controller/SoinController.php:73-109
public function create(Request $request): Response
{
    $this->assertCsrf($request);                         // sécurité CSRF
    $collection = $this->repo->collectionOwnedBy(       // RG1 isolation
        $request->param('collection_id'),
        current_user()['id']
    );
    if (!$collection) return $this->json403();

    $soin = $this->soinRepo->create([...]);             // INSERT préparé
    if ($collection['rappel_actif']) {
        $this->rappelService->generer($collection, $soin); // planifie rappel
    }
    return $this->redirect('/macollection', flash: ['Soin enregistré ✓']);
}
```

**Extrait clé — PDO préparé (SoinRepository::create())** :
```php
// src/Repository/SoinRepository.php:119-145
$stmt = $this->pdo->prepare(
    'INSERT INTO soins (id, collection_id, id_type_soin, soin_date, notes, engrais, substrat, ravageurs)
     VALUES (:id, :collection_id, :id_type_soin, :soin_date, :notes, :engrais, :substrat, :ravageurs)'
);
$stmt->execute([
    ':id' => $this->uuid(), ':collection_id' => $data['collection_id'],
    ':engrais' => (int) $data['engrais'], // binding typé booléen → 0/1
]);
```

---

### 9. Sécurité (3 min)

| Slide | Contrôle | Preuve code | Test |
|---|---|---|---|
| **9a** | Requêtes préparées PDO, `EMULATE_PREPARES=false` | `config/db.php:72` | 25 tests unitaires PASS |
| **9b** | Échappement XSS : `e()` = `htmlspecialchars(ENT_QUOTES)` | `config/helpers.php:58` | 8 tests XSS PASS |
| **9c** | Hachage bcrypt : `password_hash(PASSWORD_DEFAULT)` | `src/Service/AuthService.php:46` | — |
| **9d** | CSRF : jeton 32 bytes + `hash_equals` timing-safe | `src/Controller/AbstractController.php:213` | 8 tests CSRF PASS |
| **9e** | RG1 isolation : jointure `collections.user_id = :uid` partout | `SoinRepository`, `CollectionRepository` | Tests RG1 (SKIP — besoin base de test) |

> **Statut global** : PROUVÉ PAR LE CODE pour tous les contrôles majeurs.
> **Test à exécuter avant l'oral** : `php tests/run.php` → capturer le terminal (PASS=25 FAIL=0).

---

### 10. Déploiement (3 min)

| Élément | Détail |
|---|---|
| **Critère évalué** | Hébergement justifié, config serveur, procédure |

**Deux scénarios possibles — à trancher avant l'oral** :

#### Scénario A — Déploiement réel effectué
| Slide | Contenu |
|---|---|
| Comparatif hébergeurs (A/B/C) | GoogieHost / o2switch / Render — critères réels |
| Configuration serveur | Capture du `.env` (sans secrets), phpMyAdmin |
| Procédure déployée | Étapes réelles + URL publique fonctionnelle |
| Vérification post-déploiement | Capture de la suite HTTP `tests/http/run.php` sur le serveur distant |

#### Scénario B — Démo locale assumée
| Slide | Contenu |
|---|---|
| Dockerfile commenté | `Dockerfile:1-60` — PHP 8.2-apache, pdo_mysql, .env retiré |
| Pourquoi pas encore en ligne | Contrainte coût / validation hébergeur en cours |
| Plan de déploiement documenté | `docs/project-foundation/phase-05/05-deployment-runbook.md` |
| Démonstration locale live | `php -S localhost:8000 server.php` — montrer en direct si le jury l'autorise |

> **À décider** : demander au formateur si une URL publique est obligatoire ou si une démo locale suffit.

---

### 11. Gestion de projet, Git et outils (2 min)

| Élément | Détail |
|---|---|
| **Critère évalué** | Méthodologie, GitHub, branches, outils |
| **Preuves Git** | Conventional Commits en français (`feat(scope):`...) — PRUVÉ dans l'historique |
| **Branches** | `main` (stable) · `dev` (intégration) · `test` (recette) |
| **Board GitHub** | https://github.com/users/jeremyferrierpro-create/projects/1 |

**Slides** :
- 1 slide = captures Kanban GitHub Projects (phases 00→E4)
- 1 slide = log Git annoté (exemples de commit messages Conventional)
- 1 slide = outils utilisés : VS Code · Git/GitHub · PHP 8.3 · Composer · MySQL 8 · Docker (configuré) — uniquement ceux prouvables

**⚠️ Distinction honnête** : Docker = configuré, build testé localement. Pas de CI/CD GitHub Actions si non réellement configuré.

---

### 12. Bilan personnel et perspectives (2 min)

| Slide | Contenu |
|---|---|
| **État réel du projet** | Ce qui fonctionne, ce qui reste à faire (matrice de conformité résumée) |
| **Difficultés rencontrées** | Migration localStorage → MySQL, isolation RG1 sans RLS natif, routeur maison |
| **Compétences acquises** | POO PHP, PDO, MVC, sécurité (CSRF/XSS/bcrypt), Gitflow, MySQL schema design |
| **Limites actuelles** | Uploads non persistants (système de fichiers), pas de TLS sur connexion distante |
| **Évolutions envisagées** | Notifications push (Web Push), API REST documentée, recherche facettée, mode sombre |
| **Projet professionnel** | *À rédiger par toi* — emploi / poursuite formation / freelance / autre |

---

## Récapitulatif minutage

| # | Séquence | Durée | Cumul |
|---|---|---|---|
| 0 | Ouverture | 0:30 | 0:30 |
| EN | Pitch anglais | 5:00 | 5:30 |
| — | Transition langue | 0:10 | 5:40 |
| 1 | Besoin & contraintes | 2:00 | 7:40 |
| 2 | Maquettage | 2:00 | 9:40 |
| 3 | UML | 2:00 | 11:40 |
| 4 | Arborescence & MVC | 3:00 | 14:40 |
| 5 | Front (HTML/CSS/JS) | 4:00 | 18:40 |
| 6 | Accessibilité (5 outils) | 5:00 | 23:40 |
| 7 | BDD & SQL | 4:00 | 27:40 |
| 8 | Backend POO (démo soin) | 4:00 | 31:40 |
| 9 | Sécurité | 3:00 | 34:40 |
| 10 | Déploiement | 3:00 | *hors base* |
| 11 | Gestion projet & Git | 2:00 | *hors base* |
| 12 | Bilan | 2:00 | *hors base* |

> **Séquences 10-12** : à ajuster selon les preuves réellement obtenues (hébergement validé ?). Les 30 min FR sont atteignables en compressant 11 et 12 si le déploiement est local.

---

## Prérequis manquants — À faire avant l'oral

| Tâche | Priorité | Statut |
|---|---|---|
| Capturer les maquettes desktop/mobile | Haute | ⬜ |
| Générer le PNG du diagramme UML depuis le `.puml` | Critique | ⬜ |
| Exécuter Lighthouse + capture | Critique | ⬜ |
| Exécuter RGAA Checker + capture | Critique | ⬜ |
| Valider HTML auprès du W3C + capture | Critique | ⬜ |
| Valider CSS auprès du W3C + capture | Critique | ⬜ |
| Installer Blindly + tester + capture | Critique | ⬜ |
| Lancer `php tests/run.php` + capture terminal | Haute | ⬜ |
| Trancher scénario A ou B pour le déploiement | Critique | ⬜ |
| Rédiger la partie « projet professionnel » | Haute | ⬜ |
| Répéter la partie EN chronométrée | Haute | ⬜ |
| Répéter l'oral complet en conditions réelles | Haute | ⬜ |
