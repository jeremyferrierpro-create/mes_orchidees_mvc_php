# 02 — Audit de sécurité XSS (Phase 05)

Objectif : fermer la limitation `NOT VERIFIED` de la Phase 04B (bilan XSS exhaustif)
par un audit **reproductible** à périmètre défini.

## 1. Périmètre de l'audit

- **28 fichiers** de gabarits PHP (`templates/**/*.php`), incluant vues, composants,
  formulaires, layout et page d'erreur.
- Sorties concernées : données issues de la base (entités orchidée/conseil/collection/
  soin/notification/utilisateur), entrées utilisateur (`$_GET`, `$_POST`), valeurs
  d'attributs, champs multilignes, et le jeton CSRF.

## 2. Méthode

1. Recensement des balises `<?= … ?>` et `echo` non enveloppées dans `e()`.
2. Recherche de contextes d'injection qui ne sont **pas** couverts par un simple
   échappement HTML : `<script>`, JSON, `data-*`, URL, gestionnaires d'évènements.
3. Vérification des helpers de formulaire et du rendu des valeurs réflexives (`$query`).
4. Classification de chaque sortie nue restante (numérique / booléenne / constante).

## 3. Constats (VERIFIED)

### 3.1 Aucun contexte JavaScript/JSON injectable
Le seul `<script>` des gabarits (`footer.php`) charge un fichier externe via
`e(asset('assets/js/app.js'))`. Aucun `json_encode`, aucune interpolation PHP dans un
bloc `<script>`. → Pas de contexte de désérialisation JS côté vue.

### 3.2 Sorties réflexives échappées
Les vues `encyclopedie.php` et `conseils/index.php` réaffichent le terme de recherche
`$query` (issu de `$_GET`) :
- en attribut : `value="<?= e($query) ?>"` ;
- en texte : `pour « <?= e($query) ?> »`.
Le compteur `<?= count($conseils) ?>` est un entier. → **sûr**.

### 3.3 Helpers de formulaire
`$field()` et `$textarea()` (`orchids/form.php`, `conseils/form.php`) échappent chaque
partie dynamique via `e()` : nom, label, `value`, `type`, `placeholder`, aide. Le balisage
autour est statique. → **sûr**.

### 3.4 Échappement systématique des données DB/utilisateur
Toutes les sorties d'entités passent par `e()` (ex. `e($orchid->name)`,
`e($u['email'])`, `e($flash['message'])`, champs multilignes via `nl2br(e($x))`).

### 3.5 Sorties nues résiduelles — toutes justifiées par le type
| Modèle de sortie | Nature | Statut |
|---|---|---|
| `<?= (int) $valeur ?>`, `<?= (int) $parRole[…] ?>` | cast entier | SÛR |
| `<?= count($…) ?>` | entier PHP | SÛR |
| `<?= $stats['orchids_pending'] ?>` | entier (`StatsRepository::count()` renvoie `(int)`) | SÛR |
| `<?= $nav($path) ?>` | littéral constant `' aria-current="page"'` ou `''` | SÛR |
| `… ? 'littéral' : ''` | chaîne constante selon booléen | SÛR |
| `<?= $content ?>` (layout) | tampon HTML **déjà rendu** depuis des vues échappées | SÛR (design) |

Aucune donnée utilisateur/DB arbitraire n'est imprimée en brut.

## 4. Tests automatisés associés

La suite de la Phase 05 (`tests/run.php`) encode les garanties XSS clés comme tests
rejouables : neutralisation de `<script>`, `ENT_QUOTES` (guillemets simple/double),
terme réflexif en attribut, échappement multiligne via `nl2br(e(...))`. Résultat :
**8/8 PASS** (voir `03-regression-tests.md`).

## 5. Corrections

Aucune correction de template nécessaire : **aucun défaut démontré**. Le protocole
interdit la refonte générale ; seuls des défauts prouvés auraient été traités.

## 6. Verdict XSS

**PASS** — sur le périmètre défini (§1), audit achevé, exceptions justifiées par le
type, et garanties clés couvertes par des tests rejouables réussis.

> La mention « 100 % » n'est pas employée : le verdict vaut pour le périmètre décrit
> (gabarits de l'application MVC), pas pour d'éventuels scripts legacy hors vue.
