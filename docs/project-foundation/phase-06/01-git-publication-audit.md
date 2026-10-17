# 01 — État Git et analyse de publication (Phase 06)

## 1. État relevé (VERIFIED, Phase 06)

| Élément | Valeur |
|---|---|
| Racine | `C:/Users/jerem/Desktop/mes_orchidees` |
| Branche | `main` |
| HEAD | `6fe6872` |
| Arbre de travail | propre au début de la phase |
| Distance | `main…origin/main [ahead 5]` — confirmé (et non 4) |
| Remote | `https://github.com/jeremyferrierpro-create/mes_orchidees_php.git` |
| Commits de référence | `f00981b`, `64c6cf0`, `6fe6872` → `--is-ancestor` exit 0 |

## 2. Les cinq commits non poussés

| Hash | Sujet | Contenu principal |
|---|---|---|
| `0a9872a` | refactor(mvc) : extraction à la racine, schéma MySQL, socle encyclopédie | structure MVC + `data/schema_mysql.sql` |
| `89717f0` | feat(phase03) : conseils + collection/soins (RG1) | entités/repos/controllers associés |
| `f00981b` | feat(phase03) : notifications (RG3/RG5) + admin/modération | 18 fichiers, +1 609 |
| `64c6cf0` | chore(phase04) : durcissement sécurité + préparation déploiement | 9 fichiers, +576/−16 (CSRF 400, Dockerfile, `.dockerignore`, gabarit env) |
| `6fe6872` | test(phase05) : suite de non-régression + audit XSS + docs prod | 10 fichiers, +945 (`tests/`, docs phase-05) |

Écart avec le rapport de Phase 05 : **aucun** (cinq commits, contenus conformes).

## 3. Diff de publication (`origin/main..HEAD`)

Volume : **355 fichiers**, +11 329 / −17 258. Décomposition :

| Type | Nb | Nature |
|---|---|---|
| Renommés (R100, contenu identique) | 146 | `api/`, `includes/`, pages legacy → `archive/legacy/…` |
| Ajoutés | 113 | `src/`, `templates/`, `config/`, `public/`, `data/`, `tests/`, docs phases |
| Supprimés | 86 | `.agent/` (skills/rules) retiré du suivi |
| Modifiés | 10 | `Dockerfile`, `public/index.php`, contrôleurs, `.gitignore`, `.env.example`, README, composer.json |

## 4. Contrôle des fichiers sensibles avant publication (VERIFIED)

| Contrôle | Résultat |
|---|---|
| `.env` réel suivi/stalké ? | **NON** — ignoré via `.gitignore:6` (`git check-ignore -v` confirmé) |
| Secrets dans le diff (DSN `neon.tech`, `supabase.co`, `postgres://user:pass@`, clés PEM, logs) | **AUCUN** (grep sur l'ensemble des fichiers suivis) |
| `.env.example` (M) et `.env.production.example` (A) | **placeholders uniquement** : `DB_PASS=YOUR_MYSQL_PASSWORD`, `APP_SECRET=changer_cette_cle…` ; valeurs d'exemple locales (`root`, `mes_orchidees`, `development`) |
| Données de test / artefacts locaux (`mes_orchidees_test`, logs de build) | hors du diff (non créés dans le dépôt ; base de test côté MariaDB local uniquement) |
| Compte de démo dans les seeds | présents de façon **volontaire** (comptes fictifs du projet étudiant, hachages `PASSWORD_DEFAULT`) — à confirmer comme acceptable pour un dépôt public |
| Arborescence de cours (`docs/MODULES/`, ISO, vidéos) | **hors du dépôt applicatif** (workspace, non suivi) — ne sera pas publiée |

**Verdict audit de publication : PROPRES** — aucun élément sensible bloquant la
publication des 5 commits. Une seule vigilance humaine : le dépôt distant sera
**public ou privé** (les seeds contiennent des comptes de démonstration et le README
mentionne des choix pédagogiques).

## 5. Constat annexe : README obsolète (VERIFIED)

`README.md` décrit encore l'ancien état « JavaScript vanilla + localStorage, PHP+Supabase
prévu plus tard » (l. 28, 38, 75, 119) — **contraire à l'état actuel** (PHP MVC + MySQL).
Sans effet sur la sécurité, mais à corriger avant/au moment de la publication (action
documentaire, non faite dans cette phase pour rester dans le périmètre).

## 6. Règles respectées

Aucun push, aucune commande destructive, aucun `.env` réel lu ou affiché, historique intact.
