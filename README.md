# Mes Orchidées — Encyclopédie & Gestion de Collection

> *Le monde fascinant des orchidées, enfin centralisé.*

[![HTML5](https://img.shields.io/badge/HTML5-E34F26?logo=html5&logoColor=white)]()
[![SCSS](https://img.shields.io/badge/SCSS-CC6699?logo=sass&logoColor=white)]()
[![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?logo=javascript&logoColor=black)]()
[![DWWM](https://img.shields.io/badge/DWWM-Projet%20Fil%20Rouge%20--%202026%2F2027-blue)]()

**Projet fil rouge DWWM — Mes Orchidées**
Porté par un ancien producteur floral, fleuriste et gérant de jardinerie, aujourd'hui en reconversion développeur web.
> *« If I want to, I can, I do ! »*

📂 Dépôt GitHub : [mes_orchidees_mvc_php](https://github.com/jeremyferrierpro-create/mes_orchidees_mvc_php)

---

## Pourquoi ce projet ?

90% des sites sur les orchidées sont vieux, pas sécurisés et illisibles sur mobile. L'info existe mais elle est éparpillée.

**Mes Orchidées** fait simple :

1. **Centraliser** — une encyclopédie qui répond vite, avec les vrais noms (ordre, genre, origine...)
2. **Archiver** — une collection perso où j'enregistre mes plantes et leurs soins
3. **Accompagner** — des conseils clairs et des rappels pour ne pas oublier un arrosage

> Le projet a débuté en **JavaScript vanilla + localStorage** (front-only), a exploré **PostgreSQL/Supabase**, puis a été refactorisé en **application PHP 8 / MVC avec base MySQL** (PDO, requêtes préparées). Cet historique est documenté dans les rapports de phases (`docs/project-foundation/`).

---

## Ce qui marche aujourd'hui (MVP)

- [x] **Accueil** — recherche instantanée + mots latins qui flottent (ambiance serre)
- [x] **Encyclopédie** — 21 orchidées complètes, recherche serveur (FULLTEXT + repli), fiches détaillées + bouton `+ Collection`
- [x] **Ma Collection** — tableau de bord (total / épiphytes / terrestres / hémi), ajout de plantes, historique des soins et **rappels de soins opt-in**, conseils liés à la plante
- [x] **Conseils** — 6 rubriques (base, épiphytes, terrestres...) + 21 fiches espèces, recherche et modale qui s'ouvre au clic
- [x] **Authentification PHP** — sessions PHP durcies, mots de passe `password_hash()` (bcrypt), protection CSRF, anti-fixation
- [x] **Administration & modération** — tableau de bord, gestion des utilisateurs (rôles), validation des conseils soumis
- [x] **Responsive + Accessibilité** — RGAA/WCAG, clavier 100%, `focus-trap` dans les modales, `aria-*` partout
- [x] **PWA** — `sw.js` + `manifest.json`, consultable hors-ligne

---

## Lancer le projet (serveur local)

Prérequis : PHP ≥ 8.1, MySQL/MariaDB, Composer.

```bash
# 1. Cloner le dépôt
git clone https://github.com/jeremyferrierpro-create/mes_orchidees_mvc_php.git
cd mes_orchidees_mvc_php

# 2. Installer les dépendances et configurer l'environnement
composer install
cp .env.example .env   # puis renseigner vos accès MySQL

# 3. Créer la base : importer data/schema_mysql.sql
#    puis data/seeds/ dans l'ordre (01 → 06)

# 4. Lancer (au choix)
php -S localhost:8000 server.php   # serveur interne PHP (front-controller /public)
# ou Docker (voir Dockerfile, php:8.2-apache)
```

**Comptes de démonstration :** trois comptes **entièrement fictifs** (`admin@example.com`, `utilisateur@example.com`, `moderateur@example.com`) sont créés par `data/seeds/01_seed_users.sql`. Leurs mots de passe ne sont publiés nulle part : (re)générer un hash bcrypt avant la démonstration, selon la procédure documentée dans le seed.

---

## Stack — Honnête et assumée

| Couche | Ce qui est réellement en place |
|---|---|
| Backend | PHP ≥ 8.1, MVC maison : routeur à attributs `Mithridatem` (lib tierce), front-controller `public/index.php`, `server.php` pour le dev |
| Base de données | MySQL 8 / MariaDB via PDO, requêtes préparées (`PDO::ATTR_EMULATE_PREPARES = false`), migrations + seeds versionnés |
| Sécurité | `password_hash()` (bcrypt), CSRF vérifié en `hash_equals`, sessions durcies (anti-fixation), échappement `e()`, isolation des données par utilisateur |
| Front | SCSS (7-1) → CSS compilé, JavaScript modules ES, PWA (`sw.js` + `manifest.json`) |
| Architecture | `src/Controller` → `src/Service` → `src/Repository` → `src/Entity`, templates PHP dans `templates/` |
| Tests | Harnais maison (`php tests/run.php`, tests JS sous node), contrôles statiques `php -l` |
| Infra | Docker (`php:8.2-apache`) ; hébergeur final non encore validé |

**Choix expliqués à l'oral :**
- Le routeur découvre les routes via les attributs PHP des contrôleurs : pas de fichier de routes à maintenir à la main.
- Chaque table a son `Repository` (requêtes préparées uniquement) et son `Entity` : la logique métier reste dans les `Service`.
- L'accès aux données d'un utilisateur passe toujours par une jointure sur la collection (`collections.user_id`) : un utilisateur ne voit jamais les données d'un autre.

---

## Arborescence réelle

```
mes_orchidees_mvc_php/
├── public/            index.php (front-controller), assets/ (css, js, scss, images), sw.js, manifest.json, offline.html
├── src/
│   ├── Controller/    Home, Encyclopedie, Collection, Soin, Rappel, Conseil, Auth, Admin, Moderation, Notification, User, Page
│   ├── Entity/        Orchid, Collection, Soin, Conseil, Notification, TypeSoin, CategorieConseil
│   ├── Repository/    accès PDO + requêtes préparées
│   ├── Service/       logique métier (rappels, notifications, upload)
│   ├── Security/      session durcée, CSRF, hash
│   └── Exception/
├── templates/         layout.php + pages/ + components/
├── config/            db.php, env.php, helpers.php, routes.php
├── lib/Mithridatem/Router/   routeur (attribution tierce — ne pas modifier)
├── data/              schema_mysql.sql, migrations/, seeds/ (01 → 06)
├── tests/             run.php (harnais), http/, js/
├── archive/legacy/    ancienne application front-only PHP (référence historique)
├── docs/              documentation projet (phases, MCD/MLD, user stories, supports de cours)
├── Dockerfile, server.php, composer.json
└── .env (gitignoré — jamais committé)
```

> Chaque fichier du projet est commenté en français, pour que je puisse l'expliquer mot à mot devant le jury.

---

## Feuille de route — Simple et lisible

- [x] Maquettes Figma + première version front-only (HTML/CSS/JS vanilla)
- [x] SQL / Modélisation BDD (MCD/MLD, schema MySQL, migrations, seeds)
- [x] Refonte PHP 8 / MVC MySQL (Supabase exploré puis écarté) — auth, collection, soins + rappels, admin & modération
- [ ] Tests de recette complets + audit accessibilité formalisé → **MVP février 2027**

Travail sur branches thématiques et relecture avant intégration ; workflow Gitflow simplifié `main` ← `dev` ← `feature/*`, avec `test` comme environnement de recette.

---

## Accessibilité

- Contrastes ≥ 4.5:1, palette testée daltonisme (Adobe Color)
- Navigation clavier complète, `skip-link`, `focus-trap` dans les modales
- Sémantique `role="dialog" aria-modal`, `aria-expanded`, `alt` descriptifs
- Audit outillé (RGAA Checker, W3C Validator) : à finaliser et documenter avant la soutenance

---

## Auteur

**Jeremy Ferrier**
Parcours végétal → code. J'aime les plantes qui respirent et le code qui s'explique.

- Formation : DWWM `DEV_FAD_2026` — Projet fil rouge 2026/2027

> Ce README est écrit comme je parle : simple, direct, sans jargon inutile. Si un passage fait trop “IA”, dis-le et je le réécris.

---

## Licence

Projet étudiant DWWM, usage pédagogique. Pas de licence commerciale pour l'instant.

<div align="center">

*Mes Orchidées — le monde fascinant des orchidées.*

</div>
