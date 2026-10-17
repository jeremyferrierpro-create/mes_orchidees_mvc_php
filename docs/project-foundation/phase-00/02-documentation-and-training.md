# 02 — Documentation `/docs/` et cours de formation (§4 et §5)

---

## 1. Registre de `/docs/` (§4.1) — ampleur réelle

Le dossier `docs/` est un **archive de formation DWWM complet** (≈ 2 ans), pas la seule
documentation du projet. Comptage (hors `node_modules/` et `.git/`) :

| Format | Fichiers | Lisibles par l'agent ? |
|---|---|---|
| `.php` | 1780 | Oui (code) |
| `.css` / `.scss` | 1148 | Oui |
| `.js` / `.ts` / `.tsx` | 889 | Oui |
| `.md` | 741 | Oui |
| `.py` / `.pyc` | 741 | Oui / Non (bytecode) |
| `.pdf` | **230** | **NON (format binaire, illisible)** |
| `.png`/.webp/.jpg/.svg/.gif | ~800 | Images : oui (selectives) |
| `.docx`/`.pptx`/`.xlsx`/`.odt` | ~160 | **NON nativement** |
| `.mp4`/`.mp3` | 10 | **NON (média)** |
| `.sql` | 56 | Oui |

> **Décision de périmètre (§5) :** « 100 % des contenus lus » sur ~9000 fichiers,
> majoritairement hors-sujet pour une migration PHP MVC, est **irréalisable et non
> pertinent**. Le périmètre de formation retenu est celui **explicitement nommé** par
> la mandate (§1 du protocole) : **PHP/support_cours**, **exemple MVC**, **SQL**, **GIT**,
> **Docker**, plus la **librairie router**. Tout le reste (ANGLAIS, CNIL, UX, etc.) est
> marqué `NOT_APPLICABLE` avec justification (§5.1).

## 2. Sous-répertoires `/docs/` et pertinence

| Dossier | Pertinence migration PHP MVC | État |
|---|---|---|
| `mes_orchidees_php/` | **HAUTE** — base de travail | Analysé |
| `support_formateur/router-librairie/` | **HAUTE** — router imposé | Analysé |
| `MODULES/PHP/` | **HAUTE** — méthode MVC | Partiellement (PDF bloqué) |
| `MODULES/SQL/` | Moyenne — modèle de données | Inventorié |
| `MODULES/DOCKER/` | Moyenne — déploiement | Inventorié |
| `MODULES/GIT/` | Moyenne — versionnement | Inventorié |
| `site_html/` | Moyenne — version HTML source | Inventorié (non versionné) |
| `MODULES/{ANGLAIS,CNIL_RGPD,CONCEPTION,IA,JAVASCRIPT,PRO,...}` | **Faible/NA** pour cette phase | `NOT_APPLICABLE` |

## 3. Consultation des cours (§5.2) — couverture et blocage

### 3.1 Cours identifiés dans le périmètre

| ID | Chemin | Format | État |
|---|---|---|---|
| C1 | `MODULES/PHP/support_cours/Cours PHP_V5_partie1..6.pdf` | PDF | **BLOCKED** |
| C2 | `MODULES/PHP/support_cours/Cours PHP_V6_heritage.pdf`, `_partie7.pdf` | PDF | **BLOCKED** |
| C3 | `MODULES/PHP/support_cours/Module PHP - Pareto 80-20.pdf` | PDF | **BLOCKED** |
| C4 | `MODULES/PHP/support_cours/php_poo_classes/*.php` (Animal/Chat/Chien/test_object) | PHP | **READ_COMPLETE** |
| C5 | `MODULES/PHP/support_cours/{fonctions,tableaux}.txt` | TXT | Inventorié (non lu intégralement) |
| C6 | `MODULES/PHP/support_cours/Lexique_PHP_Complet.xlsx` | XLSX | **BLOCKED** |
| C7 | `MODULES/PHP/structure_mvc/**` (exemple MVC) | PHP/JSON/MD | **READ_COMPLETE** |
| C8 | `support_formateur/router-librairie/{README,docs,src,examples}` | MD/PHP | **READ_COMPLETE** (README + src inventoriés) |

**Bilan couverture :** **9 PDFs de cours PHP** + 1 XLSX = **`BLOCKED`**. Les supports
**lisibles** (code PHP POO, exemple MVC complet, README/src du router) sont consultés.
→ Exigence §5.2 « 100 % des contenus » : **NON SATIFAITE**, pour **blocage explicite**
(format PDF non supporté par les outils de cet agent), conformément à §5.2/§15.

### 3.2 Conséquence (arrêt conditionnel §15)

> « Les cours obligatoires ne peuvent pas être lus intégralement » est une **condition
> d'arrêt §15**. Intervention humaine requise : fournir les cours en format texte/markdown,
> ou **autoriser expressément** à s'en remettre à l'exemple MVC (C7) qui matérialise déjà
> la méthode attendue, ou désigner un autre sous-ensemble de cours comme faisant autorité.

## 4. Hiérarchie des instructions (§4.3) et CONTRADICTION

1. **Exigences explicites utilisateur** (§1 protocole) : PHP + MVC + nomenclature de routes
   + `Mithridatem Router` + code commenté « débutant ».
2. **Règles/décisions faisant autorité (constatées)** : exemple `structure_mvc` (C7) ;
   conventions `.agent/rules/stack.md`.
3. **Contraintes techniques constatées :** Neon PostgreSQL, PHP 8.3 local, Apache/Docker 8.2.
4. **Recommandations :** skills `/agent/`.
5. **Hypothèses non validées :** base de données cible définitive, docroot `public/`.

### ⚠ Conflit majeur documenté (non arbitré — §4.3 « ne pas choisir arbitrairement »)

`.agent/rules/stack.md` (présent à **la racine** ET **dans `mes_orchidees_php/`**) déclare
une stack cible : **TypeScript + Next.js (App Router) + Tailwind + Prisma/Drizzle**, avec
arborescence `app/ components/ lib/ package.json`.

Cela **contredit frontalement** la mandate utilisateur (PHP + MVC + router + `composer.json`).
Le fichier lui-même indique : *« si un projet utilise une stack différente, mets à jour
`rules/stack.md` avec les vraies valeurs observées »*. → La stack `stack.md` est **obsolète
pour ce projet** ; l'utilisateur est l'autorité (PHP/MVC primo), mais la correction du
fichier de règles est une **décision humaine** à confirmer.

## 5. Documents redondants / concurrents / obsolètes

- `README.md` du projet : décrit un état antérieur (localStorage, pas de BDD) ≠ code réel.
- `mes_orchidees_php/README` vs dépôt GitHub `mes_orchidees` (nom divergent) vs remote réel
  `mes_orchidees_php` → incohérence de traçabilité (§8.1).
- Branches : README annonce `dev → main` ; seul `main` existe sur le dépôt (voir baseline).
- 3 schémas SQL (mysql/neon/supabase) + `.docs/mcd_mld.lo1` & `.loo` (doublons, non suivis).
