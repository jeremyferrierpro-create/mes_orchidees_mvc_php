# 06 — Architecture cible (préparée, non implémentée) et roadmap (§9, §11)

> Aucun code n'est produit ici. Ce document **prépare** l'orientation et **propose** les
> phases. Leur exécution est interdite en Phase 00.

---

## 1. Architecture cible MVC (§9.1) — alignée sur l'exemple `structure_mvc`

Structure visée pour `mes_orchidees_php` (équivalences avec l'exemple de cours) :

```
mes_orchidees_php/
├── public/index.php          ← FRONT-CONTROLLER unique (remplace le switch du cours)
├── src/
│   ├── Controller/           ← récept requête, validation, contrôle d'accès, orchestration
│   │   └── AbstractController.php   (render / jsonResponse / CSRF — cf. cours)
│   ├── Entity/               ← entités & invariants métier (Orchid, User, Collection, Conseil, Soin, Notification)
│   ├── Repository/           ← contrats + impls de persistance (PDO Neon)
│   └── Service/              ← cas d'usage / logique métier
├── templates/                ← VUES (gabarits ; remplace les pages .php racine)
├── config/                   ← db, env (déplacés depuis api/config)
├── composer.json             ← ABSENT → à créer (autoload App\ + mithridatem/router)
└── .env (non commité)
```

**Répartition des responsabilités (§9.1) :**
- **Model** : `Entity/` + `Repository/` (invariants, validation domaine, persistance).
- **View** : `templates/` (gabarits + composants, accessibilité RGAA reprise du front actuel).
- **Controller** : `src/Controller/` (requêtes, validation entrées, autorisation via
  `GrantCheckerInterface` du router, orchestration).
- **Services** : `src/Service/` (cas d'usage, transactions).
- **Infrastructure** : PDO/Neon, fichiers, service d'auth.

Les règles métier ne doivent plus vivre dans les vues ni dans `assets/js` (dette R5).

## 2. Nomenclature des routes (mandate) → mapping router

| Cas d'usage | Route cible | Verbe | Router |
|---|---|---|---|
| Connexion | `/login` | POST | `Route::post` / `mapController` |
| Déconnexion | `/logout` | POST | ✓ |
| Inscription | `/register` | GET/POST | ✓ |
| Liste entité | `/orchids`, `/collections`… | GET | `{…}` |
| Fiche | `/orchid/{id}` | GET | segment dynamique `{id}` |
| Création | `/orchid/new` | GET/POST | ✓ |
| Mise à jour | `/update/orchid` (`/orchid/{id}/edit`) | GET/POST | ✓ |
| Suppression | `/orchid/delete/{id}` | POST/DELETE | ✓ + GrantChecker admin |

> Le routeur couvre nativement cette nomenclature (segments `{id}`, wildcards, contrôle
> d'accès). **Décision D1** reste à valider par l'humain avant intégration.

## 3. Principes d'architecture à évaluer (§9.2)
Séparation des couches ; couplage faible Controller↔Repository (injection) ; validation
des entrées centralisée ; gestion d'erreurs explicite (exceptions du router) ; isolation
des données par utilisateur (scoping collection, cf. migration existante) ; authz par rôles ;
transactions PDO ; testabilité (RequestContext injectable) ; accessibilité & perfs conservées.
→ **Pas de microservices** ni abstraction distribuée (injustifié pour un site PHP mono).

## 4. Roadmap proposée (§11)

| Phase | Objectif unique | Nature | Prérequis | Critère de passage |
|---|---|---|---|---|
| **00** | Baseline & audit | Lecture seule | — | **la présente** (verdict) |
| **01** | Cadrage fonctionnel (specs, règles de gestion, rôles, données) | Audit/lecture | D4, D5 tranchés | Specs validées par l'humain |
| **02** | Architecture & contrats (MVC, routes, schéma BDD autorité, intégration router) | Conception | D1,D2,D3,D7 | Plan validé ; **squelette** approuvé |
| **03+** | Incréments CRUD par entité (orchids → collections → conseils → users → admin) | **Implémentation autorisée** | 02 + sauvegarde R1 | Tests par incrément |
| **Stabilisation** | Non-régression, sécurité (R2–R4), a11y, perfs | Audit | 03+ | Tests verts + audit |
| **Prép. livraison** | Config Render, Docker, migrations, `.env` plateforme | Config | Stabilisation | Dry-run déploiement |
| **Livraison** | Déploiement | **Exécution à autorisation distincte** | Préparation | Go humain explicite |

### 4.1 Prérequis bloquants à lever dès maintenant (avant phase 01)
1. **D5** — sécuriser le travail préexistant (commit/sauvegarde, *sur autorisation*).
2. **D4** — statut des cours PDF (fournir texte **ou** valider `structure_mvc` comme autorité).
3. **D1** — arbitrage d'intégration du router (mode A/B/C).

### 4.2 Phases exigeant une autorisation humaine spécifique
- Tout commit/push/reset (hors Phase 00).
- Installation de dépendances (`composer install`).
- Toute exécution de migration/écriture en base (à fortiori sur Neon distant).
- Le déploiement (Render) et tout appel externe payant.
