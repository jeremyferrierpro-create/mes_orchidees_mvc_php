# PHASE 00 — INITIALISATION, COMPRÉHENSION ET BASELINE

**Projet :** MES ORCHIDEES
**Protocole :** v1.0 — Phase 00 (audit en lecture seule)
**Verdict :** `READY FOR HUMAN DECISION`
**Date d'audit :** 2026-10-17
**Autorité décisionnelle :** Utilisateur humain

---

## 1. Résumé exécutif

Le dépôt racine `C:\Users\jerem\Desktop\mes_orchidees\` **n'est pas** un dépôt Git :
c'est un espace de travail contenant un archive de formation DWWM complet (`docs/MODULES/`)
plusieurs sous-projets versionnés séparément.

Le projet réellement concerné par la mandate est le dépôt **`docs/mes_orchidees_php/`**
(base de travail imposée), un site d'encyclopédie d'orchidées et de gestion de collection.

**Constat central :** la migration HTML → PHP MVC est **partiellement engagée et incohérente**.
Des fichiers `.php` et une API `api/*.php` (PDO + Neon PostgreSQL + JWT) existent déjà,
mais l'architecture n'est **pas MVC** : les pages racine sont encore des gabarits HTML
peuplés côté client depuis des fichiers `.json`, sans front-controller, sans routes,
sans Composer et sans le `Mithridatem Router` pourtant souhaité.

La baseline technique est établie. Plusieurs **décisions humaines bloquantes** et
**contradictions documentaires** doivent être tranchées avant toute implémentation.

## 2. Périmètre couvert / non couvert

- **COUVERT (VERIFIED) :** état Git de tous les sous-dépôts, arborescence et code
  PHP réel de `mes_orchidees_php`, configuration API/DB/auth, `.htaccess`, `Dockerfile`,
  `.env` (noms de variables uniquement, jamais les valeurs), structure de la librairie
  `Mithridatem Router`, exemple MVC de référence `MODULES/PHP/structure_mvc`, inventaire
  complet des skills `/agent/`, fichier de règles `stack.md`.
- **NON COUVERT / BLOCKED :** contenu intégral des cours PHP (format **PDF**, illisible
  par les outils de cet agent) → exigence §5.2 de couverture 100 % **non satisfaisable**
  en lecture seule. Voir `02-documentation-and-training.md`.

## 3. État du projet

| Dimension | État observé | Classification |
|---|---|---|
| Versionnement racine | Aucun `.git` à la racine ; sous-dépôts séparés | VERIFIED |
| Base de travail | `docs/mes_orchidees_php/`, branche `main`, HEAD `c031e12` | VERIFIED |
| Working tree | **NON PRE** — 7 fichiers modifiés + 2 non suivis (préexistants) | VERIFIED |
| Architecture | PHP « plat » + API par endpoints ; **pas de MVC** | VERIFIED |
| Dépendances PHP | **Aucun `composer.json`** ; router non installé | VERIFIED |
| Base de données | Neon PostgreSQL distant (DSN réel présent) | VERIFIED (nom) |
| Router souhaité | Non intégré ; library présente en local, PSR-4, PHP ^8.1 | VERIFIED |

## 4. Verdict

`READY FOR HUMAN DECISION` — L'analyse préparatoire est complète et la baseline est
fiable pour le code. Toutefois, trois arbitraires humains sont indispensables avant
la phase 01 : (a) périmètre/doc de la formation PDF inaccessible, (b) contradiction de
la stack déclarée (`stack.md` Next.js/TS) avec la mandate PHP, (c) stratégie de
préservation des modifications préexistantes non commitées.

## 5. Livrables

- [INDEX.md](./INDEX.md) — le présent document
- [01-project-inventory.md](./01-project-inventory.md) — inventaire technique
- [02-documentation-and-training.md](./02-documentation-and-training.md) — `/docs/` + cours
- [03-agent-skills.md](./03-agent-skills.md) — skills `/agent/`
- [04-baseline.md](./04-baseline.md) — Git, environnement, tests, code préexistant
- [05-risks-and-open-decisions.md](./05-risks-and-open-decisions.md) — risques et décisions
- [06-proposed-roadmap.md](./06-proposed-roadmap.md) — phases proposées

## 6. Preuves principales (chemins)

- Base : `docs/mes_orchidees_php/index.php`, `encyclopedie.php`, `api/**`, `.htaccess`, `Dockerfile`, `.env`
- Router : `docs/support_formateur/router-librairie/composer.json`, `README.md`, `src/**`
- Réf. MVC : `docs/MODULES/PHP/structure_mvc/{composer.json,public/index.php,src/Controller/*}`
- Cours : `docs/MODULES/PHP/support_cours/*.pdf` (illisibles → BLOCKED)
- Règles : `.agent/rules/stack.md` (racine + projet)

---

**ARRÊT EN FIN DE PHASE 00.** Aucune ligne de code applicatif n'a été modifiée,
aucune dépendance installée, aucune donnée consultée en valeur, aucun service lancé.
Voir §14 du protocole : en attente d'une instruction explicite pour la phase 01.
