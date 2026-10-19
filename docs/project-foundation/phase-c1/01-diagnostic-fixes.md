# Lot 1 — Trois diagnostics : causes exactes et corrections

## 1. P1006 — `HomeController.php` L22 `Expected type 'null|string'. Found 'array'.`

**Diagnostic reproduit** (outils IDE réels, le 2026-10-18) puis **cause établie par
le code**, pas par supposition :

- Contrat réel de l'application : `AbstractController::render(string $template,
  array $data = [], ?string $title = null): string` (`src/Controller/AbstractController.php` L51).
- L'appel `render('pages/home', [], 'MES ORCHIDEES')` est **conforme** à ce contrat.
- Deux copies d'archives de cours déclarent **la même FQCN** `App\Controller\AbstractController`
  avec un contrat différent :
  - `docs/MODULES/PHP/structure_mvc/src/Controller/AbstractController.php` L14 :
    `render(string $template, ?string $title, array $data = []): void`
  - `docs/MODULES/PHP/structure_mvc - de base- vierge/.../AbstractController.php` : même signature.
- Intelephense indexe tout le workspace et fusionne les symboles dupliqués : il
  validait l'appel contre la **mauvaise** signature (arg 2 = `?string` → « Expected
  null|string, found array »). Aucun conflit à l'exécution : l'autoloader PSR-4
  (`composer.json` : `App\` → `src/` uniquement) ne charge jamais les copies.

**Correction à la source** : création de `.vscode/settings.json` avec
`intelephense.files.exclude` (`**/docs/**` + défauts `vendor`, `node_modules`,
`.git`). Ni cast, ni suppression de diagnostic, ni modification du métier — le
problème est indexiciel, la correction l'est aussi.

## 2. P1075 — `OrchidController.php` L192 `Not all paths return a value.`

**Cause** : `update(): ?string` se termine par `$this->redirectTo(...)` ; or
`redirectTo()` déclarait `: void` **alors qu'elle appelle `exit`** — Intelephense
jugeait le point de retour implicite atteignable.

**Correction à la source** : `AbstractController::redirectTo(): never` (type natif
PHP 8.1+ ; projet en PHP **8.3.33**, vérifié). Le docblock `@return void` devient
`@return never` et le paramètre `$status` (déjà présent au code) est documenté.
Aucun `return` arbitraire ajouté ; les chemins 404 (retour `string`) et succès
(sortie par redirection) gardent leur comportement. Bénéfice collatéral : tout
appel à `redirectTo()` est désormais reconnu terminal par l'analyse statique.

## 3. SCSS — `_cards.scss` L84 `Also define the standard property 'line-clamp'`

**Cause** : `-webkit-line-clamp: 4` sans propriété standard (règle de compatibilité
du linter CSS natif).

**Correction** : ajout de `line-clamp: 4;` après la déclaration webkit (le bloc
`display: -webkit-box` + `-webkit-box-orient: vertical` + `overflow: hidden` est
conservé — comportement visuel identique, navigateurs modernes couverts).
Recompilation `style.css` (expanded + embed-sources) et `style.min.css`
(compressed) avec le binaire `sass` déjà utilisé par le projet (exit 0 ; seuls
les avertissements `@import` préexistants à l'architecture du dépôt).

**Non-régression glass C0 vérifiée dans le CSS compilé** : sélecteurs
`.rappel-card`, `.rappel-bandeau` toujours présents (L4266/L4296/L4460+).

## Validation lot 1

- `php -l` : OK (`AbstractController`, `OrchidController`, et tous les fichiers touchés ensuite).
- Suite HTTP de référence : **272 PASS / 0 FAIL** au pied du lot (base de référence C0) — inchangée.
- Re-lecture IDE des 3 diagnostics : **NOT VERIFIED** au moment du bilan (language
  server en re-indexation après le changement de `.vscode/settings.json`, CLI
  Intelephense non installée). Voir 04-rapport-validation.md pour le statut final.
